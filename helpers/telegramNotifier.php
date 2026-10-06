<?php
/**
 * Aviso por Telegram de una orden nueva a los administradores de la empresa (y al admin de la sucursal).
 * Solo si la empresa tiene el permiso NOTIFY_TELEGRAM. Ver clases/cl_telegram.php.
 * Nunca lanza excepciones ni retrasa la orden más de unos segundos si Telegram no responde.
 */
function notificarTelegramNuevaOrden($orden)
{
    try {
        require_once __DIR__ . "/../clases/cl_telegram.php";
        $telegram = new cl_telegram();

        if (!$telegram->empresaNotifica(cod_empresa)) {
            return false;
        }
        $chats = $telegram->chatsEmpresa(cod_empresa, $orden['cod_sucursal']);
        if (count($chats) == 0) {
            return false;
        }

        $texto = telegramTextoOrden($orden, "Nuevo pedido en " . $orden['sucursal'] . " (#" . $orden['cod_orden'] . ")");
        $enviados = 0;
        foreach ($chats as $chat_id) {
            if ($telegram->sendMessage($chat_id, $texto)) {
                $enviados++;
            }
        }
        return $enviados > 0;
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/../logs/' . (defined('alias') ? alias : 'general') . '/telegram-errores.log',
            '[' . date('Y-m-d H:i:s') . '] notificarTelegramNuevaOrden: ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        return false;
    }
}

// Texto (HTML de Telegram) con el resumen de la orden. Todo dato variable se escapa.
function telegramTextoOrden($orden, $titulo)
{
    $esc = function ($t) {
        return htmlspecialchars((string) $t, ENT_NOQUOTES, 'UTF-8');
    };
    $tipo = ($orden['is_envio'] == 1) ? "Delivery" : "Pickup";
    $emoji = ($orden['is_envio'] == 1) ? '🛵' : '📦';
    $entrega = ($orden['is_programado']) ? dateTimeLatino($orden['hora_retiro']) : "Ahora";

    $texto = "<b>" . $esc($titulo) . "</b>\n";
    $texto .= "Cliente: <i>" . $esc(trim($orden['nombre'] . ' ' . $orden['apellido'])) . "</i>\n";
    $texto .= "Total: <b>$" . $esc($orden['total']) . "</b>\n";
    $texto .= "$emoji $tipo, Entrega: " . $esc($entrega) . "\n";

    $emojisPago = ['E' => '💵', 'T' => '💳', 'TB' => '🏦'];
    foreach ((array) $orden['pagos'] as $pago) {
        $emojiPago = isset($emojisPago[$pago['id']]) ? $emojisPago[$pago['id']] : '❓';
        $texto .= "$emojiPago " . $esc($pago['nombre']) . ": $" . $esc($pago['monto']) . "\n";
    }
    return $texto;
}
