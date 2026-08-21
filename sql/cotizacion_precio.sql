-- Tabla para el ticket exacto de cotizacion de envio (Fase 1 del fix de cobro de envio).
-- Distinto del cache de distancia (que redondea coordenadas a ~111m y dura 120 dias,
-- solo para no golpear Google Maps de mas): esto es un match exacto por ID, con
-- vigencia corta, que /ordenes/validar usa como fuente autoritativa del precio a cobrar.
--
-- Alcance actual: solo couriers internos (GOOGLE_MAPS/LINEA_RECTA). Los externos
-- (GACELA/PICKER/PEDIDOS_YA) siguen protegidos solo por la deteccion pasiva de la Fase 0.
--
-- Ejecutar manualmente en cada ambiente (dev/prod).

CREATE TABLE IF NOT EXISTS tb_cotizacion_precio (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    cod_sucursal    INT NOT NULL,
    courier_nombre  VARCHAR(30) NOT NULL,
    tariff_id       INT NOT NULL DEFAULT 0,
    latitud         DECIMAL(10,7) NOT NULL,
    longitud        DECIMAL(10,7) NOT NULL,
    distancia_km    DECIMAL(10,3) NULL,
    precio          DECIMAL(10,2) NOT NULL,
    cod_usuario     INT NULL,
    device_type     VARCHAR(10) NULL,
    creado_en       DATETIME NOT NULL,
    vigente_hasta   DATETIME NOT NULL,

    INDEX idx_vigente     (vigente_hasta),
    INDEX idx_sucursal    (cod_sucursal)
);
