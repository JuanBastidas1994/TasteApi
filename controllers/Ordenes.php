<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_ordenes.php";
require_once "clases/cl_usuarios.php";
require_once "clases/cl_empresas.php";
require_once "clases/cl_sucursales.php";

$Clordenes = new cl_ordenes();
$Clusuarios = new cl_usuarios();
$Clempresas = new cl_empresas();
$Clsucursales = new cl_sucursales();

// require_once "strategies/Values/ClientFirstOrderValues.php";

if ($method == "GET") {
	$num_variables = count($request);
	// if($num_variables == 1){
	// 	$strategyClass = ClientFirstOrderValues::STRATEGY[0];
	// 	$resp = (new $strategyClass)->setAward(120);
	// 	showResponse([ 'success' => 2, 'data' => $resp ]);
	// }
	if ($num_variables == 2) {
		$cod_usuario = $request[1];
		$ordenes = $Clordenes->listaByUser($cod_usuario);
		if ($ordenes) {
			array_walk_recursive($ordenes, function (&$toDecode) {
				$toDecode = html_entity_decode($toDecode);
			});
			$return['success'] = 1;
			$return['mensaje'] = "Correcto";
			$return['data'] = $ordenes;
		} else {
			$return['success'] = 0;
			$return['mensaje'] = "No hay datos";
		}
		showResponse($return);
	} else if ($num_variables == 3) {
		if ($request[1] == "id") {
			$cod_orden = $request[2];
			$ordenes = $Clordenes->get_orden_array($cod_orden);
			if ($ordenes) {
				array_walk_recursive($ordenes, function (&$toDecode) {
					$toDecode = html_entity_decode($toDecode);
				});
				$return['success'] = 1;
				$return['mensaje'] = "Correcto";
				$return['data'] = $ordenes;
			} else {
				$return['success'] = 0;
				$return['mensaje'] = "No hay datos";
			}
			showResponse($return);
		}
		if ($request[1] == "preorden") {
			$cod_preorden = $request[2];
			$return = getPreOrden($cod_preorden);
			showResponse($return);
		}
	}
} else if ($method == "POST") {
	$num_variables = count($request);
	if ($num_variables == 1) {
		$return = crear();
		showResponse($return);
	} else if ($num_variables == 2) {
		if ($request[1] == "validar") {
			$return = validarOrdenCorrecta();
			showResponse($return);
		}
		if ($request[1] == "preorden") {
			$return = convertirPreorden();
			showResponse($return);
		}
		if ($request[1] == "preordentoken") {
			$return = convertirPreordenToken();
			showResponse($return);
		}
		if ($request[1] == "verifytransaction") {
			$return = verifyTransaction();
			showResponse($return);
		}
		if ($request[1] == "preorden-failure") {
			$return = failurePreorden();
			showResponse($return);
		}
		if ($request[1] == "preorden-closemodal") {
			$return = closemodalPreorden();
			showResponse($return);
		}
		if ($request[1] == "calificar") {
			$return = calificarOrden();
			showResponse($return);
		}
	}

	$return['success'] = 0;
	$return['mensaje'] = "Evento no existente";
	showResponse($return);
} else {
	$return['success'] = 0;
	$return['mensaje'] = "El metodo " . $method . " para Ordenes aun no esta disponible.";
	showResponse($return);
}

