<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */
require_once "clases/cl_giftcards.php";
require_once "clases/cl_ordenes.php";
require_once "clases/cl_usuarios.php";
require_once "clases/cl_clientes.php";
require_once "clases/cl_sucursales.php";
$Clgiftcards = new cl_giftcards();
$Clordenes = new cl_ordenes();
$Clusuarios = new cl_usuarios();
$ClSucursales = new cl_sucursales();


	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 1){
			$return['success'] = 1;
			$return['mensaje'] = "Lista Giftcards";
			$return['data'] = $Clgiftcards->lista();
			showResponse($return);
		}
		if($num_variables == 2){	//GIFTCARDS DE UN USUARIO
			$cod_usuario = $request[1];
			
			$return['success'] = 1;
			$return['mensaje'] = "Correcto";
			$return['compradas'] = $Clgiftcards->lista_compradas($cod_usuario);
			$return['mis_giftcard'] = $Clgiftcards->lista_mis_giftcards($cod_usuario);
			showResponse($return);
		}
		if($num_variables == 3){	//DETALLE DE UNA GIFTCARD DE UN USUARIO
			$cod_usuario = $request[1];
			$cod_usuario_giftcard = $request[2];
			
			$return['success'] = 1;
			$return['mensaje'] = "Correcto";
			$return['giftcard'] = $Clgiftcards->getGitcardUsuario($cod_usuario, $cod_usuario_giftcard);
			showResponse($return);
		}
	}
	else if($method == "POST"){
		$num_variables = count($request);
		if($num_variables == 1){
			//$cod_usuario = $request[1];
			$return = crear();
			showResponse($return);
		}
		if($num_variables == 2){
			if($request[1] == "asignar"){
				//$return['mensaje'] = "asignar";
				showResponse(asignar());
			}
			if($request[1] == "preorden"){
				showResponse(crearPreorden());
			}
			if($request[1] == "confirmar"){
				showResponse(confirmarCompra());
			}
		}

		$return['success']= 0;
		$return['mensaje']= "Evento no existente";
		showResponse($return);
	}	
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Giftcard aun no esta disponible.";
		showResponse($return);
	}


function crear(){
	global $Clgiftcards;
	global $Clordenes;
	global $input;
	
	$datosObligatorios = array("monto","giftcard","cod_usuario");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
			$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}
	extract($input);
	logAdd(json_encode($input),"trama-ingreso","crear-giftcard");

	$existGift = $Clgiftcards->getGitcardEmpresaById($giftcard);
	if(!$existGift){
		$return['success'] = 0;
		$return['mensaje'] = "Tipo de Giftcard no existente";
		return $return;
	}

	//CREACION DE LA ORDEN
	$iva = $monto * 0.12;
	$total = $monto + $iva;
	$metodoEnvio[0]['tipo'] = "app";
	$metodoEnvio[0]['precio'] = 0;

	$metodoPago[0]['tipo'] = "T";
	$metodoPago[0]['monto'] = $total;

	$producto[0]['id'] = 127; //ID DE LA GIFTCARD
	$producto[0]['cantidad'] = 1;
	$producto[0]['descripcion'] = "";
	$producto[0]['precio'] = $monto;

	$orden['cod_sucursal'] = sucursaldefault;
	$orden['subtotal'] = $monto;
	$orden['descuento'] = 0;
	$orden['iva'] = $iva;
	$orden['total'] = $total;
	$orden['metodoEnvio'] = $metodoEnvio;
	$orden['metodoPago'] = $metodoPago;
	$orden['productos'] = $producto;

	$id = "";
	$codigo = "";

	do{
		$codigo = passRandom();
	}while($Clgiftcards->getUserGiftcardByCode($codigo));

	if($Clgiftcards->crear($monto, $giftcard, $cod_usuario, $codigo)){
		$return['success'] = 1;
		$return['mensaje'] = "Giftcard creada correctamente";
		$return['codigo'] = $codigo;

		/*
		if($Clordenes->crear($orden, $cod_usuario, $id)){
			$return['id'] = $id;
		}else{
			$return['success'] = 0;
			$return['mensaje'] = "No se pudo crear la orden, por favor intentelo nuevamente";
		}*/
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "No se pudo crear la giftcard, por favor comunicate con soporte";
	}
	return $return;
}

function crearPreorden(){
	global $Clgiftcards;
	global $Clordenes;
	global $ClSucursales;
	global $input;

	$datosObligatorios = array("monto","giftcard","cod_usuario");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
			$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}
	extract($input);
	logAdd(json_encode($input),"trama-ingreso","giftcard-preorden");

	$existGift = $Clgiftcards->getGitcardEmpresaById($giftcard);
	if(!$existGift){
		$return['success'] = 0;
		$return['mensaje'] = "Tipo de Giftcard no existente";
		return $return;
	}

	$montosPermitidos = explode(",", $existGift['montos']);
	if(!in_array(strval($monto), $montosPermitidos)){
		$return['success'] = 0;
		$return['mensaje'] = "El monto seleccionado no es válido para esta Giftcard";
		return $return;
	}

	//JSON minimo, solo para que Nuvei::getToken tenga un preorden_id valido al que referenciar
	$json = json_encode([
		'tipo' => 'giftcard',
		'cod_giftcard' => intval($giftcard),
		'monto' => floatval($monto),
		'cod_usuario' => intval($cod_usuario),
	]);

	$preordenId = $Clordenes->saveJson($cod_usuario, $json, $monto);
	if(!$preordenId){
		$return['success'] = 0;
		$return['mensaje'] = "No se pudo crear la preorden, por favor vuelva a intentarlo";
		$return['errorCode'] = "PREORDEN_ERROR";
		return $return;
	}

	$proveedor = 0;
	$paymentTokens = $ClSucursales->getPaymentTokens(sucursaldefault, $proveedor);

	$return['success'] = 1;
	$return['mensaje'] = "Preorden creada correctamente";
	$return['data'] = [
		'preordenId' => intval($preordenId),
		'total' => floatval($monto),
		'cod_sucursal' => sucursaldefault,
		'payment_tokens' => $paymentTokens,
	];
	return $return;
}

