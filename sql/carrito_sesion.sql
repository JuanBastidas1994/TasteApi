-- Tabla para persistencia de carrito abandonado
-- Se crea una vez cuando el usuario entra al checkout

CREATE TABLE IF NOT EXISTS carrito_sesion (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    cart_token  VARCHAR(100)  NOT NULL,
    cod_empresa INT           NOT NULL,
    cod_usuario INT           NULL,
    email       VARCHAR(120)  NULL,
    telefono    VARCHAR(20)   NULL,
    origen      ENUM('WEB','APP') DEFAULT 'WEB',

    cart_json   LONGTEXT      NULL,

    estado      ENUM('ACTIVO','ABANDONADO','CONVERTIDO','EXPIRADO') DEFAULT 'ACTIVO',

    recovery_source VARCHAR(20)  NULL,

    abandoned_at  DATETIME NULL,
    recovered_at  DATETIME NULL,
    converted_at  DATETIME NULL,

    cod_preorden  INT NULL,
    cod_orden     INT NULL,

    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,

    INDEX idx_cart_token (cart_token),
    INDEX idx_cod_empresa (cod_empresa),
    INDEX idx_estado     (estado),
    INDEX idx_updated_at (updated_at)
);
