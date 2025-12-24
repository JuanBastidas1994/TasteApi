<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */
require_once "helpers/calcularPrecioEnvio.php";
require_once "clases/cl_sucursales.php";
require_once "clases/cl_empresas.php";
$ClSucursales = new cl_sucursales();
$ClEmpresas = new cl_empresas();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 1){
			$sucursales = $ClSucursales->lista();
			if(count($sucursales)>0){
				$return['success'] = 1;
				$return['mensaje'] = "Correcto";
				$return['data'] = $sucursales;
			}else{
				$return['success'] = 0;
				$return['mensaje'] = "No hay sucursales";
			}
			showResponse($return);
		}
		else if($num_variables == 2){
		    $cod_sucursal = $request[1];
			if(is_numeric($cod_sucursal)){	//SOLO UNA SUCURSAL
				$resp = $ClSucursales->get($cod_sucursal);
				if($resp){
				    if($resp['abierto'] == false){  //Ver a que hora abre
            		    $resp['motivo_cierre'] = $ClSucursales->motivo_cierre;
            		    $resp['prox_status_enable'] = "Disponible ".$ClSucursales->proximaApertura($resp['cod_sucursal']);
            		    if(!isset($resp['motivo_cierre'])){
            		        $resp['motivo_cierre'] = "Tienda Cerrada";
            		    }
            		}else{                                      //Ver a que hora cierra
            		    $resp['motivo_cierre'] = "";
            		    list($hora, $minuto, $segundo) = explode(':', $resp['hora_fin']);
            		    $resp['prox_status_enable'] = "Cierra hoy a las ".$hora.":".$minuto;
            		}
            		
					$return['success'] = 1;
					$return['mensaje'] = "Sucursal encontrada";
					$return['data'] = $resp;
				}else{
					$return['success'] = 0;
					$return['mensaje'] = "No existe esta sucursal";
				}
				showResponse($return);
			}
			if($cod_sucursal == "pickup" || $cod_sucursal == "delivery"){
				$sucursales = $ClSucursales->lista($cod_sucursal);
				if(count($sucursales)>0){
					$return['success'] = 1;
					$return['mensaje'] = "Correcto";
					$return['data'] = $sucursales;
				}else{
					$return['success'] = 0;
					$return['mensaje'] = "No hay sucursales $cod_sucursal";
				}
				showResponse($return);
			}
			if($cod_sucursal == "type"){
			    $sucursales = $ClSucursales->lista();
			    if(count($sucursales)>0){
			        
			        $officePickup = array_values(array_filter($sucursales, fn($s) => $s['pickup'] == 1));
                    $officeInsite = array_values(array_filter($sucursales, fn($s) => $s['insite'] == 1));
			        
					$return['success'] = 1;
					$return['mensaje'] = "Correcto";
					$return['data'] = $sucursales;
					$return['pickup'] = $officePickup;
					$return['insite'] = $officeInsite;
					
				}else{
					$return['success'] = 0;
					$return['mensaje'] = "No hay sucursales $cod_sucursal";
				}
				showResponse($return);
			}
		    
		    
		}
		else if($num_variables == 3){
			$latitud = $request[1];
			$longitud = $request[2];
			
			if($latitud == "intervalos"){
				$return = getIntervalos($longitud);
			    showResponse($return);
			}
			
			if($latitud == "disponibilidad"){
				$return = getDisponibilidad($longitud);
			    showResponse($return);
			}
			
			if($latitud == "onlypickup"){
				$return = getSucursalPickup($longitud);
			    showResponse($return);
			}
			
			$return = getCobertura($latitud, $longitud);
			showResponse($return);
		}else if($num_variables == 4){
			$latitud = $request[1];
			$longitud = $request[2];
			$cod_sucursal = $request[3];
			
			if($latitud == "intervalos"){
				$return = getIntervalos($longitud, $cod_sucursal);
			    showResponse($return);
			}
			
			if($latitud == "disponibilidad"){
				$return = getDisponibilidad($longitud, $cod_sucursal);
			    showResponse($return);
			}
		
			showResponse($return);
		}else{
			$return['success']= 0;
			$return['mensaje']= "Url no valida para Sucursales, por favor revisar los parametros";
			showResponse($return);
		}
	}
	else if ($method == "POST") {
	    $num_variables = count($request);
	    if ($num_variables == 2) {
    		if ($request[1] == "checkout") {
    			$return = getInfoCheckout();
    			showResponse($return);
    		}
			if ($request[1] == "precio") {
    			$return = getPrecioDefinitivo();
    			showResponse($return);
    		}
    	}else{
    	    showResponse(['success' => 0, 'mensaje' => 'Para sucursal no hay metodo encontrado']);
    	}
	}
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Sucursales aun no esta disponible.";
		showResponse($return);
	}

