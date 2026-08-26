<?php

// A nivel de archivo, no por funcion: getUltimaCotizacionReciente() usaba
// CACHE_PRECISION_DECIMALES sin haber cargado este helper (solo se ejecuta
// para couriers externos, que nunca pasan por getDistanciaRuta(), la unica
// funcion de este archivo que si lo requeria) - "Undefined constant" en
// producción, sera un Error fatal en PHP 8+. Con esto ya no depende de que
// cada función que use algo de cache.php se acuerde de requerirlo.
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/couriers.php';

// Distancia en linea recta por matematica pura (haversine, misma formula que
// ya usa la consulta SQL de getConPrecio) - no depende de poligonos de
// cobertura, cache, ni ninguna API externa. Ultimo recurso cuando ni siquiera
// se puede resolver la cobertura del punto: nunca hay que confiar en el envio
// que mande el cliente, esto siempre se puede calcular con solo las
// coordenadas base de la sucursal.
function calcularDistanciaLineaRecta($lat1, $lng1, $lat2, $lng2){
    $lat1 = deg2rad($lat1); $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2); $lng2 = deg2rad($lng2);
    $cos = cos($lat1) * cos($lat2) * cos($lng2 - $lng1) + sin($lat1) * sin($lat2);
    $cos = max(-1, min(1, $cos)); //evita NAN por error de redondeo en puntos casi identicos
    return 6378 * acos($cos);
}

// Registro de solo-inserción de cada cotización de envío calculada, exitosa o no.
// No representa el estado actual de nada (una ubicación puede cotizarse muchas
// veces) - es historial para poder investigar qué courier/distancia/tarifa se
// usó, incluso en checkouts que nunca llegan a convertirse en orden.
// Nunca debe poder tumbar el flujo real: cualquier fallo aquí se traga.
function logCotizacionEnvio($origen, $cod_sucursal, $latitud, $longitud, $distancia, $distancia_fuente, $courier_precio, $tariff_id, $precio){
    try{
        $cod_usuario = (defined('user_id') && user_id > 0) ? user_id : null;
        $dispositivo = defined('device_type') ? device_type : null;
        $query = "INSERT INTO tb_cotizacion_envio_log
            (cod_usuario, cod_sucursal, latitud, longitud, distancia_km, distancia_fuente, courier_precio, tariff_id, device_type, precio, origen, fecha)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Conexion::ejecutar($query, [
            $cod_usuario, $cod_sucursal, $latitud, $longitud, $distancia,
            $distancia_fuente, $courier_precio, $tariff_id, $dispositivo, $precio, $origen, fecha()
        ]);
    }catch(\Throwable $e){
        // Telemetria - nunca debe afectar el flujo real
    }
}

// Ultima cotizacion reciente para una sucursal/ubicacion, usada solo para deteccion
// pasiva de couriers externos (nunca bloquea). Redondea a la misma precision que el
// cache de distancia (~111m) para tolerar pequeñas variaciones de GPS entre requests.
function getUltimaCotizacionReciente($cod_sucursal, $latitud, $longitud, $minutos = 15){
    try{
        $query = "SELECT precio, fecha FROM tb_cotizacion_envio_log
            WHERE cod_sucursal = ?
            AND ROUND(latitud, " . CACHE_PRECISION_DECIMALES . ") = ROUND(?, " . CACHE_PRECISION_DECIMALES . ")
            AND ROUND(longitud, " . CACHE_PRECISION_DECIMALES . ") = ROUND(?, " . CACHE_PRECISION_DECIMALES . ")
            AND fecha >= (NOW() - INTERVAL ? MINUTE)
            AND precio IS NOT NULL AND precio > 0
            ORDER BY fecha DESC LIMIT 1";
        return Conexion::buscarRegistro($query, [$cod_sucursal, $latitud, $longitud, $minutos]);
    }catch(\Throwable $e){
        return null;
    }
}

function getPrecioCourier($cod_courier, $sucursal, $latitud, $longitud, $tarifa_id = 0, $getRuta = true){
    $cod_sucursal = $sucursal['cod_sucursal'];
    $courier = "";
    $precio = 0;
    $distancia = false;
    if($cod_courier == COURIER_GACELA){
        $courier = COURIERS_EXTERNOS[COURIER_GACELA];
        $precio = getPrecioGacela($cod_sucursal, $latitud, $longitud);
    }else if($cod_courier == COURIER_PICKER){
        $courier = COURIERS_EXTERNOS[COURIER_PICKER];
        $precio = getPrecioPicker($cod_sucursal, $latitud, $longitud);
    }else if($cod_courier == COURIER_PEDIDOS_YA){
        $courier = COURIERS_EXTERNOS[COURIER_PEDIDOS_YA];
        $precio = getPrecioPedidosYa($sucursal, $latitud, $longitud);
    }else{
        if($getRuta){
            $courier = "GOOGLE_MAPS";
            $respDistancia = getDistanciaRuta($sucursal, $latitud, $longitud);
            if($respDistancia){
                $distancia = $respDistancia;
            }
        }
        if(!$distancia){
            $distancia = $sucursal['distance'];
            $courier = "LINEA_RECTA";
        }
        if($tarifa_id > 0){
            $precio = getPriceWithTariff($tarifa_id, $distancia);
        }
        
        if($precio==0){ //Por si acaso no haya tarifas al menos no de precio 0
            $precio = calculatePriceByDistance($sucursal, $distancia);
        }
    }

    return [
        'courierName' => $courier,
        'precio' => $precio,
        'distancia' => $distancia
    ];
}

