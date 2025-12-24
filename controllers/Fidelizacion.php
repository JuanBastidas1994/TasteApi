<?php
	/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */
require_once "helpers/calcularPuntosCliente.php";

require_once "clases/cl_empresas.php";
require_once "clases/cl_clientes.php";
$Clempresas = new cl_empresas();

$divisor = 0;
$puntosxdivisor = 0;
$config = $Clempresas->getFidelizacion();
if($config){
    $divisor = $config['divisor_puntos'];
    $puntosxdivisor = $config['monto_puntos'];
}

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 2){
			$return = getInfo($request[1]);
			showResponse($return);
		}
		if($num_variables == 3){
            if($request[1] == "purchase-code"){
				$return = CodePurchase($request[2]);
				showResponse($return);
			}
		}

		$return['success']= 0;
		$return['mensaje']= "Evento no existente";
		showResponse($return);
	}
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para puntos no esta disponible.";
		showResponse($return);
	}


/*FUNCIONES*/
function getInfo($user_id){
	$Clclientes = new cl_clientes();
    
    require_once "clases/cl_usuarios.php";
    $Clusuarios = new cl_usuarios();
    
    $usuario = $Clusuarios->get($user_id);
    if(!$usuario){
        responseError("Usuario no existe", "CLIENTE_INEXISTENTE");
    }
    
    $cod_usuario = $usuario['cod_usuario'];
    if(!$Clclientes->getByUser($cod_usuario))
		return [ 'success' => 0, 'mensaje' => 'Usuario no tiene un cliente creado'];
    
    $wallet = getWallet($cod_usuario);
		
	$saldo = $wallet['saldo'];
	$nivel = $wallet['nivel'];
    $nivelImg = url_resource."nivel".$nivel['posicion'].".png";
    if($nivel['imagen'] !== "")
        $nivelImg = url.$nivel['imagen'];

	$loyalty = [
	    'total_dinero' => number_format($wallet['dinero'],2,".",""),
	    'total_puntos' => $wallet['puntos'],
	    'total_saldo' => floor($saldo),
	    'total_saldo_real' => $saldo,
	    'cod_nivel' => $nivel['cod_nivel'],
	    'num_nivel' => $nivel['posicion'],
	    'nivel' => strtoupper(html_entity_decode($nivel['nombre'])),
        'imagen' => $nivelImg,
	    'puntaje_minimo' => $nivel['punto_inicial'],
	    'puntaje_maximo' => $nivel['punto_final'],
    	'cliente' => [
    	    'cod_cliente' => $Clclientes->cod_cliente,
    	    'nombre' => $Clclientes->nombre,
    	    'num_documento' => $Clclientes->cedula,
    	    'fecha_nac' => $Clclientes->fecha_nac,
    	],
	    'fecha_act' => fecha(),
        'nivelcomplete' => $nivel, 
	];
	
	return [
	    'success' => 1,
	    'mensaje' => 'Informacion del cliente',
	    'data' => $loyalty
	];
}

function CodePurchase($user_id){
    require_once "clases/cl_usuarios.php";
    $Clusuarios = new cl_usuarios();
    
    $usuario = $Clusuarios->get($user_id);
    if($usuario){
        $num_documento = $usuario['num_documento'];
        
        $code = $Clusuarios->getPurchaseCodeActive($user_id);
        if(!$code){
            $codigo = md5($num_documento.datetime_format2());
            $codigo = hash("crc32b", $codigo);
            $saveCode = $Clusuarios->createPurchaseCodeActive($user_id, $codigo, '00:10:00');
            if($saveCode){
                $code = $Clusuarios->getPurchaseCodeActive($user_id);
                $mensaje = "Código nuevo generado";
            }else{
                $return['success'] = 0;
                $return['mensaje'] = "No se pudo generar el codigo";
                return $return;
            }
        }else{
            $mensaje = "Código con tiempo aún disponible";
        }
        $code['expiracion'] = "Expira a las ".date("H:i", strtotime($code['fecha_expiracion']));
        $code['tiempo'] = minutesRestantes($code['fecha_expiracion']);
		$horaExpiracion = explode(" ",$code['fecha_expiracion'])[1];
        $code['tiempo_restante'] = minutesRestantes2(fecha(), $horaExpiracion);
        $code['expiracion_fecha'] = date_format(date_create($code['fecha_expiracion']), "m/d/Y");
        $code['expiracion_hora'] = date_format(date_create($code['fecha_expiracion']), "H:i:s");
        
        $return['success'] = 1;
        $return['mensaje'] = $mensaje;
        $return['code'] = $code;
    }else{
        responseError("Usuario no existente", "USUARIO_INEXISTENTE");
    }
    return $return;
}
?>