function getPrecioDefinitivo(){
	global $ClSucursales;

	$input = validateInputs(array("latitud", "longitud", "office_id"));
    extract($input);

	$sucursal = $ClSucursales->getConPrecio($office_id,$latitud, $longitud);
	if(!$sucursal){
		showResponse(['success' => 0, 'mensaje' => 'Sucursal no existente o inactiva']);
	}

	$precioEnvio = getPrecioCourier(0, $sucursal, $latitud, $longitud, true);
	showResponse([
        'success' => 1,
        'mensaje' => 'Precio Envio Exitoso', 
        'precio' => $precioEnvio['precio'], 
		'courier' => $precioEnvio['courierName'],
		'distancia' => $precioEnvio['distancia'],
    ]);
}

function getCobertura($latitud, $longitud){
	global $ClSucursales;
	$sucDelivery = $ClSucursales->withDelivery();
	if(!$sucDelivery) {
		$return['success'] = 0;
		$return['mensaje'] = "La sucursal ha apagado los envíos temporalmente";
		$return['error_code'] = "NO_DELIVERY";
		return $return;
	}

	$sucursales = $ClSucursales->listaCobertura($latitud, $longitud);
	if($sucursales){
		foreach($sucursales as $key => $item){
			$cod_sucursal = $item['cod_sucursal'];
		    $sucursales[$key]['distance_line_rect'] = $item['distance'];
		
			$cod_courier = 0; //MOTORIZADOS PROPIOS
			$courier = $ClSucursales->getCourier($cod_sucursal);
			if($courier){
				$cod_courier = $courier['cod_courier'];
			}
			$sucursales[$key]['cod_courier'] = $cod_courier;

			if($sucursales[$key]['abierto'] == false){  //Ver a que hora abre
			    $sucursales[$key]['prox_apertura'] = $ClSucursales->proximaApertura($cod_sucursal);
			    if(!isset($sucursales[$key]['motivo_cierre'])){
			        $sucursales[$key]['motivo_cierre'] = "Tienda Cerrada";
			    }
			}else{                                      //Ver a que hora cierra
			    list($hora, $minuto, $segundo) = explode(':', $item['hora_fin']);
			    $sucursales[$key]['prox_cierre'] = "Hoy a las ".$hora.":".$minuto;
			}
		}

		usort($sucursales, build_sorter('distance'));  //ORDENAR POR LA DISTANCIA
		
		$item = $sucursales[0];
		if($item['abierto'] == false){ //Si el local esta cerrado, busca el que este abierto
		    foreach($sucursales as $sucursal){
		        if($sucursal['abierto'] == true){
		            $item = $sucursal;
		            $sucursales[0] = $item;
		            //Solo la primera!!
		        }
		    }
		}
		$cod_sucursal = $item['cod_sucursal'];
		$cod_courier = $item['cod_courier'];
		//Precio Por Courier
		try{
			$routeParam = isset($_GET['route']) ? $_GET['route'] : 1;
			$route = $routeParam == 1 ? true : false;
			$precioEnvio = getPrecioCourier($cod_courier, $item, $latitud, $longitud, $route);
			$sucursales[0]['precio'] = $precioEnvio['precio'];
			$sucursales[0]['metodo_cobertura'] = $precioEnvio['courierName'];
			$sucursales[0]['distancia'] = $precioEnvio['distancia'];
		}catch (Exception $e) {
			if($courier['validar_cobertura'] == 1){
				responseError("No hay cobertura. Error: " . $cod_sucursal . " - " . $e->getMessage() , "COURIER_VALIDATOR");

				$sucursales[0]['abierto'] = false;
				$sucursales[0]['programar_pedido'] = 0;
				$sucursales[0]['delivery_disponible'] = false;
				$sucursales[0]['delivery_cierre_motivo'] = $e->getMessage();
				$sucursales[0]['motivo_cierre'] = $e->getMessage();
			}
		}

		$return['success'] = 1;
		$return['mensaje'] = "Correcto nueva modificacion";
		$return['data'] = $sucursales;
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "No hay Cobertura";
	}
	showResponse($return);
}