function validarOrdenCorrecta(){
	global $Clusuarios;
	global $Clordenes;
	global $input;
	$msgError = "";
	extract($input);

	$input = validateInputs(array("cod_usuario", "cod_sucursal", "telefono", "total", "metodoEnvio", "metodoPago", "productos"));
    logAdd(json_encode($input),"trama-ingreso","validar-orden");

	//QUITAR SÍMBOLOS JSON PREORDEN
	$metodoEnvio["direccion"] = sinComillas($metodoEnvio["direccion"]);
	$metodoEnvio["referencia"] = sinComillas($metodoEnvio["referencia"]);
	$input['metodoEnvio'] = $metodoEnvio;
	
// 	foreach ($input["productos"] as &$pr) {
// 		$pr["comentarios"] = sinComillas($pr["comentarios"]);
// 	}
	$input["comentarios"] = sinComillas($input["comentarios"]);

	/*USUARIO EXISTE*/
	$usuario = $Clusuarios->get($cod_usuario);
	if (!$usuario) {
		$return['success'] = 0;
		$return['mensaje'] = "Usuario no encontrado";
		$return['errorCode'] = "USUARIO_INEXISTENTE";
		showResponse($return);
	} else {
		// VERIFICAR SI EL USUARIO ESTÁ BLOQUEADO
		$bloqueo = $Clusuarios->getBloqueoUsuario($cod_usuario);
		if($bloqueo) {
			$return['success'] = 0;
			$return['mensaje'] = "Usuario bloqueado" . ". " . $bloqueo["descripcion"];
			$return['errorCode'] = "USUARIO_BLOQUEADO";
			showResponse($return);
		}
    
    	$num_documento = $usuario['num_documento'];
    	/*
    	if (trim($num_documento) === "") {
    		$return['success'] = 0;
    		$return['mensaje'] = "Numero de documento no válido";
    		$return['errorCode'] = "NUMDOC_INVALIDO";
    		showResponse($return);
    	}*/
    	
	}

	/*USUARIO EXISTE*/

	/*VALIDAR TELEFONO */
	if (trim($telefono) === "" && strlen($telefono) !== 9) {
		$return['success'] = 0;
		$return['mensaje'] = "Teléfono no válido, el teléfono debe tener 9 dígitos sin incluir +593";
		$return['errorCode'] = "TELEFONO_INVALIDO";
		showResponse($return);
	} else {	//VALIDA SI EL USUARIO NO TIENE UN TELEFONO POR PRIMERA VEZ...
		$Clusuarios->set_telefono($cod_usuario, $telefono);
	}
	// $input['telefono'] = "+593".$telefono;
	/*VALIDAR TELEFONO */

	/*CUPON EXISTE*/
	$cupon_code = isset($input['cupon']) ?  $input['cupon'] : "";
	if ($cupon_code !== "") {
		$query = "SELECT * 
					FROM tb_codigo_promocional 
					WHERE codigo = '$cupon_code' 
					AND estado='A' 
					AND fecha_expiracion >= NOW() 
					AND usos_restantes > 0 
					AND cod_empresa = " . cod_empresa;
		$resp = Conexion::buscarRegistro($query);
		if (!$resp) {
			$return['success'] = 0;
			$return['mensaje'] = "Cupon $cupon_code no válido";
			$return['errorCode'] = "CUPON_INVALIDO";
			showResponse($return);
		} else {
			if($resp["ilimitado"] == 0) {
				/*VERIFICAR SI EL USUARIO YA UTILIZÓ EL CUPÓN*/
				$couponUsed = $Clusuarios->getCouponUsed($cod_usuario, $cupon_code);
				if ($couponUsed) {
					$return['success'] = 0;
					$return['mensaje'] = "Cupón $cupon_code ya utilizado. Ingrese otro cupón o remuévalo para continuar con la compra";
					$return['errorCode'] = "CUPON_UTILIZADO";
					showResponse($return);
				}
			}
		}
	}
	/*CUPON EXISTE*/

	/*INICIO VERIFICAR MONTO*/
	$isEfectivo = false;
	$tarjetaAmount = 0;
	$tipo = "";
	$MetodoPago = $input['metodoPago'];
	
	if(count($MetodoPago) > 2){
	    $return["success"] = 0;
		$return["mensaje"] = "Excedió el limite de formas de pago";
		$return["errorCode"] = "MAX_LIMIT_PAYMENT_METHODS";
		showResponse($return);
	}
	
	$msgError = "";
	$creditAmount = findPaymentCredit($MetodoPago, $cod_usuario, $msgError);
	if($creditAmount == -1){
	    showResponse([ 'success' => 0, 'mensaje' => $msgError, 'errorCode' => 'DESCONOCIDO' ]);
	}
	if($creditAmount > 0 && count($MetodoPago) == 1){ //Los puntos son la unica forma de pago
	    if($creditAmount < $total){
	        showResponse([ 'success' => 0, 'mensaje' => 'Los puntos no cubren la totalidad de la orden', 'errorCode' => 'FALTA_FORMA_PAGO' ]);
	    }
	}
	
	foreach ($MetodoPago as $key => $pago) {
		$tipo = $pago['tipo'];
		$monto = number_format($total - $creditAmount,2);
		if($tipo == "T"){
		    $tarjetaAmount = $monto;
		}else if($tipo == "E"){
		    $isEfectivo = true;
		}

		// VALIDAR CANT MAX FORMA DE PAGO
		if($tipo !== "P"){
    		$q = "SELECT nombre, monto_maximo FROM tb_empresa_forma_pago WHERE cod_forma_pago = '$tipo' AND cod_empresa = " .cod_empresa;
    		$r = Conexion::buscarRegistro($q);
    		if($r) {
    			if($r["monto_maximo"] > 0)
    				if($monto > $r["monto_maximo"]) {
    					showResponse([ 'success' => 0, 'mensaje' => "El monto máximo en pago con {$r["nombre"]} es $ {$r["monto_maximo"]}", 'errorCode' => "FORMAS_PAGO_MONTO_MAXIMO_SUPERADO" ]);
    				}
    		}
    		$MetodoPago[$key]['monto'] = $monto;
		}
	}
	$input['metodoPago'] = $MetodoPago;

	$onlyPaymentsMethods = array_column($MetodoPago, 'tipo');
	if(count($onlyPaymentsMethods) > count(array_unique($onlyPaymentsMethods))){
        $return['success'] = 0;
		$return['mensaje'] = "Hay un error en tus formas de pago, probablemente no seleccionaste efectivo o tarjeta";
		$return['errorCode'] = "PAGO_REPETIDO";
		logAdd(json_encode($return),"respuesta-api","validar-orden");
		showResponse($return);
    }
	/*FIN VERIFICAR MONTOS*/

	/*PRODUCTOS*/
	$num_items = 0;
	require_once "clases/cl_productos.php";
	$Clproductos = new cl_productos();
	foreach ($productos as $item) {
		$id = $item['id'];
		$producto = $Clproductos->get($id, $cod_sucursal);
		if ($producto) {
			if (!$producto['disponible']) {
				$name = $producto['nombre'];
				$return['success'] = 0;
				$return['mensaje'] = "El producto $name se encuentra agotado";
				$return['errorCode'] = "PRODUCTO_AGOTADO";
				showResponse($return);
			}
			$num_items += $item['cantidad'];
		} else {
			$return['success'] = 0;
			$return['mensaje'] = "Producto con id $id no existe. Error COD_SUC: $cod_sucursal";
			$return['errorCode'] = "PRODUCTO_INEXISTENTE";
			showResponse($return);
		}
	}
	/*PRODUCTOS*/
	
	/*CANTIDAD PARA 400Grados*/
	if(cod_empresa == 204){
	    if(($num_items%2) !== 0){
	        $return['success'] = 0;
			$return['mensaje'] = "Los productos en el carrito deben ser pares para continuar";
			$return['errorCode'] = "PAR_UNAVAILABLE";
			showResponse($return);
	    }
	}
	/*FIN CANTIDAD*/

	/*SUCURSAL ABIERTA O DISPONIBLE*/
	require_once "clases/cl_sucursales.php";
	$ClSucursales = new cl_sucursales();
	
	//VALIDACION DATOS DE ENVIO
	$hora = $metodoEnvio['hora'] ?? "";
	$tipo = $metodoEnvio['tipo'];
	if(!in_array($tipo, ['delivery','envio', 'pickup', 'onsite'])){
	    showResponse([ 'success' => 0, 'mensaje' => "Tipo de envío no permitido", 'errorCode' => "TIPO_ENVIO_NO_PERMIT" ]);
	}
	
	if($tipo == "pickup" && $hora == ""){ //Pickup
	    showResponse([ 'success' => 0, 'mensaje' => "En Pickup debes escoger una hora de retiro válida, por favor revisa la hora escogida", 'errorCode' => "PICKUP_HOUR_ERROR" ]);
	}

	$hora = ($hora !== "") ? $hora : fecha();
		
	//Si la fecha y hora escogida para la entrega es menor que la fecha y hora actual... la fecha y hora escogida sera la actual
	if (strtotime($hora) < strtotime(fecha())) {
		$hora = fecha();
	}
	
	

	$resp = $ClSucursales->get($cod_sucursal);
	if ($resp) {
	    if(cod_empresa != 204){ //Para 400 grados no debe validar si esta abierto o cerrado
    		$disponibilidad = $ClSucursales->disponibilidad($cod_sucursal, $hora);
    		if (!$disponibilidad) {
    			$return['success'] = 0;
    			$return['mensaje'] = "Sucursal " . $resp['nombre'] ." ". $ClSucursales->motivo_cierre;
    			$return['errorCode'] = "SUCURSAL_NO_DISPONIBLE";
    			showResponse($return);
    		}
	    }
	} else {
		$return['success'] = 0;
		$return['mensaje'] = "Sucursal no existente";
		$return['errorCode'] = "SUCURSAL_INEXISTENTE";
		showResponse($return);
	}
	/*SUCURSAL ABIERTA O DISPONIBLE*/
	
	
	/*CARRITO PARA LLENAR DATA FALTANTE*/
	require_once "clases/cl_empresas.php";
	require_once "clases/cl_carrito.php";
	$Clcarrito = new cl_carrito($input, $cod_sucursal);
	$cart = $Clcarrito->getArray();
	
	$input['iva'] = $cart['iva'];
	$input['descuento'] = $cart['descuento'];
	$input['base0'] = $cart['base0'];
	$input['base12'] = $cart['base12'];
	$input['subtotal'] = $cart['subtotal'];
	$input['total'] = $cart['total'];
	$input['tax'] = $cart['percentIva'];
	$input['service'] = $cart['service'];
	
	foreach($productos as $key => $producto){
	    $productCart = findProductInCartByTime($cart['productos'], $producto['time']);
	    if($productCart){
	        $producto['opciones'] = $productCart['opciones'];
	        $producto['precio'] = $productCart['precio'];
	        $producto['precio_no_tax'] = $productCart['precio_no_tax'];
	        $producto['base0'] = $productCart['base0'];
	        $producto['base12'] = $productCart['base12'];
	        $producto['subtotal0'] = $productCart['subtotal0'];
	        $producto['subtotal12'] = $productCart['subtotal12'];
	        $producto['adicional_total'] = $productCart['precio_adicional'];
	        $producto['adicional_no_tax'] = $productCart['precio_adicional_no_tax'];
	        $producto['adicional_no_tax_total'] = $productCart['precio_adicional_no_tax_total'];
	        $producto['descuento'] = $productCart['descuento'];
	        $producto['descuentoPorcentaje'] = $productCart['descuentoPorcentaje'];
	        $producto['promocion'] = $productCart['promocion'];
	        $producto['comentarios'] = sanitizeString($producto['comentarios']);
	        $productos[$key] = $producto;
	    }
	}
	$input['productos'] = $productos;
	
    
    // Guardar Trama si el pago es con tarjeta..
	// Para guradar las tildes correctamente json_encode( $text, JSON_UNESCAPED_UNICODE )
	
    $PreordenId = 0;
    if(!isset($with_token))
    	$PreordenId = $Clordenes->saveJson($cod_usuario, json_encode($input, JSON_UNESCAPED_UNICODE), $tarjetaAmount);
	else
		$PreordenId = $Clordenes->saveJsonToken($cod_usuario, $with_token, json_encode($input, JSON_UNESCAPED_UNICODE), $tarjetaAmount); // GUARDADO DE JSON CON TOKEN
    if(!$PreordenId){
        $return['success'] = 0;
		$return['mensaje'] = "No se pudo crear la preorden, por favor vuelva a intentarlo";
		$return['errorCode'] = "PREORDEN_ERROR";
		$return['preordenId'] = intval($PreordenId);
		showResponse($return);
    }
	
	$return['success'] = 1;
	$return['mensaje'] = "Orden Lista para ejecutarse";
	$return['trama'] = $input;
	//JUAN
	logAdd(json_encode($input, JSON_UNESCAPED_UNICODE),"ejecutado","preordenconvertidajc");
	
	
	$total = 0;
	$subtotal = 0;
	$iva = 0;
	$service = 0;
	if($tarjetaAmount > 0){
	    $total = number_format($tarjetaAmount, 2);
	    $service = number_format($cart['service'], 2);
	    if($service > 0){
	        $tarjetaAmount = $tarjetaAmount - $service;
	    }
    	
    	$subtotal = number_format($tarjetaAmount / (($cart['percentIva'] / 100) + 1),2);
    	$iva = number_format($tarjetaAmount - $subtotal,2);
	}
	
	$return['data'] = [
	    'preordenId' => $PreordenId,
	    'tarjetaAmount' => floatval($total),
	    'total' => floatval($total),
	    'subtotal' => floatval($subtotal),
	    'iva' => floatval($iva),
	    'iva_porcentaje' => intval($cart['percentIva']),
	    'servicio' => floatval($service)
	];
	$return['trama'] = $input;
	showResponse($return);
}

