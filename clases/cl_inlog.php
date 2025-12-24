<?php

class cl_inlog
{
		var $URL = "https://www.softwarecristal.com/web/api/";
		var $TrackUrl = "https://app.beetrack.com/api/external/v1/dispatches/";
		var $msgError = "";
		
		var $idCliente, $token;

        public function __construct($pcod_sucursal=null)
		{
			$this->getTokens($pcod_sucursal);
		}

		public function getTokens($cod_sucursal){
		    $query = "SELECT * FROM tb_inlog_sucursal 
                WHERE cod_sucursal = $cod_sucursal
                AND estado = 'A' limit 0,1";
			$resp = Conexion::buscarRegistro($query);
			if($resp){
			    $this->URL .= '?token='.$resp['token'];
            	$this->idCliente = $resp['idCliente'];
			}else{
			    
			}
		}

		public function GuiaTracking($idOrden){
			if($this->idCliente == ""){
    	        $this->msgError = "Empresa no tiene configurado Inlog o esta fuera de servicio";
    	        return false;
    	    }
			
			$token = $this->idCliente.str_pad($idOrden, 8, "0", STR_PAD_LEFT);
			//$token = "81332153";
			$endpoint = $this->TrackUrl.$token."?histories=true";
			$ch = curl_init($endpoint);
		    $headers = array();
		    $headers[] = 'Content-Type: application/json';
		    $headers[] = 'X-AUTH-TOKEN: ff2505f4ceaff0435ac9de96a79f563a3b29a866fdfae8a5f70d2451ea5f162c';
		  
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
		    curl_setopt($ch, CURLOPT_HTTPHEADER,$headers); 
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    $response = curl_exec($ch);
		    
		    if($response === false){
                $this->msgError = "Curl error: " . curl_error($ch);
                return false;
            }
		    curl_close($ch);
		    return json_decode($response,true);
		}
}
?>