<?php
require_once "clases/cl_categorias.php";
require_once "clases/cl_productos.php";
$Clcategorias = new cl_categorias();
$Clproductos = new cl_productos();

$num_variables = count($request);
if($method == "GET"){
    if($num_variables == 2){
    	$cod_sucursal = isset($request[1]) ? $request[1] : 0;
    	$return = productos($cod_sucursal);
    	showResponse($return);
    }
    if($num_variables == 3){
        $input['alias'] = $request[1];
        $input['sucursal'] = $request[2];
        showResponse(detalle_producto());
    }
    if($num_variables == 4){
        $input['alias'] = $request[1];
        $input['sucursal'] = $request[2];
        $input['add'] = $request[3];
        showResponse(detalle_producto());
    }
}else if($method == "POST"){
	if($num_variables == 2){
		if($request[1] == "detalle"){
			showResponse(detalle_producto());
		}
	}
}else{
	$return['success']= 0;
	$return['mensaje']= "El metodo ".$method." para Productos oahu aun no esta disponible.";
	showResponse($return);
}

//FUNCIONES
function productos($cod_sucursal = 0){
	global $Clcategorias;
	global $Clproductos;

	$Clproductos->cod_sucursal = $cod_sucursal;
	$categorias = $Clcategorias->lista();
	foreach ($categorias as $key => $categoria) {
		$productos = $Clproductos->listaBycategoria($categoria['cod_categoria']);
		
		foreach($productos as $key2 => $producto){
			$productos[$key2]['oahu_arma_bowl'] = $Clproductos->getArmaBowl($producto['cod_producto']);
		}
		
		$categorias[$key]['productos'] = $productos;
	}

	$return['success'] = 1;
	$return['mensaje'] = "Correcto";
	$return['data'] = $categorias;
	return $return;
}

function detalle_producto(){
	global $Clcategorias;
	global $Clproductos;
	global $input;

	extract($input);
	$datosObligatorios = array("alias","sucursal");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}
	$producto = $Clproductos->getInfoByAlias($alias, $sucursal);
	if($producto){
		if($Clproductos->getArmaBowl($producto['cod_producto'])){
			$day = dayOfWeek(fecha()); //2 Martes - 4 Jueves
			//OPCIONES
			//Get Fruta y Topping
			$opciones = $producto['opciones'];
			if($day == 4){
				$opciones = addMaxItemToOption('fruta', $opciones);
			}else if($day == 2){
				$opciones = addMaxItemToOption('topping', $opciones);
			}
	
			$add = isset($input['add']) ? $input['add'] : '';
			if($add == 'fruta'){
				$opciones = addMaxItemToOption('fruta', $opciones);
			}else if($add == 'topping'){
				$opciones = addMaxItemToOption('topping', $opciones);
			}
			$producto['opciones'] = $opciones;
		}

		$return['success'] = 1;
		$return['mensaje'] = "Detalle del producto";
		$return['data'] = $producto;
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "Producto no encontrado, por favor vuelva a intentarlo";
	}
	showResponse($return);
}

function addMaxItemToOption($findToTitleOption, $opciones){
	foreach($opciones as $key => $opcion){
		$pos = strpos(strtolower($opcion['titulo']), $findToTitleOption);
		if($pos !== false){
			$opciones[$key]['cantidad'] = $opcion['cantidad'] + 1;
		}
	}

	return $opciones;
}