//SE ESTA PREGUNTANDO POR PROGRAMAR PEDIDO DE LA TABLA EMPRESA, DEBERÁ CAMBIAR AL DE LA TABLA SUCURSAL
function getIntervalos($cod_sucursal, $fecha=""){
    global $ClSucursales;
    if($fecha == "")
        $fecha = fecha_only();
    
    $resp = $ClSucursales->get($cod_sucursal);
    if($resp){
        $horasDisponibles=[];
        
        $disponibilidad = $ClSucursales->getHorarioFecha($cod_sucursal, $fecha);
        if($disponibilidad){
            $disponibilidad['fecha'] = $fecha;
            $disponibilidad['currentDay'] = true;
            $horas = getListaHoraIntervalos($disponibilidad['hora_ini'], $disponibilidad['hora_fin'], 30, $cod_sucursal, true);
        	foreach ($horas as $dt) {
        		$hora = $dt->format('H:i');
        		
        		if($fecha == fecha_only()){
        		    if($hora <= hora_only()){
        		        continue;
        		    }  
        		}
        		
        		$h['hora'] = $hora;
        		$h['disponible'] = $ClSucursales->datetimeDisponibilidad($cod_sucursal, $fecha, $hora);
        		$horasDisponibles[]=$h;
        	}
        }
        
        /*DIAS PARA AGENDAR EN EL FUTURO*/
        $dias=[];
        if($resp['programar_pedido'] == 1){
            require_once "clases/cl_empresas.php";
            $ClEmpresas = new cl_empresas();
            $programar = $ClEmpresas->getProgramar();
            if($programar['programar_pedido'] == 1){
                $resp['programar_dias'] = $programar['dias'];
                $cantDias = $programar['dias'];
                $fechaMaxima = fecha_only();
                
                for($x=0; $x<$cantDias; $x++){
                    $d['dia'] = $fechaMaxima;
                    $d['diaTexto'] = fechaLatinoShort($fechaMaxima);
                    if($x==0)
                        $weekname = "Hoy";
                    else if($x == 1)    
                        $weekname = "Mañana";
                    else
                        $weekname = Weekday($fechaMaxima);
                    $d['weekname'] = $weekname;
                    $dias[$x] = $d;
                    $fechaMaxima = fechaXDiasMas($x+1);
                }
            }
            else{
                $resp['programar_pedido'] = 0;
            }
        }
        $return['success'] = 1;
		$return['mensaje'] = "Correcto";
		$return['data'] = $resp;
		$return['disponibilidad'] = $disponibilidad;
		$return['intervalos'] = $horasDisponibles;
		$return['dias'] = $dias;
    }else{
        $return['success'] = 0;
		$return['mensaje'] = "Sucursal no existente";
    }
    return $return;
}

function getDisponibilidad($cod_sucursal, $fecha=""){
    global $ClSucursales;
    if($fecha == "")
        $fecha = fecha();
        
    $resp = $ClSucursales->get($cod_sucursal);
    if($resp){
        $disponibilidad = $ClSucursales->disponibilidad($cod_sucursal, $fecha);
        if($disponibilidad){
            $return['success'] = 1;
		    $return['mensaje'] = "Sucursal disponible";
		    $return['disponibilidad'] = $disponibilidad;
        }else{
            $return['success'] = 0;
		    $return['mensaje'] = "Sucursal no disponible en este horario";
        }
    }else{
        $return['success'] = 0;
		$return['mensaje'] = "Sucursal no existente";
    }
    return $return;
}

function getSucursalPickup($cod_sucursal){
	global $ClSucursales;

	$sucursal = $ClSucursales->getSucursal($cod_sucursal);
	if($sucursal){
	    $sucursal['horarios'] = $ClSucursales->getHorarios($sucursal['cod_sucursal']);
		
		if($sucursal['abierto'] == false){  //Ver a que hora abre
		    $sucursal['motivo_cierre'] = $ClSucursales->motivo_cierre;
		    $sucursal['prox_status_enable'] = "Disponible ".$ClSucursales->proximaApertura($sucursal['cod_sucursal']);
		    if(!isset($sucursal['motivo_cierre'])){
		        $sucursal['motivo_cierre'] = "Tienda Cerrada";
		    }
		}else{                                      //Ver a que hora cierra
		    $sucursal['motivo_cierre'] = "";
		    list($hora, $minuto, $segundo) = explode(':', $sucursal['hora_fin']);
		    $sucursal['prox_status_enable'] = "Cierra hoy a las ".$hora.":".$minuto;
		}
		
		//HORARIOS RETIRAR PICKUP
		$horas=getIntervalsHour($sucursal['cod_sucursal']);
		$sucursal['horas_pickup'] = $horas;
		
		//PROGRAMACION DE PEDIDOS
		if($sucursal['programar_pedido'] == 1) {
		    $dias=[];
		    $diasProg = $sucursal['programar_pedido_dias'];
		    
		    $fechaProg = fecha_only();
		    if(count($horas) == 0){
		        $fechaProg = fechaXDiasMas(1);
		        $sucursal['horas_pickup'] = getIntervalsHour($sucursal['cod_sucursal'], $fechaProg);
		    }
		    
		    for($x=0; $x<$diasProg; $x++){
                $d['dia'] = $fechaProg;
                $d['diaTexto'] = fechaLatinoShortWeekday($fechaProg);
                $dias[$x] = $d;
                $fechaProg = fechaXDiasMasDate($fechaProg, 1);
            }
            $sucursal['programar_dias'] = $dias;
		}
		

		$return['success'] = 1;
		$return['mensaje'] = "Correcto";
		$return['data'] = $sucursal;
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "No hay Cobertura";
	}
	showResponse($return);
}

