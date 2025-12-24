<?php

class cl_datafast
{
        var $Isinitialize = true;
        var $URL = "https://test.oppwa.com/v1/checkouts";
        var $ambiente = "development";
        var $fase = "FASE1";
        var $api = "";
        var $entityId = "";
        var $customParameters = "";
        var $MID = "";
        var $TID = "";
        var $msgError = "";
        
		public function __construct($pcod_sucursal=null)
		{
		    if($pcod_sucursal !== null){
		        $this->getTokensByOffice($pcod_sucursal);
		    }else{
			    $this->getTokens();
		    }
		}
		
		public function getTokens(){
		    $query =  "SELECT * FROM tb_empresa_datafast WHERE cod_empresa = ".cod_empresa;		
			$resp = Conexion::buscarRegistro($query);
			if($resp){
			    $cp = $resp['mid']."_".$resp['tid'];
			    $this->api = 'Bearer '.$resp['api'];
			    $this->entityId = $resp['entityId'];
			    $this->MID = $resp['mid'];
			    $this->TID = $resp['tid'];
			    $this->customParameters = $cp;
			    $this->fase = $resp['fase'];
			    $this->ambiente = $resp['ambiente'];
			    if($resp['ambiente'] == "development")
			        $this->URL = "https://eu-test.oppwa.com/v1/";
    			else    
    			    $this->URL = "https://eu-prod.oppwa.com/v1/";
    			$this->Isinitialize = true;    
			}else{
			    $this->Isinitialize = false;
			}
		}
		
		public function getTokensByOffice($office_id){
		    $query =  "SELECT * FROM tb_empresa_sucursal_datafast WHERE estado = 'A' AND cod_sucursal = $office_id";		
			$resp = Conexion::buscarRegistro($query);
			if($resp){
			    $cp = $resp['mid']."_".$resp['tid'];
			    $this->api = 'Bearer '.$resp['api'];
			    $this->entityId = $resp['entityId'];
			    $this->MID = $resp['mid'];
			    $this->TID = $resp['tid'];
			    $this->customParameters = $cp;
			    $this->fase = $resp['fase'];
			    $this->ambiente = $resp['ambiente'];
			    if($resp['ambiente'] == "development")
			        $this->URL = "https://eu-test.oppwa.com/v1/";
    			else    
    			    $this->URL = "https://eu-prod.oppwa.com/v1/";
    			$this->Isinitialize = true;    
			}else{
			    $this->Isinitialize = false;
			}
		}
		
