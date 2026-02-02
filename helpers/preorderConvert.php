<?php
require_once "clases/cl_ordenes.php";
require_once "clases/cl_usuarios.php";
require_once "clases/cl_empresas.php";
require_once "clases/cl_sucursales.php";
$Clordenes = new cl_ordenes();
$Clusuarios = new cl_usuarios();
$Clempresas = new cl_empresas();
$Clsucursales = new cl_sucursales();

function storePreorder($preorden, $paymentId, $paymentAuth, $paymentProvider, &$total=0){
    global $Clordenes;
    global $Clusuarios;
    global $Clempresas;
    global $Clsucursales;

    $cod_preorden = $preorden['cod_preorden'];
	
	if($preorden['estado'] !== "VALIDADA" && $preorden['estado'] !== "CERRADA"){
	    throw new Exception('Esta preorden ya fue utilizada, por favor pulsa el boton procesar pago nuevamente');
	}
	
	$ordenTrama = json_decode($preorden['json'], true);
	$ordenTrama['paymentProveedor'] = $paymentProvider; //1 DATAFAST - 2 PAYMENTEZ - 3 PAYPHONE
	$ordenTrama['lot_number'] = (isset($lot_number)) ? $lot_number : "";
	
	$cod_sucursal = $ordenTrama['cod_sucursal'];
	$cod_usuario = $ordenTrama['cod_usuario'];
	$total = $ordenTrama['total'];
	$envio = $ordenTrama['envio'];
	
	//Cobro
	debitPaymentCard($paymentProvider, $paymentId, $paymentAuth, $cod_sucursal);
	$ordenTrama['paymentId'] = $paymentId;
    $ordenTrama['paymentAuth'] = $paymentAuth;

	// OBTENER DATOS DE LA EMPRESA Y SUCURSAL SI GRAVA IVA
	$sucursales = $Clsucursales->get($cod_sucursal);
	$empresa = $Clempresas->getByCode($sucursales['cod_empresa']);
	$Clordenes->porcentajeIva = $empresa['impuesto'];
	// $Clordenes->sucursal_grava_iva = $sucursales['envio_grava_iva'];
	
	//Ver si es una orden alta demanda
	$altaDemanda = $Clsucursales->getAltaDemanda($cod_sucursal);
	$ordenTrama['alta_demanda'] = ($altaDemanda) ? 1 : 0;
	
	$Clordenes->convertingPreOrden($cod_preorden, $paymentId, $paymentAuth);
	if ($Clordenes->crear($ordenTrama, $cod_usuario, $id)) {

		// DATOS FACTURACION NUEVO
		if (isset($ordenTrama['billing_data'])) {
		    if($ordenTrama['billing_data']){
		        $datos_facturacion = $Clusuarios->getDatosFacturacion($cod_usuario);
		        if($datos_facturacion){
        			$Clordenes->saveOrdenDatosFacturacion($id, $datos_facturacion);
		        }
		    }
		}
		
		$Clordenes->setStatusPreorden($cod_preorden, 'PAGADA', $id);

		//Productos Gratis
		$totalWithoutEnvio = number_format($total - $envio, 2);
		$freeProduct = $Clordenes->applyFreePromo($cod_sucursal, $totalWithoutEnvio, 'WEB');
		if($freeProduct){
			if($freeProduct['tipo'] == 'FIRST_ORDER'){
				if($Clusuarios->getNumOrders($cod_usuario) == 1){
					$Clordenes->addFreeProductToOrder($freeProduct['cod_producto'], 1, $id);
				}
			}
		}
		
		
		//Add Firebase
		$metodopago = $ordenTrama['metodoPago'][0]['tipo'];
		
		$envio = ($ordenTrama['metodoEnvio']['tipo'] == "delivery") ? "envio" : $ordenTrama['metodoEnvio']['tipo'];

		$sonidoAutosignacion = validarSonidoAutoasignacion($ordenTrama['metodoEnvio']["hora"]);

		$Clordenes->addOrdenFirebase($id, $cod_sucursal, $total, $metodopago, $envio, $sonidoAutosignacion["minutos"], $sonidoAutosignacion["sonar"], $sonidoAutosignacion["auto_asignar"]);
		
		return $id;
	}else{
	    $Clordenes->setStatusPreorden($cod_preorden, 'FALLADA', 0, 'Fallo la creacion de ordenes');
		//TODO Debería hacer el rollback del cobro??
		throw new Exception('No se pudo crear la orden, por favor intentelo nuevamente');
	}
}

function debitPaymentCard($paymentProvider, &$paymentId, &$paymentAuth, $cod_sucursal){
    if($paymentProvider == 2 || $paymentProvider == 0){ //Paymentez o Efectivo
        return true;
    }else if($paymentProvider == 1){ //Datafast
        require_once "clases/cl_datafast.php";
	    $Cldatafast = new cl_datafast($cod_sucursal);
	    
	    if (!$Cldatafast->Isinitialize)
	        throw new Exception('Datafast no está configurado para esta sucursal');
	    
	    $resp = $Cldatafast->debitar($paymentId);
	    logAdd(json_encode($resp),"Respuesta Datafast","crear-orden-datafast");
	    
	    if (isset($resp['result']['code'])) {
	        $code = $resp['result']['code'];
	        if ($code == $Cldatafast->getDebitCodeSuccess()) {
	            $paymentId = $resp['id'];
	            $paymentAuth = (isset($resp['resultDetails']['AuthCode'])) ? $resp['resultDetails']['AuthCode'] : "";
	        }else{
	            $error = isset($resp['result']['description']) ? $resp['result']['description'] : "";
	            throw new Exception("No se pudo generar el cobro. ErroCode: $code - Desc: $error");
	        }
	    }else{
	        throw new Exception('No se pudo generar el cobro, error desconocido');
	    }
    }else if($paymentProvider == 3){ //Payphone
        require_once "clases/cl_payphone.php";
	    $Clpayphone = new cl_payphone($cod_sucursal); //Debe ser por sucursal
	    
	    if (!$Clpayphone->isInitialized)
	        throw new Exception('Payphone no está configurado para esta sucursal');
	    
	    if ($Clpayphone->existPayment($paymentId, $paymentAuth))
	        throw new Exception('El pago y la orden ya existen, intente nuevamente');
	        
	    $payment = $Clpayphone->approvedPayment($paymentId, $paymentAuth);
	    
	    if (isset($payment["respuesta_payphone"]["errorCode"]))
	        throw new Exception('No se pudo generar el cobro '.json_encode($payment));
	       // throw new Exception('No se pudo generar el cobro '.$payment["respuesta_payphone"]["errorCode"]);
    }
    return true;
}

?>