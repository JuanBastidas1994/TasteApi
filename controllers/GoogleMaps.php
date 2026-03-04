<?php


if ($method == "GET") {
    $num_variables = count($request);
    $metodo = $request[1];

    if ($metodo == "autocomplete") {
        $filtro = urldecode($request[2]);
        try {
            $data = ExecuteQuery("https://maps.googleapis.com/maps/api/place/autocomplete/json?input=$filtro&language=es&components=country:ec&key=".API_GOOGLE_MAPS);
            // Siempre devuelve array aunque falle
            if ($data && isset($data->status) && $data->status == "OK") {
                showResponse(["success" => 1, "predictions" => $data->predictions]);
            } else {
                showResponse(["success" => 0, "predictions" => []]);
            }
        } catch (Exception $e) {
            showResponse(["success" => 0, "predictions" => []]);
        }
    }else if($metodo == "details") {
        $placeId = $request[2];
        try {
            $data = ExecuteQuery("https://maps.googleapis.com/maps/api/place/details/json?place_id=$placeId&fields=geometry&key=".API_GOOGLE_MAPS);
            if ($data && isset($data->status) && $data->status == "OK") {
                showResponse([
                    "success" => 1,
                    "lat" => $data->result->geometry->location->lat,
                    "lng" => $data->result->geometry->location->lng
                ]);
            } else {
                showResponse(["success" => 0, "mensaje" => "No se pudo obtener el lugar"]);
            }
        } catch (Exception $e) {
            showResponse(["success" => 0, "mensaje" => "Error al obtener detalles"]);
        }
    }

    showResponse([
        'success' => 0,
        'mensaje' => "Evento no existente"
    ]);
}else{
	showResponse([
        'success' => 0,
        'mensaje' => "El metodo " . $method . " para Ordenes aun no esta disponible."
    ]);
}

function ExecuteQuery($link){
  $ch = curl_init($link);
  $headers = array();
  $headers[] = 'Content-Type: application/json';

  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");                                                                     
  curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);   
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
  $response = curl_exec($ch);
  curl_close($ch);
  return json_decode($response);
}

?>