function findPaymentCredit($payments, $user_id, &$msgError){
    foreach ($payments as $payment) {
        $tipo = $payment['tipo'];
        if ($tipo == "P") {
            $monto = $payment['monto'];
            $wallet = getWallet($user_id);

			$dinero = $wallet['dinero'];
			if ($dinero < $monto) {
			    $msgError = "Dinero en Puntos insuficientes. El cliente posee $" . $dinero;
				return -1;
			} 
            return $monto;
        }
    }
    return 0;
}

function findProductInCart($productsCard, $id){
    foreach($productsCard as $key => $producto){
	    if($producto['cod_producto'] == $id){
	        return $producto;
	    }
	}
	return false;
}

function findProductInCartByTime($productsCard, $time){
    foreach($productsCard as $key => $producto){
	    if($producto['identificador'] == $time){
	        return $producto;
	    }
	}
	return false;
}

function getPreOrden($preordenId){
	global $Clordenes;
	global $Clusuarios;
	
	$preorden = $Clordenes->getPreOrden($preordenId);
	if(!$preorden){
	    $return['success'] = 0;
		$return['mensaje'] = "Preorden no existente";
		$return['errorCode'] = "PREORDEN_INEXISTENTE";
		showResponse($return);
	}
	
	if($preorden['estado'] !== "VALIDADA" && $preorden['estado'] !== "CERRADA"){
	    $return['success'] = 0;
		$return['mensaje'] = "Esta preorden ya fue utilizada, por favor vuelve a abrir el modal de paymentez";
		$return['errorCode'] = "PREORDEN_USADA";
		showResponse($return);
	}
	
	$ordenTrama = json_decode($preorden['json'], true);
	
	//Buscar informacion de pago con tarjeta
	$tarjeta = 0;
	$metodoPago = $ordenTrama['metodoPago'];
	foreach($metodoPago as $pago){
	    if($pago['tipo'] == "T")
	        $tarjeta = number_format($pago['monto'],2);
	}
	
	$return['success'] = 1;
	$return['mensaje'] = "Información de la PreOrden";
	$return['usuario'] = $Clusuarios->get2($preorden['cod_usuario']);
	$return['tarjeta'] = floatval($tarjeta);
	$return['preorden'] = $ordenTrama;
	return $return;
}

