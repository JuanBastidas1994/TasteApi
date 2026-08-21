<?php

// Ticket exacto de cotizacion de envio (Fase 1 del fix de cobro de envio).
// Distinto del cache de distancia de calcularPrecioEnvio.php (que redondea coordenadas
// a ~111m y dura 120 dias, solo para no golpear Google Maps de mas): esto es un match
// EXACTO por ID, con vigencia corta, que /ordenes/validar usa como fuente autoritativa
// del precio a cobrar en vez de recalcular en vivo.
//
// Alcance actual: solo couriers internos (GOOGLE_MAPS/LINEA_RECTA). Los externos
// (GACELA/PICKER/PEDIDOS_YA) cobran por consulta y su precio puede variar legitimamente
// (surge pricing) - siguen protegidos solo por la deteccion pasiva de la Fase 0, nunca
// por este ticket. Ver sql/cotizacion_precio.sql para el esquema.

define('COTIZACION_PRECIO_TTL_MINUTOS', 10);

function crearCotizacionPrecio($cod_sucursal, $courier_nombre, $tariff_id, $latitud, $longitud, $distancia_km, $precio){
    try{
        $cod_usuario = (defined('user_id') && user_id > 0) ? user_id : null;
        $dispositivo = defined('device_type') ? device_type : null;
        $query = "INSERT INTO tb_cotizacion_precio
            (cod_sucursal, courier_nombre, tariff_id, latitud, longitud, distancia_km, precio, cod_usuario, device_type, creado_en, vigente_hasta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE))";
        $ok = Conexion::ejecutar($query, [
            $cod_sucursal, $courier_nombre, $tariff_id, $latitud, $longitud, $distancia_km, $precio,
            $cod_usuario, $dispositivo, COTIZACION_PRECIO_TTL_MINUTOS
        ]);
        if(!$ok) return null;
        return Conexion::lastId();
    }catch(\Throwable $e){
        return null;
    }
}

// Busca el ticket por ID exacto, exige que no haya expirado y que corresponda a la
// misma sucursal/tarifa de la orden que se esta validando. No compara coordenadas
// (para eso esta el ID) ni recalcula nada - es una busqueda directa.
function buscarCotizacionValida($cotizacion_id, $cod_sucursal, $tariff_id){
    if(empty($cotizacion_id)) return null;
    try{
        $query = "SELECT * FROM tb_cotizacion_precio
            WHERE id = ? AND cod_sucursal = ? AND tariff_id = ? AND vigente_hasta >= NOW()
            LIMIT 1";
        return Conexion::buscarRegistro($query, [$cotizacion_id, $cod_sucursal, $tariff_id]);
    }catch(\Throwable $e){
        return null;
    }
}