function getPrecioGacela($cod_sucursal, $latitud, $longitud){
    require_once "clases/cl_gacela.php";
	$ClGacela = new cl_gacela($cod_sucursal);
    $route = $ClGacela->costoCarrera($latitud, $longitud);
    if(isset($route->data->estimated_fare)){
        return number_format($route->data->estimated_fare, 2);
    }else{
        $error = isset($route->status) ? $route->status : "Courier no llega a este sector en esta hora";
        throw new Exception($error);
    }
}

function getPrecioPicker($cod_sucursal, $latitud, $longitud){
    require_once "clases/cl_picker.php";
    $ClPicker = new cl_picker($cod_sucursal);
    $route = $ClPicker->costoCarrera($latitud, $longitud);
    $sucursal['picker'] = $route;
    if(isset($route->data) && $route->statusCode == 200){
        return number_format($route->data->deliveryFee, 2);
    }else{
        $error = "No se pudo obtener el precio de Picker";
        throw new Exception($error);
    }
}

function getPrecioPedidosYa($sucursal, $latitud, $longitud){
    require_once "clases/cl_pedidosya.php";
    $ClPedidosYa = new cl_pedidosya($sucursal['cod_sucursal']);
    $route = $ClPedidosYa->getEstimates($sucursal, $latitud, $longitud);
    if(isset($route["deliveryOffers"][0]["pricing"]["total"])) {
        return number_format($route["deliveryOffers"][0]["pricing"]["total"], 2);
    }else{
        $error = isset($route['code']) ? $route['code'] : "ERROR COBERTURA PEDIDOS YA";
        $error = ($error == "WAYPOINTS_OUT_OF_ZONE") ? "FUERA DE COBERTURA" : $error;
        throw new Exception($error);
    }
}

function getDistanciaRuta($sucursal, $latitud, $longitud){
    require_once "helpers/cache.php";
    $latR = round($latitud,  CACHE_PRECISION_DECIMALES);
    $lngR = round($longitud, CACHE_PRECISION_DECIMALES);
    $cacheKey = "dist_{$sucursal['cod_sucursal']}_{$latR}_{$lngR}";

    //Verificar si la data ya esta en cache
    $cached = getCache($cacheKey);
    if ($cached !== null) {
        registrarStatCache(true);
        return $cached; // ni tocamos Google 🎉
    }

    require_once "clases/cl_sucursales.php";
	$ClSucursales = new cl_sucursales();
    $route = $ClSucursales->getDistanciaRutaGoogle($sucursal['latitud'], $sucursal['longitud'], $latitud, $longitud);
    if (!$route) return false;

    $distancia = number_format($route['distancia']/1000, 3, ".","");
    logGoogleMaps($sucursal['latitud'], $sucursal['longitud'], $latitud, $longitud, $distancia, 0);

    //Guardar en cache durante 24 horas.
    setCache($cacheKey, $distancia, CACHE_TTL_DISTANCIA);
    registrarStatCache(false);

    return $distancia;
}

function calculatePriceByDistance($sucursal, $distancia){
    require_once "clases/cl_sucursales.php";
	$ClSucursales = new cl_sucursales();
    return number_format($ClSucursales->getPrecio($distancia, $sucursal['cod_sucursal']),2);
}

function getTarifaEnvio($cod_sucursal, $peso = 0, $productos_ids = []){
    //Si solo es una tarifa retornamos esa
    $query = "SELECT cod_tarifa FROM tb_tarifa WHERE cod_sucursal = ? LIMIT 2";
    $tarifas = Conexion::buscarVariosRegistro($query, [$cod_sucursal]);
    if(!$tarifas) return false;
    if(count($tarifas) === 1){
        return $tarifas[0]['cod_tarifa'];
    }
    
    //Primero detectar productos con tarifa forzada
    if(count($productos_ids)> 0){
        $allIds = implode(",",$productos_ids);
        $query = "
            SELECT t.cod_tarifa
            FROM tb_producto_tarifa_forzada ptf
            INNER JOIN tb_tarifa t 
                ON t.cod_tarifa = ptf.cod_tarifa
            WHERE ptf.cod_producto IN ($allIds)
            AND t.cod_sucursal = $cod_sucursal
            ORDER BY 
                t.peso_max_kg IS NULL DESC,  -- prioriza NULL (cuando solo hay una tarifa)
                t.peso_max_kg DESC
            LIMIT 1
        ";
        $tarifaForzada = Conexion::buscarRegistro($query);
        if($tarifaForzada){
            return $tarifaForzada['cod_tarifa'];
        }
    }

    $query = "SELECT cod_tarifa
        FROM tb_tarifa
        WHERE cod_sucursal = ?
        AND (peso_max_kg IS NULL OR peso_max_kg >= ?)
        ORDER BY peso_max_kg ASC
        LIMIT 1";
    $tarifa = Conexion::buscarRegistro($query, [$cod_sucursal, $peso]);
    return $tarifa ? $tarifa['cod_tarifa'] : null;
}

function getTarifaDefault($cod_sucursal){
    $query = "SELECT cod_tarifa FROM tb_tarifa WHERE cod_sucursal = ? ORDER BY cod_tarifa ASC LIMIT 1";
    $tarifa = Conexion::buscarRegistro($query, [$cod_sucursal]);
    return $tarifa ? $tarifa['cod_tarifa'] : 0;
}

function getPriceWithTariff($tarifa_id, $distancia){
    require_once "clases/cl_sucursales.php";
	$ClSucursales = new cl_sucursales();
    return number_format($ClSucursales->getTarifaPrecio($distancia, $tarifa_id),2);
}


?>