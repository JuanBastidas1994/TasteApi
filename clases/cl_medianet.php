<?php

class cl_medianet
{
    const URL_DEVELOPMENT = 'https://qa-api.medianetpay.ec/app/webservice/webcheckout/rest';
    const URL_PRODUCTION = 'https://api.medianetpay.ec:4443/app/webservice/webcheckout/rest';

    var $isInitialized = false;
    var $ambiente = 'development';
    var $user = "";
    var $key = "";
    var $URL = "";

    public function __construct($pcod_sucursal = 0)
    {
        $tokens = $this->getTokens($pcod_sucursal);
        if ($tokens) {
            $this->ambiente = $tokens['ambiente'];
            $this->user = $tokens['username'];
            $this->key = $tokens['key_webservice'];
            $this->URL = ($this->ambiente == 'production') ? self::URL_PRODUCTION : self::URL_DEVELOPMENT;
            $this->isInitialized = true;
        }
    }

    public function getTokens($cod_sucursal)
    {
        $cod_sucursal = intval($cod_sucursal);
        $query = "SELECT * FROM tb_empresa_sucursal_medianet WHERE estado = 'A' AND cod_sucursal = $cod_sucursal";
        return Conexion::buscarRegistro($query);
    }

    /**
     * Genera el link de pago (WebCheckout). Devuelve ['success' => bool, 'link' => string, ...]
     * $total y $base en dólares; $ivaPercent es el porcentaje (ej. 15).
     */
    public function createLink($reference, $total, $base, $ivaPercent, $description, $urlBack, $urlRedirect)
    {
        $payload = [
            "currency" => "USD",
            "description" => mb_substr($description, 0, 200),
            "iva" => $ivaPercent,
            "key_webservice" => $this->key,
            "reference" => $reference,
            "url_back" => $urlBack,
            "url_redirect" => $urlRedirect,
            "username" => $this->user,
            "value" => $total,
            "value_base_not_iva" => $base
        ];

        $curl = curl_init($this->URL);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $result = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Nunca registrar key_webservice
        $logPayload = $payload;
        $logPayload['key_webservice'] = '***';
        logAdd(json_encode(['request' => $logPayload, 'http' => $httpCode, 'response' => $result, 'curl_error' => $error]), "createLink", "medianet");

        if ($result === false) {
            return ["success" => false, "error" => "No se pudo conectar con Medianet: $error"];
        }

        $respuesta = json_decode($result, true);
        if ($httpCode !== 200 || !is_array($respuesta) || empty($respuesta['link'])) {
            return ["success" => false, "error" => "Medianet no devolvió el link de pago", "http_code" => $httpCode, "respuesta" => $respuesta];
        }

        return ["success" => true, "link" => $respuesta['link']];
    }

    /** Valida la IP de origen del callback. Lista vacía = sin validación (QA/local). */
    public static function isAllowedIp($ip)
    {
        $allowed = array_filter(array_map('trim', explode(',', MEDIANET_ALLOWED_IPS)));
        if (count($allowed) == 0) return true;
        return in_array($ip, $allowed, true);
    }

    public static function isApproved($response)
    {
        return mb_strtolower(trim((string)$response)) === 'aprobada';
    }
}
