<?php

class cl_gacela
{
	var $URL = "https://gacela.dev/api/";
	var $URLNEW = "https://api.tookanapp.com/v2";
	var $apiKey = "";
	var $tokenSucursal = "";
	var $msgError = "";
	var $tokens = "";
	public function __construct($pcod_sucursal = null)
	{
		$tokens = $this->getTokens($pcod_sucursal);
		if ($tokens) {
			$this->tokens = $tokens;
			$this->apiKey = $tokens['api'];
			$this->tokenSucursal = $tokens['token'];
			if ($tokens['ambiente'] == "development")
				$this->URL = "https://gacela.dev/api/";
			else
				$this->URL = "https://gacela.co/api/";
		}
	}

	public function getTokens($cod_sucursal)
	{
		$query = "SELECT gs.*, s.nombre
						FROM tb_gacela_sucursal gs, tb_sucursales s
						WHERE gs.cod_sucursal = s.cod_sucursal
						AND gs.cod_sucursal = $cod_sucursal
						AND gs.estado = 'A'";
		return Conexion::buscarRegistro($query);
	}

	public function cobertura($latitud, $longitud)
	{
		$data['api_token'] = $this->tokenSucursal;
		$data['destination_latitude'] = $latitud;
		$data['destination_longitude'] = $longitud;
		$json = json_encode($data);

		$ch = curl_init($this->URL . "tracking/coverage");
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Bearer ' . $this->apiKey;

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
		return json_decode($response);
	}

	public function costoCarreraDeprecated($latitud, $longitud)
	{
		$data['api_token'] = $this->tokenSucursal;
		$data['destination_latitude'] = $latitud;
		$data['destination_longitude'] = $longitud;
		$json = json_encode($data);

		$ch = curl_init($this->URL . "v2/tracking/fare");
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Bearer ' . $this->apiKey;

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		logAdd(json_encode($json), "json-solicitud-precio", "gacela");
		return json_decode($response);
	}

	public function costoCarrera($latitude, $longitude)
	{
		$tokens = (object)$this->tokens;
		$data = [
			'template_name' => $tokens->custom_field_template,
			'pickup_latitude' => $tokens->latitude,
			'pickup_longitude' => $tokens->longitude,
			'api_key' => $tokens->api_key,
			'delivery_latitude' => $latitude,
			'delivery_longitude' => $longitude,
			'formula_type' => 2
		];

		$json = json_encode($data);

		$ch = curl_init($this->URLNEW . "/get_fare_estimate");
		$headers = array();
		$headers[] = 'Content-Type: application/json';

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		logAdd(json_encode($json), "json-solicitud-precio", "gacela");
		return json_decode($response);
	}

	public function trackingOrder($token)
	{
		$ch = curl_init($this->URL . "order_tracking/" . $token);

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
		return json_decode($response, true);
	}
}