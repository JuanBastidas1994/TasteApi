<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT
	POST /medianet/getLink  -> genera el link de pago (requiere Api-Key y User-Id)
	POST /medianet/callback -> url_back de Medianet (sin Api-Key; la empresa se resuelve por la referencia, ver verificateMedianetCallback) */

require_once "clases/cl_ordenes.php";
require_once "clases/cl_empresas.php";
require_once "clases/cl_medianet.php";

const MEDIANET_PROVIDER = 4;

if ($method == "POST" && count($request) == 2) {
	if ($request[1] == "getLink") {
		showResponse(getLink());
	}
	if ($request[1] == "callback") {
		showResponse(callback());
	}
}

showResponse(['success' => 0, 'mensaje' => 'Evento no existente']);

/*FUNCIONES*/

/** Monto a cobrar con tarjeta según la preorden (nunca confiar en el monto que manda el cliente). */
function medianetCardAmount($preorden){
	$trama = json_decode($preorden['json'], true);
	$monto = 0;
	foreach (($trama['metodoPago'] ?? []) as $pago) {
		if ($pago['tipo'] == "T") $monto += floatval($pago['monto']);
	}
	return round($monto, 2);
}

function getLink(){
	global $input;
	$usuario = validateUserAuthenticated();
	validateInputs(array("preorden_id", "cod_sucursal", "return_url"));
	extract($input);

	try {
		$Clordenes = new cl_ordenes();
		$preorden = $Clordenes->getPreOrden($preorden_id);
		if (!$preorden || $preorden['cod_usuario'] != $usuario['cod_usuario'])
			throw new Exception('Preorden no existente');

		if (!in_array($preorden['estado'], ['VALIDADA', 'CERRADA', 'FALLADA']))
			throw new Exception('Esta preorden ya fue utilizada, por favor vuelve a procesar el pedido');

		$total = medianetCardAmount($preorden);
		if ($total <= 0)
			throw new Exception('La preorden no tiene un monto a pagar con tarjeta');

		if (!filter_var($return_url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $return_url))
			throw new Exception('La URL de retorno no es válida');

		$ClMedianet = new cl_medianet($cod_sucursal);
		if (!$ClMedianet->isInitialized)
			throw new Exception('Medianet no está configurado para esta sucursal, por favor comunicarse con soporte');

		$empresa = (new cl_empresas())->get();
		$iva = intval($empresa['impuesto']);
		$base = round($total / (1 + ($iva / 100)), 2);

		// Referencia única por intento (máx. 40). El callback recupera la preorden con el número antes del guion.
		$reference = $preorden_id . '-' . time();

		$base_back = rtrim(MEDIANET_CALLBACK_BASE ?: url_api, '/');
		$urlBack = $base_back . '/medianet/callback';
		$urlRedirect = $return_url . (strpos($return_url, '?') === false ? '?' : '&') . 'medianet_ref=' . $preorden_id;

		$resp = $ClMedianet->createLink($reference, $total, $base, $iva, "Compra en " . name_site . " Preord#$preorden_id", $urlBack, $urlRedirect);
		if (!$resp['success'])
			throw new Exception('No se pudo generar el link de pago');

		$Clordenes->setPaymentIdPreOrden($preorden_id, $reference);

		return ['success' => 1, 'mensaje' => 'Link creado correctamente', 'link' => $resp['link'], 'reference' => $reference];
	} catch (Exception $e) {
		return ['success' => 0, 'mensaje' => $e->getMessage()];
	}
}

/** Confirmación de pago enviada por MedianetPay (url_back). Debe ser idempotente. */
function callback(){
	global $input;

	logAdd(json_encode($input), "trama-ingreso", "medianet-callback");

	if (!cl_medianet::isAllowedIp($_SERVER['REMOTE_ADDR'] ?? '')) {
		logAdd("IP no autorizada: " . ($_SERVER['REMOTE_ADDR'] ?? ''), "rechazado", "medianet-callback");
		http_response_code(403);
		echo json_encode(['success' => 0, 'mensaje' => 'No autorizado']);
		exit();
	}

	$reference = trim((string)($input['reference'] ?? ''));
	$cod_preorden = intval(explode('-', $reference)[0]);
	if ($cod_preorden <= 0)
		return ['success' => 0, 'mensaje' => 'Referencia inválida'];

	$paymentId = substr((string)($input['payment_reference'] ?? ''), 0, 60);
	$paymentAuth = substr((string)($input['authorization'] ?? ''), 0, 15);

	try {
		$Clordenes = new cl_ordenes();
		$preorden = $Clordenes->getPreOrden($cod_preorden);
		if (!$preorden)
			return ['success' => 0, 'mensaje' => 'Preorden no existente'];

		// Reintento del callback: la orden ya existe
		if ($preorden['cod_orden'] != 0)
			return ['success' => 1, 'mensaje' => 'Orden ya creada'];

		if ($preorden['estado'] === 'CREANDO_ORDEN')
			return ['success' => 1, 'mensaje' => 'Orden en proceso'];

		if (!cl_medianet::isApproved($input['response'] ?? '')) {
			if (in_array($preorden['estado'], ['VALIDADA', 'CERRADA']))
				$Clordenes->failurePreOrden($cod_preorden, $paymentId, '', 'Pago rechazado Medianet');
			return ['success' => 1, 'mensaje' => 'Pago no aprobado registrado'];
		}

		// Aprobado: el monto y la moneda deben coincidir con la preorden
		$esperado = medianetCardAmount($preorden);
		$recibido = round(floatval($input['amount'] ?? 0), 2);
		if (($input['currency'] ?? '') !== 'USD' || abs($esperado - $recibido) > 0.01) {
			logAdd("Monto/moneda no coinciden preorden=$cod_preorden esperado=$esperado recibido=$recibido", "critico", "medianet-callback");
			return ['success' => 0, 'mensaje' => 'El monto no coincide con la preorden'];
		}

		// Un pago aprobado tiene prioridad sobre un intento previo rechazado
		if ($preorden['estado'] === 'FALLADA')
			$Clordenes->setStatusPreorden($cod_preorden, 'VALIDADA');

		$preorden = $Clordenes->marcarPagoExitosoPreOrden($cod_preorden, $paymentId, $paymentAuth, '');

		require_once "helpers/preorderConvert.php";
		$total = 0;
		$id = storePreorder($preorden, $paymentId, $paymentAuth, MEDIANET_PROVIDER, $total);

		try {
			require_once "helpers/notificationsToClient.php";
			notifyNewOrder($id);
		} catch (Exception $e) {
			logAdd("Error notifyNewOrder orden $id: " . $e->getMessage(), "error", "medianet-callback");
		}

		try {
			require_once "helpers/pixelFacebook.php";
			trackPurchaseServer($id);
		} catch (Exception $e) {
			logAdd("Error trackPurchaseServer orden $id: " . $e->getMessage(), "error", "medianet-callback");
		}

		return ['success' => 1, 'mensaje' => 'Orden creada correctamente'];
	} catch (Exception $e) {
		logAdd("Error creando orden preorden=$cod_preorden: " . $e->getMessage(), "critico", "medianet-callback");
		return ['success' => 0, 'mensaje' => $e->getMessage()];
	}
}
