<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_ordenes.php";
require_once "clases/cl_usuarios.php";
require_once "clases/cl_sucursales.php";
require_once "clases/cl_empresas.php";
require_once "clases/cl_productos.php";
$ClEmpresas = new cl_empresas();
$Clordenes = new cl_ordenes();
$Clusuarios = new cl_usuarios();
$ClSucursales = new cl_sucursales();
$Clproductos = new cl_productos();

if($method == "POST"){
	$num_variables = count($request);
	if($num_variables == 1){
		$return = getInfoCheckout();
		showResponse($return);
	}
	else if($num_variables == 2){
	    $first = $request[1];
	    if($first=="sugerencias"){
			$return = getSugerencias();
			showResponse($return);
		}
	}
	
	$return['success']= 0;
	$return['mensaje']= "Evento no existente";
	showResponse($return);
}	
else{
	$return['success']= 0;
	$return['mensaje']= "El metodo ".$method." para Ordenes aun no esta disponible.";
	showResponse($return);
}

function getInfoCheckout(){
    global $ClSucursales;
    global $ClEmpresas;
    global $Clusuarios;

    $input = validateInputs(array("type", "office_id", "user_id"));
    extract($input);
    
    $office = $ClSucursales->get($office_id);
    if(!$office)
        showResponse(['success' => 0, 'mensaje' => 'Sucursal no existente o inactiva']);
    
    if($type == "onsite")
        $office['programar_pedido'] = 0;
    
    if(!$office['abierto'])
        $office['prox_apertura'] = $ClSucursales->proximaApertura($office_id);
        
    $office['programar_pedido'] = ($office['programar_pedido'] == 1) ?  true : false;
    if($office['programar_pedido']){
        $dates = $ClSucursales->getProgramarPedido($office_id);
        if($dates){
            $datesAvailables = [];
            foreach($dates as $key => $date){
                $hoursAvailables = getIntervalsHour($office_id, $date['dia']);
                if(count($hoursAvailables) > 0){
                    $dates[$key]['horas_pickup'] = $hoursAvailables;
                    $datesAvailables[] = $dates[$key];
                }
                
            }
            $office['programar_disponibilidad'] = $datesAvailables;
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
    //TODO Falta una tabla que indique la sucursal que botón de pago tiene
    $proveedor = 0;
    $save_card = false;
    $paymentTokens = $ClSucursales->getPaymentTokens($office_id, $proveedor);
    if($proveedor != 1 && $proveedor > 0){ //Paymentez o Payphone
        $office['payment_tokens'] = $paymentTokens;
        $save_card = ($paymentTokens['save_card'] == 1);
    }
    
    $type = in_array(strtolower($type), ['delivery', 'd', 'envio']) ? "envio" : "pickup";
    
    $payments = $ClEmpresas->getFormasPagoEmpresa($type, 
                                                    ($proveedor==0), 
                                                    $save_card,
                                                    $office['transferencia_img']
                                                );
    
    //Fidelizacion
    $clientLoyalty = null;
    $loyalty = $ClEmpresas->getFidelizacion();
    if($loyalty){
        $user = $Clusuarios->get2($user_id);
        if($user){
            $wallet = getWallet($user_id);
            $clientLoyalty = [
                "balance" => floor($wallet['saldo']),
                "points" => $wallet['puntos'],
                "amount" => number_format($wallet['dinero'],2,".",""),
            ];
        }
    }

    require_once "helpers/verificarProductoGratisUsuario.php";
    $freeProduct = getProductFreeAvailableUser($office_id, $user_id, 'WEB');
    
    showResponse([
        'success' => 1,
        'mensaje' => 'Lista', 
        'business_loyalty' => $loyalty, 
        'client_loyalty'=>$clientLoyalty, 
        'office' => $office, 
        'payment_methods' => $payments,
        'payment_provider' => $proveedor,
        'free_product' => $freeProduct,
        'save_card' => $save_card,
        'validate_phone' => false,
    ]);
}

function getSugerencias(){
    global $input;
    extract($input);
    if(!isset($input['office_id'])){
        showResponse(['success' => 0, 'mensaje' => 'El identificador de la oficina es obligatorio']);
    }
    
    //Sugerencias
    $suggestionsClient = getSuggestionsProducts($office_id);
    showResponse(['success' => 1, 'mensaje' => 'Lista', 'suggestions' => $suggestionsClient ]);
    
}

//SUCURSAL FUNCIONES
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
		    
		    $disponible = $ClSucursales->datetimeDisponibilidad($cod_sucursal, $fecha, $hora);
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

function getSuggestionsProducts($cod_sucursal){
    global $Clproductos;
    $query = "SELECT cod_web_modulos_producto as id FROM tb_web_modulos_productos WHERE cod_empresa = ".cod_empresa." AND modulo = 'SUGERENCIAS'";
    $resp = Conexion::buscarRegistro($query);
    if(!$resp) return null;
    
    $Clproductos->cod_sucursal = $cod_sucursal;
    return $Clproductos->listaModuloWeb($resp['id']);
    
    
    
}

?>