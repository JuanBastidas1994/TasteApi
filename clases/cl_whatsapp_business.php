<?php

class cl_ultramsg
{
        var $URL = "https://graph.facebook.com/v19.0/";
        var $token = 'EAAIbomSTwGoBOyMCYBywu1QUIgXik854Ngp8ZCzgdfHqbXyAxe5ozikIKrO9EBYyJOp5AvW2FQXDcxMX7JXrcu01LJRzOtaueSxFISA4HdqqCKMEu8h61aIsUNuxgJYP5YRLTeuEXngB5ER4nLwHm56NzCMyOtU43EjCBLZCmdZCLqPZB2g0Xv2eZATeQUTJKyP5oGH9IDmDsT83VbKqPoUUhFxsZD';
        //TEST
        var $headers = [];
		
		public function __construct()
		{
            $this->headers[] = 'Content-Type: application/json';
            $this->headers[] = "Authorization: Bearer $this->token";
		}
		
		/*ENVIAR MENSAJES*/
		public function sendOTP($phone, $otp, $priority=10)
        {
            $params = [
                "messaging_product" => "whatsapp",
                "to" => $phone,
                "type" => "template",
                "template" => [
                    "name" => "otp_code",
                    "language" => [
                    "code" => "es_EC"
                    ],
                    "components" => [
                    [
                        "type" => "body",
                        "parameters" => [
                        [
                            "type" => "text",
                            "text" => (string) $otp
                        ]
                        ]
                    ],
                    [
                        "type" => "button",
                        "sub_type" => "otp",
                        "index" => "0",
                        "parameters" => [
                            [
                                "type" => "payload",
                                "payload" => (string) $otp
                            ]
                        ]
                    ]
                    ]
                ]
            ];
            $ch = curl_init($this->URL.'/messages');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));  
            curl_setopt($ch, CURLOPT_HTTPHEADER,$this->headers);  
            $response = curl_exec($ch);
            curl_close($ch);
            return json_decode($response, true);
        }
        
        
        function getChatByOffice($cod_sucursal){
            $query = "SELECT * FROM tb_telegram_sucursal WHERE estado = 'ACTIVO' AND cod_sucursal = $cod_sucursal";
            return Conexion::buscarRegistro($query);
        }
}
?>