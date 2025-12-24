<?php

function getPrecioCourier($cod_courier, $sucursal, $latitud, $longitud, $getRuta = true){
    $cod_sucursal = $sucursal['cod_sucursal'];
    $courier = "";
    $precio = 0;
    $distancia = 0;
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
            $precio = getPrecioRuta($sucursal, $latitud, $longitud, $distancia);
            if(!$precio){
                $courier = "LINEA_RECTA";
                $precio = getPrecioLineaRecta($sucursal);
                $distancia = $sucursal['distance'];
            }
        }else{
            $courier = "LINEA_RECTA";
            $precio = getPrecioLineaRecta($sucursal);
            $distancia = $sucursal['distance'];
        }
    }

    return [
        'courierName' => $courier,
        'precio' => $precio,
        'distancia' => $distancia
    ];
}

/* 
    ! Deprecated 2025-09-16

    function getPrecioGacela($cod_sucursal, $latitud, $longitud){
    require_once "clases/cl_gacela.php";
	$ClGacela = new cl_gacela($cod_sucursal);
    $route = $ClGacela->costoCarrera($latitud, $longitud);
    if(isset($route->results->total)){
        return number_format($route->results->total, 2);
    }else{
        $error = isset($route->status) ? $route->status : "Courier no llega a este sector en esta hora";
        throw new Exception($error);
    }
} */

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

function getPrecioRuta($sucursal, $latitud, $longitud, &$distancia){
    require_once "clases/cl_sucursales.php";
	$ClSucursales = new cl_sucursales();
    $distancia = $sucursal['distance'];
    $route = $ClSucursales->getDistanciaRutaGoogle($sucursal['latitud'], $sucursal['longitud'], $latitud, $longitud);
    if($route){
        $distancia = number_format($route['distancia']/1000, 3, ".","");
        $precio = number_format($ClSucursales->getPrecio($distancia, $sucursal['cod_sucursal']),2);
        logGoogleMaps($sucursal['latitud'], $sucursal['longitud'], $latitud, $longitud, $distancia, $precio);
        return $precio;
    }else{
        return false;
    }
}

function getPrecioLineaRecta($sucursal){
    require_once "clases/cl_sucursales.php";
	$ClSucursales = new cl_sucursales();
    $distancia = $sucursal['distance'];
    return number_format($ClSucursales->getPrecio($distancia, $sucursal['cod_sucursal']),2);
}


?>