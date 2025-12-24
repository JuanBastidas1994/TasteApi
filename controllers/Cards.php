<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */
require_once "clases/cl_cards.php";
require_once "clases/cl_usuarios.php";
$ClCards = new cl_cards();
$Clusuarios = new cl_usuarios();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 1){	//TARJETAS DE UN USUARIO
			$return = lista();
			showResponse($return);
		}
		else if($num_variables == 3){	//TARJETA DETALLE DE UN USUARIO
			$cod_usuario = $request[1];
			$cod_usuario_card = $request[2];
			
			$return = get($cod_usuario_card, $cod_usuario);
			showResponse($return);
		}
		$return['success']= 0;
		$return['mensaje']= "Evento no existente";
		showResponse($return);
	}
	else if($method == "POST"){
		$num_variables = count($request);
		if($num_variables == 1){
			$return = crear();
			showResponse($return);
		}
		else if($num_variables == 2) {
			if($request[1] == "remove") {
				$return = eliminar();
				showResponse($return);
			}
		}
		$return['success']= 0;
		$return['mensaje']= "Evento no existente";
		showResponse($return);
	}	
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Tarjetas aun no esta disponible.";
		showResponse($return);
	}


function lista(){
    global $ClCards;
    $usuario = validateUserAuthenticated();
    
    $cod_usuario = $usuario['cod_usuario'];
    try{
        $office_id = $_GET['office_id'] ?? sucursaldefault;
        if($office_id > 0){
            
    		require_once "clases/cl_paymentez.php";
    		$ClPaymentez = new cl_paymentez($office_id);
    		
    		$resp = $ClPaymentez->getCards($cod_usuario);
    		if($resp['cards']){
    		    $cards = $resp['cards'];
    		    foreach($cards as $card){
    		        if(!$ClCards->getExistToken($card['token'], $cod_usuario)){
		            	$ClCards->user_id = $cod_usuario;
                    	$ClCards->token = $card['token'];
                    	$ClCards->expiry_month = $card['expiry_month'];
                    	$ClCards->expiry_year = $card['expiry_year'];
                    	$ClCards->bin = $card['bin'];
                    	$ClCards->number = $card['number'];
                    	$ClCards->type = $card['type'];
                    	$ClCards->alias = $ClCards->getNombreTarjeta($card['type']) . " ****" . $card['number'];
                    	$ClCards->status = $card['status'];
                    	$ClCards->reference = $card['transaction_reference'];
                    	$ClCards->cod_sucursal_created = $office_id;
                    	$ClCards->crear();
    		        }
    		    }
    		}
        }
        
        $return['success'] = 1;
		$return['mensaje'] = "Correcto";
		$return['cards'] = $ClCards->lista($cod_usuario);
		return $return;
	}catch (Exception $e) {
		showResponse(['success' => 1, 'mensaje' => $e->getMessage(), 'cards' => $ClCards->lista($cod_usuario), 'errorCode' => 'ERROR_TRANSACTION']);
	}
    
    
			
	$return['success'] = 1;
	$return['mensaje'] = "Correcto";
	$return['cards'] = $ClCards->lista($cod_usuario);
}

function crear(){
	global $ClCards;
	global $Clusuarios;

	$usuario = validateUserAuthenticated();
	$input = validateInputs(array("expiry_month","expiry_year","bin","number","status","token","transaction_reference","type", "office_id"));
	extract($input);
	
	if($ClCards->getExistToken($token, $usuario['cod_usuario'])){
	   // responseError('Tarjeta ya existe');
	    $return['success'] = 0;
		$return['mensaje'] = "Tarjeta ya existe";
	    return $return;
	}
	
	$ClCards->user_id = $usuario['cod_usuario'];
	$ClCards->token = $token;
	$ClCards->expiry_month = $expiry_month;
	$ClCards->expiry_year = $expiry_year;
	$ClCards->bin = $bin;
	$ClCards->number = $number;
	$ClCards->type = $type;
	$ClCards->alias = $ClCards->getNombreTarjeta($type) . " ****" . $number;
	$ClCards->status = $status;
	$ClCards->reference = $transaction_reference;
	$ClCards->cod_sucursal_created = $office_id;
	if($ClCards->crear()) {
	    $return['success'] = 1;
		$return['mensaje'] = "Tarjeta creada correctamente";
		//$return['codigo'] = $codigo;
	}else{
	    $return['success'] = 0;
		$return['mensaje'] = "No se pudo crear la tarjeta, por favor comunicate con soporte";
	}
	return $return;
}

