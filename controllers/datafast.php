<?php
require_once "clases/cl_datafast.php";
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
    global $input;
    
    $datosObligatorios = array("cod_usuario","total","cod_sucursal","preorden_id"); //FALTA IP
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}
	
	extract($input);

	/*INFO USUARIO*/
	$Clusuarios = new cl_usuarios();
	$usuario = $Clusuarios->get($cod_usuario);
	if(!$usuario){
	    $return['success'] = 0;
    	$return['mensaje'] = "Usuario no existente";
		return $return;
	}
	
	/*Info preorden*/
	require_once "clases/cl_ordenes.php";
	$Clordenes = new cl_ordenes();
	$preorden = $Clordenes->getPreOrden($preorden_id);
    if(!$preorden){
	    $return['success'] = 0;
    	$return['mensaje'] = "Preorden no existente";
		return $return;
	}
	
	$Cldatafast = new cl_datafast($cod_sucursal);
	if(!$Cldatafast->Isinitialize){
	    $return['success'] = 0;
    	$return['mensaje'] = "DataFast no está configurado para esta empresa, por favor comunicarse con soporte";
		return $return;
	}
	
	$envioDatafast = [];
	if($Cldatafast->ambiente=="development" && $Cldatafast->fase == 'FASE1')
	    $respDatafast = $Cldatafast->getTransactionFase1($total, $envioDatafast);
	else
	    $respDatafast = $Cldatafast->getTransactionProduction($usuario, $total, "180.160.10.1", $envioDatafast);

	if(isset($respDatafast['id'])){
	    
	    //Guardar Token Datafast junsto a Preorden Id
	    $Clordenes->setPaymentIdPreOrden($preorden_id, $respDatafast['id']);
	    
	    $return['success'] = 1;
	    $return['mensaje'] = "Token generado correctamente";
	    $return['token'] = $respDatafast['id'];
		$return['URL'] = $Cldatafast->URL;
	    $return['respDatafast'] = $respDatafast;
	    $return['envDatafast'] = $envioDatafast;
		$return['ClassDatafast'] = $Cldatafast;
	}else{
	    $error = "";
	    $code = isset($respDatafast['result']['code']) ? $respDatafast['result']['code'] : 0;
	    $error = isset($respDatafast['result']['description']) ? $respDatafast['result']['description'] : "";
	    
	    $return['success'] = 0;
	    $return['mensaje'] = "No se pudo generar el token. ErroCode: $code - Desc: $error";
	    $return['datafast'] = $respDatafast;
	    $return['envDatafast'] = $envioDatafast;
	    $return['classDatafast'] = $Cldatafast;
	}
	
	//$return['info'] = $Cldatafast;
	//$return['usuario'] = $usuario;
	
	mylog(json_encode($return), "GET_TOKEN");
	return $return;    
	
}