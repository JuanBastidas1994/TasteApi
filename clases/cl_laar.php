<?php

class cl_laar
{
		var $URL = "https://api.laarcourier.com:9727/";
		var $cod_empresa = "";
		var $cod_sucursal = "";
		var $msgError = "";
		var $ciudadOrigen = "";
		
        var $IsInitialized = false;
		var $username, $password, $API;
		
		public function __construct($pcod_empresa=null, $pcod_sucursal=null)
		{
		    if($pcod_empresa != null)
			    $this->cod_empresa = $pcod_empresa;
			    
			if($pcod_sucursal != null)
			    $this->cod_sucursal = $pcod_sucursal;
			
			$this->URL = "https://api.laarcourier.com:9727/";
			if($this->cod_empresa != "" && $this->cod_sucursal != ""){
			    $this->getCredentials();
			    $this->getCiudadOrigen();
			    
			}
		}
		
		public function getCredentials()
		{
    		$cod_empresa = $this->cod_empresa;
    		$cod_sucursal = $this->cod_sucursal;
    		$query = "SELECT * FROM tb_laar_sucursal WHERE cod_empresa = $cod_empresa AND cod_sucursal = $cod_sucursal";
    		$resp = Conexion::buscarRegistro($query);
    		if($resp){
    			$this->username = $resp['username'];
    			$this->password = $resp['password'];
    			$this->getToken();
    		}
    	}
    	
    	public function getToken(){
    	    $data['username'] = $this->username;
    	    $data['password'] = $this->password;
            $json = json_encode($data);
            
            $link = $this->URL.'authenticate';
            $ch = curl_init($link);
            
            $headers = array();
            $headers[] = 'Content-Type: application/json';
    
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
            $info = json_decode($response,true);
            if(isset($info['token']))
                $this->API = $info['token'];
            else{
                $this->msgError = $info['Message'];
                return false;
            }
            $this->IsInitialized = true;
            return true;
    	}
    	
    	public function getCiudadOrigen(){
    	    $query = "SELECT c.codigo FROM tb_sucursales s, tb_ciudades c WHERE c.cod_ciudad = s.cod_ciudad AND s.cod_sucursal = $this->cod_sucursal";
    		$resp = Conexion::buscarRegistro($query);
    		if($resp){
    			$this->ciudadOrigen = $resp['codigo'];
    		}
    	}

        public function getCodeCiudadById($id){
            $query = "SELECT c.codigo FROM tb_ciudades c WHERE c.cod_ciudad = $id";
    		$resp = Conexion::buscarRegistro($query);
    		if($resp)
    			return $resp['codigo'];
    		else
                return false;
        }

		public function DataGuia($token){
			if($this->API == ""){
    	        $this->msgError = "Empresa no tiene configurado Laar o esta fuera de servicio";
    	        return false;
    	    }
    	    
    	    if($this->ciudadOrigen == ""){
    	        $this->msgError = "La sucursal no tiene definida una ciudad origen";
    	        return false;
    	    }

			$ch = curl_init($this->URL."guias/v2/".$token);
		    $headers = array();
		    $headers[] = 'Content-Type: application/json';
		    $headers[] = 'Authorization: Bearer '.$this->API;
		  
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

		public function GuiaTracking($token){
			if($this->API == ""){
    	        $this->msgError = "Empresa no tiene configurado Laar o esta fuera de servicio";
    	        return false;
    	    }
    	    
    	    if($this->ciudadOrigen == ""){
    	        $this->msgError = "La sucursal no tiene definida una ciudad origen";
    	        return false;
    	    }

			$ch = curl_init($this->URL."guias/".$token."/tracking");
		    $headers = array();
		    $headers[] = 'Content-Type: application/json';
		    $headers[] = 'Authorization: Bearer '.$this->API;
		  
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
		
		public function costoEnvio($ciudadDestino, $piezas, $peso, &$data = null){
		    if($this->API == ""){
    	        $this->msgError = "Empresa no tiene configurado Laar o esta fuera de servicio";
    	        return false;
    	    }
    	    
    	    if($this->ciudadOrigen == ""){
    	        $this->msgError = "La sucursal no tiene definida una ciudad origen";
    	        return false;
    	    }
    	    
			$data['codigoServicio'] = 201202002002013;
			$data['codigoCiudadOrigen'] = $this->ciudadOrigen;
			$data['codigoCiudadDestino'] = $ciudadDestino;
			//$data['piezas'] = $piezas;
			$data['piezas'] = 1;
			$data['peso'] = floatval($peso);
			$json = json_encode($data);

			$ch = curl_init($this->URL."cotizadores/tarifa/normal");
		    $headers = array();
		    $headers[] = 'Content-Type: application/json';
		    $headers[] = 'Authorization: Bearer '.$this->API;
		  
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
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