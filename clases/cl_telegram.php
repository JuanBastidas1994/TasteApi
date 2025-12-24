<?php

class cl_telegram
{
        var $URL = "https://api.telegram.org/bot";
        var $token = "5622946611:AAHBz99L0pAmOeppF3RSyNW9agQ1TLJqEqY"; //tasteordenes_bot
        var $chat_id = "";
		
		public function __construct()
		{
            //$this->token = $ptoken;
            $this->URL = $this->URL.$this->token;
		}
		
		/*ENVIAR MENSAJES*/
		public function sendMessage($chat_id, $text)
        {
            $json = ['chat_id'       => $chat_id,
                     'text'          => $text,
                     'parse_mode'    => 'HTML'];
                     
            $ch = curl_init($this->URL.'/sendMessage');
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
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
        
        public function sendOrder($chat_id, $html, $callback_data = null, $button_text = "Ver más detalles")
        {
            $json = ['chat_id'       => $chat_id,
                     'text'          => $html,
                     'parse_mode'    => 'HTML'];
            if ($callback_data) {
                $json['reply_markup'] = json_encode([
                    'inline_keyboard' => [[
                        [
                            'text' => $button_text,
                            'callback_data' => $callback_data
                        ]
                    ]]
                ]);
            }
                     
            $ch = curl_init($this->URL.'/sendMessage');
            
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
        
        public function getChatsAvailables($cod_sucursal = 0){
            $cod_empresa = cod_empresa;
            $query = "SELECT tu.*, u.nombre, u.cod_rol, u.cod_sucursal
                    FROM tb_telegram_usuarios tu
                    INNER JOIN tb_usuarios u 
                        ON tu.cod_usuario = u.cod_usuario
                        AND u.cod_empresa = $cod_empresa
                        AND u.estado = 'A'
                        AND tu.estado = 'A'
                        AND tu.chat_id IS NOT NULL
                        AND (
                            u.cod_rol <> 3 OR (u.cod_rol = 3 AND u.cod_sucursal = $cod_sucursal)
                        )";
            return Conexion::buscarVariosRegistro($query, NULL);
        }
        
        
        function getChatByOffice($cod_sucursal){
            $query = "SELECT * FROM tb_telegram_sucursal WHERE estado = 'ACTIVO' AND cod_sucursal = $cod_sucursal";
            return Conexion::buscarRegistro($query);
        }
}
?>