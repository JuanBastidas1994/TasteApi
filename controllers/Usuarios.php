<?php
	/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_usuarios.php";
require_once "clases/cl_clientes.php";
$Clusuarios = new cl_usuarios();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 2){
		    if(is_numeric($request[1])){
            	$resp = $Clusuarios->get2($request[1]);
            	if($resp){
					
					if($resp['estado'] !== 'A')
						$resp['mensaje_inactivo'] = "Tu cuenta esta inhabilitada, por favor contacta con soporte";
					
				    $phone = normalizarTelefono($resp['telefono']);
					$resp['telefono'] = ($phone) ? $phone : "";
					
					/*if($resp['telefono_verificado'] == 0)
					    $resp['telefono'] = "";*/

            		$return['success'] = 1;
            		$return['mensaje'] = "Correcto";
            		$return['data'] = $resp;
            	}else{
            		$return['success'] = 0;
            		$return['mensaje'] = "No hay datos";
            	}
    			showResponse($return);
		    }	
		}
		if($num_variables == 3){
			$first = $request[1];
			if($first=="ubicaciones"){
				$return = lista_ubicaciones($request[2]);
				showResponse($return);
			}
			else if($first=="notificaciones"){
				$user = $Clusuarios->get($request[2]);
				$Clusuarios->fecha_create = $user["fecha_create"];
			    $resp = $Clusuarios->getNotificaciones($request[2]);
			    if($resp){
			        array_walk_recursive($resp,function(&$toDecode){
					    $toDecode = html_entity_decode($toDecode);
				    });
			        $return['success'] = 1;
			        $return['mensaje'] = "Lista de notificaciones";
			        $return['data'] = $resp;
			    }else{
			        $return['success'] = 0;
			        $return['mensaje'] = "No hay notificaciones";
			    }
				showResponse($return);
			}
			else if($first=="eliminar_cuenta"){
				$cod_usuario = $request[2];
				$return = EliminarCuenta($request[2]);
				showResponse($return);
			}
		}

		$return['success']= 0;
		$return['mensaje']= "Evento no existente";
		showResponse($return);
	}
	else if($method == "POST"){
		$num_variables = count($request);
		if($num_variables == 2){
			$first = $request[1];
			if($first=="login_google"){
				$return = login_google();
				showResponse($return);
			}
			
			else if($first=="pre_login_express"){
				$return = preLoginExpress();
				showResponse($return);
			}
			else if($first=="login_express"){
				$return = loginExpress();
				showResponse($return);
			}
			else if($first=="registro_express"){
				$return = registroExpress();
				showResponse($return);
			}

			else if($first=="intento_pago"){
				$return = intento_pago();
				showResponse($return);
			}else if($first=="add_phone"){
				$return = add_phone();
				showResponse($return);
			}else if($first=="add_phone_novalidate"){
				$return = add_phone_no_validate();
				showResponse($return);
			}else if($first=="add_birthday"){
				$return = add_birthday();
				showResponse($return);
			}else if($first=="ubicaciones"){
				$return = save_direccion();
				showResponse($return);
			}else if($first=="suscripcion"){
				$return = suscribir();
				showResponse($return);
			}else if ($first=="facturacion") {
				$return = saveDatosFacturacion();
				showResponse($return);
			}else if ($first=="cupon") {
				$return = cupon_available();
				showResponse($return);
			}
		}
		
		$return['success']= 0;
		$return['mensaje']= "Evento no existente";
		showResponse($return);
	}else if($method == "DELETE"){
		$num_variables = count($request);
		$first = $request[1];
		if($num_variables == 3){
			if($first=="ubicaciones"){
				$return = delete_direccion($request[2]);
				showResponse($return);
			}
		}	
	}else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Login aun no esta disponible.";
		showResponse($return);
	}