function get($cod_usuario_card, $cod_usuario){
	global $ClCards;
	
	$card = $ClCards->get($cod_usuario_card, $cod_usuario);
	if($card) {
		$return['success'] = 1;
		$return['mensaje'] = "Tarjeta detalle";
		$return['data'] = $card;
	}
	else {
		$return['success'] = 0;
		$return['mensaje'] = "La tarjeta no existe, por favor comunicate con soporte";
	}
	return $return;
}

function eliminar(){
	global $ClCards;

	$usuario = validateUserAuthenticated();
	$input = validateInputs(array("card_id"));
	extract($input);

	$cod_usuario = $usuario['cod_usuario'];
	$card = $ClCards->get($card_id, $cod_usuario);
	if(!$card) responseError("Tarjeta no existente", "TARJETA_INEXISTENTE");

	try{
		$cod_sucursal = $card['cod_sucursal_created'];
		require_once "clases/cl_paymentez.php";
		$ClPaymentez = new cl_paymentez($cod_sucursal);
		
		$resp = $ClPaymentez->removeCard($cod_usuario, $card['token']);
		if(!$resp) throw new Exception('No se pudo eliminar la tarjeta en Paymentez');
		if(isset($resp['error'])){
			$errortext = isset($resp['error']['type']) ? $resp['error']['type'] : 'No se pudo procesar el pago';
			throw new Exception($errortext);
		}

		if($ClCards->eliminar($card_id, $cod_usuario)){
			$return['success'] = 1;
			$return['mensaje'] = "Tarjeta eliminada correctamente";
		}else{
			$return['success'] = 0;
			$return['mensaje'] = "No se pudo eliminar la tarjeta, por favor comunícate con soporte";
		}
		return $return;
	}catch (Exception $e) {
		showResponse(['success' => 0, 'mensaje' => $e->getMessage(), 'errorCode' => 'ERROR_TRANSACTION']);
	}
}

function getTokensNuvei($cod_sucursal) {
	$query = "SELECT * FROM tb_empresa_sucursal_paymentez WHERE cod_sucursal = $cod_sucursal";
	$resp = Conexion::buscarRegistro($query);
	if(!$resp) {
		$cod_empresa = cod_empresa;
		$query = "SELECT * FROM tb_empresa_paymentez WHERE cod_empresa = $cod_empresa";
		$resp = Conexion::buscarRegistro($query);
	}
	return $resp;
}

function setTarjetaPredeterminada() {
	global $ClCards;
	global $input;
	
	$datosObligatorios = array("cod_usuario_cards", "cod_usuario");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
			$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}

	extract($input);

	$card = $ClCards->get($cod_usuario_cards, $cod_usuario);
	if(!$card) {
		$return["success"] = 0;
		$return["mensaje"] = "Tarjeta no encontrada";
		return $return;
	}

	if(!$ClCards->setTarjetaPredeterminada($cod_usuario_cards, $cod_usuario)) {
		$return["success"] = 0;
		$return["mensaje"] = "Error al asignar tarjeta como predeterminada";
		return $return;
	}

	$return["success"] = 1;
	$return["mensaje"] = "Tarjeta asignada como predeterminada";
	$return["data"] = $card;
	return $return;
}
?>