function confirmarCompra(){
	global $Clgiftcards;
	global $Clordenes;
	global $input;

	$datosObligatorios = array("cod_preorden","paymentId","paymentAuth","paymentProvider");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
			$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}
	extract($input);
	logAdd(json_encode($input),"trama-ingreso","giftcard-confirmar");

	$cod_preorden = intval($cod_preorden);

	//Idempotencia: si esta preorden ya genero una giftcard (reintento del front), no duplicar
	$giftcardExistente = $Clgiftcards->getGiftcardByPreorden($cod_preorden);
	if($giftcardExistente){
		$return['success'] = 1;
		$return['mensaje'] = "Giftcard ya creada anteriormente";
		$return['codigo'] = $giftcardExistente['codigo'];
		return $return;
	}

	$preorden = $Clordenes->getPreOrden($cod_preorden);
	if(!$preorden){
		$return['success'] = 0;
		$return['mensaje'] = "Preorden no existente";
		$return['errorCode'] = "PREORDEN_INEXISTENTE";
		return $return;
	}

	if(!in_array($preorden['estado'], ['VALIDADA', 'PAGADA_NO_CREADA'])){
		$return['success'] = 0;
		$return['mensaje'] = "Esta preorden ya fue utilizada";
		$return['errorCode'] = "PREORDEN_USADA";
		return $return;
	}

	$ordenTrama = json_decode($preorden['json'], true);
	if(!$ordenTrama || !isset($ordenTrama['tipo']) || $ordenTrama['tipo'] !== 'giftcard'){
		$return['success'] = 0;
		$return['mensaje'] = "Preorden inválida para Giftcard";
		$return['errorCode'] = "PREORDEN_TIPO_INVALIDO";
		return $return;
	}

	require_once "helpers/preorderConvert.php";
	try{
		debitPaymentCard($paymentProvider, $paymentId, $paymentAuth, sucursaldefault);
	}catch(Exception $e){
		$Clordenes->failurePreOrden($cod_preorden, $paymentId, $paymentAuth, $e->getMessage());
		$return['success'] = 0;
		$return['mensaje'] = $e->getMessage();
		return $return;
	}

	$cod_giftcard = $ordenTrama['cod_giftcard'];
	$monto = $ordenTrama['monto'];
	$cod_usuario_orden = $ordenTrama['cod_usuario'];

	$codigo = "";
	do{
		$codigo = passRandom();
	}while($Clgiftcards->getUserGiftcardByCode($codigo));

	if($Clgiftcards->crear($monto, $cod_giftcard, $cod_usuario_orden, $codigo, $cod_preorden, $paymentId, $paymentAuth, $paymentProvider)){
		$Clordenes->setStatusPreorden($cod_preorden, 'PAGADA', 0);
		$return['success'] = 1;
		$return['mensaje'] = "Giftcard creada correctamente";
		$return['codigo'] = $codigo;
	}else{
		$Clordenes->setStatusPreorden($cod_preorden, 'FALLADA', 0, 'No se pudo crear la giftcard');
		$return['success'] = 0;
		$return['mensaje'] = "No se pudo crear la giftcard, por favor comunicate con soporte";
	}
	return $return;
}

function asignar(){
	global $Clgiftcards;
	global $Clusuarios;
	global $input;
	
	$datosObligatorios = array("cod_usuario","codigo");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
			$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}
	extract($input);
	logAdd(json_encode($input),"trama-ingreso","asignar-giftcard");

	/*USUARIO EXISTE*/
	$usuario = $Clusuarios->get($cod_usuario);
	if(!$usuario){
		$return['success'] = 0;
		$return['mensaje'] = "Usuario no encontrado";
		$return['errorCode'] = "USUARIO_INEXISTENTE";
		return $return;
	}else{
		$num_documento = $usuario['num_documento'];
		if(trim($num_documento) ===""){
			$return['success'] = 0;
			$return['mensaje'] = "Numero de documento no válido";
			$return['errorCode'] = "NUMDOC_INVALIDO";
			return $return;
		}
	}

	$Clclientes = new cl_clientes($num_documento);
	if(!$Clclientes->get()){
		$return['success'] = 0;
		$return['mensaje'] = "Información de cliente no existente";
		$return['errorCode'] = "CLIENTE_ERROR";
		return $return;
	}
	
	/*USUARIO EXISTE*/

	$giftcard = $Clgiftcards->getUserGiftcardByCode($codigo);
	if(!$giftcard){
		$return['success'] = 0;
		$return['mensaje'] = "Código de giftcard incorrecto, si cree que esto es un error comuníquese con soporte";
		return $return;
	}

	if(intval($giftcard['cod_usuario_receptor']) > 0){
		$return['success'] = 0;
		$return['mensaje'] = "Giftcard ya asignada a un usuario!! intenta con otro código";
		return $return;
	}
	
	if($Clgiftcards->setUserGiftcard($cod_usuario, $giftcard['cod_usuario_giftcard'])){
		$return['success'] = 1;
		$return['mensaje'] = "Giftcard agregada correctamente";
		$return['data'] = $giftcard;
		if($Clclientes->AddDinero($giftcard['monto'], $Clclientes->cod_cliente, 5, 9999)){
	    	$return['add_monto_regalo'] = true;
	    }
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "No se pudo adjuntar la giftcard, por favor intentalo nuevamente";
	}
	return $return;
}
?>