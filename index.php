<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Api-Key, Content-type, *");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header("Allow: GET, POST, OPTIONS, PUT, DELETE");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');    // cache for 1 day
header("Content-type:application/json; charset=utf-8");

require_once "funciones.php";
require_once "customException.php";
require_once "helpers/logs.php";
require_once "helpers/walletHelper.php";
//METODOS
$funciones = array(
    "productos" => 	"controllers/Productos.php",
    "categorias" => "controllers/CategoriaProductos.php",
    "usuarios"   => "controllers/Usuarios.php",
    "sucursales" => "controllers/Sucursales.php",
	"checkout"   => "controllers/Checkout.php",
    "ordenes"   => 	"controllers/Ordenes.php",
	"tracking"   => 	"controllers/Tracking.php",
    "carrito"   => 	"controllers/Carrito.php",
    "puntos" => "controllers/Fidelizacion.php",
    "noticias" => "controllers/Noticias.php",
    "giftcards" => "controllers/Giftcards.php",
    "cards" => "controllers/Cards.php",
    "codigos-promocionales" => "controllers/CodigoPromocional.php",
    "cupones" => "controllers/Cupones.php",
    "configuracion" => "controllers/Configuracion.php",
    "datafast" => "controllers/datafast.php",
    "nuvei" => "controllers/Nuvei.php",
    "app" => "controllers/app_config.php",
    "correos"=> "controllers/Correos.php",
	"applogs" => "controllers/Logs.php",
	"retail" => "controllers/Retail.php",
	
	//CUSTOM
	//"osole" => "controllers/custom/osole/osole.php",
	"productos-oahu" => "controllers/custom/oahu/productos.php",
);

$empresa = NULL;
//Solicitud Info
$method = $_SERVER['REQUEST_METHOD'];
if($method == "OPTIONS"){
    $return['success']= 1;
	$return['mensaje']= "Validacion completa";
    showResponse($return);
}
$endpoint = $_SERVER['REQUEST_URI'];

// saveLog('server.log', json_encode($_SERVER));

if(verificateWs($empresa))
{
	$cod_empresa = $empresa['cod_empresa'];
	$alias = $empresa['alias'];
	$files = url_sistema.'assets/empresas/'.$alias.'/';
	$filesUpload = url_sistema.'assets/empresas/'.$alias.'/';
	define('cod_empresa',$cod_empresa);
	define('alias',$alias);
	define('url',$files);
	define('urlUpload',$filesUpload);
	define('name_site',$empresa['nombre']);
	define('url_web',$empresa['url_web']);
	define('api_key',$empresa['api_key']);
	define('contact_manager',$empresa['telefono']);
	define('user_id',getUserHeader());

	if($empresa['fidelizacion'] == 1)
		define('fidelizacion',true);
	else	
		define('fidelizacion',false);
	
	$cod_sucursal = getFirstSucursal();
	define('sucursaldefault',$cod_sucursal);
    
    logVerifyFolder();
    logEndpoint($endpoint, $method);

	$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $_SERVER['HTTP_REFERER'];
	$request = explode("/", trim($path,'/'));
	if(count($request)>0){
		if (array_key_exists($request[0], $funciones)) {
		    logEntityFolder($request[0]);
			if($method == "POST"){
			    $json = file_get_contents('php://input');
				$json = str_replace("\\n", ". ", $json);
				$input = json_decode($json,true);
				logPostInfo($endpoint, $json);
				if (JSON_ERROR_NONE !== json_last_error()){
					$return['success']= -1;
					$return['mensaje']= "El Json de entrada no tiene un formato correcto.";
					showResponse($return);
				}
				if(count($input)==0){
					$return['success']= -1;
					$return['mensaje']= "No hay valor de entrada";
					showResponse($return);
				}
			}
            
            
		    require_once $funciones[$request[0]];
		}else{
			$return['success']= -1;
			$return['mensaje']= "Evento ".$request[0]." no existente, por favor verificar la URL.";
		}
	}
}	
else
{
	$return['success']= -1;
	$return['mensaje']= "No autorizado";
	showResponse($return);
}

$return['success']= 0;
$return['mensaje']= "No hay respuesta, metodo no encontrado";
echo json_encode($return);

function showResponse($return){
	/* MANEJO DE ERRORES */
	/*
	switch ($return['success']) {
		case -1:	//NO AUTORIZADO
			http_response_code(401);
			break;
		case 1:
			http_response_code(200);
			break;
		case 0:
			http_response_code(200);
			break;
		default:
			http_response_code(401);
			break;
	}*/
	http_response_code(200);
	echo json_encode($return);
	exit();
}

function availableMessageWhatsapp(){
	require_once "clases/cl_empresas.php";
	$Clempresas = new cl_empresas();
	if($Clempresas->getPermiso('NOTIFY_WHATSAPP')){
		$phone = contact_manager;
		if(strlen($phone) > 10){
			return true;
		}
	}
	return false;
}

function responseError($mensaje = 'Ocurrió un error', $errorCode = 'ERROR_DEFAULT'){
    showResponse(['success' => 0, 'mensaje' => $mensaje, 'errorCode' => $errorCode]);
}

function validateUserAuthenticated(){
	if(!user_id){
		showResponse(['success' => 0, 'mensaje' => 'Se requiere autenticación', 'errorCode' => 'NO_AUTHENTICATED']);
	}

	require_once "clases/cl_usuarios.php";
	$Clusuarios = new cl_usuarios();
	$usuario = $Clusuarios->get(user_id);
	if(!$usuario){
		showResponse(['success' => 0, 'mensaje' => 'Usuario incorrecto', 'errorCode' => 'INCORRECT_USER']);
	}
	return $usuario;
}

function validateInputs($arrayInputs){
    global $input;
    foreach ($arrayInputs as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$mensaje = "Falta informacion, Error: Campo $value es obligatorio";
			showResponse(['success' => 0, 'mensaje' => $mensaje, 'errorCode' => 'FALTA_INFORMACION']);
		}
	}
	return $input;
}

function validateInputsArrayValues($arrayInputs, $arrayValues){
    foreach ($arrayInputs as $key => $value) {
		if (!array_key_exists($value, $arrayValues)) {
			$mensaje = "Falta informacion, Error: Campo $value es obligatorio";
			showResponse(['success' => 0, 'mensaje' => $mensaje, 'errorCode' => 'FALTA_INFORMACION']);
		}
	}
	return $arrayValues;
}

function dd($a){
    var_dump($a);
    exit();
}

?>