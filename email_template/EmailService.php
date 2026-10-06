<?php
/**
 * Envío de correos por API (Resend). Reemplaza al SMTP/PHPMailer: DigitalOcean bloquea los puertos SMTP.
 *
 * Config (.env):
 *   MAIL_DRIVER=resend   resend = envía de verdad | log = no envía, guarda el correo en logs/<alias>/emails.log (local/pruebas)
 *   RESEND_API_KEY=re_xxx
 *   MAIL_FROM=pedidos@tu-dominio.com   debe ser de un dominio verificado en Resend
 *   MAIL_REPLY_TO=info@tu-dominio.com  a donde llegan las respuestas del cliente (tu bandeja de GSuite)
 *   MAIL_REDIRECT_TO=tu@correo.com     SOLO para local/pruebas: todo correo va a esta dirección
 *
 * Nunca lanza excepciones: un correo que falla no debe romper un pedido ni un login. Retorna [ok, id, error]
 * y deja el detalle en logs/<alias>/emails-errores.log.
 */
class EmailService {

    const RESEND_URL = 'https://api.resend.com/emails';

    /**
     * @param string $to       correo del destinatario
     * @param string $subject  asunto
     * @param string $html     cuerpo HTML
     * @param string $fromName nombre visible del remitente (nombre de la empresa)
     * @return array ['ok' => bool, 'id' => string|null, 'error' => string|null]
     */
    public static function send($to, $subject, $html, $fromName) {
        $to = strtolower(trim(self::limpiarLinea($to)));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return self::fallo("Correo destino inválido: '$to'", $subject);
        }

        $subject = self::limpiarLinea($subject);

        // Salvaguarda de pruebas: la BD local puede tener correos de clientes reales
        $redirigir = strtolower(trim(self::config('MAIL_REDIRECT_TO')));
        if ($redirigir !== '' && filter_var($redirigir, FILTER_VALIDATE_EMAIL)) {
            $to = $redirigir;
        }
        $from = self::dirigir($fromName, self::config('MAIL_FROM'));
        $replyTo = self::config('MAIL_REPLY_TO');

        if (self::config('MAIL_DRIVER', 'resend') === 'log') {
            self::escribir('emails.log', "[" . date('Y-m-d H:i:s') . "] A: $to | De: $from | Asunto: $subject\n$html\n\n");
            return ['ok' => true, 'id' => 'log', 'error' => null];
        }

        $apiKey = self::config('RESEND_API_KEY');
        if ($apiKey === '' || self::config('MAIL_FROM') === '') {
            return self::fallo('Falta RESEND_API_KEY o MAIL_FROM en el .env', $subject, $to);
        }

        $payload = [
            'from' => $from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ];
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $payload['reply_to'] = $replyTo;
        }

        $ch = curl_init(self::config('RESEND_API_URL', self::RESEND_URL));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'User-Agent: taste-api/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $respuesta = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false) {
            return self::fallo("No se pudo conectar con Resend: $errorCurl", $subject, $to);
        }

        $json = json_decode($respuesta, true);
        if ($status >= 200 && $status < 300 && is_array($json) && !empty($json['id'])) {
            return ['ok' => true, 'id' => $json['id'], 'error' => null];
        }

        $detalle = is_array($json) && isset($json['message']) ? $json['message'] : substr((string) $respuesta, 0, 300);
        return self::fallo("Resend respondió HTTP $status: $detalle", $subject, $to);
    }

    private static function config($clave, $defecto = '') {
        $valor = function_exists('env') ? env($clave, $defecto) : $defecto;
        return $valor === null ? $defecto : (string) $valor;
    }

    // "Nombre <correo>" sin caracteres que rompan el encabezado
    private static function dirigir($nombre, $correo) {
        $nombre = trim(preg_replace('/[<>"\\\\,;:]/', '', self::limpiarLinea($nombre)));
        return $nombre !== '' ? "$nombre <$correo>" : $correo;
    }

    // Evita inyección de encabezados (saltos de línea en asunto/nombre/correo)
    private static function limpiarLinea($texto) {
        return trim(str_replace(["\r", "\n", "\0"], ' ', (string) $texto));
    }

    private static function fallo($mensaje, $asunto, $to = '') {
        self::error($mensaje, $asunto, $to);
        return ['ok' => false, 'id' => null, 'error' => $mensaje];
    }

    /** Registra un problema de correo en logs/<alias>/emails-errores.log */
    public static function error($mensaje, $asunto = '', $to = '') {
        self::escribir('emails-errores.log', "[" . date('Y-m-d H:i:s') . "] A: $to | Asunto: $asunto | $mensaje\n");
    }

    private static function escribir($archivo, $texto) {
        $carpeta = dirname(__DIR__) . '/logs/' . (defined('alias') ? alias : 'general');
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0775, true);
        }
        @file_put_contents($carpeta . '/' . $archivo, $texto, FILE_APPEND | LOCK_EX);
    }
}
