<?php

require "./httpClient.php";

class cl_paymentez {

    var $URL;
    var $SERVER_APPLICATION_CODE, $SERVER_APP_KEY;
    var $paymentezApi;

    public function __construct($pcod_sucursal=0)
    {
        $tokens = $this->getTokens($pcod_sucursal);
        if($tokens){
            
            $stg = $tokens['ambiente'];
            $this->URL = ($stg == "production") ? 'https://ccapi.paymentez.com/v2/' : 'https://ccapi-stg.paymentez.com/v2/';
            $this->SERVER_APPLICATION_CODE = $tokens['server_code'];
            $this->SERVER_APP_KEY = $tokens['server_key'];
    
            $this->paymentezApi = new HttpClient(['Content-Type: application/json']);
        }
    }

    public function getTokens($cod_sucursal){
        $query =  "SELECT * FROM tb_empresa_sucursal_paymentez WHERE cod_sucursal = ".$cod_sucursal;		
		$resp = Conexion::buscarRegistro($query);
		if($resp) return $resp;

	    $query =  "SELECT * FROM tb_empresa_paymentez WHERE cod_empresa = ".cod_empresa;		
		$resp = Conexion::buscarRegistro($query);
		if($resp) return $resp;
	}


    public function generateAuthToken(){
        $server_application_code = $this->SERVER_APPLICATION_CODE;
        $server_app_key = $this->SERVER_APP_KEY;
        $date = new DateTime();
        $unix_timestamp = $date->getTimestamp();
        $uniq_token_string = $server_app_key.$unix_timestamp;
        $uniq_token_hash = hash('sha256', $uniq_token_string);
        $auth_token = base64_encode($server_application_code.";".$unix_timestamp.";".$uniq_token_hash);
        return $auth_token;
    }

    public function get($transaction_id){
        $response = $this->paymentezApi->get($this->URL."transaction/$transaction_id", 
                    [ 'Auth-Token:'.$this->generateAuthToken() ]);
        return json_decode($response, true);
    }

    public function getCards($user_id){
        $response = $this->paymentezApi->get($this->URL."card/list?uid=$user_id", 
                    [ 'Auth-Token:'.$this->generateAuthToken() ]);
        return json_decode($response, true);
    }

    public function removeCard($user_id, $token){
        $verify = [
            "user" => [ "id" => "".$user_id ],
            "card" => [ "token" => $token ], //Id de la transacción
        ];
        $response = $this->paymentezApi->post($this->URL."card/delete/", $verify, 
        [ 'Auth-Token:'.$this->generateAuthToken() ]);
        return json_decode($response, true);
    }
    
    public function initReference($usuario, $preorder_id, $amount){
        $no_tax = $amount / 1.15;
        $tax = $amount - $no_tax;
        
        $phone = normalizarTelefono($usuario['telefono']);
        $phone = ($phone) ? '0'.substr($phone, 4) : "";

        $transaction = [
            "user" => [
                "id" => "".$usuario['cod_usuario'],
                "email" => $usuario['correo'],
                "phone" => $phone
            ],
            "order" => [
                "description" => "Compra en ". name_site." #$preorder_id",
                "dev_reference" => "".$preorder_id,
                "amount" => (float)number_format($amount,2),
                "vat" => (float)number_format($tax,2),
                "taxable_amount" => (float)number_format($no_tax,2),
                "tax_percentage" => 15,
                "installments_type" => 0// Número de cuotas: 2 con intereses, 3 sin intereses
            ],
            "conf" => [ // Configuración adicional
                "theme" => [
                    "logo" => "https://cdn.paymentez.com/img/nv/nuvei_logo.png",
                    "primary_color" => "#C800A1",
                    "secondary_color" => "#C800A1"
                ]
            ]
        ];

        $response = $this->paymentezApi->post($this->URL."transaction/init_reference/", $transaction, 
                    [ 'Auth-Token:'.$this->generateAuthToken() ]);
        logAdd($response,"init-reference","nuvei");
        return json_decode($response, true);
    }


    //3DS
    public function debitByToken3ds($usuario, $preorder_id, $token, $cvv, $amount, $callbackUrl){
        ini_set('serialize_precision', -1);
        
        $no_tax = $this->noRound($amount / 1.15);
        $tax = $this->noRound($amount - $no_tax);

        $transaction = [
            "user" => [
                "id" => "".$usuario['cod_usuario'],
                "email" => $usuario['correo']
            ],
            "card" => [
                "token" => $token,
                "cvc" => $cvv,
            ],
            "order" => [
                "description" => "Compra en ". name_site,
                "dev_reference" => "".$preorder_id,
                "amount" => $this->noRound($amount),
                "vat" => $this->noRound($tax),
                "taxable_amount" => $this->noRound($no_tax),
                "tax_percentage" => 15,
            ],
            "extra_params" => [
                "threeDS2_data" => [
                    "term_url" => $callbackUrl.'?id='.$preorder_id,
                    "device_type" => 'browser',
                    "reference_id" => '2',
                ],
                "browser_info" => [
                    "ip" => '191.99.47.213',
                    "language" => 'ES',
                    "java_enabled" => true,
                    "js_enabled" => true,
                    "color_depth" => 24,
                    "screen_height" => 1200,
                    "screen_width" => 1900,
                    "timezone_offset" => 0,
                    "user_agent" => 'Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/70.0.3538.110 Safari\/537.36',
                    "accept_header" => 'text/html',
                ],
            ],
        ];

        $response = $this->paymentezApi->post($this->URL."transaction/debit/", $transaction, 
                    [ 'Auth-Token:'.$this->generateAuthToken() ]);
        logAdd(json_encode($transaction), "transaction", "paymentez-3ds-envio");
        logAdd($response,"get-challenge","paymentez-transaction-debit");
        return json_decode($response, true);
    }
    
    public function noRound($value){
        return round($value * 100) / 100;
    }

    //Si se solicitó un desafío, se debe solicitar este punto final para validar la respuesta del desafío
    public function verifyTransaction($usuario, $transaction_id, $type, $value=''){ //Type: 35=AUTHENTICATION_CONTINUE, 36=BY_CRES
        $verify = [
            "user" => [ "id" => "".$usuario['cod_usuario'] ],
            "transaction" => [ "id" => $transaction_id ], //Id de la transacción
            "type" => $type,
            "value" => $value,
            "more_info" => true,
        ];
        $response = $this->paymentezApi->post($this->URL."transaction/verify/", $verify, 
        [ 'Auth-Token:'.$this->generateAuthToken() ]);
        logAdd($response,"verify","paymentez-transaction-verify");
        return json_decode($response, true);
    }

}
