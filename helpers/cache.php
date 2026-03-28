<?php

define('CACHE_DIR', __DIR__ . '/../cache/');
define('CACHE_PRECISION_DECIMALES', 3); // 3 = ~111m | 2 = ~1.1km — cambiar para experimentar

// El archivo de stats cambia cada mes automáticamente
define('CACHE_STATS_FILE', CACHE_DIR . '_stats_' . date('Y_m') . '.json');

/* ═══════════════════════════════════════
 *  CORE
 * ═══════════════════════════════════════ */

function getCache($key) {
    $file = CACHE_DIR . $key . '.json';
    if (!file_exists($file)) return null;

    $data = json_decode(file_get_contents($file), true);
    if (!$data) return null;

    // ¿Expiró?
    if (time() > $data['expires_at']) {
        unlink($file);
        return null;
    }

    return $data['value'];
}

function setCache($key, $value, $ttl = 86400) {
    if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);

    $data = [
        'value'      => $value,
        'expires_at' => time() + $ttl
    ];
    file_put_contents(CACHE_DIR . $key . '.json', json_encode($data));
}

/* ═══════════════════════════════════════
 *  STATS MENSUALES
 * ═══════════════════════════════════════ */

function registrarStatCache($hit) {
    if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);

    $stats = file_exists(CACHE_STATS_FILE)
        ? json_decode(file_get_contents(CACHE_STATS_FILE), true)
        : [
            'hits'                => 0,
            'misses'              => 0,
            'ahorro_estimado_usd' => 0,
            'costo_estimado_usd'  => 0,
            'precision_decimales' => CACHE_PRECISION_DECIMALES,
            'mes'                 => date('Y-m'),
          ];

    if ($hit) {
        $stats['hits']++;
        $stats['ahorro_estimado_usd'] = round($stats['hits'] * 0.005, 4);
    } else {
        $stats['misses']++;
        $stats['costo_estimado_usd'] = round($stats['misses'] * 0.005, 4);
    }

    $stats['ultima_actualizacion'] = date('Y-m-d H:i:s');
    file_put_contents(CACHE_STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

/* ═══════════════════════════════════════
 *  INVALIDACIÓN
 * ═══════════════════════════════════════ */

/** Llamar cuando se mueve físicamente una sucursal (cambia lat/lng) */
function invalidarCacheSucursal($cod_sucursal) {
    $files = glob(CACHE_DIR . "dist_{$cod_sucursal}_*.json");
    if (!$files) return 0;
    foreach ($files as $file) {
        unlink($file);
    }
    return count($files);
}

/* ═══════════════════════════════════════
 *  LIMPIEZA  (llamar desde el dashboard)
 * ═══════════════════════════════════════ */

function limpiarCacheVencido() {
    $files = glob(CACHE_DIR . 'dist_*.json'); // solo archivos de distancia, no stats
    if (!$files) return ['eliminados' => 0, 'conservados' => 0, 'total' => 0];

    $eliminados  = 0;
    $conservados = 0;

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data || time() > $data['expires_at']) {
            unlink($file);
            $eliminados++;
        } else {
            $conservados++;
        }
    }

    return [
        'total'       => count($files),
        'eliminados'  => $eliminados,
        'conservados' => $conservados,
    ];
}

/** Devuelve todos los archivos de stats mensuales para mostrar en dashboard */
function getStatsHistorial() {
    $files  = glob(CACHE_DIR . '_stats_*.json');
    $result = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) $result[] = $data;
    }
    // Ordenar del más reciente al más antiguo
    usort($result, fn($a, $b) => strcmp($b['mes'], $a['mes']));
    return $result;
}