function getInfoCheckout(){
    global $ClSucursales;
    global $ClEmpresas;
    global $input;
    extract($input);
    if(!isset($input['type'])){
        showResponse(['success' => 0, 'mensaje' => 'El tipo es obligatorio']);
    }
    if(!isset($input['office_id'])){
        showResponse(['success' => 0, 'mensaje' => 'El identificador de la oficina es obligatorio']);
    }
    
    $office = $ClSucursales->get($office_id);
    if($office){
        $office['programar_pedido'] = ($office['programar_pedido'] == 1) ?  true : false;
        // $office['horas_pickup'] = getIntervalsHour($office_id);
        if($office['programar_pedido']){
            $dates = $ClSucursales->getProgramarPedido($office_id);
            if($dates){
                foreach($dates as $key => $date){
                    $dates[$key]['horas_pickup'] = getIntervalsHour($office_id, $date['dia']);
                }
                $office['programar_disponibilidad'] = $dates;
            }else{
                $office['programar_pedido'] = false;
                $office['programar_disponibilidad'] = [];    
            }
        }else{
            $dia = fecha_only();
            $office['programar_disponibilidad'][0] = [
                "dia" => $dia,
                "diaTexto" => fechaLatinoShortWeekday($dia),
                "horas_pickup" => getIntervalsHour($office_id),
            ];
        }
        
        /*TOKENS PAYMENTEZ*/
        $office['payment_tokens'] = $ClSucursales->getTokensPaymentez($office_id);
    }
    
    $payments = $ClEmpresas->getFormasPagoEmpresa($type == "delivery" ? "envio" : "pickup");
    
    showResponse(['success' => 1, 'mensaje' => 'Lista', 'office' => $office, 'payment_methods' => $payments]);
}

function getIntervalsHour($cod_sucursal, $fecha=""){
    global $ClSucursales;
    $isCurrentDay = false;
    $dateCurrent = fecha_only();
    if($fecha == ""){
        $fecha = $dateCurrent;
        $isCurrentDay = true;
    }else{
        if($dateCurrent == $fecha){
            $isCurrentDay = true;
        }
    }
        
    $disponibilidad = $ClSucursales->getHorarioFecha($cod_sucursal, $fecha);
    if($disponibilidad){
        
		$addTime = true;
		$hora_ini = $disponibilidad['hora_ini'];
		if($isCurrentDay) {
			$hi = hora_create($hora_ini);
			$hc = hora();
			if($hc > $hi) {
				$hora_ini = getNextHour();
				$addTime = false;
			}
		}
        
        $horas = [];
        $intervalos = getListaHoraIntervalos($hora_ini, $disponibilidad['hora_fin'], 30, $cod_sucursal, $addTime);
		foreach ($intervalos as $intervalo) {
		    $hora = $intervalo->format('H:i');
		    
		    $disponible = $ClSucursales->datetimeDisponibilidad($cod_sucursal, fecha_only(), $hora);
		    if($disponible){
		        $horas[] = $hora;
		    }
		}
		return $horas;
    }else{
        return [];
    }
}

function getListaHoraIntervalos($hora_inicio, $hora_fin, $intervalo, $cod_sucursal, $addTime){
	global $ClSucursales;

	if($addTime) {
		$hasTiempoProgramar = $ClSucursales->getSucursalTiempoProgramar($cod_sucursal);
		if($hasTiempoProgramar) {
			$hora_inicio = sumarTiempo2($hora_inicio, "+" . $hasTiempoProgramar["hora_apertura"], "minute");
			$hora_fin = sumarTiempo2($hora_fin, "-" . $hasTiempoProgramar["hora_cierre"], "minute");
		}
	}

  $start = new DateTime($hora_inicio);
  $end   = new DateTime($hora_fin);
  $interval = DateInterval::createFromDateString($intervalo.' minute');
  $period = new DatePeriod($start, $interval, $end);
  return $period;
}

function fechaXDiasMas($num){
  date_default_timezone_set('America/Guayaquil');
  $time = time();
  $fecha = date("Y-m-d", $time);  //FECHA Y HORA ACTUAL
  return date("Y-m-d",strtotime($fecha."+ ".$num." days"));
}

function fechaXDiasMasDate($fecha, $num){
  return date("Y-m-d",strtotime($fecha."+ ".$num." days"));
}
?>