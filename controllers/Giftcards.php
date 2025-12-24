<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */
require_once "clases/cl_giftcards.php";
require_once "clases/cl_ordenes.php";
require_once "clases/cl_usuarios.php";
require_once "clases/cl_clientes.php";
$Clgiftcards = new cl_giftcards();
$Clordenes = new cl_ordenes();
$Clusuarios = new cl_usuarios();


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