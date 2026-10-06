<?php

if($method == "GET"){
	$num_variables = count($request);
	if($num_variables == 3){
		if($request[1] == "orden"){
			$resp = orden($request[2]);
			showResponse($resp);
		}
	}
}
else{
	$return['success']= 0;
	$return['mensaje']= "El metodo ".$method." para Correos aun no esta disponible.";
	showResponse($return);
}

function orden($cod_orden){
	require_once "clases/cl_ordenes.php";
	$Clordenes = new cl_ordenes();

	$orden = $Clordenes->get_orden_array($cod_orden);
	if(!$orden){
		$return['success'] = 1;
		$return['mensaje'] = "Orden no existente";
		return $return;
	}

	/*
	$detalle = $Clordenes->get_detalle_orden($cod_orden);
	if($detalle){
		$orden['detalle2'] = $detalle;
	}
*/

	//$logo = url;

	$return['success'] = 1;
	$return['data'] = $orden;
	return $return;

}

