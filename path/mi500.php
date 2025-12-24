<?php
http_response_code(500);

$return['success']= -1;
$return['mensaje']= "Error Interno - Error 500";

header("Content-type:application/json");
echo json_encode($return);

?>