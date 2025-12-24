<?php
http_response_code(404);

$return['success']= -1;
$return['mensaje']= "URL invalida - Error 404";

header("Content-type:application/json");
echo json_encode($return);

?>