<?php
$logFolder = "";
$entityFolder = "";

//Verificar carpeta existente
function logVerifyFolder(){
    global $logFolder;

    $folder = "logs/".alias;
    if (!is_dir($folder)) {
        @mkdir($folder, 0777, true);
    }
    $logFolder = $folder;
}

function logEntityFolder($entity){
    global $logFolder;
    global $entityFolder;

    $folder = $logFolder."/".$entity;
    if (!is_dir($folder)) {
        @mkdir($folder, 0777, true);
    }
    $entityFolder = $folder;
}

//Guarda todos los endpoints consumidos
function logEndpoint($endpoint, $metodo){
    global $logFolder;
    $file = $logFolder."/todas-peticiones.log";
    $log = "[".fecha()."][".$metodo."] ENDPOINT: ".$endpoint."";
	saveLog($file, $log);
}

//Guarda la información enviada por post
function logPostInfo($endpoint, $json){
    global $logFolder;
    $file = $logFolder."/post-peticiones.log";
    $log = "[".fecha()."][".$endpoint."] ".$json;
    saveLog($file, $log);
}

//
function logAdd($string, $detail, $filename){
    global $entityFolder;
    
    $file = $entityFolder."/".$filename.".log";
    $log = "[".fecha()."][".$detail."] ".$string;
    saveLog($file, $log);
}



function saveLog($dirFile, $string){
    file_put_contents($dirFile, PHP_EOL . $string, FILE_APPEND);
}


function mylog($texto, $title=""){
    global $request;
    $folder = "logs/".alias;

    if (!is_dir($folder)) {
        @mkdir($folder, 0777, true);
    }
    $file = $folder."/".$request[0].".log";
    $log = "[".fecha()."] ".$title." ".$texto;
	file_put_contents($file, PHP_EOL . $log, FILE_APPEND);
}

function logGoogleMaps($lat1, $lon1, $lat2, $lon2, $distancia, $precio, $dirFile = 'log_distancias.txt') {
    global $logFolder;
    $googleMapsLink = "https://www.google.com/maps/dir/$lat1,$lon1/$lat2,$lon2";

    $string = "Ubicacion 1: $lat1 - $lon1" . PHP_EOL;
    $string .= "Ubicacion 2: $lat2 - $lon2" . PHP_EOL;
    $string .= "Distancia: {$distancia} km" . PHP_EOL;
    $string .= "Precio: $precio" . PHP_EOL;
    $string .= "Google Maps: $googleMapsLink" . PHP_EOL;
    $string .= str_repeat('-', 40) . PHP_EOL;

    file_put_contents("logs/".$dirFile, $string, FILE_APPEND);
}

//Registra respuestas con error/inesperadas de la API de Routes de Google Maps
function logGoogleMapsError($url, $requestBody, $httpCode, $curlError, $response, $dirFile = 'log_distancias_errores.txt') {
    $fecha = date("Y-m-d H:i:s");

    $string  = "[$fecha]" . PHP_EOL;
    $string .= "URL: $url" . PHP_EOL;
    $string .= "Request: $requestBody" . PHP_EOL;
    $string .= "HTTP Code: $httpCode" . PHP_EOL;
    if ($curlError) {
        $string .= "cURL Error: $curlError" . PHP_EOL;
    }
    $string .= "Response: $response" . PHP_EOL;
    $string .= str_repeat('-', 40) . PHP_EOL;

    if (!is_dir("logs")) {
        @mkdir("logs", 0777, true);
    }

    file_put_contents("logs/".$dirFile, $string, FILE_APPEND);
}

?>