function convertirPreorden(){
	global $Clordenes;
	
	$input = validateInputs(array("cod_preorden", "paymentId", "paymentAuth", "paymentProvider"));
	extract($input);
	
	logAdd(json_encode($input),"trama-ingreso","preorden-convertir");
	
	try{
	    if($paymentProvider == 1){ //Datafast o Payphone
	        $preorden = $Clordenes->getPreOrdenByPaymentId($paymentId);
	    }else{
    	    $preorden = $Clordenes->getPreOrden($cod_preorden);
	    }
	    
        if(!$preorden)
    	        throw new Exception('Preorden no existente');
    	        
    	 //Validar si la preorden ya fue creada
    	 if($preorden['cod_orden'] != 0){
    	     return [
    		    'success' => 1,
    		    'id' => $preorden['cod_orden'],
    		    'mensaje' => 'Pago realizado con éxito, puedes revisarlo en tu lista de órdenes',
    		    'detalle' => 'Orden creada correctamente',
    		    'preorden' => $preorden
    		];
    	 }

		$total = 0;
		require_once "helpers/preorderConvert.php";
	    $id = storePreorder($preorden, $paymentId, $paymentAuth, $paymentProvider, $total);
	    
	    require_once "helpers/notificationsToClient.php";
		notifyNewOrder($id);
	    
		require_once "helpers/pixelFacebook.php";
		trackPurchaseServer($id);


		validarCuponera($id); //Cuponera Furiast

		
		return [
		    'success' => 1,
		    'id' => $id,
		    'mensaje' => 'Pago realizado con éxito, puedes revisarlo en tu lista de órdenes',
		    'detalle' => 'Orden creada correctamente',
			'preorden' => $preorden,
			'total' => $total
		];
	}catch (Exception $e) {
		showResponse(['success' => 0, 'mensaje' => $e->getMessage()]);
	}
}

