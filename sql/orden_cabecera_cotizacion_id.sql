-- Liga la orden confirmada (tb_orden_cabecera) al ticket exacto de cotizacion
-- (tb_cotizacion_precio) que la respaldó, cuando aplica (couriers internos,
-- clientes ya actualizados a la Fase 1). Sigue el mismo patrón que las
-- columnas distancia_km/distancia_fuente/courier_precio/tariff_id/device_type
-- que ya existían, todas llenadas desde envio_meta en cl_ordenes.php.
--
-- Ejecutar manualmente en cada ambiente (dev/prod).

ALTER TABLE tb_orden_cabecera
  ADD COLUMN cotizacion_id INT NULL AFTER device_type;
