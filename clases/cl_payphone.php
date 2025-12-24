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
        curl_close($curl);
        
        //En la variable result obtienes todos los parámetros de respuesta
        $return["respuesta_payphone"] = json_decode($result, true);
        return $return;
    }

    function existPayment($idPayphone, $idClientPayphone){
        $query = "SELECT *
                    FROM tb_orden_pagos
                    WHERE observacion = '$idPayphone'
                    AND observacion2 = '$idClientPayphone'";
        return Conexion::buscarRegistro($query);
    }
}
