<?php
/*	Pago con Deuna (QR / link / código). Variables heredadas del index: $method, $request, $input.

	POST /deuna/create        { cod_preorden, format? }   Crea el cobro y devuelve QR / link / código
	GET  /deuna/status/{id}                                Estado del pago (el front lo consulta cada pocos segundos)
	POST /deuna/webhook                                    Notificación de pago exitoso de Deuna (header Api-Key de la empresa)
	POST /deuna/mock-pay      { cod_preorden }            Solo sucursales en ambiente "mock": simula que el cliente pagó

	El pago se confirma por dos caminos (webhook y status); ambos pasan por deunaConfirmarPago(), que es idempotente. */

require_once "clases/cl_ordenes.php";
require_once "clases/cl_deuna.php";

require_once "clases/cl_usuarios.php";
require_once "clases/cl_empresas.php";
require_once "clases/cl_sucursales.php";

// storePreorder() (helpers/preorderConvert.php) usa estos objetos globales, igual que controllers/Ordenes.php
$Clordenes = new cl_ordenes();
$Clusuarios = new cl_usuarios();
$Clempresas = new cl_empresas();
$Clsucursales = new cl_sucursales();

// El API silencia los errores fatales; aquí quedan en el log "deuna" para poder diagnosticarlos.
register_shutdown_function(function(){
	$e = error_get_last();
	if($e && in_array($e["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])){
		logAdd($e["message"]." en ".$e["file"].":".$e["line"], "fatal", "deuna");
	}
});

const DEUNA_EXPIRA_MINUTOS = 15;
const DEUNA_CODIGO_MINUTOS = 3;
const DEUNA_FORMATOS = [0, 1, 2, 3, 4, 5]; // 0 link, 1 QR, 2 QR+link, 3 código, 4 QR+código, 5 todo

if($method == "POST"){
	$accion = isset($request[1]) ? $request[1] : "";
	if($accion == "create"){
		showResponse(deunaCrear());
	}else if($accion == "webhook"){
		deunaWebhook();
	}else if($accion == "mock-pay"){
		showResponse(deunaMockPay());
	}
}else if($method == "GET"){
	if(isset($request[1]) && $request[1] == "status" && isset($request[2])){
		showResponse(deunaEstado((int)$request[2]));
	}
}
showResponse(['success' => 0, 'mensaje' => "El metodo $method para deuna no está disponible en esta ruta."]);


/* FUNCIONES */

/** Preorden del usuario autenticado, sin orden creada. Responde con error y corta si no cumple. */
function deunaPreordenDelUsuario($cod_preorden){
	global $Clordenes;
	validateUserAuthenticated();

	$preorden = $Clordenes->getPreOrden($cod_preorden);
	if(!$preorden){
		showResponse(['success' => 0, 'mensaje' => 'Preorden no encontrada', 'errorCode' => 'PREORDEN_INEXISTENTE']);
	}
	if($preorden['cod_usuario'] != user_id){
		showResponse(['success' => 0, 'mensaje' => 'Preorden no encontrada', 'errorCode' => 'PREORDEN_INEXISTENTE']);
	}
	return $preorden;
}

function deunaCrear(){
	global $input;
	global $Clordenes;
	$input = validateInputs(array("cod_preorden"));
	$cod_preorden = (int)$input['cod_preorden'];
	$format = isset($input['format']) ? (int)$input['format'] : 3; // por defecto solo código único de 6 dígitos
	if(!in_array($format, DEUNA_FORMATOS, true)) $format = 3;

	$preorden = deunaPreordenDelUsuario($cod_preorden);
	if($preorden['cod_orden'] != 0){
		return ['success' => 0, 'mensaje' => 'Esta preorden ya fue pagada', 'errorCode' => 'PREORDEN_USADA'];
	}
	if(!in_array($preorden['estado'], ['VALIDADA', 'CERRADA', 'PAGADA_NO_CREADA'])){
		return ['success' => 0, 'mensaje' => 'Esta preorden ya fue utilizada, vuelve a armar tu pedido', 'errorCode' => 'PREORDEN_USADA'];
	}

	$trama = json_decode($preorden['json'], true);
	$cod_sucursal = (int)$trama['cod_sucursal'];
	$monto = round((float)$preorden['amount'], 2);
	if($monto < 0.01){
		return ['success' => 0, 'mensaje' => 'El monto a pagar con Deuna no es válido', 'errorCode' => 'MONTO_INVALIDO'];
	}

	$Cldeuna = new cl_deuna($cod_sucursal);
	if(!$Cldeuna->isInitialized){
		return ['success' => 0, 'mensaje' => 'Deuna no está configurado para esta sucursal, por favor comunicarse con soporte', 'errorCode' => 'DEUNA_NO_CONFIGURADO'];
	}

	$intento = cl_deuna::contarCobros($cod_preorden) + 1;
	$reference = "T$cod_preorden-$intento"; // < 20 caracteres y único por intento
	$minutos = ($format == 3) ? DEUNA_CODIGO_MINUTOS : DEUNA_EXPIRA_MINUTOS; // el código numérico vence a los 3 min y no es configurable
	$resp = $Cldeuna->createPayment($monto, $reference, "Pedido ".name_site." #$cod_preorden", $format, $minutos);
	if(!$resp['success']){
		logAdd(json_encode($resp), "error-create", "deuna");
		return ['success' => 0, 'mensaje' => 'No se pudo generar el cobro con Deuna: '.$resp['error'], 'errorCode' => 'DEUNA_ERROR'];
	}
	$data = $resp['data'];

	$fecha = fecha();
	$expira = date('Y-m-d H:i:s', strtotime($fecha.' +'.$minutos.' minutes'));
	$guardado = Conexion::ejecutar(
		"INSERT INTO tb_preorden_deuna(cod_preorden, cod_sucursal, transaction_id, reference, monto, estado, fecha_create, fecha_expira)
			VALUES(:cod_preorden, :cod_sucursal, :transaction_id, :reference, :monto, 'PENDIENTE', :fecha, :expira)",
		[':cod_preorden' => $cod_preorden, ':cod_sucursal' => $cod_sucursal, ':transaction_id' => $data['transactionId'],
		 ':reference' => $reference, ':monto' => $monto, ':fecha' => $fecha, ':expira' => $expira]
	);
	if(!$guardado){
		return ['success' => 0, 'mensaje' => 'No se pudo registrar el cobro, por favor vuelva a intentarlo', 'errorCode' => 'DEUNA_ERROR'];
	}

	return [
		'success' => 1,
		'mensaje' => 'Cobro generado',
		'data' => [
			'transactionId' => $data['transactionId'],
			'qr' => isset($data['qr']) ? $data['qr'] : null,
			'deeplink' => isset($data['deeplink']) ? $data['deeplink'] : null,
			'numericCode' => isset($data['numericCode']) ? $data['numericCode'] : null,
			'amount' => $monto,
			'expired_at' => $expira,
			'expires_in' => $minutos * 60,
			'mock' => $Cldeuna->isMock,
		],
	];
}

function deunaEstado($cod_preorden){
	global $Clordenes;
	$preorden = deunaPreordenDelUsuario($cod_preorden);

	$pagada = deunaRespuestaPagada($preorden);
	if($pagada) return $pagada;

	// Los cobros pendientes se consultan en Deuna (el webhook puede no haber llegado todavía)
	$pendientes = Conexion::buscarVariosRegistro(
		"SELECT * FROM tb_preorden_deuna WHERE cod_preorden = :cod AND estado = 'PENDIENTE' ORDER BY id DESC",
		[':cod' => $cod_preorden]
	);
	foreach ($pendientes ?: [] as $cobro) {
		$Cldeuna = new cl_deuna($cobro['cod_sucursal']);
		if(!$Cldeuna->isInitialized) continue;
		$info = $Cldeuna->getStatus($cobro['transaction_id'], 0);
		if($info['success'] && $info['data']['status'] == 'APPROVED'){
			$res = deunaConfirmarPago($cobro['transaction_id'], 'status');
			if($res['success']) return $res;
			return ['success' => 0, 'mensaje' => $res['mensaje'], 'errorCode' => 'ORDEN_NO_CREADA', 'data' => ['status' => 'PAID_NO_ORDER']];
		}
	}

	$ultimo = cl_deuna::getUltimoCobro($cod_preorden);
	if(!$ultimo){
		return ['success' => 0, 'mensaje' => 'No hay un cobro de Deuna para esta preorden', 'errorCode' => 'SIN_COBRO'];
	}
	if($ultimo['estado'] == 'PENDIENTE' && strtotime($ultimo['fecha_expira']) < time()){
		cl_deuna::marcarVencido($ultimo['transaction_id']);
		$ultimo['estado'] = 'VENCIDO';
	}
	return [
		'success' => 1,
		'mensaje' => ($ultimo['estado'] == 'PENDIENTE') ? 'Esperando el pago' : 'El cobro venció, genera uno nuevo',
		'data' => ['status' => ($ultimo['estado'] == 'PENDIENTE') ? 'PENDING' : 'EXPIRED', 'expired_at' => $ultimo['fecha_expira']],
	];
}

/** Respuesta estándar cuando la preorden ya tiene orden; null si aún no. */
function deunaRespuestaPagada($preorden){
	if($preorden['cod_orden'] == 0) return null;
	return [
		'success' => 1,
		'mensaje' => 'Pago realizado con éxito, puedes revisarlo en tu lista de órdenes',
		'id' => generarTracking($preorden['cod_orden']),
		'data' => ['status' => 'PAID', 'id' => generarTracking($preorden['cod_orden'])],
	];
}

function deunaWebhook(){
	global $input;
	logAdd(json_encode($input), "webhook", "deuna");

	if(!isset($input['idTransaction']) || !isset($input['status'])){
		http_response_code(200);
		showResponse(['success' => 0, 'mensaje' => 'Notificación sin idTransaction o status, ignorada']);
	}
	if($input['status'] != 'SUCCESS'){
		showResponse(['success' => 1, 'mensaje' => 'Estado no exitoso, ignorado']);
	}

	$cobro = cl_deuna::getCobro($input['idTransaction']);
	if(!$cobro){
		// 200 para que Deuna no reintente un cobro que no es nuestro
		showResponse(['success' => 0, 'mensaje' => 'Transacción desconocida, ignorada']);
	}

	$res = deunaConfirmarPago($cobro['transaction_id'], 'webhook');
	if(!$res['success']){
		// Que Deuna reintente (3 veces cada 30 s)
		http_response_code(500);
		echo json_encode($res);
		exit();
	}
	showResponse(['success' => 1, 'mensaje' => $res['mensaje']]);
}

function deunaMockPay(){
	global $input;
	$input = validateInputs(array("cod_preorden"));
	$cod_preorden = (int)$input['cod_preorden'];
	deunaPreordenDelUsuario($cod_preorden);

	$cobro = cl_deuna::getUltimoCobro($cod_preorden);
	if(!$cobro) return ['success' => 0, 'mensaje' => 'No hay un cobro de Deuna para esta preorden'];
	$Cldeuna = new cl_deuna($cobro['cod_sucursal']);
	if(!$Cldeuna->isInitialized || !$Cldeuna->isMock){
		return ['success' => 0, 'mensaje' => 'Solo disponible en sucursales con Deuna en ambiente simulado'];
	}

	Conexion::ejecutar("UPDATE tb_preorden_deuna SET transfer_number = :t WHERE transaction_id = :id", [':t' => '9'.random_int(10000000000, 99999999999), ':id' => $cobro['transaction_id']]);
	return deunaConfirmarPago($cobro['transaction_id'], 'mock-pay');
}

/**
 * Confirma un cobro con Deuna y crea la orden. Idempotente: lo llaman el webhook, el polling del front y mock-pay,
 * posiblemente a la vez, por eso la preorden se bloquea y se vuelve a leer dentro del bloqueo.
 * @return array ['success' => 0|1, 'mensaje' => ..., 'id' => tracking, ...]
 */
function deunaConfirmarPago($transactionId, $origen){
	global $Clordenes;
	$cobro = cl_deuna::getCobro($transactionId);
	if(!$cobro) return ['success' => 0, 'mensaje' => 'Cobro no encontrado'];

	$cod_preorden = (int)$cobro['cod_preorden'];
	$lock = "deuna_preorden_$cod_preorden";
	$tomado = Conexion::buscarRegistro("SELECT GET_LOCK(:lock, 20) AS ok", [':lock' => $lock]);
	if(!$tomado || $tomado['ok'] != 1){
		return ['success' => 0, 'mensaje' => 'La preorden se está procesando, intenta de nuevo en unos segundos'];
	}

	try{
		$preorden = $Clordenes->getPreOrden($cod_preorden);
		if(!$preorden) return ['success' => 0, 'mensaje' => 'Preorden no encontrada'];

		$pagada = deunaRespuestaPagada($preorden);
		if($pagada){
			if($cobro['estado'] != 'APROBADO'){
				// Se pagó dos veces la misma preorden: el segundo pago no tiene orden y hay que devolverlo a mano
				logAdd("PAGO DUPLICADO preorden=$cod_preorden transaction=$transactionId origen=$origen", "duplicado", "deuna");
			}
			return $pagada;
		}

		// Verificación contra Deuna: nunca se confía en el contenido del webhook
		$Cldeuna = new cl_deuna($cobro['cod_sucursal']);
		if(!$Cldeuna->isInitialized) return ['success' => 0, 'mensaje' => 'Deuna no está configurado para esta sucursal'];
		$info = $Cldeuna->getStatus($transactionId, 0);
		if(!$info['success']) return ['success' => 0, 'mensaje' => 'No se pudo verificar el pago con Deuna: '.$info['error']];
		$pago = $info['data'];
		if($pago['status'] != 'APPROVED'){
			return ['success' => 0, 'mensaje' => 'El pago aún no está aprobado en Deuna ('.$pago['status'].')'];
		}
		if(abs((float)$pago['amount'] - (float)$cobro['monto']) > 0.009){
			logAdd("MONTO DISTINTO preorden=$cod_preorden transaction=$transactionId cobrado={$cobro['monto']} pagado={$pago['amount']}", "monto", "deuna");
			return ['success' => 0, 'mensaje' => 'El monto pagado no coincide con el cobro'];
		}

		cl_deuna::marcarAprobado($transactionId, $pago['transferNumber']);

		return deunaCrearOrden($preorden, $transactionId, $pago['transferNumber']);
	}finally{
		Conexion::buscarRegistro("SELECT RELEASE_LOCK(:lock)", [':lock' => $lock]);
	}
}

/** Convierte la preorden pagada en orden (mismo proceso que POST /ordenes/preorden). */
function deunaCrearOrden($preorden, $transactionId, $transferNumber){
	global $Clordenes;
	$cod_preorden = (int)$preorden['cod_preorden'];
	try{
		require_once "helpers/preorderConvert.php";
		$total = 0;
		$id = storePreorder($preorden, $transactionId, $transferNumber, 4, $total);

		try {
			require_once "helpers/notificationsToClient.php";
			notifyNewOrder($id);
		} catch (Exception $e) {
			logAdd("Error notifyNewOrder orden $id: ".$e->getMessage(), "error", "post-orden");
		}
		try {
			require_once "helpers/pixelFacebook.php";
			trackPurchaseServer($id);
		} catch (Exception $e) {
			logAdd("Error trackPurchaseServer orden $id: ".$e->getMessage(), "error", "post-orden");
		}

		return [
			'success' => 1,
			'mensaje' => 'Pago realizado con éxito, puedes revisarlo en tu lista de órdenes',
			'id' => generarTracking($id),
			'data' => ['status' => 'PAID', 'id' => generarTracking($id)],
			'total' => $total,
		];
	}catch(Throwable $e){
		// Ya se cobró: la preorden queda reintentable para el siguiente webhook / consulta del front
		logAdd("Pagó pero no se creó la orden preorden=$cod_preorden transaction=$transactionId: ".$e->getMessage()." en ".$e->getFile().":".$e->getLine(), "pagada-no-creada", "deuna");
		$Clordenes->setStatusPreorden($cod_preorden, 'PAGADA_NO_CREADA', 0, $e->getMessage());
		return ['success' => 0, 'mensaje' => $e->getMessage()];
	}
}
