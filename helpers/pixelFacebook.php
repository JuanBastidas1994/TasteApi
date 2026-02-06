<?php

function trackPurchaseServer($orderId) {

    require_once "clases/cl_ordenes.php";
	$Clordenes = new cl_ordenes();

    $orden = $Clordenes->getOrdenPixelFacebook($orderId);
	if(!$orden) return;


    $email = $orden['correo'];
    $pixelId = $orden['facebook_pixel'];
    $accessToken = $orden['facebook_pixel_verify'];

    $url = "https://graph.facebook.com/v18.0/$pixelId/events?access_token=$accessToken";

    $data = [
        'data' => [[
            'event_name' => 'Purchase',
            'event_time' => time(),
            'event_id' => $orderId, // MISMO que en el frontend
            'action_source' => 'website',
            'user_data' => [
                'em' => $email ? [hash('sha256', strtolower(trim($email)))] : []
            ],
            'custom_data' => [
                'currency' => 'USD',
                'value' => $orden['total'],
                'order_id' => $orderId
            ]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    logAdd(json_encode($response),"Respuesta PIXEL FACEBOOK","enviar-pixel-facebook");

    return $response;
}