/*LOGIN Y REGISTER FORM*/
function preLoginExpress() {
	global $Clusuarios;
	global $input;
	extract($input);

	if(!isset($email)) {
		$return['success'] = 0;
    	$return['mensaje'] = "Falta el correo";
		return $return;
	}

	if(!validar_correo($email)) {
		$return['success'] = 0;
		$return['mensaje'] = "El correo no tiene un formato correcto";
		$return['error_code'] = "CORREO_FORMATO_INVALIDO";
		return $return;
	}
	else {
	    $domain = explode("@", $email)[1];
	    if(!checkdnsrr($domain, "MX")){
	        $return['success'] = 0;
    	    $return['mensaje'] = "El correo es inválido, por favor verifica nuevamente el correo";
    	    $return['error_code'] = "DOMINIO_INEXISTENTE";
    	    return $return;
	    }
	}

	$usuario = $Clusuarios->getUserActiveByEmail($email);
    if($usuario) {
		if($usuario['estado'] == "I"){
	        $return['success'] = 0;
    		$return['mensaje'] = "Usuario inactivo, por favor comunícate con nosotros si crees que esto es un error.";
			$return['error_code'] = "USUARIO_INACTIVO";
			return $return;
	    }

		//LOGIN FORMULARIO
		$usersAllowed = [
			268, // USUARIO: JOSUE SANTILLAN BOLONCITY
			37612, // USUARIO: JOSUE SANTILLAN SAMBOLON
			51261, // USUARIO: JOSUE SANTILLAN PORTONES 
			51002, // USUARIO: JOSUE SANTILLAN ROLL IT 
		];	    
        $codigo = codeNumber(LOGIN_EMAIL_NUM_DIGITS);
		$cod_usuario = $usuario["cod_usuario"];

		// ? CODIGO GENÉRICO PARA QUE APPLE Y GOOGLE PUEDAN ACCEDER A LAS APPS EN REVISIÓN
		if(in_array((int)$cod_usuario, $usersAllowed)) 
			$codigo = "0017";

		if($Clusuarios->setUserCodeLogin($cod_usuario, $codigo)) {
			ExecuteRemoteQuery(url_api . "correos/loginExpress.php?alias=" . alias . "&id=$cod_usuario&pass=$codigo");
	
			$return['success'] = 1;
			$return['mensaje'] = "Código temporal actualizado";
			$return['type'] = "login";
		}
		else {
			$return['success'] = 0;
			$return['mensaje'] = "Error al insertar código temporal";
			$return['error_code'] = "CODIGO_TEMPORAL_NO_CREADO";
		}
    }
	else {
        $return['success'] = 1; //Se debe registrar
		$return['mensaje'] = "Se ha enviado un código a tu correo para que puedas registrarte";
		$return['type'] = "registro";
		$return['error_code'] = "CORREO_INEXISTENTE";
		
		$codigo = codeNumber(LOGIN_EMAIL_NUM_DIGITS);
		if($Clusuarios->setUserCodeRegister($email, $codigo)) {
			ExecuteRemoteQuery(url_api . "correos/registroExpress.php?alias=" . alias . "&pass=$codigo&correo=$email");
		}
		
		/*VALIDAR CAMPOS EN EL REGISTRO*/
		$return['registro_test'] = false;
		if(isset($version)){
			$query = "SELECT * FROM tb_app_registro_reglas WHERE origen='$origen' AND version_code='$version'";
			if(Conexion::buscarRegistro($query)){
				$return['registro_test'] = true;
			}
		}
    }
    return $return;
}