function failurePreorden(){
    global $input;
	global $Clordenes;
	
	$datosObligatorios = array("cod_preorden", "paymentId", "paymentAuth", "motivo");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$mensaje = "Falta informacion, Error: Campo $value es obligatorio";
			$return['success'] = 0;
			$return['mensaje'] = $mensaje;
			$return['errorCode'] = "FALTA_INFORMACION";
			showResponse($return);
		}
	}
	extract($input);
	
	logAdd(json_encode($input),"trama-ingreso","preorden-fallo");
	$preorden = $Clordenes->getPreOrden($cod_preorden);
	if(!$preorden){
	    $return['success'] = 0;
		$return['mensaje'] = "Preorden no existente";
		$return['errorCode'] = "PREORDEN_INEXISTENTE";
		showResponse($return);
	}
	
	if($preorden['estado'] !== "VALIDADA"){
	    $return['success'] = 0;
		$return['mensaje'] = "Esta preorden ya fue utilizada, no se puede cambiar de estado";
		$return['errorCode'] = "PREORDEN_USADA";
		showResponse($return);
	}
	
	$Clordenes->failurePreOrden($cod_preorden, $paymentId, $paymentAuth, $motivo);
	$return['success'] = 1;
	$return['mensaje'] = "Se edito la informacion de la preorden fallada exitosamente";
	return $return;
}

