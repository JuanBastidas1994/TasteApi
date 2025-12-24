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
				    $resp['alta_demanda'] = false;
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
            		    
            		    //Si esta abierto consultar si esta en alta demanda.
            		    $resp['alta_demanda'] = $ClSucursales->getAltaDemanda($resp['cod_sucursal']);
            		}
            		
            		
					$return['success'] = 1;
					$return['mensaje'] = "Informacion de sucursal exitosa";
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
			
			if($latitud == "disponibilidad"){
				$return = getDisponibilidad($longitud);
			    showResponse($return);
			}
			
			
			$return = getCobertura($latitud, $longitud);
			showResponse($return);
		}else if($num_variables == 4){
			$latitud = $request[1];
			$longitud = $request[2];
			$cod_sucursal = $request[3];
			
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