function loginExpress() {
	global $Clusuarios;
	global $input;
	extract($input);

	if(!isset($email)){
		$return['success'] = 0;
    	$return['mensaje'] = "Falta el correo";
		$return['error_code'] = "CORREO_FALTANTE";
		return $return;
	}

	if(!isset($code)){
		$return['success'] = 0;
    	$return['mensaje'] = "Falta el código temporal";
		$return['error_code'] = "CODIGO_TEMPORAL_FALTANTE";
		return $return;
	}

	$usuario = $Clusuarios->LoginExpress($email, $code);
    if($usuario) {
        $user = $Clusuarios->get2($usuario['id']);
	    $phone = normalizarTelefono($user['telefono']);
		$user['telefono'] = ($phone) ? $phone : "";
		
        $return['success'] = 1;
	    $return['mensaje'] = "Login correcto";
	    $return['data'] = $user;

		$cod_usuario = $usuario["id"];
		$Clusuarios->setEstadoCodigoTempLogin($cod_usuario, $code, "I");
		
		//DAR CUPON A LOS QUE NO LE DI EN EL REGISTRO
		$origin = (isset($origin)) ? $origin : "";
		if($origin !== "WEB"){
		    setCupon($cod_usuario, 'REGISTRO'); //Funciones.php
		}
    }
	else {
        $return['success'] = 0;
	    $return['mensaje'] = "El código temporal no existe o ya se utilizó";
		$return['error_code'] = "CODIGO_TEMPORAL_INVALIDO";
    }
    return $return;
}


function registroExpress(){
	global $Clusuarios;
	global $input;

	$input = validateInputs(array("nombre", "correo", "codigo"));
	extract($input);

	$code = $Clusuarios->getCodeRegisterExpress($correo, $codigo);
	if(!$code) {
		responseError("El código temporal no existe o ya se utilizó", "CODIGO_TEMPORAL_INVALIDO");
	}
	$validarCedula = false;
	$num_documento = "";
// 	if(isset($input['origen']) && isset($input['version'])){
// 		$query = "SELECT * FROM tb_app_registro_reglas WHERE origen='$origen' AND version_code='$version' AND cod_empresa = ".cod_empresa;
// 		if(Conexion::buscarRegistro($query)){
// 			$validarCedula = false;
// 			$cedula = "0999999955";
// 		}
// 	}

// 	if($validarCedula){
// 		if(!ValidarCedula($num_documento)){
// 			responseError("La cédula $num_documento no es válida", "NUMDOCUMENTO_INVALIDO");
// 		}
// 	}

	$validName = validarNombre($nombre);
	if(!$validName['success']){
		responseError($validName['mensaje'], "NOMBRE_INVALIDO");
	}

    $correo = trim($correo);
	if(!validar_correo($correo)){
		responseError("El correo no tiene un formato correcto", "INFORMACION_INVALIDA");
	}else{
	    $domain = explode("@", $correo)[1];
	    if(!checkdnsrr($domain, "MX")){
			responseError("El correo es inválido, por favor revifica nuevamente el correo", "INFORMACION_INVALIDA");
	    }
	}

    if($Clusuarios->usuarioDisponible($correo)){
    	$Clusuarios->cod_empresa = cod_empresa;
	    $Clusuarios->cod_rol = 4;
	    $Clusuarios->nombre = $nombre;
	    $Clusuarios->num_documento = $num_documento;
		$Clusuarios->correo = $correo;
	    $Clusuarios->usuario = $correo;
	    $Clusuarios->password = "N0P4ss";
	    $Clusuarios->apellido = '';
	    $Clusuarios->telefono = '';
	    $Clusuarios->fecha_nacimiento = "";
        
        $cod_usuario = 0;
	    if($Clusuarios->registro($cod_usuario)){
	        
	        $Clclientes = new cl_clientes();
	        $Clclientes->create($cod_usuario, $nombre, $cod_cliente, $num_documento);

			//DAR DE BAJA EL CÓDIGO TEMPORAL DE REGISTRO
			$Clusuarios->setEstadoCodigoTempRegister($cod_usuario, $codigo, "I");
	        
	    	$return['success'] = 1;
	    	$return['mensaje'] = "Registro completado con éxito";
	    	
	    	/*INFORMACION DEL USUARIO*/
    		$resp = $Clusuarios->get2($cod_usuario);
		    if($resp){
			    $return['data'] = $resp;
		    }
		    
		    //CUPONES
		    setCupon($cod_usuario, 'REGISTRO'); //Funciones.php
	    }else{
	    	$return['success'] = 0;
	    	$return['mensaje'] = "Error al registrar";
	    }
    }else{
    	$return['success'] = 0;
	    $return['mensaje'] = "Correo ya registrado. Revisa tu correo con la contraseña o restablece tu contraseña para iniciar sesión.";
	    $return['error_code'] = "USUARIO_EXISTENTE";
    }
    return $return;
}

