<?php

class cl_ultramsg
{
        var $URL = "https://api.ultramsg.com/";
        //PROD
        // var $instance = "instance90285";
        // var $token = "8291rhvd0saedj7r";
        
        //TEST
        var $instance = "instance89505";
        var $token = "euz272ib029lfh5f";
        var $headers = [];
		
		public function __construct()
		{
            $this->URL = $this->URL.$this->instance;
            $this->headers[] = 'content-type: application/x-www-form-urlencoded';
		}
		
		public function setInstance($instance, $token){
		    $this->instance = $instance;
		    $this->token = $token;
		    $this->URL = "https://api.ultramsg.com/".$instance;
		}
		
		/*ENVIAR MENSAJES*/
		public function sendMessage($phone, $text, $priority=10)
        {
            $params = [
                'token' => $this->token,
                'to' => $phone,
                'body' => $text,
                'priority' => $priority
            ];
            $ch = curl_init($this->URL.'/messages/chat');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));  
            curl_setopt($ch, CURLOPT_HTTPHEADER,$this->headers);  
            $response = curl_exec($ch);
            curl_close($ch);
        }
        
		public function sendOTP($phone, $code, $priority=0)
        {
            $params = [
                'token' => $this->token,
                'to' => $phone,
                'body' => 'Bienvenido a '.name_site.', Tu código de acceso es: '.$code,
                'priority' => $priority
            ];
            $ch = curl_init($this->URL.'/messages/chat');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));  
            curl_setopt($ch, CURLOPT_HTTPHEADER,$this->headers);  
            $response = curl_exec($ch);
            curl_close($ch);
            return json_decode($response, true);
        }
        
        public function sendVideo($phone, $url, $text="", $priority=0)
        {
            $params = [
                'token' => $this->token,
                'to' => $phone,
                'video' => $url,
                'caption' => $text,
                // 'priority' => $priority
            ];
            $ch = curl_init($this->URL.'/messages/video');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));  
            curl_setopt($ch, CURLOPT_HTTPHEADER,$this->headers);  
            $response = curl_exec($ch);
            curl_close($ch);
            return json_decode($response, true);
        }
        
        public function sendImage($chat_id, $url, $subtitle="")
        {
            $json = ['chat_id'      => $chat_id,
                     'photo'        => $url,
                     'caption'      => $subtitle];
                     
            $ch = curl_init($this->URL.'/sendPhoto');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
        
        public function sendContact($chat_id, $phone_number, $name)
        {
            $json = ['chat_id'          => $chat_id,
                     'phone_number'     => $phone_number,
                     'first_name'       => $name];
                     
            $ch = curl_init($this->URL.'/sendContact');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
        
        public function sendPoll($chat_id, $question, $options) //ENCUESTA
        {
            $json = ['chat_id'          => $chat_id,
                     'question'     => $question,
                     'options'       => $options,
                     'is_anonymous'     => false];
                     
            $ch = curl_init($this->URL.'/sendPoll');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
        
        public function sendMediaGroup($chat_id, $galery)
        {
            $json = ['chat_id'          => $chat_id,
                     'media'     => $galery];
                     
            $ch = curl_init($this->URL.'/sendMediaGroup');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }



        public function sendLocation($chat_id, $latitud, $longitud)
        {
            global $URL;
            $json = ['chat_id'       => $chat_id,
                     'latitude'     => $latitud,
                     'longitude'    => $longitud];
                     
            $ch = curl_init($this->URL.'/sendLocation');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
        
        /*BOT*/
        public function addURLtoBot($url){
            //$link = "https://api.telegram.org/bot".$token."/setWebhook?url=".$url;
            
            $ch = curl_init($this->URL."/setWebhook?url=".$url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }

        public function getURLtoBot(){
            //$link = "https://api.telegram.org/bot".$token."/getWebhookInfo";
            $ch = curl_init($this->URL."/getWebhookInfo");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
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