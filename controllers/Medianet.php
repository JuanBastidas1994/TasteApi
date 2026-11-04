<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT
	POST /medianet/getLink  -> genera el link de pago (requiere Api-Key y User-Id)
	El url_back de Medianet NO vive en este API (Medianet no envia cabeceras): es webhooks/medianet_callback.php del dashboard Taste */

require_once "clases/cl_ordenes.php";
require_once "clases/cl_empresas.php";
require_once "clases/cl_medianet.php";


if ($method == "POST" && count($request) == 2) {
	if ($request[1] == "getLink") {
		showResponse(getLink());
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

		// url_back: URL completa del webhook (MEDIANET_URL_BACK), se envía tal cual a Medianet
		$urlBack = trim(MEDIANET_URL_BACK);
		if (!filter_var($urlBack, FILTER_VALIDATE_URL))
			throw new Exception('Medianet: falta configurar MEDIANET_URL_BACK en el .env del API');
		// medianet_ref va en la query real (antes del "#"): la web usa HashRouter y con "?m=slug" (demos) o utm en la URL,
		// pegarlo al final dejaba "#/checkout/paymentprocess&medianet_ref=1" y la ruta no existía (página en blanco)
		$partes = explode('#', $return_url, 2);
		// Quitar cualquier medianet_ref que ya traiga la URL (reintentos de pago) para no duplicarlo
		$partes[0] = preg_replace('/[?&]medianet_ref=[^&]*/', '', $partes[0]);
		// si se quitó el primer parámetro, el siguiente queda con "&" en vez de "?"
		if (strpos($partes[0], '?') === false && ($pos = strpos($partes[0], '&')) !== false)
			$partes[0] = substr_replace($partes[0], '?', $pos, 1);
		$urlRedirect = $partes[0] . (strpos($partes[0], '?') === false ? '?' : '&') . 'medianet_ref=' . $preorden_id . (isset($partes[1]) ? '#' . $partes[1] : '');

		$resp = $ClMedianet->createLink($reference, $total, $base, $iva, "Compra en " . name_site . " Preord#$preorden_id", $urlBack, $urlRedirect);
		if (!$resp['success'])
			throw new Exception('No se pudo generar el link de pago');

		$Clordenes->setPaymentIdPreOrden($preorden_id, $reference);

		return ['success' => 1, 'mensaje' => 'Link creado correctamente', 'link' => $resp['link'], 'reference' => $reference];
	} catch (Exception $e) {
		return ['success' => 0, 'mensaje' => $e->getMessage()];
	}
}