/*LOGIN WITH GOOGLE*/
function login_google(){
	global $Clusuarios;
	global $input;
	extract($input);
	
	$datosObligatorios = array("nombre", "correo");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
    		$return['error_code'] = "FALTA_INFORMACION";
			return $return;
		}
	}

	$apellido = "";

	if(count(explode(" ", $nombre)) < 2) {
		$return['success'] = 0;
    	$return['mensaje'] = "Ingrese al menos un nombre y un apellido";
    	$return['error_code'] = "NOMBRE_INVALIDO";
    	return $return;
	}

    $correo = trim($correo);
	if(!validar_correo($correo)){
		$return['success'] = 0;
    	$return['mensaje'] = "El correo no tiene un formato correcto";
    	$return['error_code'] = "INFORMACION_INVALIDA";
    	return $return;
	}else{
	    $domain = explode("@", $correo)[1];
	    if(!checkdnsrr($domain, "MX")){
	        $return['success'] = 0;
    	    $return['mensaje'] = "El correo es inválido, por favor revifica nuevamente el correo";
    	    $return['error_code'] = "INFORMACION_INVALIDA";
    	    return $return;
	    }
	}
    
    $usuario = $Clusuarios->getUserActiveByEmail($correo);
    if(!$usuario){ //CREAR
    	$Clusuarios->cod_empresa = cod_empresa;
	    $Clusuarios->cod_rol = 4;
	    $Clusuarios->nombre = $nombre;
	    $Clusuarios->apellido = $apellido;
	    $Clusuarios->telefono = "";
		$Clusuarios->correo = $correo;
	    $Clusuarios->usuario = $correo;
	    $Clusuarios->password = "N0P4ss";
	    $Clusuarios->fecha_nacimiento = "";
	    $Clusuarios->num_documento = "";
        
        $cod_usuario = 0;
	    if($Clusuarios->registro($cod_usuario)){
	        $Clclientes = new cl_clientes();
	        $Clclientes->create($cod_usuario, $nombre, $cod_cliente, "");
	        
	        //TODO no debería crearse el cliente si no tengo cedula
	    	$return['success'] = 1;
	    	$return['mensaje'] = "Registro completado con éxito";
	    	
	    	/*INFORMACION DEL USUARIO*/
    		$resp = $Clusuarios->get2($cod_usuario);
		    if($resp){
			    $return['data'] = $resp;
		    }
		    
		    //CUPONES
		    setCupon($cod_usuario, 'REGISTRO'); //Funciones.php
	    }else{
	    	$return['success'] = 0;
	    	$return['mensaje'] = "Error al registrar";
	    }
    }else{
    	if($usuario['estado'] == "I"){
	        $return['success'] = 0;
    		$return['mensaje'] = "Usuario inactivo, por favor comunícate con nosotros si crees que esto es un error.";
			$return['error_code'] = "USUARIO_INACTIVO";
			return $return;
	    }
	    
	    //LOGIN GOOGLE
	    $user = $Clusuarios->get2($usuario['cod_usuario']);
	    $phone = normalizarTelefono($user['telefono']);
		$user['telefono'] = ($phone) ? $phone : "";
	    
        $return['success'] = 1;
        $return['mensaje'] = "Login correcto";
        $return['type'] = "google";
        $return['data'] = $user;
    }
    return $return;
}


