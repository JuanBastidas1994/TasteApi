-- Endpoint: POST /ordenes/preorden-pago-exitoso
-- Permite registrar el cobro exitoso (Nuvei/Datafast/etc.) en la preorden
-- ANTES de crear la orden, para no perder paymentId/paymentAuth/lot_number
-- si la creación de la orden falla o nunca se completa.
--
-- Ejecutar manualmente en cada ambiente (dev/prod).

ALTER TABLE tb_preorden_json
  MODIFY estado ENUM('VALIDADA','CREANDO_ORDEN','PAGADA','PAGADA_NO_CREADA','FALLADA','CERRADA') NULL,
  ADD COLUMN lot_number VARCHAR(30) NULL AFTER paymentAuth,
  ADD COLUMN num_intentos_creacion INT NOT NULL DEFAULT 0 AFTER lot_number;