function closemodalPreorden(){
    global $input;
	global $Clordenes;
	
	$datosObligatorios = array("cod_preorden");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$mensaje = "Falta informacion, Error: Campo $value es obligatorio";
			$return['success'] = 0;
			$return['mensaje'] = $mensaje;
			$return['errorCode'] = "FALTA_INFORMACION";
			showResponse($return);
		}
	}
	extract($input);
	
	logAdd(json_encode($input),"trama-ingreso","preorden-cerro-modal");
	$cod_preorden = intval($cod_preorden);
	$preorden = $Clordenes->getPreOrden($cod_preorden);
	if(!$preorden){
	    $return['success'] = 0;
		$return['mensaje'] = "Preorden no existente";
		$return['errorCode'] = "PREORDEN_INEXISTENTE";
		showResponse($return);
	}
	
	if($preorden['estado'] !== "VALIDADA"){
	    $return['success'] = 0;
		$return['mensaje'] = "Esta preorden ya fue utilizada, no se puede cambiar de estado";
		$return['errorCode'] = "PREORDEN_USADA";
		showResponse($return);
	}
	
	$Clordenes->closePreOrden($cod_preorden);
	$return['success'] = 1;
	$return['mensaje'] = "Se edito la informacion de la preorden cerrada exitosamente";
	return $return;
}

function validarSonidoAutoasignacion($fecha_retiro) {
	try {
		$sonar = 1;
		$auto_asignar = 1;
		$minutos = 0;
		
		//NO SONAR SI NO ES PARA HOY
		$fecha_retiro = ($fecha_retiro != "") ? $fecha_retiro : fecha(); 
		$fechaOrden = explode(" ", $fecha_retiro)[0]; 
		if($fechaOrden <> fecha_only()) {
			$sonar = 0;
			$auto_asignar = 0;
		}
		else {
			$diffTime = diffTime($fecha_retiro, fecha());
			$minutos = (int)$diffTime["minutos"] + ((int)$diffTime["horas"] * 60);
			if($minutos > 15){
				$auto_asignar = 0;
			}
		}

		return array(
			"sonar" => $sonar, 
			"auto_asignar" => $auto_asignar, 
			"minutos" => $minutos
		);

	} catch (\Throwable $th) {
		//throw $th;
		return array(
			"sonar" => $sonar, 
			"auto_asignar" => $auto_asignar, 
			"minutos" => $minutos
		);
	}
	
}

// function 
function calificarOrden(){
    global $input;
	global $Clordenes;
	
	$datosObligatorios = array("cod_orden", "calificacion", "mensaje");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
			$mensaje = "Falta informacion, Error: Campo $value es obligatorio";
			$return['success'] = 0;
			$return['mensaje'] = $mensaje;
			showResponse($return);
		}
	}
	extract($input);
	
	if(intval($calificacion) == 0){
	    $return['success'] = 0;
		$return['mensaje'] = "Debes escoger un nivel de satisfacción";
		showResponse($return);
	}
	
	$orden = $Clordenes->get($cod_orden);
	if($orden){
	    $isSave = $Clordenes->setCalification($cod_orden, $calificacion, $mensaje);
	    if($isSave){
	        $return['success'] = 1;
		    $return['mensaje'] = "Calificación enviada correctamente";
		    showResponse($return);
	    }else{
	        $return['success'] = 0;
		    $return['mensaje'] = "No se pudo enviar la calificación";
		    showResponse($return);
	    }
	}else{
	    $return['success'] = 0;
	    $return['mensaje'] = "La orden no existe";
	    showResponse($return);
	}
	
}