function EliminarCuenta($cod_usuario){
	global $Clusuarios;
	$respuesta = $Clusuarios->deleteUser($cod_usuario);
	if($respuesta){
		$return['success'] = 1;
		$return['mensaje'] = "Cuenta eliminada correctamente";
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "Error al eliminar la cuenta";
	}
	return $return;
}

/*-------------UBICACIONES------------------*/
function lista_ubicaciones($cod_usuario){
	global $Clusuarios;
	$direcciones = $Clusuarios->direcciones($cod_usuario);
	if($direcciones){
		$return['success'] = 1;
		$return['mensaje'] = "Correcto";
		$return['data'] = $direcciones;
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "No hay datos";
	}
	return $return;
}

function save_direccion(){
	global $Clusuarios;
	global $input;
	extract($input);
	
	$datosObligatorios = array("cod_usuario","nombre","direccion","referencia","latitud","longitud");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}	
	}

	$resp = $Clusuarios->save_direcciones($cod_usuario, $nombre, $direccion, $latitud, $longitud, $referencia);
    if($resp){
        $return['success'] = 1;
	    $return['mensaje'] = "Direccion ingresada correctamente";
    }else{
        $return['success'] = 0;
	    $return['mensaje'] = "Error al ingresar la informacion";
    }
    return $return;
}

function delete_direccion($cod_direccion){
	global $Clusuarios;

	$direccion = $Clusuarios->get_direccion($cod_direccion);
	if($direccion){
		if($Clusuarios->delete_direccion($cod_direccion)){
			$return['success'] = 1;
			$return['mensaje'] = "Dirección eliminada correctamente";
		}else{
			$return['success'] = 0;
			$return['mensaje'] = "No se pudo eliminar la direccion, por favor intentalo nuevamente";
		}
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "Dirección no existente, por favor actualiza tu información";
	}
	return $return;
}

/*LOGS*/
function intento_pago(){
	global $Clusuarios;
	global $input;
	
	$datosObligatorios = array("cod_usuario", "data", "proveedor", "tipo", "origen");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}	
	}
	extract($input);
	logAdd(json_encode($input),"trama-ingreso","intento-pago");

	$monto = (isset($input['monto'])) ? $input['monto'] : 0;

	$json = json_encode($data);

	
	$usuario = $Clusuarios->get($cod_usuario);
	if($usuario){
	    if($usuario['estado'] == "I"){
	        $return['success'] = 0;
    		$return['mensaje'] = "Usuario inactivo, por favor comunícate con nosotros si crees que esto es un error.";
			return $return;
	    }

		//ESTADO DEL PAGO
		$estadoPago["status"] = $tipo;
		$estadoPago["info"] = "";

		$fraude = 0;
		$estadoFraude = "I";
		if($proveedor == 2) { // PAYMENTEZ
			if($tipo == "failure") {
				$estadoPago["detail"] = $data["transaction"]["message"];
				$codigosFraude = array(6, 11, 21, 24);
				if(in_array($data["transaction"]["status_detail"], $codigosFraude)) { //FRAUDE
					$fraude = 1;
					$estadoFraude = "A";
					$estadoPago["text"] = "Pago rechazado por el sistema antifraudes, por favor revisar los datos de la tarjeta.";
				}
				else if($data["transaction"]["status_detail"] == 9) {
					if(strpos($json, "fondo") !== false || strpos($json, "FONDO") !== false) {
						$estadoPago["text"] = "Fondos insuficientes";
					}
					else {
						$estadoPago["text"] = "Pago rechazado, por favor revisa los datos de tu tarjeta. Se aceptan Visa o Mastercard";
					}
				}
				else {
					$estadoPago["text"] = "Ocurrió un error en el pago, por favor revisar los datos de la tarjeta.";
				}
			}
			else if($tipo == "success") {
				$estadoPago["detail"] = $data["transaction"]["message"];
				$estadoPago["text"] = "Pago exitoso";
				// $Clusuarios->removerBloqueUsuario($cod_usuario);
				$Clusuarios->removerIntentosPagoFraude($cod_usuario);
			}
		}

		$resp = $Clusuarios->setLogPago($cod_usuario, $proveedor, $monto, $origen, $tipo, $fraude, $json, $estadoFraude);
		if($resp){

			if($fraude == 1) {
				$fraudes = $Clusuarios->getIntentosPagoDiaActual($cod_usuario);
				if($fraudes) {
					if(count($fraudes) == 2) {
						$estadoPago["info"] = "Queda un intento de pago restante, caso contrario se bloquerá el usuario";
					}
					if(count($fraudes) > 2) {
						$Clusuarios->setBloqueoUsuario($cod_usuario, 1, "Múltiples intentos de pagos erróneos en un día");
					}
				}
	
				$fraudes = $Clusuarios->getIntentosPagoFraude($cod_usuario);
				if($fraudes) {
					if(count($fraudes) == 5) {
						$estadoPago["info"] = "Queda un intento de pago restante, caso contrario se bloquerá el usuario";
					}
					if(count($fraudes) > 5) {
						$bloqueo = $Clusuarios->setBloqueoUsuario($cod_usuario, 365, "Múltiples intentos de pagos erróneos");
						if($bloqueo) {
							$Clusuarios->setEstadoUsuario($cod_usuario, "I");
						}
					}
				}
			}

			$return['success'] = 1;
			$return['mensaje'] = "Se ha guardado el log";
			$return['payment'] = $estadoPago;
		}else{
			$return['success'] = 0;
			$return['mensaje'] = "No se pudo guardar el log";
		}
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "Usuario no existe, por favor verifica e intenta nuevamente";
	}
	return $return;
}

