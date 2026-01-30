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
    if(cod_empresa == 204){
        sendMessageWhatsappVideo($orden);
    }
	
	//Enviar mensajes por telegram al administrador
	sendMessageTelegram($orden);
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
	$texto = "Gracias por apoyar a una empresa familiar 400 Grados. Estamos preparando tu pedido.";
	
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