<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */
require_once "clases/cl_productos.php";
require_once "clases/cl_carrito.php";
require_once "clases/cl_empresas.php";
require_once "clases/cl_sucursales.php";
$Clproductos = new cl_productos();
$promociones = null;

	if($method == "POST"){
		$num_variables = count($request);
		if($num_variables == 1){
			$return = get();
			showResponse($return);
		}
		if($num_variables == 2){
			$return = get2();
			showResponse($return);
		}
		
		$return['success']= 0;
		$return['mensaje']= "Evento no existente";
		showResponse($return);
	}	
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Carrito aun no esta disponible.";
		showResponse($return);
	}

function get(){
	global $Clproductos;
	global $promociones;
	global $input;
	extract($input);
	
	//VALORES PERMITIDOS
	//cod_sucursal, productos, y (envio O entrega), cupon(este no es obligatorio)
	//'envio' sigue obligatorio para clientes viejos que no mandan 'entrega' -
	//cuando llega 'entrega' con lat/lng, cl_carrito calcula el envio el mismo.
	$datosObligatorios = array("cod_sucursal","productos");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}
	if (!array_key_exists("envio", $input) && !array_key_exists("entrega", $input)) {
		$return['success'] = 0;
		$return['mensaje'] = "Falta informacion, Error: Campo envio o entrega es obligatorio";
		return $return;
	}

	$Clcarrito = new cl_carrito($input, $cod_sucursal);
	$cart = $Clcarrito->getArray();

	if ($cart['envio_error']) {
		$return['success'] = 0;
		$return['mensaje'] = $cart['envio_error'];
		$return['errorCode'] = 'ENTREGA_SIN_UBICACION';
		return $return;
	}

	$return['success'] = 1;
	$return['mensaje'] = "Lista Carrito";
	$return = $return + $cart;
	return $return;
}

function get2(){
	global $input;
	extract($input);
	
	//VALORES PERMITIDOS
	//cod_sucursal, productos, envio, cupon(este no es obligatorio)
	$datosObligatorios = array("cod_sucursal","productos","envio");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}
	
	require_once "clases/cl_carrito2.php";
	$Clcarrito = new cl_carrito2($input, $cod_sucursal);
	
	$return['success'] = 1;
	$return['mensaje'] = "Lista Carrito";
	$return = $return + $Clcarrito->getArray();
	return $return;
}	

?>