/*UPDATE INFORMACION*/
function add_phone(){
	global $Clusuarios;

	$input = validateInputs(array("cod_usuario", "phone"));
	extract($input);

	$Clusuarios = new cl_usuarios();
	$usuario = $Clusuarios->get($cod_usuario);
    if(!$usuario){
    	$return['success'] = 0;
    	$return['mensaje'] = "Usuario no existente";
    	$return['errorCode'] = "USUARIO_INEXISTENTE";
    	return $return;
    }
    
    $phone = normalizarTelefono($phone);
    if(!$phone){
        $return['success'] = 0;
        $return['mensaje'] = 'El teléfono no tiene un formato válido';
    }
    
    if(!isset($input['otp'])){ //Generar el OTP
        $code = codeNumber();
        if($Clusuarios->setUserCodePhone($cod_usuario, $code)){
            require_once "clases/cl_ultramsg.php";
            $ClMessages = new cl_ultramsg();
            $message = $ClMessages->sendOTP($phone, $code);
            $sent = isset($message['sent']) ? $message['sent'] : false;
            if($sent){
                $return['success'] = 1;
            	$return['mensaje'] = "OTP enviado correctamente";
            	$return['whatsapp'] = $message;
            }else{
                $mensaje = isset($message['error']) ? $message['error'] : "El código OTP no pudo ser entregado, por favor intentalo nuevamente";
                $return['success'] = 0;
            	$return['mensaje'] = $mensaje;
            	$return['whatsapp'] = $message;
            }
        }else{
            $return['success'] = 0;
            $return['mensaje'] = 'Ocurrió un error al generar el código';
        }
    	return $return;
    }else{
        $otp = $input['otp'];
    	if($Clusuarios->getCodePhone($cod_usuario, $otp)) {
    		$update = $Clusuarios->set_telefono_verificado($cod_usuario, $phone);
    		if($update){
    		    $return = [ 'success' => 1, 'mensaje' => 'Teléfono actualizado correctamente' ];
    		}else{
    		    $return = [ 'success' => 0, 'mensaje' => 'No se pudo actualizar el telefono, intentelo nuevamente' ];
    		}
    	}else{
    		$return = [
    		    'success' => 0,
    		    'mensaje' => 'El código ingresado no es correcto',
    		    'error_code' => "CODIGO_TEMPORAL_INVALIDO" 
    		];
    	}
    	return $return;
    }
}

