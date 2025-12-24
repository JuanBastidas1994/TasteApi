<?php
http_response_code(403);

$return['success']= -1;
$return['mensaje']= "No tiene permisos para acceder - Error 403";

header("Content-type:application/json");
echo json_encode($return);

?>