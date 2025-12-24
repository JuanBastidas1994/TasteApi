<?php
require_once "clases/cl_empresas.php";
$ClEmpresas = new cl_empresas();

if($method == "POST"){
    $num_variables = count($request);
    if($num_variables == 2){
        $metodo = $request[1];
        if($metodo == "crash"){
            $resp = logAppCaida();
			showResponse($resp);
        }
        if($metodo == "storage"){
            $resp = logStorage();
			showResponse($resp);
        }
    }
}else{
	$return['success']= 0;
	$return['mensaje']= "El metodo ".$method." para Logs aun no esta disponible.";
	showResponse($return);
}

function logStorage(){
    global $input;

    extract($input);

    mylog(json_encode($input), "STORAGE_FRONT_PAGE");

    $return['success'] = 1;
    $return['mensaje'] = "Log creado correctamente";
    return $return;
}

function logAppCaida(){
    global $input;

    extract($input);
	$datosObligatorios = array("origen","version","cod_usuario","error");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
		    $return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}

    $observacion1 = (isset($input['obs1'])) ? $input['obs1'] : "";
    $observacion2 = (isset($input['obs2'])) ? $input['obs2'] : "";

    $cod_empresa = cod_empresa;
    $query = "INSERT INTO log_error_app
            SET cod_empresa = $cod_empresa,
                SO = '$origen',
                version_code = '$version',
                usuario_logeado = $cod_usuario,
                obs1 = '$observacion1',
                obs2 = '$observacion2',
                error = '$error',
                fecha = NOW()";          
    if(Conexion::ejecutar($query,NULL)){
        $return['success'] = 1;
        $return['mensaje'] = "Log creado correctamente";
    }else{
        $return['success'] = 0;
        $return['mensaje'] = "No se pudo crear el log";
    }
    return $return;
}