function add_phone_no_validate(){
	global $Clusuarios;

	$input = validateInputs(array("cod_usuario", "phone"));
	extract($input);

	$Clusuarios = new cl_usuarios();
	$usuario = $Clusuarios->get($cod_usuario);
    if(!$usuario){
    	$return['success'] = 0;
    	$return['mensaje'] = "Usuario no existente";
    	$return['errorCode'] = "USUARIO_INEXISTENTE";
    	return $return;
    }
    
    $phone = normalizarTelefono($phone);
    if(!$phone){
        $return['success'] = 0;
        $return['mensaje'] = 'El teléfono no tiene un formato válido';
    }
    
    $update = $Clusuarios->set_telefono($cod_usuario, $phone);
	if($update){
	    $return = [ 'success' => 1, 'mensaje' => 'Teléfono actualizado correctamente', 'telefono' => $phone ];
	}else{
	    $return = [ 'success' => 0, 'mensaje' => 'No se pudo actualizar el telefono, intentelo nuevamente' ];
	}
    		
    return $return;
}

function add_birthday(){
	global $Clusuarios;

	$usuario = validateUserAuthenticated();
	$input = validateInputs(array("date"));
	extract($input);

	if($Clusuarios->set_fecha_nacimiento($usuario['cod_usuario'], $date)){
		return [ 'success' => 1, 'mensaje' => 'Fecha actualizada correctamente', 'date' => fechaLatino($date) ];
	}else{
		return [ 'success' => 0, 'mensaje' => 'No se pudo actualizar la fecha de nacimiento, por favor intentelo nuevamente' ];
	}
}

/*SUSCRIBIR*/
function suscribir(){
	global $Clusuarios;
	global $input;
	extract($input);
	
	$datosObligatorios = array("correo");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}	
	}

	if(!validar_correo($correo)){
		$return['success'] = 0;
		$return['mensaje'] = "Correo no válido, por favor verifique la información";
		return $return;
	}


	$query = "SELECT * FROM tb_empresa_suscripciones WHERE correo='$correo' AND estado='A' AND cod_empresa = ".cod_empresa;
	if(Conexion::buscarRegistro($query)){
		$return['success'] = 0;
		$return['mensaje'] = "Correo ya registrado";
		return $return;
	}

	$origen = isset($input['origen']) ? $input['origen'] : "WEB";
	$fecha = fecha();
	$cod_empresa = cod_empresa; 
	$query = "INSERT INTO tb_empresa_suscripciones(cod_empresa, correo, fecha, origen, estado)
			VALUES($cod_empresa, '$correo', '$fecha', '$origen', 'A')";
	if(Conexion::ejecutar($query,NULL)){
		$return['success'] = 1;
		$return['mensaje'] = "Correo suscripto correctamente";
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "No se pudo suscribir el correo, por favor intentelo nuevamente";
	}
	return $return;
}

