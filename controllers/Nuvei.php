<?php
require_once "clases/cl_paymentez.php";
require_once "clases/cl_usuarios.php";

if($method == "POST"){
	$num_variables = count($request);
    if($num_variables == 2){
        $first = $request[1];
		if($first=="getToken"){
		    $return = getToken();
			showResponse($return);
		}
    }
}else if($method == "GET"){
    $return['success']= 0;
	$return['mensaje']= "El metodo ".$method." para datafast aun no esta disponible.";
	showResponse($return);
}else{
	$return['success']= 0;
	$return['mensaje']= "El metodo ".$method." para datafast aun no esta disponible.";
	showResponse($return);
}


/*FUNCIONES*/
function getToken(){
	$input = validateInputs(array("cod_usuario","total","cod_sucursal","preorden_id"));
	extract($input);
	
	try{

    	/*INFO USUARIO*/
    	$Clusuarios = new cl_usuarios();
    	$usuario = $Clusuarios->get($cod_usuario);
    	if(!$usuario)
    	    throw new Exception('Usuario no existente');
    	
    	/*Info preorden*/
    	require_once "clases/cl_ordenes.php";
    	$Clordenes = new cl_ordenes();
    	$preorden = $Clordenes->getPreOrden($preorden_id);
        if(!$preorden)
            throw new Exception('Preorden no existente');
    	
    	$ClPaymentez = new cl_paymentez($cod_sucursal);
    // 	if(!$ClPaymentez->Isinitialize)
    // 	    throw new Exception('Nuvei no está configurado para esta empresa, por favor comunicarse con soporte');
    	    
    	$resp = $ClPaymentez->initReference($usuario, $preorden_id, $total);
    	if(!$resp) throw new Exception('No se pudo procesar el pago');
    	
    	if(!isset($resp['reference'])){
    		$errortext = isset($resp['error']['type']) ? $resp['error']['type'] : 'No se pudo procesar el pago';
    		throw new Exception($errortext);
    	}
    	
    	$referencia = $resp['reference'];
    	// $Clordenes->setPaymentIdPreOrden($preorden_id, $referencia); //Guardar Token Datafast junsto a Preorden Id
    		
    	return [
    	    'success' => 1,
    	    'message' => 'Referencia creada correctamente',
    	    'reference' => $referencia
    	];
    	
    	mylog(json_encode($return), "GET_TOKEN");
    	return $return;    
    	
	}catch (Exception $e) {
		return ['success' => 0, 'mensaje' => $e->getMessage()];
	}
	
}