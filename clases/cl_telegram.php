<?php
/**
 * Avisos por Telegram a administradores (permiso NOTIFY_TELEGRAM de la empresa).
 *
 * Quién recibe: usuarios con un chat vinculado en tb_telegram_usuarios (estado 'A').
 * El vínculo lo crea el servicio del bot (api.mie-commerce.com/bot): el usuario genera un código en
 * Perfil del dashboard y se lo escribe al bot; el bot guarda chat_id/user_id y pasa el estado a 'A'.
 *
 * Config (.env):
 *   TELEGRAM_BOT_TOKEN=<token del bot, el mismo con el que se vincularon los usuarios>
 *   TELEGRAM_API_URL=https://api.telegram.org/bot   (opcional; solo para pruebas con un servidor falso)
 *
 * Nunca lanza excepciones: un aviso que falla no debe romper una orden. Los errores quedan en
 * logs/<alias>/telegram-errores.log
 */
class cl_telegram
{
    const API_URL = 'https://api.telegram.org/bot';

    private $token;
    private $apiUrl;
    private $caido = false; // si Telegram no responde, no se insiste con los demás chats de la misma petición

    public function __construct()
    {
        $this->token = trim((string) $this->config('TELEGRAM_BOT_TOKEN'));
        $this->apiUrl = rtrim($this->config('TELEGRAM_API_URL', self::API_URL), '/');
    }

    public function habilitado()
    {
        return $this->token !== '';
    }

    // ¿La empresa tiene el aviso por Telegram activado? (permiso NOTIFY_TELEGRAM)
    public function empresaNotifica($cod_empresa)
    {
        $filas = Conexion::buscarVariosRegistro(
            "SELECT 1 FROM tb_permisos_empresas
             WHERE identificador = 'NOTIFY_TELEGRAM' AND cod_empresa = :cod_empresa AND habilitado = 1 AND estado = 'A'
             LIMIT 1",
            [':cod_empresa' => intval($cod_empresa)]
        );
        return !empty($filas);
    }

    // Nueva orden: admins de la empresa y el admin de la sucursal de esa orden (rol 3 solo de su sucursal)
    public function chatsEmpresa($cod_empresa, $cod_sucursal)
    {
        $filas = Conexion::buscarVariosRegistro(
            "SELECT tu.chat_id
             FROM tb_telegram_usuarios tu
             INNER JOIN tb_usuarios u ON u.cod_usuario = tu.cod_usuario
             WHERE u.cod_empresa = :cod_empresa
               AND u.estado = 'A' AND tu.estado = 'A'
               AND tu.chat_id IS NOT NULL AND tu.chat_id <> ''
               AND (u.cod_rol <> 3 OR u.cod_sucursal = :cod_sucursal)",
            [':cod_empresa' => intval($cod_empresa), ':cod_sucursal' => intval($cod_sucursal)]
        );
        return $this->soloChats($filas);
    }

    // Orden asignada a una flota: todos los usuarios activos de la flota con chat vinculado
    public function chatsFlota($cod_flota)
    {
        $filas = Conexion::buscarVariosRegistro(
            "SELECT tu.chat_id
             FROM tb_telegram_usuarios tu
             INNER JOIN tb_usuarios u ON u.cod_usuario = tu.cod_usuario
             WHERE u.cod_empresa = :cod_flota
               AND u.estado = 'A' AND tu.estado = 'A'
               AND tu.chat_id IS NOT NULL AND tu.chat_id <> ''",
            [':cod_flota' => intval($cod_flota)]
        );
        return $this->soloChats($filas);
    }

    // Envía un mensaje (HTML de Telegram) a un chat. Retorna true si Telegram lo aceptó.
    public function sendMessage($chat_id, $html)
    {
        if ($this->caido) { // ya falló antes en esta petición (sin token o Telegram caído): no se repite ni se vuelve a registrar
            return false;
        }
        if (!$this->habilitado()) {
            $this->caido = true;
            $this->error('Falta TELEGRAM_BOT_TOKEN en el .env');
            return false;
        }

        $ch = curl_init($this->apiUrl . $this->token . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['chat_id' => $chat_id, 'text' => $html, 'parse_mode' => 'HTML'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
        ]);
        $respuesta = curl_exec($ch);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false) {
            $this->caido = true;
            $this->error("No se pudo conectar con Telegram: $errorCurl");
            return false;
        }
        $json = json_decode($respuesta, true);
        if (is_array($json) && !empty($json['ok'])) {
            return true;
        }
        $detalle = is_array($json) && isset($json['description']) ? $json['description'] : substr((string) $respuesta, 0, 200);
        $this->error("Telegram rechazó el mensaje para el chat $chat_id: $detalle");
        return false;
    }

    private function soloChats($filas)
    {
        $chats = [];
        foreach ((array) $filas as $fila) {
            $chats[$fila['chat_id']] = $fila['chat_id']; // sin repetidos
        }
        return array_values($chats);
    }

    private function config($clave, $defecto = '')
    {
        $valor = function_exists('env') ? env($clave, $defecto) : $defecto;
        return $valor === null ? $defecto : (string) $valor;
    }

    private function error($mensaje)
    {
        $carpeta = dirname(__DIR__) . '/logs/' . (defined('alias') ? alias : 'general');
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0775, true);
        }
        @file_put_contents($carpeta . '/telegram-errores.log', '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . "\n", FILE_APPEND | LOCK_EX);
    }
}