function saveDatosFacturacion() {
	global $Clusuarios;
	global $input;
	extract($input);

	$datosObligatorios = array("cod_usuario", "nombre", "telefono", "correo", "num_documento", "direccion", "tipo_documento");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}	
	}

		// VALIDAR NUM DOCUMENTO
		if($esExtranjero == 0) {
			if($tipo_documento == "DNI") {
				if(strlen(trim($num_documento)) <> 10) {
					$return['success'] = 0;
					$return['mensaje'] = "Formato de cédula es inválido";
					return $return;
				}
		
				if(!ValidarCedula(trim($num_documento))) {
					$return['success'] = 0;
					$return['mensaje'] = "Número de cédula es inválido";
					return $return;
				}
			}
			else if($tipo_documento == "RUCN") {
				if(strlen(trim($num_documento)) <> 13) {
					$return['success'] = 0;
					$return['mensaje'] = "Formato de RUC natural es inválido";
					return $return;
				}
		
				if(!ValidarCedula(trim($num_documento))) {
					$return['success'] = 0;
					$return['mensaje'] = "Número de RUC natural es inválido";
					return $return;
				}
			}
			else if($tipo_documento == "RUCJ") {
				if(strlen(trim($num_documento)) <> 13) {
					$return['success'] = 0;
					$return['mensaje'] = "Formato de RUC jurídico es inválido";
					return $return;
				}
		
				if(!validar_ruc_juridico_ecuador(trim($num_documento))) { 
					$return['success'] = 0;
					$return['mensaje'] = "Número de RUC jurídico es inválido";
					return $return;
				}
			}
		}
	
	$Clusuarios->cod_usuario = $cod_usuario;
	$Clusuarios->nombre = str_replace("'", "", trim($nombre));
	$Clusuarios->telefono = trim($telefono);
	$Clusuarios->correo = str_replace("'", "", trim($correo));
	$Clusuarios->num_documento = trim($num_documento);
	$Clusuarios->direccion = str_replace("'", "", trim($direccion));
	$Clusuarios->esExtranjero = $esExtranjero;
	$Clusuarios->tipoDocumento = $tipo_documento;

	$datos = $Clusuarios->getDatosFacturacion($cod_usuario);
	if(!$datos) {
		if($Clusuarios->saveDatosFacturacion()) {
			$return['success'] = 1;
			$return['mensaje'] = "Datos de facturación guardados correctamente";
			return $return;
		}
		else {
			$return['success'] = 0;
			$return['mensaje'] = "Error al guardar los datos de facturación";
			return $return;
		}
	}
	else {
		if($Clusuarios->editDatosFacturacion()) {
			$return['success'] = 1;
			$return['mensaje'] = "Datos de facturación editados correctamente";
			return $return;
		}
		else {
			$return['success'] = 0;
			$return['mensaje'] = "Error al editar los datos de facturación";
			return $return;
		}
	}
}

function cupon_available(){
	require_once "clases/cl_ordenes.php";
    global $Clusuarios;
    global $input;
	$Clordenes = new cl_ordenes();

	//Tipo = WEB | APP
	$input = validateInputs(array("cod_usuario", "cod_sucursal", "total", "envio", "tipo"));
	extract($input);

	$usuario = $Clusuarios->get($cod_usuario);
	if(!$usuario)
		showResponse(['success' => 0, 'mensaje' => 'Usuario no encontrado']);

	
	//TODO falta mostrar cuanto tiene que aumentar a la orden para obtener el producto gratis
	//$mensaje = "Agrega algo más al carrito. La compra en productos debe ser mayor a \$5";
	$subtotal = number_format(($total - $envio),2);
	$freeProduct = $Clordenes->getFreePromo($cod_sucursal, $subtotal, $tipo);
	if(!$freeProduct)
		showResponse(['success' => 0, 'mensaje' => 'No hay promocion creada']);
	
	if($freeProduct['tipo'] !== 'FIRST_ORDER')
		showResponse(['success' => 0, 'mensaje' => 'No hay promocion first order creada']);

	$num_orders = $Clusuarios->getNumOrders($usuario['cod_usuario']);
	if($num_orders > 0)
		showResponse(['success' => 0, 'mensaje' => 'Usuario ya cuenta con ordenes anteriores']);

	$mensaje = "Usuario aplica promocion";
	$aplica = true;
	return [ 
		"success" => 1, 
		"mensaje" => $mensaje, 
		"aplica"=>$aplica, 
		"cupon" => [
			'titulo' => 'Producto Gratis',
			'imagen' => url.$freeProduct['imagen'],
			'descripcion' => 'Tienes un producto gratis'
		] 
	];
}
?>