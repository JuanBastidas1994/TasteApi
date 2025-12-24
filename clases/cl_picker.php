<?php

class cl_picker
{
		var $URL = "https://dev-api.pickerexpress.com/api/";
		var $apiKey = "";
		var $tokenSucursal = "";
		var $msgError = "";
		public function __construct($pcod_sucursal=null)
		{
			$this->getTokens($pcod_sucursal);
		}
		
		public function getTokens($cod_sucursal){
		    $query = "SELECT gs.cod_empresa, gs.cod_sucursal, gs.api, gs.ambiente, s.nombre
						FROM tb_picker_sucursal gs, tb_sucursales s
						WHERE gs.cod_sucursal = s.cod_sucursal
						AND gs.cod_sucursal = $cod_sucursal
						AND gs.cod_empresa = ".cod_empresa."
						AND gs.estado = 'A'";
			$resp = Conexion::buscarRegistro($query);
			if($resp){
			    $this->apiKey = $resp['api'];
			    if($resp['ambiente'] == "development")
			        $this->URL = "https://dev-api.pickerexpress.com/api/";
    			else    
    			    $this->URL = "https://api.pickerexpress.com/api/";
			}else{
			    
			}
		}

		public function costoCarrera($latitud, $longitud){
			$data['latitude'] = $latitud;
			$data['longitude'] = $longitud;
			$json = json_encode($data);

			$ch = curl_init($this->URL."preCheckout");
		    $headers = array();
		    $headers[] = 'Content-Type: application/json';
		    $headers[] = 'Authorization: Bearer '.$this->apiKey;
		  
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");    
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);                                                                 
		    curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);      
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
		    $response = curl_exec($ch);
            if($response === false){
                $this->msgError = "Curl error: " . curl_error($ch);
                return false;
            }

		    curl_close($ch);
		    return json_decode($response);
		}
		
		public function trackingOrder($tokenOrden){
			$ch = curl_init($this->URL."getBookingDetails?id=".$tokenOrden);
            $headers = array();
		    $headers[] = 'Content-Type: application/json';
            $headers[] = 'content-language: es';
		    $headers[] = 'Authorization: Bearer '.$this->apiKey;

		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");    
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);  
		    $response = curl_exec($ch);
		    curl_close($ch);
		    return json_decode($response, true);
		}
}
?>