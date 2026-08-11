<?php

	
function notifyNewOrder($order_id){
    //Enviar correo al usuario
	ExecuteRemoteQuery(url_api . "correos/orden_complete.php?alias=" . alias . "&id=$order_id");

	require_once "clases/cl_ordenes.php";
	$Clordenes = new cl_ordenes();

	$orden = $Clordenes->getOrderForNotify($order_id);
	if(!$orden) return;

	//Enviar mensaje por whatsapp al administrador
// 	sendMessageWhatsapp($orden);

    //204 es 400Grados
    if(cod_empresa == 204 || cod_empresa == 70){
        sendMessageWhatsappVideo($orden);
    }

	//Enviar mensajes por telegram al administrador
	sendMessageTelegram($orden);

	// Push al cliente confirmando que su pedido entró — getOrderForNotify() no trae cod_usuario,
	// así que se re-consulta con get() (sí lo trae) en vez de agregarlo a esa query compartida.
	notificarPedidoRecibido($Clordenes->get($order_id));
}

/**
 * Primer push al cliente en el ciclo de vida del pedido (estado ENTRANTE) — antes de esto no se
 * mandaba ninguna notificación push, solo correo/whatsapp/telegram. El resto de los estados
 * (ACEPTADA/ASIGNADA/ENVIANDO/ENTREGADA/etc) los maneja api_gestion_ordenes y api_flotas, cada
 * uno dueño de la transición que dispara — acá solo el arranque.
 */
function notificarPedidoRecibido($orden){
	if(!$orden || empty($orden['cod_usuario']) || empty($orden['cod_orden'])) return false;

	$tokens = getPushTokensCliente($orden['cod_usuario']);
	if(empty($tokens)) return false;

	return enviarExpoPushCliente($tokens, '¡Pedido recibido! 🙌', 'Recibimos tu pedido y ya lo enviamos al restaurante.', [
		'orden_id' => generarTracking($orden['cod_orden']),
		'estado'   => 'ENTRANTE',
		'type'     => 'order_tracking',
	]);
}

// Tokens Expo del cliente (puede tener varios dispositivos) — mismo patrón que
// api_gestion_ordenes/helpers/notificationsToClient.php::getPushTokensCliente().
function getPushTokensCliente($cod_usuario){
	$sql = "SELECT token FROM tb_push_tokens WHERE cod_usuario = :cod_usuario";
	$registros = Conexion::buscarVariosRegistro($sql, [':cod_usuario' => $cod_usuario]);
	if(!$registros) return [];
	return array_column($registros, 'token');
}

// Envío crudo a la API de Expo, en chunks de 100 (límite de Expo por request). Nombrada distinto
// de un posible enviarExpoPush() futuro en otro helper de este mismo repo, para no chocar.
function enviarExpoPushCliente($tokens, $titulo, $mensaje, $data = []){
	if(empty($tokens)) return false;

	$mensajes = [];
	foreach($tokens as $token){
		$mensajes[] = [
			"to"    => $token,
			"title" => $titulo,
			"body"  => $mensaje,
			"data"  => $data,
		];
	}

	$respuestas = [];
	foreach(array_chunk($mensajes, 100) as $chunk){
		$ch = curl_init("https://exp.host/--/api/v2/push/send");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"Accept: application/json",
			"Accept-Encoding: gzip, deflate",
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($chunk));
		$respuestas[] = curl_exec($ch);
		curl_close($ch);
	}

	return $respuestas;
}


function sendMessageWhatsapp($orden){
    require_once "clases/cl_empresas.php";
    $Clempresas = new cl_empresas();
    
    if(!$Clempresas->getPermiso('NOTIFY_WHATSAPP')) return false;
    
    $phone = contact_manager;
    if(strlen($phone) < 10) return false;
    
    require_once "clases/cl_ultramsg.php";
	$ClMessages = new cl_ultramsg();
	
	extract($orden);
	$tipo = ($is_envio == 1) ? "Delivery" : "Pickup";
	$entrega = ($is_programado) ? fechaLatinoShort($hora_retiro) : "Ahora";
	$texto = "*($cod_orden)* Ha ingresado un nuevo pedido *$tipo* a la sucursal *$sucursal* de *$$total* a nombre de *$nombre*. Entrega: $entrega ";
	
	$ClMessages->sendMessage(contact_manager, $text, 0);
}

function sendMessageWhatsappVideo($orden){
    require_once "clases/cl_empresas.php";
    $Clempresas = new cl_empresas();
    
    if(!$Clempresas->getPermiso('NOTIFY_WHATSAPP')) return false;
    
    $phone = $orden['telefono'];
    if(strlen($phone) < 10) return false;
    
    require_once "clases/cl_ultramsg.php";
	$ClMessages = new cl_ultramsg();
	$ClMessages->setInstance('instance150737', 'br5wrz8e57z1t166');
	
	extract($orden);
	$texto = "Gracias por apoyar a una empresa familiar 400 Grados. Estamos preparando tu pedido. ";
    if($orden['is_express'] == 0){
        $hora = intval(fechaToFormat($orden['fecha'], 'H'));
        if($hora < 12){
            $texto .= "Tu pedido será despachado entre la 1 y 6pm";
        }else{
            $texto .= "Tu pedido será entregado el día de mañana entre la 1pm y 6pm";
        }
    }
	
	$url = "https://dashboard.mie-commerce.com/videos/entrante.mp4";
	
	$ClMessages->sendVideo($phone, $url, $texto, 0);
}


function sendMessageTelegram($orden){
    
    require_once "clases/cl_empresas.php";
    $Clempresas = new cl_empresas();
    
    // global $Clempresas;
    if(!$Clempresas->getPermiso('NOTIFY_TELEGRAM')) return false;
    
    require_once "clases/cl_telegram.php";
	$clTelegram = new cl_telegram();
	
	extract($orden);
	
	$chats = $clTelegram->getChatsAvailables($cod_sucursal);
	foreach($chats as $chat){
	   // $clTelegram->sendOrder($chat['chat_id'],buildTextTelegram($orden),'orderdetail_'.$cod_orden);
	    $clTelegram->sendOrder($chat['chat_id'],buildTextTelegram($orden));
	}
    
}

function buildTextTelegram($orden){
	extract($orden);
	$tipo = ($is_envio == 1) ? "Delivery" : "Pickup";
	$emoji = ($is_envio == 1) ? '🛵' : '📦';
	$entrega = ($is_programado) ? dateTimeLatino($hora_retiro) : "Ahora";
	
	$texto = "<b>Nuevo pedido en $sucursal (#$cod_orden)</b>\n";
	$texto .= "Cliente: <i>$nombre</i>\n";
	$texto .= "Total: <b>$$total</b>\n";
	$texto .= "$emoji $tipo, Entrega: $entrega\n";
	
	foreach($pagos as $pago){
        $id = $pago['id'];
        $nombre = $pago['nombre'];
        $monto = $pago['monto'];
        switch ($id) {
            case 'E':
                $emojiPayment = '💵';
                break;
            case 'T':
                $emojiPayment = '💳';
                break;
            case 'TB':
                $emojiPayment = '🏦';
                break;
            default:
                $emojiPayment = '❓';
                break;
        }
        $texto .= "$emojiPayment $nombre: $$monto\n";
    }
    
	return $texto;
}

?>