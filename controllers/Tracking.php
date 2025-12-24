<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_ordenes.php";
require_once "clases/cl_usuarios.php";
$Clordenes = new cl_ordenes();
$Clusuarios = new cl_usuarios();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 2){
			if($request[0] == "tracking"){
				$cod_orden = $request[1];
				$return = tracking($cod_orden);
			    showResponse($return);
			}
		}
	}	
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Tracking aun no esta disponible.";
		showResponse($return);
	}

function tracking($cod_orden){
    global $Clordenes;
	global $input;
	
	$orden = $Clordenes->getOrdenTracker($cod_orden);
	if($orden){
		if($orden['is_envio'] == 0){
			list($dia,$hora) = explode(" ",$orden['fecha_retiro']);
	    	$orden['fecha_text_retiro'] = fechaLatinoShortWeekday($dia);
	    	$orden['hora_retiro'] = $hora;
		}
		
		$tiposEnvio = [ 0 => 'pickup', 1 => 'delivery', 2 => 'onsite' ];
		$orden['type'] = $tiposEnvio[$orden['is_envio']] ?? 'desconocido';
		
		if($orden['estado'] == "ANULADA" || $orden['estado'] == "CANCELADA"){
			$orden['motivo_cancelacion'] = $Clordenes->getMotivoAnulacion($cod_orden);
		}
		
		$payment = $Clordenes->getPaymentsShowUser($cod_orden);
		if($payment){
		    $monto = $payment['monto'];
		    if($payment['forma_pago'] == 'E'){
		        $payment['mensaje'] = ($orden['is_envio'] == 1) ? "Debes entregar al motorizado en Efectivo $$monto" : "Al retirar tu pedido debes cancelar en caja $$monto";
		        $orden['payment'] = $payment;
		    }
		    else if($payment['forma_pago'] == 'TB'){
		        if($orden['estado'] == "ENTRANTE"){
		            $payment['mensaje'] = "Debes transferir a nuestra cuenta bancaria el valor de $$monto";
		            $payment['imagen'] = ($orden['transferencia_img'] == "") ? url."transferencia_bancaria.png" : url.$orden['transferencia_img']; 
		            $orden['payment'] = $payment;
		        }else if($orden['estado'] !== "ENTRANTE" && $orden['estado'] !== "ANULADA"){
		            $payment['mensaje'] = "Tu transferencia ha sido verificada y aprobada";
		            $orden['payment'] = $payment;
		        }else{
		            $orden['payment'] = null;        
		        }
		    }else
		        $orden['payment'] = null;
		}else
		    $orden['payment'] = null;
		 
		 $orden['pagos'] = $payment;
	    
		$return['success'] = 1;
		$return['mensaje'] = "Correcto";
		$return['data'] = $orden;
		
		/*TIMELINE*/
		$historial = $Clordenes->getHistorialByOrder($cod_orden);
		//Añadir estado entrante
		$aux['estado'] = "ENTRANTE";
		$aux['fecha'] = $orden['fecha'];
		array_unshift($historial, $aux);
		
		if($orden['is_envio'] == 0){    //PICKUP
		    $return['data']['timeline'] = getTimeline($historial, 'PICKUP', $orden['estado']);
		    $return['data']['tracking'] = null;
		    
		}else{                          //DELIVERY
			$tracking = null;
			$timeline = null;
            $timeline = getTimeline($historial, 'DELIVERY', $orden['estado']);
		    /*POSICION MOTORIZADO*/
    		if($orden['courier']==99){   //MIS MOTORIZADOS
    		    $tracking = $Clordenes->getMotorizadoByOrder($cod_orden);
    		}else if($orden['courier']==1){ //GACELA
    		    if($orden['token_courier'] !== ""){
    		        require_once "clases/cl_gacela.php";
    		        $Clgacela = new cl_gacela($orden['cod_sucursal']);
    		        $resp = $Clgacela->trackingOrder($orden['token_courier']);
    		        if($resp){
                      $driver = $resp['results']['driver'];
                      if($driver != null){
						$tracking = reorderInfoMotorizado($driver['name'],$driver['lastname'],$driver['phone'],$driver['photo'],$driver['lat'],$driver['lng']);
                      }
                    }
    		    }
    		}else if($orden['courier']==3){ //PICKER
    		    if($orden['token_courier'] !== ""){
    		        require_once "clases/cl_picker.php";
    		        $Clpicker = new cl_picker($orden['cod_sucursal']);
    		        $resp = $Clpicker->trackingOrder($orden['token_courier']);
    		        if($resp){
						if(isset($resp['data'])){
							$driver = $resp['data'];
							$lat = $driver['currentCoordinates']['coordinates'][1];
							$lng = $driver['currentCoordinates']['coordinates'][0];
							$tracking = reorderInfoMotorizado($driver['driverName'],'',$driver['driverMobile'],$driver['driverImage']['thumbnail'],$lat,$lng);
						}
                    }
                    //TODO Quitar del endpoint
					$return['data']['respPicker'] = $resp;
					$return['data']['classPicker'] = $Clpicker;
    		    }
    		}else if($orden['courier']==5){ //PEDIDOSYA
    		    if($orden['token_courier'] !== ""){
    		        require_once "clases/cl_pedidosya.php";
    		        $ClPedidosYa = new cl_pedidosya($orden['cod_sucursal']);
    		        $resp = $ClPedidosYa->tranckingOrder($orden['token_courier']);
    		        if(isset($resp["latitude"])) {
						$tracking = reorderInfoMotorizado($resp['deliveryName'], "", "", "", $resp['latitude'], $resp['longitude']);
                    }
    		    }
    		}
			$return['data']['timeline'] = $timeline;
    		$return['data']['tracking'] = $tracking;
		}
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "Orden inexistente";
	}
	return $return;
}


