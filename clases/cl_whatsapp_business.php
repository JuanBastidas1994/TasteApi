<?php

class cl_whatsapp_business
{
    var $URL          = "https://graph.facebook.com/v19.0/";
    var $phone_number_id = '583641058173588';
    var $app_id       = '593334239477866';
    var $app_secret   = 'dea184ea9e0779348b25301d96a43ca3';
    var $token        = '';
    var $headers      = [];

    public function __construct()
    {
        $this->token      = $this->obtenerToken();
        $this->headers[]  = 'Content-Type: application/json';
        $this->headers[]  = "Authorization: Bearer {$this->token}";
    }

    private function obtenerToken()
    {
        $config = Conexion::buscarRegistro(
            "SELECT valor, fecha_expiracion FROM tb_config_api WHERE clave = 'whatsapp_token'"
        );

        if (!$config) {
            error_log('cl_whatsapp_business: No se encontró token en BD');
            return '';
        }

        // Si expira en menos de 10 días, renovar
        if (strtotime($config['fecha_expiracion']) < strtotime('+10 days')) {
            return $this->renovarToken($config['valor']);
        }

        return $config['valor'];
    }

    private function renovarToken($token_actual)
    {
        $url = "https://graph.facebook.com/v19.0/oauth/access_token"
             . "?grant_type=fb_exchange_token"
             . "&client_id={$this->app_id}"
             . "&client_secret={$this->app_secret}"
             . "&fb_exchange_token={$token_actual}";

        $data = json_decode(file_get_contents($url), true);

        if (isset($data['access_token'])) {
            Conexion::ejecutar(
                "UPDATE tb_config_api 
                 SET valor = '{$data['access_token']}', 
                     fecha_expiracion = DATE_ADD(NOW(), INTERVAL 60 DAY)
                 WHERE clave = 'whatsapp_token'", null);
            return $data['access_token'];
        }

        error_log('cl_whatsapp_business: Error renovando token - ' . json_encode($data));
        return $token_actual;
    }

    /*ENVIAR MENSAJES*/
    public function sendOTP($phone, $otp, $priority = 10)
    {
        $params = [
            "messaging_product" => "whatsapp",
            "to"                => $phone,
            "type"              => "template",
            "template"          => [
                "name"       => "otp_code",
                "language"   => ["code" => "es_EC"],
                "components" => [
                    [
                        "type"       => "body",
                        "parameters" => [
                            ["type" => "text", "text" => (string) $otp]
                        ]
                    ],
                    [
                        "type"       => "button",
                        "sub_type"   => "url",
                        "index"      => "0",
                        "parameters" => [
                            ["type" => "text", "text" => (string) $otp]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($this->URL . $this->phone_number_id . '/messages');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    function getChatByOffice($cod_sucursal)
    {
        $query = "SELECT * FROM tb_telegram_sucursal WHERE estado = 'ACTIVO' AND cod_sucursal = $cod_sucursal";
        return Conexion::buscarRegistro($query);
    }
}
?>