//Pago Paymentez Token
function convertirPreordenToken(){
	global $Clordenes;
	
	$usuario = validateUserAuthenticated();
	$input = validateInputs(array("preorden_id", "callbackUrl")); //TODO falta pedir url de callback
	extract($input);
	
	logAdd(json_encode($input),"trama-ingreso","preorden-convertir");
	
	try{
	    $preorden = $Clordenes->getPreOrden($preorden_id);
        if(!$preorden)
    	        throw new Exception('Preorden no existente');

		$cod_preorden = $preorden['cod_preorden'];
		$ordenTrama = json_decode($preorden['json'], true);
		$cod_sucursal = $ordenTrama['cod_sucursal'];
		$cardPayment = $ordenTrama['metodoPago'][0];

		require_once "clases/cl_paymentez.php";
		$ClPaymentez = new cl_paymentez($cod_sucursal);
		$resp = $ClPaymentez->debitByToken3ds($usuario, $cod_preorden, $cardPayment['detail'], $cardPayment['cvv'], $cardPayment['monto'], $callbackUrl);

		if(!$resp) throw new Exception('No se pudo procesar el pago');
		if(!isset($resp['transaction'])){
			$errortext = isset($resp['error']['type']) ? $resp['error']['type'] : 'No se pudo procesar el pago';
			throw new Exception($errortext);
		}
		
		logAdd(json_encode($resp),"resp-nuvei","nuvei-respuesta");
		//TODO no debería ser restrictiva
		// 31 (OTP) => tipo BY_OTP, hasta 3 intentos (si me da pending)
		//Tarjeta de prueba OTP 36417002140808 012345 (correcto) - 54321 (incorrecto)
		//https://paymentez.github.io/api-doc/#test-cards-otp-diners
		extract($resp);
		
		if($transaction['status'] == 'success'){
			$total = 0;
			require_once "helpers/preorderConvert.php";
			$id = storePreorder($preorden, $transaction['id'], $transaction['authorization_code'], 2, $total);
			require_once "helpers/notificationsToClient.php";
			notifyNewOrder($id);
			return [ 
				'success' => 1, 
				'id' => $id, 
				'mensaje' => 'Pago realizado con éxito, puedes revisarlo en tu lista de órdenes', 
				'total' => $total
			];
		}else if($transaction['status'] == 'pending'){
			if($transaction['status_detail'] == 35 || $transaction['status_detail'] == 36){ //Se requiere desafío
			    if(!isset($resp['3ds'])) throw new Exception('No es una orden 3DS');
			    
				$Clordenes->setPaymentIdPreOrden($cod_preorden, $transaction['id']);
				$challenge = $resp['3ds']['browser_response'];
				return [
					'success' => 0,
					'mensaje' => 'Se require desafio',
					'status_detail' => $transaction['status_detail'],
					'challenge' => ($transaction['status_detail'] == 35) ? $challenge['hidden_iframe'] : $challenge['challenge_request'],
					'errorCode' => 'CHALLENGE'
				];
			}else if($transaction['status_detail'] == 31){  //OTP
			    $Clordenes->setPaymentIdPreOrden($cod_preorden, $transaction['id']);
				return [
					'success' => 0,
					'mensaje' => 'Se require desafio OTP',
					'status_detail' => $transaction['status_detail'],
					'challenge' => 'OTP',
					'errorCode' => 'CHALLENGE'
				];
			}else{
				throw new Exception('No se pudo verificar la orden');
			}
		}else{
			throw new Exception('Error en la transacción '.$transaction['status'].'('.$transaction['status_detail'].')');
		}

		return [
		    'success' => 0,
		    'response' => $resp,
		    'mensaje' => 'Pago realizado con éxito, puedes revisarlo en tu lista de órdenes',
		];

		
		
	}catch (Exception $e) {
		showResponse(['success' => 0, 'mensaje' => $e->getMessage(), 'errorCode' => 'ERROR_TRANSACTION']);
	}
}

