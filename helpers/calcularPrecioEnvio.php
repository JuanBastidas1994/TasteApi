<?php

function getPrecioCourier($cod_courier, $sucursal, $latitud, $longitud, $tarifa_id = 0, $getRuta = true){
    $cod_sucursal = $sucursal['cod_sucursal'];
    $courier = "";
    $precio = 0;
    $distancia = false;
    if($cod_courier == 1){
        $courier = "GACELA";
        $precio = getPrecioGacela($cod_sucursal, $latitud, $longitud);
    }else if($cod_courier == 3){
        $courier = "PICKER";
        $precio = getPrecioPicker($cod_sucursal, $latitud, $longitud);
    }else if($cod_courier == 5){
        $courier = "PEDIDOS_YA";
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
    setCache($cacheKey, $distancia);
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

function getPriceWithTariff($tarifa_id, $distancia){
    require_once "clases/cl_sucursales.php";
	$ClSucursales = new cl_sucursales();
    return number_format($ClSucursales->getTarifaPrecio($distancia, $tarifa_id),2);
}


?>