function getTimeline($historial, $tipo, $currentStatus){
    $timeline = [];
    $query = "SELECT * FROM tb_steps_timeline 
    WHERE tipo = '$tipo' ORDER BY posicion ASC";
    $resp = Conexion::buscarVariosRegistro($query);    
    foreach($resp as $key => $steps){
        $aux['estado'] = $steps['estado'];
        $aux['titulo'] = $steps['titulo'];
        $aux['image'] = url_resource.$steps['imagen'];
        $aux['texto'] = html_entity_decode($steps['desc_no_complete']);
        $aux['fecha'] = "--";
        $aux['complete'] = false;
        $aux['current'] = false;

        $estado = $steps['estado'];
        $item = getHistorial($historial, $estado);
        if($item){
            list($dia,$hora) = explode(" ",$item['fecha']);

            $aux['texto'] = html_entity_decode($steps['desc_complete']);
            $aux['fecha'] = fechaLatinoShortWeekday($dia)." - ".$hora;
            $aux['complete'] = true;
            if($estado == $currentStatus)
            $aux['current'] = true;
        }else{
            if($currentStatus == "ANULADA" || $currentStatus == "CANCELADA"){
                $aux['estado'] = "ANULADA";
                $aux['titulo'] = "Pedido Cancelado";
                $aux['image'] = url_resource."pas1.png";
                $aux['texto'] = html_entity_decode("La Orden fue cancelada, si crees que es un error por favor comunícate con nosotros");
                $aux['fecha'] = "--";
                $aux['complete'] = true;
                $aux['current'] = true;
                $timeline[] = $aux;
                break;   
            }
        }
        $timeline[] = $aux;
    }
    return $timeline;
}

function getHistorial($historial, $estado){
    foreach($historial as $item){
        if($item['estado'] == $estado)
            return $item;
    }
    return false;
}

function reorderInfoMotorizado($name, $lastname, $phone, $photo, $lat, $lng){
	if($name === null)
		return null;
	$moto['nombre'] = $name;
	$moto['apellido'] = $lastname;
	$moto['telefono'] = $phone;
	$moto['imagen'] = $photo;
	$moto['latitud'] = $lat;
	$moto['longitud'] = $lng;
	$moto['fecha_ubicacion'] = "";

	return $moto;
}

?>