<?php

class cl_payphone
{
    var $URL = "";
    var $isInitialized = false;
    var $identificador = "";
    var $token = "";
    
    public function __construct($pcod_sucursal=null)
    {
        $this->getTokens($pcod_sucursal);
    }
    
    public function getTokens($cod_sucursal){
        $query =  "SELECT * 
                    FROM tb_empresa_sucursal_payphone 
                    WHERE cod_sucursal = $cod_sucursal";		
        $resp = Conexion::buscarRegistro($query);
        if($resp){
            $this->identificador = $resp["identificador"];
            $this->token = $resp["token"];
            $this->isInitialized = true;
            return;
        }
        $this->isInitialized = false;
    }

    public function approvedPayment($payPhoneId, $payPhoneClientTransactionId){
        //Preparar JSON de llamada
        $data_array =   array(
                            "id"=> (int)$payPhoneId,
                            "clientTxId"=>$payPhoneClientTransactionId 
                        );
        
        $data = json_encode($data_array);
        
        //Iniciar Llamada
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://pay.payphonetodoesposible.com/api/button/V2/Confirm");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt_array($curl, array(
        CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer ". $this->token, "Content-Type:application/json"),
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);

        if($result === false){
            $error = curl_error($curl);
            curl_close($curl);
            return [
                "success" => false,
                "error" => "cURL error",
                "detalle" => $error
            ];
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $respuesta = json_decode($result, true);

        //Verificar si la respuesta es un JSON valido
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                "success" => false,
                "error" => "JSON inválido",
                "raw" => $result
            ];
        }

        //Verificar estado de la respuesta de Payphone
        if ($httpCode !== 200) {
            return [
                "success" => false,
                "error" => "HTTP error",
                "http_code" => $httpCode,
                "respuesta" => $respuesta
            ];
        }

        if (isset($respuesta['errorCode'])) {
            return [
                "success" => false,
                "error" => "PayPhone error",
                "errorCode" => $respuesta['errorCode'],
                "message" => $respuesta['message'] ?? "",
                "data" => $respuesta
            ];
        }

        if (!isset($respuesta['transactionStatus']) || $respuesta['transactionStatus'] !== 'Approved') {
            return [
                "success" => false,
                "error" => "Transacción no aprobada",
                "statusCode" => $respuesta['statusCode'] ?? null,
                "transactionStatus" => $respuesta['transactionStatus'] ?? null,
                "data" => $respuesta
            ];
        }

        return [
            "success" => true,
            "data" => $respuesta
        ];
    }

    function existPayment($idPayphone, $idClientPayphone){
        $query = "SELECT *
                    FROM tb_orden_pagos
                    WHERE observacion = '$idPayphone'
                    AND observacion2 = '$idClientPayphone'";
        return Conexion::buscarRegistro($query);
    }
}
