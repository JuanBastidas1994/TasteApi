<?php

require_once "clases/cl_usuarios.php";
require_once "clases/cl_empresas.php";
$Clusuarios = new cl_usuarios();
$Clempresas = new cl_empresas();

if($method == "POST"){
    $num_variables = count($request);
    if($num_variables == 2){
        $metodo = $request[1];
        if($metodo == "suscribir"){
            $return = suscribirTopic();
			showResponse($return);
        }
    }

	showResponse(['success'=> 0, 'mensaje'=> 'Evento no existente']);
}else{
    showResponse(['success'=> 0, "El metodo $method para Login aun no esta disponible."]);
}


function suscribirTopic(){
    global $Clempresas;
    $usuario = validateUserAuthenticated();
    $input = validateInputs(array("token"));
    extract($input);

    try {
        $userId     = $usuario['cod_usuario'];
        $businessId = $usuario['cod_empresa'];
        $topics = ["usuario$userId"];

        $topic = $Clempresas->getTopic();
        if($topic){
            $topics[] = $topic;
        }


        $accessToken = getFirebaseAccessToken();
        $results     = [];

        foreach($topics as $topic) {
            $url = "https://iid.googleapis.com/iid/v1/$token/rel/topics/$topic";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json",
                "access_token_auth: true"  // Header requerido para OAuth2 en IID API
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $results[$topic] = $httpCode === 200 ? 'ok' : json_decode($response, true);
        }

        showResponse(['success' => 1, 'topics' => $results]);

    } catch (Exception $e) {
        showResponse(['success' => 0, 'mensaje' => $e->getMessage(), 'errorCode' => 'ERROR_TRANSACTION']);
    }
}

// Genera el access token usando la Service Account
function getFirebaseAccessToken() {
    $serviceAccount = json_decode(file_get_contents(FIREBASE_CONFIG_URL), true);

    $now    = time();
    $header = base64url_encode(json_encode(["alg" => "RS256", "typ" => "JWT"]));
    $claim  = base64url_encode(json_encode([
        "iss"   => $serviceAccount['client_email'],
        // "scope" => "https://www.googleapis.com/auth/firebase.messaging",
        "scope" => "https://www.googleapis.com/auth/cloud-platform",
        "aud"   => "https://oauth2.googleapis.com/token",
        "iat"   => $now,
        "exp"   => $now + 3600
    ]));

    $signature = '';
    openssl_sign("$header.$claim", $signature, $serviceAccount['private_key'], 'SHA256');
    $jwt = "$header.$claim." . base64url_encode($signature);

    // Intercambiar JWT por access token
    $ch = curl_init("https://oauth2.googleapis.com/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
        "assertion"  => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($response['access_token'])) {
        throw new Exception('No se pudo obtener access token de Firebase');
    }

    return $response['access_token'];
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

?>