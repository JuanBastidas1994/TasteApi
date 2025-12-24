<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */
	require_once "clases/cl_usuarios.php";
	$Clusuarios = new cl_usuarios();
		
	//TODO tb_cupones debe traer los cupones tipo le croissant
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
	else if($method == "POST"){
	    $num_variables = count($request);
    	if($num_variables == 1){
    		$return = findCoupon();
    		showResponse($return);
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
	
	function findCoupon(){
	    global $Clusuarios;
	    global $input;
	    $datosObligatorios = array("codigo", "total", "cod_usuario");
    	foreach ($datosObligatorios as $key => $value) {
    		if (!array_key_exists($value, $input)) {
    			$return['success'] = 0;
    			$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
    			return $return;
    		}
    	}
    	extract($input);
	    
	    $query = "SELECT * FROM tb_codigo_promocional WHERE codigo = '$codigo' AND estado='A' AND fecha_expiracion >= NOW() AND usos_restantes > 0 AND cod_empresa = ".cod_empresa;
		$cupon = Conexion::buscarRegistro($query);
	    if($cupon){
	        
	        if($cupon["ilimitado"] == 0) {
				$couponUsed = $Clusuarios->getCouponUsed($cod_usuario, $codigo); //Verificar si el usuario ya uso este cupón
				if ($couponUsed) {
					$return['success'] = 0;
					$return['mensaje'] = "Cupón $codigo ya utilizado. Ingrese otro cupón o remuévalo para continuar con la compra";
					showResponse($return);
				}
			}
	        
	        if ($total <= $cupon['restriccion']) {
                $return['success'] = 0;
			    $return['mensaje'] = "El cupón solo se puede aplicar en compras mayores a $".$cupon['restriccion']." sin el valor del envío";
            }else{
                $return['success'] = 1;
			    $return['mensaje'] = "Codigo Promocional Válido";
			    $return['data'] = $cupon;
            }
	    }else{
	        $return['success'] = 0;
			$return['mensaje'] = "El cupón puede que ya esté caducado o mal escrito";
	    }
	    showResponse($return);
	}
?>