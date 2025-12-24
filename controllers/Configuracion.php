<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_empresas.php";
$ClEmpresas = new cl_empresas();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 1){
		    $return = getConfiguracion();
			showResponse($return);
		}else if($num_variables == 2){
			$return = getConfiguracion($request[1]);
			showResponse($return);
		}
		else{
			$return['success']= 0;
			$return['mensaje']= "Url no valida para configuracion, por favor revisar los parametros";
			showResponse($return);
		}
	}
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para configuracion aun no esta disponible.";
		showResponse($return);
	}


function getConfiguracion($aplicacion = ""){
	global $ClEmpresas;
	$return['success'] = 1;
	$return['mensaje'] = "Correcto";

	$info = [];
	$empresa = $ClEmpresas->get();
	if($empresa){
		$info['address'] = $empresa['direccion'];
		$info['phone'] = $empresa['telefono'];
		$info['email'] = $empresa['correo'];
		$info['color'] = $empresa['color'];
		$info['pixel'] = $empresa['facebook_pixel'];
		$info['url_android'] = $empresa['url_android'];
		$info['url_ios'] = $empresa['url_ios'];
		$info['promo_text'] = $empresa['promo_text'];
		$info['social_networks'] = $ClEmpresas->getRedesSociales();
		$info['save_card'] = $ClEmpresas->hasSaveCard();
	}

	$return['informacion'] = $info;
	
	/*FIDELIZACION*/
	$return['fidelizacion_active'] = fidelizacion;
	$fidelizacion = $ClEmpresas->getFidelizacion();
	if($fidelizacion){
		$fidelizacion['activo'] = true;
		$return['fidelizacion'] = [
		    'amount' => number_format($fidelizacion['divisor_puntos'],2),
		    'points' => number_format($fidelizacion['monto_puntos'],2),
		];
		$return['niveles'] = $ClEmpresas->getNiveles();
		$return['faqs'] = $ClEmpresas->getFaqs();
	}else{
		$fidelizacion['activo'] = false;
		$return['fidelizacion'] = null;
	}

	/*VERSION APP*/
	if($aplicacion !== ""){
		$versionApp = $ClEmpresas->getAppVersion($aplicacion);
		if($versionApp){
			$versionApp['code'] = intval($versionApp['code']);
			$versionApp['texto'] = html_entity_decode($versionApp['texto']);
			$return['app_version'] = $versionApp;

			if($aplicacion == "ANDROID")
				$return['app_version']['url'] = $empresa['url_android'];
			
			if($aplicacion == "IOS")
				$return['app_version']['url'] = $empresa['url_ios'];
		}

		/*VALIDAR CAMPOS EN EL REGISTRO*/
		$return['registro_test'] = false;
		if(isset($_GET['version'])){
			$version = $_GET['version'];
			$query = "SELECT * FROM tb_app_registro_reglas WHERE origen='$aplicacion' AND version_code='$version'";
			if(Conexion::buscarRegistro($query)){
				$return['registro_test'] = true;
			}
		}
	}
	return $return;
}	
?>