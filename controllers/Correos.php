<?php

if($method == "GET"){
	$num_variables = count($request);
	if($num_variables == 3){
		if($request[1] == "orden"){
			$resp = orden($request[2]);
			showResponse($resp);
		}
	}
}
else{
	$return['success']= 0;
	$return['mensaje']= "El metodo ".$method." para Correos aun no esta disponible.";
	showResponse($return);
}

function orden($cod_orden){
	require_once "clases/cl_ordenes.php";
	$Clordenes = new cl_ordenes();

	$orden = $Clordenes->get_orden_array($cod_orden);
	if(!$orden){
		$return['success'] = 1;
		$return['mensaje'] = "Orden no existente";
		return $return;
	}

	/*
	$detalle = $Clordenes->get_detalle_orden($cod_orden);
	if($detalle){
		$orden['detalle2'] = $detalle;
	}
*/

	//$logo = url;

	$return['success'] = 1;
	$return['data'] = $orden;
	return $return;

}

function enviarEmail($html){
	$asunto = "Orden Recibida en ".$name;
	//$nombre = "Juan Carlos";

	$mail = new PHPMailer();
	try {
		//Server settings
		$mail->SMTPDebug = 2; 
		// Enable verbose debug output
		
		//GMAIL
		$mail->Host = 'smtp.gmail.com';
		$mail->SMTPAuth = true;
		$mail->Username = 'juankbastidasjuve@gmail.com';
		$mail->Password = 'fcjuventus'; 
		$mail->SMTPSecure = 'ssl';
		$mail->Port = 465;
			
		//Recipients
		$correoReplyTo = "info@mie-commerce.com";
		$mail->setFrom($setFrom, $name);
		$mail->addAddress($correo, html_entity_decode($nombre));
		$mail->addReplyTo($correoReplyTo, $name);
			
		//Content
		$mail->isHTML(true);
		$mail->CharSet = 'UTF-8';
		$mail->Subject = $asunto;
		$mail->Body    = $html;
		$mail->AltBody = 'DigitalMind';

		if (!$mail->send())
		{
			$return['success']= 0;
			$return['mensaje']= "Error al enviar el correo";
		}
		else
		{
			$return['success'] = 1;
			$return['mensaje'] = "Correo enviado correctamente";
			$return['correo'] = $correo;
		}
		} catch (Exception $e) {
		$return['success']= 0;
			$return['mensaje']= "Error al enviar el correo";
	}
}