<?php

class cl_gacela
{
		var $URL = "https://gacela.dev/api/";
		var $apiKey = "";
		var $tokenSucursal = "";
		var $msgError = "";
		public function __construct($pcod_sucursal=null)
		{
			$this->getTokens($pcod_sucursal);
		}
		
		public function getTokens($cod_sucursal){
		    /*$query = "SELECT gs.cod_empresa, gs.cod_sucursal, gs.api, gs.token, gs.ambiente, s.nombre
						FROM tb_gacela_sucursal gs, tb_sucursales s
						WHERE gs.cod_sucursal = s.cod_sucursal
						AND gs.cod_sucursal = $cod_sucursal
						AND gs.cod_empresa = ".cod_empresa."
						AND gs.estado = 'A'";*/
			$query = "SELECT gs.cod_empresa, gs.cod_sucursal, gs.api, gs.token, gs.ambiente, s.nombre
						FROM tb_gacela_sucursal gs, tb_sucursales s
						WHERE gs.cod_sucursal = s.cod_sucursal
						AND gs.cod_sucursal = $cod_sucursal
						AND gs.estado = 'A'";
			$resp = Conexion::buscarRegistro($query);
			if($resp){
			    $this->apiKey = $resp['api'];
			    $this->tokenSucursal = $resp['token'];
			    if($resp['ambiente'] == "development")
			    $this->URL = "https://gacela.dev/api/";
    			else    
    			    $this->URL = "https://gacela.co/api/";
			}else{
			    
			}
		}

		public function cobertura($latitud, $longitud){
			$data['api_token'] = $this->tokenSucursal;
			$data['destination_latitude'] = $latitud;
			$data['destination_longitude'] = $longitud;
			$json = json_encode($data);

			$ch = curl_init($this->URL."tracking/coverage");
		    $headers = array();
		    $headers[] = 'Content-Type: application/json';
		    $headers[] = 'Authorization: Bearer '.$this->apiKey;
		  
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");    
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);                                                                 
		    curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);      
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
		    $response = curl_exec($ch);
		    curl_close($ch);
		    return json_decode($response);
		}

		public function costoCarrera($latitud, $longitud){
			$data['api_token'] = $this->tokenSucursal;
			$data['destination_latitude'] = $latitud;
			$data['destination_longitude'] = $longitud;
			$json = json_encode($data);

			$ch = curl_init($this->URL."v2/tracking/fare");
		    $headers = array();
		    $headers[] = 'Content-Type: application/json';
		    $headers[] = 'Authorization: Bearer '.$this->apiKey;
		  
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");    
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);                                                                 
		    curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);      
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
		    $response = curl_exec($ch);
		    curl_close($ch);
		    
		    logAdd(json_encode($json),"json-solicitud-precio","gacela");
		    return json_decode($response);
		}
		
		public function trackingOrder($token){
			$ch = curl_init($this->URL."order_tracking/".$token);
		  
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");    
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
		    $response = curl_exec($ch);
		    curl_close($ch);
		    return json_decode($response, true);
		}
}
?>