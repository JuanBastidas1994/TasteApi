<?php
/**
 * Cron: Detección de carritos abandonados y expirados
 *
 * Lee cart_abandonment_minutes y cart_expiry_hours desde tb_empresas
 * y procesa cada empresa con su propia configuración.
 *
 * Ejecutar cada 5–10 minutos:
 *   php /ruta/a/TasteApi/cron/carrito_abandonado.php
 *
 * Ejemplo crontab:
 *   *\/5 * * * * php /home/usuario/public_html/TasteApi/cron/carrito_abandonado.php >> /home/usuario/logs/carrito_cron.log 2>&1
 */

// Solo permitir ejecución desde CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acceso denegado');
}

// ── Bootstrap ─────────────────────────────────────────────────────────────

$rootPath = dirname(__DIR__);

require_once $rootPath . '/config.php';
require_once $rootPath . '/conexion.php';

$timestamp = date('[Y-m-d H:i:s]');
$con       = Conexion::obtenerConexion();

echo "$timestamp Iniciando detección de carritos\n";

// ── Obtener empresas con su configuración de carrito ──────────────────────

$empresas = $con->query(
    "SELECT cod_empresa, nombre,
            cart_abandonment_minutes,
            cart_expiry_hours
     FROM tb_empresas
     WHERE estado = 1"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($empresas)) {
    echo "$timestamp Sin empresas activas. Fin.\n";
    exit(0);
}

echo "$timestamp Empresas a procesar: " . count($empresas) . "\n";

// ── Procesar cada empresa ─────────────────────────────────────────────────

$sqlExpirado = "UPDATE carrito_sesion
                SET estado = 'EXPIRADO'
                WHERE cod_empresa = ?
                AND estado IN ('ACTIVO', 'ABANDONADO')
                AND updated_at < NOW() - INTERVAL ? HOUR";

$sqlAbandonado = "UPDATE carrito_sesion
                  SET estado = 'ABANDONADO', abandoned_at = NOW()
                  WHERE cod_empresa = ?
                  AND estado = 'ACTIVO'
                  AND updated_at < NOW() - INTERVAL ? MINUTE";

$stmtExp = $con->prepare($sqlExpirado);
$stmtAbn = $con->prepare($sqlAbandonado);

foreach ($empresas as $empresa) {
    $cod      = $empresa['cod_empresa'];
    $nombre   = $empresa['nombre'];
    $minutos  = (int) $empresa['cart_abandonment_minutes'];
    $horas    = (int) $empresa['cart_expiry_hours'];

    try {
        // 1. Expirados primero (tienen prioridad)
        $stmtExp->execute([$cod, $horas]);
        $rowsExp = $stmtExp->rowCount();

        // 2. Abandonados
        $stmtAbn->execute([$cod, $minutos]);
        $rowsAbn = $stmtAbn->rowCount();

        echo "$timestamp [$nombre] Expirados: $rowsExp | Abandonados (>{$minutos}m): $rowsAbn\n";

    } catch (Exception $ex) {
        echo "$timestamp [$nombre] ERROR: " . $ex->getMessage() . "\n";
        error_log(
            date('[Y-m-d H:i:s] ') . "[$nombre] " . $ex->getMessage() . PHP_EOL,
            3,
            $rootPath . '/errores_sql.log'
        );
    }
}

echo "$timestamp Fin de ejecución\n";