	//GET TOKEN EN DESARROLLO FASE 1
		public function getTransactionFase1($total, &$data){
    		$data['entityId'] = $this->entityId;
    		$data['amount'] = number_format($total, 2);
    		$data['currency'] = "USD";
    		$data['paymentType'] = "DB";
    		
    		$data = http_build_query($data);
    		
    		$ch = curl_init("https://eu-test.oppwa.com/v1/checkouts");
            $headers = array();
            $headers[] = 'Authorization: '.$this->api;
          
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);      
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);   
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            if(curl_errno($ch)){
            	return curl_errno($ch);
            }
            curl_close($ch);
            return json_decode($response,true);
    	}
    	
    	
    //GET TOKEN EN PRODUCCION O DESARROLLO FASE 2
    	public function getTransactionProduction($usuario, $total, $ip, &$data){
        	$customParameters = $this->customParameters;
        	
        	if($this->ambiente == "development"){
        	    $data['testMode'] = "EXTERNAL";
        	}
        	
        	$total = number_format($total, 2);
        	$base0 = number_format(0, 2, ".","");
        	$base12 = number_format($total / 1.15, 2, ".","");
        	$iva = number_format($total - $base12, 2, ".","");
        	
        	$data['entityId'] = $this->entityId;
        	$data['amount'] = $total;
        	$data['currency'] = "USD";
        	$data['paymentType'] = "DB";
        	$data['merchantTransactionId'] = $this->generateTransactionId($usuario['cod_usuario']);

        	/*INFORMACION USUARIO*/
        	$firstName = $usuario['nombre'];
        	$secondName = "";
        	$nombre = explode(" ",$usuario['nombre'],2);
        	if(isset($nombre[1])){
        		$firstName = $nombre[0];
        		$secondName = $nombre[1];
        	}
        
        	$data['customer.givenName'] = $firstName;
        	$data['customer.middleName'] = $secondName;
        	$data['customer.surname'] = $secondName;
        	$data['customer.merchantCustomerId'] = $usuario['cod_usuario'];
        	$data['customer.email'] = $usuario['correo'];
        	$data['customer.phone'] = $usuario['telefono'];
        	$data['customer.identificationDocType'] = "IDCARD";
        	//$data['customer.identificationDocId'] = substr($usuario['num_documento'], 0, 10);
        	//$data['customer.identificationDocId'] = substr('0952423606', 0, 10);
        	$data['customer.identificationDocId'] = str_pad($usuario['cod_usuario'], 10, '0', STR_PAD_LEFT);
        	$data['customer.ip'] = $ip;
        	
        	/*INFORMACION DE LOS PRODUCTOS*/
        	$data['cart.items[0].name'] = "Compra en linea";
        	$data['cart.items[0].description'] = "Compra en linea";
        	$data['cart.items[0].price'] = $base12;
        	$data['cart.items[0].quantity'] = 1;
        	
        	/*INFORMACION DE UBICACION*/
        	$data['billing.street1'] = "Alborada 13ava etapa";
        	$data['billing.country'] = "EC";
        	$data['shipping.street1'] = "Alborada 13ava etapa";
        	$data['shipping.country'] = "EC";
        	
        	/*DATOS ADICIONALES*/
        	$data['risk.parameters[USER_DATA2]'] = alias;
        	$data['customParameters[SHOPPER_VAL_BASE0]'] = $base0;
        	$data['customParameters[SHOPPER_VAL_BASEIMP]'] = $base12;
        	$data['customParameters[SHOPPER_VAL_IVA]'] = $iva;
        	$data['customParameters[SHOPPER_MID]'] = $this->MID;
        	$data['customParameters[SHOPPER_TID]'] = $this->TID;
        	$data['customParameters[SHOPPER_ECI]'] = '0103910';
        	$data['customParameters[SHOPPER_PSERV]'] = '17913101';
        	$data['customParameters[SHOPPER_VERSIONDF]'] = 2;
        	
        	$json = json_encode($data);
        	$data = http_build_query($data);
        	
        	mylog($data, "SEND_COBRO_DATAFAST");

        	$ch = curl_init($this->URL."checkouts");
            $headers = array();
            $headers[] = 'Authorization: '.$this->api;
          
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            if(curl_errno($ch)){
            	return curl_errno($ch);
            }
            curl_close($ch);
            return json_decode($response,true);
        }
        
        
        public function debitar($id){
        	$url = $this->URL."checkouts/".$id."/payment?entityId=".$this->entityId;
        	$ch = curl_init($url);
            $headers = array();
            $headers[] = 'Authorization: '.$this->api;
          
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");                                                                     
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);      
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);   
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            if(curl_errno($ch)){
            	return curl_errno($ch);
            }
            curl_close($ch);
            return json_decode($response,true);
        }
        
        public function getDebitCodeSuccess(){
            $codeSuccess = "000.100.112"; //FASE 2 DEVELOPMENT
        	if($this->ambiente == "production")
        	    $codeSuccess = "000.000.000";   //PRODUCCION
        	else if($this->ambiente == "FASE1"){
        	    $codeSuccess = "000.100.110"; //FASE 1 DEVELOPMENT
        	}
        	
        	return $codeSuccess;
        }
    	
    	//FUNCIONES ADICIONALES
        function generateTransactionId($cod_usuario){
            date_default_timezone_set('America/Guayaquil');
            $transaction = $cod_usuario.date("YmdHis", time());
        	return str_pad($transaction, 10, '0', STR_PAD_LEFT);
        }
}
?>