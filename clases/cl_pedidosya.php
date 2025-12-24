<?php
class cl_pedidosya {
    var $URL = "https://courier-api.pedidosya.com/v3";
    var $cod_empresa, $cod_sucursal, $ambiente, $token, $estado;
    var $isTest;
    var $street, $city, $latitude, $longitude;

    public function __construct($pCodSucursal=0) {
        $this->cod_sucursal = $pCodSucursal;
        $this->getToken();
    }

    public function getToken() {
        $query = "SELECT * FROM tb_pedidosya_sucursales 
                    WHERE cod_sucursal = $this->cod_sucursal
                    AND estado = 'A' 
                    LIMIT 0,1";
        $resp = Conexion::buscarRegistro($query);
        if($resp) {
            $this->token = $resp["token"];
            $this->isTest = false;
            if($resp["ambiente"] == "developmet")
                $this->isTest = true;
        } 
    }

    public function boolToString($value) {
        if($value)
            return "true";
        return "false";
    }

    public function getCoverage($latitude, $longitude) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->URL . 'estimate/coverage',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "waypoints": [
                    {
                        "addressStreet": "'.$this->street.'",
                        "city": "'.$this->city.'",
                        "latitude": '.$latitude.',
                        "longitude": '.$longitude.',
                        "type": "PICK_UP"
                    }
                ]
            }',
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
                
        return json_decode($response);
    }

    public function getEstimates($office, $latitude, $longitude) {
        $json = '{
                    "referenceId": "'.$office["cod_sucursal"].'",
                    "isTest": '.$this->boolToString($this->isTest).',
                    "items": [
                        {
                            "type": "STANDARD",
                            "value": 1,
                            "description": "Comida preparada",
                            "quantity": 1,
                            "volume": 3,
                            "weight": 1
                        }
                    ],
                    "waypoints": [
                        {
                            "type": "PICK_UP",
                            "addressStreet": "'.$office["direccion"].'",
                            "addressAdditional": "",
                            "city": "Guayaquil",
                            "latitude": '.$office["latitud"].',
                            "longitude": '.$office["longitud"].',
                            "phone": "'.$office["telefono"].'",
                            "name": "'.$office["nombre"].'",
                            "instructions": ""
                        },
                        {
                            "type": "DROP_OFF",
                            "latitude": '.$latitude.',
                            "longitude": '.$longitude.',
                            "addressStreet": "Direccion del cliente",
                            "addressAdditional": "",
                            "city": "Guayaquil",
                            "phone": "+5939999999",
                            "name": "Cliente",
                            "instructions": ""
                        }
                    ]
                }';

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->URL . '/shippings/estimates',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->token,
                'Content-Type: application/json'
            ),
        ));
        
        $response = curl_exec($curl);
        curl_close($curl);          
        
        // file_put_contents('jsonpedidosya.log', $json);

        return json_decode($response, true);
    }

    public function createOrder($sucursal, $orden) {
        $total = number_format($orden["total"], 2);
        $json = '{
                    "referenceId": "'.$orden['id'].'",
                    "isTest": '.$this->boolToString($this->isTest).',
                    "notificationMail": "'.$orden['usuario']['correo'].'",
                    "items": [
                        {
                            "type": "STANDARD",
                            "value": '.$total.',
                            "description": "Comida preparada",
                            "quantity": 1,
                            "volume": 3,
                            "weight": 1
                        }
                    ],
                    "waypoints": [
                        {
                            "type": "PICK_UP",
                            "addressStreet": "'.$sucursal['direccion'].'",
                            "addressAdditional": "'.$sucursal['direccion'].'",
                            "city": "Guayaquil",
                            "latitude": '.$sucursal['latitud'].',
                            "longitude": '.$sucursal['longitud'].',
                            "phone": "'.$sucursal['telefono'].'",
                            "name": "'.$sucursal['nombre'].'",
                            "instructions": "El ascensor esta roto."
                        },
                        {
                            "type": "DROP_OFF",
                            "latitude": '.$orden['latitud'].',
                            "longitude": '.$orden['longitud'].',
                            "addressStreet": "'.$orden['direccion'].'",
                            "addressAdditional": "'.$orden['referencia2'].'",
                            "city": "Guayaquil",
                            "phone": "'.$orden['usuario']['telefono'].'",
                            "name": "'.$orden['usuario']['nombre'].'",
                            "instructions": "Entregar en mano"
                        }
                    ]
                }';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->URL . '/shippings',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        file_put_contents("LogsPedidosYa.log", PHP_EOL . $json . PHP_EOL . $response, FILE_APPEND);
        return json_decode($response, true);
    }

    public function cancelOrder($shippingId, $reason="Cancelada por el comercio") {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->URL . '/shippings/'.$shippingId.'/cancel',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "reasonText": "'.$reason.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        
        file_put_contents("LogsPedidosYa.log", PHP_EOL . $response, FILE_APPEND);
        return json_decode($response, true);
    }

    public function tranckingOrder($shippingId) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->URL . '/shippings/'.$shippingId.'/tracking',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->token
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        return json_decode($response, true);
    }
    
    public function setOfficeToken(&$cod_pedidosya_sucursal) {
        $query = "INSERT INTO tb_pedidosya_sucursales
                    SET cod_empresa = $this->cod_empresa,
                    cod_sucursal = $this->cod_sucursal,
                    ambiente = '$this->ambiente',
                    token = '$this->token',
                    estado = 'A'";
        $resp = Conexion::ejecutar($query, null);
        if($resp)
            $cod_pedidosya_sucursal = Conexion::lastId();
        return $resp;
    }

    public function updateOfficeToken($cod_pedidosya_sucursal) {
        $query = "UPDATE tb_pedidosya_sucursales
                    SET token = '$this->token'
                    WHERE cod_pedidosya_sucursal = $cod_pedidosya_sucursal";
        return Conexion::ejecutar($query, null);
    }

    public function getOfficeEnvironment($cod_sucursal, $ambiente) {
        $query = "SELECT * 
                    FROM tb_pedidosya_sucursales
                    WHERE ambiente = '$ambiente'
                    AND cod_sucursal = $cod_sucursal";
        return Conexion::buscarRegistro($query);
    }

    function shipping_status($urlWebhook) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->URL . '/webhooks-configuration',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS =>'{
                "webhooksConfiguration": [
                    {
                        "isTest": '.$this->boolToString($this->isTest).',
                        "topic": "SHIPPING_STATUS",
                        "notificationType": "WEBHOOK",
                        "urls": [
                            {
                                "url": "'.$urlWebhook.'",
                                "authorizationKey": ""
                            }
                        ]
                    }
                ]
            }',
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }
}
?>