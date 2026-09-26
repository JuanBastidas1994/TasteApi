<?php
/**
 * Carga .env sin Composer. Compatible PHP 7.3+.
 * Si la variable ya existe en el entorno (Forge), no la pisa.
 */
if (!function_exists('load_env')) {
    function load_env($path)
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    function env($key, $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            if (array_key_exists($key, $_ENV)) {
                $value = $_ENV[$key];
            } else {
                return $default;
            }
        }

        $lower = strtolower($value);
        if ($lower === 'true' || $lower === '1') {
            return true;
        }
        if ($lower === 'false' || $lower === '0') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }

        return $value;
    }
}
