<?php
/**
 * Pre-calentamiento de cache de distancias
 *
 * Genera una grilla de puntos dentro de cada zona de cobertura y pre-rellena
 * el cache de distancias de Google Maps para que el primer usuario real no
 * pague la latencia ni dispare la llamada a la API.
 *
 * Uso:
 *   php scripts/precalentar_cache.php                   (todas las sucursales)
 *   php scripts/precalentar_cache.php --sucursal=5      (solo una sucursal)
 *   php scripts/precalentar_cache.php --dry-run         (contar puntos sin llamar a Google)
 *   php scripts/precalentar_cache.php --forzar          (recalcular aunque ya esté en cache)
 */

define('CLI_SCRIPT', true);

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config.php';
require_once $rootDir . '/conexion.php';
require_once $rootDir . '/helpers/cache.php';
require_once $rootDir . '/clases/cl_sucursales.php';

// ─── Argumentos CLI ───────────────────────────────────────────────────────────
$opts      = getopt('', ['sucursal:', 'dry-run', 'forzar']);
$filtroCod = isset($opts['sucursal']) ? (int)$opts['sucursal'] : null;
$dryRun    = isset($opts['dry-run']);
$forzar    = isset($opts['forzar']);

// ─── Configuración ────────────────────────────────────────────────────────────
$STEP         = pow(10, -CACHE_PRECISION_DECIMALES); // 0.001° ≈ 111m
$DELAY_US     = 80000; // 80ms entre llamadas a Google ≈ 12 req/s (límite default: 50 QPS)

// ─── Obtener sucursales con polígonos ─────────────────────────────────────────
$whereExtra = $filtroCod ? "AND s.cod_sucursal = $filtroCod" : '';
$query = "SELECT s.cod_sucursal, s.nombre, s.latitud, s.longitud,
                 ST_AsText(sc.zone) AS wkt
          FROM tb_sucursales s
          INNER JOIN tb_sucursal_cobertura sc ON sc.cod_sucursal = s.cod_sucursal
          WHERE s.estado = 'A' AND s.delivery = 1 $whereExtra";
$sucursales = Conexion::buscarVariosRegistro($query);

if (!$sucursales) {
    echo "No se encontraron sucursales con polígono de cobertura.\n";
    exit(1);
}

$ClSucursales = new cl_sucursales();
$totalCalls   = 0;
$skipped      = 0;
$errors       = 0;
$totalPuntos  = 0;

echo "=== Pre-calentamiento de cache ===\n";
echo "Modo: " . ($dryRun ? 'DRY-RUN (sin llamadas a Google)' : 'REAL') . "\n";
echo "TTL cache: " . (CACHE_TTL_DISTANCIA / 86400) . " días\n\n";

foreach ($sucursales as $suc) {
    $ring = parsearWkt($suc['wkt']);
    if (!$ring) {
        echo "  [WARN] No se pudo parsear WKT de sucursal {$suc['cod_sucursal']}\n";
        continue;
    }

    // Bounding box
    // NOTA: las coordenadas en WKT están guardadas como Point(lat lng),
    // así que el primer número de cada par es latitud y el segundo longitud.
    // Si ves que los puntos quedan invertidos, intercambia $c[0]/$c[1] aquí.
    $lats = array_column($ring, 0);
    $lngs = array_column($ring, 1);
    $latMin = min($lats); $latMax = max($lats);
    $lngMin = min($lngs); $lngMax = max($lngs);

    // Estimación de puntos para esta sucursal
    $cols = ceil(($latMax - $latMin) / $STEP) + 1;
    $rows = ceil(($lngMax - $lngMin) / $STEP) + 1;
    $estimado = $cols * $rows;

    echo "Sucursal {$suc['cod_sucursal']} — {$suc['nombre']}\n";
    echo "  Bbox: [{$latMin},{$lngMin}] → [{$latMax},{$lngMax}]\n";
    echo "  Grilla: {$cols}×{$rows} = ~{$estimado} puntos en bbox\n";

    $puntosSucursal = 0;

    for ($lat = $latMin; $lat <= $latMax + $STEP / 2; $lat += $STEP) {
        for ($lng = $lngMin; $lng <= $lngMax + $STEP / 2; $lng += $STEP) {
            $latR = round($lat, CACHE_PRECISION_DECIMALES);
            $lngR = round($lng, CACHE_PRECISION_DECIMALES);

            if (!puntoEnPoligono($latR, $lngR, $ring)) continue;

            $puntosSucursal++;
            $totalPuntos++;
            $cacheKey = "dist_{$suc['cod_sucursal']}_{$latR}_{$lngR}";

            if ($dryRun) continue;

            if (!$forzar && getCache($cacheKey) !== null) {
                $skipped++;
                continue;
            }

            $route = $ClSucursales->getDistanciaRutaGoogle(
                $suc['latitud'], $suc['longitud'], $latR, $lngR
            );

            if ($route) {
                $distancia = number_format($route['distancia'] / 1000, 3, '.', '');
                setCache($cacheKey, $distancia, CACHE_TTL_DISTANCIA);
                $totalCalls++;
                usleep($DELAY_US);
            } else {
                $errors++;
                echo "  [ERROR] No se obtuvo ruta para [{$latR},{$lngR}]\n";
            }
        }
    }

    echo "  Puntos dentro del polígono: {$puntosSucursal}\n\n";
}

// ─── Resumen ──────────────────────────────────────────────────────────────────
echo "=== RESULTADO ===\n";
echo "Puntos totales dentro de polígonos: {$totalPuntos}\n";

if ($dryRun) {
    $costoEstimado = number_format($totalPuntos * 0.005, 2);
    echo "Costo estimado si se ejecuta real: \${$costoEstimado} USD\n";
    echo "(Con cache existente el costo real será menor)\n";
} else {
    echo "Llamadas a Google realizadas: {$totalCalls}\n";
    echo "Ya en cache (saltados):       {$skipped}\n";
    echo "Errores:                      {$errors}\n";
    echo "Costo estimado:               \$" . number_format($totalCalls * 0.005, 2) . " USD\n";
}


// ─── Funciones auxiliares ─────────────────────────────────────────────────────

/**
 * Parsea un WKT POLYGON o MULTIPOLYGON y devuelve el anillo exterior
 * como array de [coord1, coord2] (el orden depende de cómo fueron insertados).
 *
 * Ejemplo WKT: POLYGON((-0.123 -78.456, ...))
 */
function parsearWkt(string $wkt): ?array {
    // Extraer el primer anillo (exterior)
    if (!preg_match('/\(+([^()]+)\)/', $wkt, $m)) return null;

    $pares = explode(',', trim($m[1]));
    $ring  = [];
    foreach ($pares as $par) {
        $coords = preg_split('/\s+/', trim($par));
        if (count($coords) < 2) continue;
        $ring[] = [(float)$coords[0], (float)$coords[1]];
    }
    return count($ring) >= 3 ? $ring : null;
}

/**
 * Ray-casting: ¿está el punto ($a, $b) dentro del polígono?
 * $ring: array de [$a, $b] con el mismo orden de coordenadas que el punto.
 */
function puntoEnPoligono(float $a, float $b, array $ring): bool {
    $inside = false;
    $n = count($ring);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $ai = $ring[$i][0]; $bi = $ring[$i][1];
        $aj = $ring[$j][0]; $bj = $ring[$j][1];
        if ((($bi > $b) !== ($bj > $b)) &&
            ($a < ($aj - $ai) * ($b - $bi) / ($bj - $bi) + $ai)) {
            $inside = !$inside;
        }
    }
    return $inside;
}
