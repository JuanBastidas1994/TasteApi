<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 2){
			$codigo = $request[1];
			
			$query = "SELECT * FROM tb_codigo_promocional WHERE codigo = '$codigo' AND estado='A' AND fecha_expiracion >= NOW() AND usos_restantes > 0 AND cod_empresa = ".cod_empresa;
			$resp = Conexion::buscarRegistro($query);
			if($resp){
			    $return['success'] = 1;
			    $return['mensaje'] = "Codigo Promocional Valido";
			    $return['data'] = $resp;
			}else{
			    $return['success'] = 0;
			    $return['mensaje'] = "Codigo promocional no valido";
			}
			
			showResponse($return);
		}
	}
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Giftcard aun no esta disponible.";
		showResponse($return);
	}
?>