function verifyTransaction(){
	global $Clordenes;
	
	$usuario = validateUserAuthenticated();
	$input = validateInputs(array("preorden_id", "cres", "otp")); //TODO falta pedir url de callback
	extract($input);
	
	try{
	    $preorden = $Clordenes->getPreOrden($preorden_id);
        if(!$preorden)
    	        throw new Exception('Preorden no existente');

		$cod_preorden = $preorden['cod_preorden'];
		$ordenTrama = json_decode($preorden['json'], true);
		$cod_sucursal = $ordenTrama['cod_sucursal'];

		require_once "clases/cl_paymentez.php";
		$ClPaymentez = new cl_paymentez($cod_sucursal);
		
		$type = 'AUTHENTICATION_CONTINUE';
		$value = '';
		if($cres !== ''){
		    $type = 'BY_CRES';
		    $value = $cres;
		}
		    
		if($otp !== ''){
		    $type = 'BY_OTP'; 
		    $value = $otp;
		}
		$resp = $ClPaymentez->verifyTransaction($usuario, $preorden['paymentId'], $type, $value);

		if(!$resp) throw new Exception('No se pudo procesar el pago, no hay respuesta de Pasarela de pagos');
		if(!isset($resp['transaction'])){
			$errortext = isset($resp['error']['type']) ? $resp['error']['type'] : 'No se pudo procesar el pago, falta transacción';
			throw new Exception($errortext);
		}

        logAdd(json_encode($resp),"resp-nuvei","nuvei-respuesta-desafio");
		extract($resp);
		
		if($transaction['status'] == 'success'){
			$total = 0;
			require_once "helpers/preorderConvert.php";
			$id = storePreorder($preorden, $transaction['id'], $transaction['authorization_code'], 2, $total);
			require_once "helpers/notificationsToClient.php";
			notifyNewOrder($id);
			return [ 
				'success' => 1, 
				'id' => $id, 
				'mensaje' => 'Pago realizado con éxito, puedes revisarlo en tu lista de órdenes', 
				'total' => $total
			];
		}else if($transaction['status'] == 'pending'){
			if($transaction['status_detail'] == 35 || $transaction['status_detail'] == 36){ //Se requiere desafío
			    if(!isset($resp['3ds'])) throw new Exception('No es una orden 3DS');
			    
				$challenge = $resp['3ds']['browser_response'];
				return [
					'success' => 0,
					'mensaje' => 'Se require desafio',
					'status_detail' => $transaction['status_detail'],
					'challenge' => ($transaction['status_detail'] == 35) ? $challenge['hidden_iframe'] : $challenge['challenge_request'],
					'errorCode' => 'CHALLENGE'
				];
			}else if($transaction['status_detail'] == 31){  //OTP
			    $Clordenes->setPaymentIdPreOrden($cod_preorden, $transaction['id']);
				return [
					'success' => 0,
					'mensaje' => 'Se require desafio OTP',
					'status_detail' => $transaction['status_detail'],
					'challenge' => 'OTP',
					'errorCode' => 'CHALLENGE'
				];
			}else{
				throw new Exception('No se pudo verificar la orden');
			}
		}else{
			throw new Exception('Error en la transacción '.$transaction['status'].'('.$transaction['status_detail'].')');
		}

		return [
		    'success' => 0,
		    'response' => $resp,
		    'mensaje' => 'Pago realizado con éxito, puedes revisarlo en tu lista de órdenes',
		];

		
		
	}catch (Exception $e) {
		showResponse(['success' => 0, 'mensaje' => $e->getMessage(), 'errorCode' => 'ERROR_TRANSACTION']);
	}
}


function validarCuponera($order_id){
    if(cod_empresa == 70){
        $products = [5339, 5453];
        $ids = implode(",", $products);
        
        $codes = [];
        $query = "SELECT cod_producto, SUM(cantidad) as cantidad FROM tb_orden_detalle
                    WHERE cod_producto IN($ids) AND cod_orden = $order_id GROUP BY cod_producto";
        $items = Conexion::buscarVariosRegistro($query, NULL);
        if($items){
            foreach($items as $item){
                $product_id = $item['cod_producto'];
                $cantidad = $item['cantidad'];
                for($x=1; $x<=$cantidad; $x++){
                    $codes[] = $order_id.$product_id.$x;
                }
            }
            
            if(count($codes) > 0){
                $values = [];
                foreach ($codes as $code) {
                    $values[] = "($order_id, '$code')";
                }
                $valuesString = implode(', ', $values);
                $query = "INSERT INTO tb_orden_cuponera (cod_orden, codigo) VALUES $valuesString";
                Conexion::ejecutar($query, null);
                
                ExecuteRemoteQuery(url_api . "correos/orden_cuponera.php?alias=" . alias . "&id=$order_id");
            }
            
        }
	}
}
