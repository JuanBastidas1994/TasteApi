<?php

class cl_cards
{
	var $user_id, $token, $type, $bin, $number, $expiry_month, $expiry_year, $reference, $status, $alias, $cod_sucursal_created;
	
	public function __construct()
	{
	}
	
	public function lista($user_id){
		$query = "SELECT uc.cod_usuario_cards, uc.token, uc.type, uc.bin, uc.number, uc.expiry_month, uc.expiry_year, uc.alias, uc.predeterminada, IFNULL(t.nombre, 'Unknown') as nombre, IFNULL(t.imagen, 'url_imagen_default') as imagen
		        FROM tb_usuario_cards uc
				LEFT JOIN tb_tarjetas t
					ON uc.type = t.id1
				WHERE uc.estado='A' 
				AND uc.status='valid' 
				AND uc.cod_usuario = $user_id";
		$cards = Conexion::buscarVariosRegistro($query);
		foreach ($cards as &$card) {
			$card['card_id'] = $card['cod_usuario_cards'];
			unset($card['cod_usuario_cards']);
			$card["expired"] = false;
			if($card["expiry_year"] < date("Y")) {
				$card["expired"] = true;
			}
			else {
				if($card["expiry_month"] < date("m") && $card["expiry_year"] == date("Y")) {
					$card["expired"] = true;
				}
			}
		}
		return $cards;
	}

	public function crear() {
		$cards = $this->lista($this->user_id);
		$predeterminada = count($cards) > 0 ? 0 : 1; 

		$query = "INSERT INTO tb_usuario_cards(cod_usuario, token, type, status, bin, number, reference, expiry_month, expiry_year, alias, predeterminada, cod_sucursal_created, estado) ";
		$query.= "VALUES($this->user_id, '$this->token', '$this->type', '$this->status', '$this->bin', '$this->number', '$this->reference', $this->expiry_month, $this->expiry_year, '$this->alias', $predeterminada, $this->cod_sucursal_created, 'A')";
		return Conexion::ejecutar($query,NULL);
	}
	
	public function getExistToken($token, $user_id){
	    $query = "SELECT * FROM tb_usuario_cards WHERE token = '$token' AND cod_usuario = $user_id AND estado = 'A'";
	    return Conexion::buscarRegistro($query,NULL);
	}

	public function get($cod_usuario_cards, $cod_usuario){
		$query = "SELECT uc.*, IFNULL(t.nombre, 'Unknown') as nombre, IFNULL(t.imagen, 'url_imagen_default') as imagen  
				FROM tb_usuario_cards uc
				LEFT JOIN tb_tarjetas t
					ON uc.type = t.id1
				WHERE uc.cod_usuario_cards = $cod_usuario_cards
				AND uc.cod_usuario = $cod_usuario";
		$card = Conexion::buscarRegistro($query,NULL);
		if($card) {
			$card['card_id'] = $card['cod_usuario_cards'];
			unset($card['cod_usuario_cards']);
			$card["expired"] = false;
			if($card["expiry_year"] < date("Y")) {
				$card["expired"] = true;
			}
			else {
				if($card["expiry_month"] < date("m") && $card["expiry_year"] == date("Y")) {
					$card["expired"] = true;
				}
			}
		}
		return $card;
	}
	
	public function eliminar($cod_usuario_cards, $cod_usuario){
// 		$query = "UPDATE tb_usuario_cards 
// 					SET estado = 'I' 
// 					WHERE cod_usuario_cards = $cod_usuario_cards
// 					AND cod_usuario = $cod_usuario";
		$query = "DELETE FROM tb_usuario_cards 
					WHERE cod_usuario_cards = $cod_usuario_cards
					AND cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query, NULL);
	}

	public function setTarjetaPredeterminada($cod_usuario_cards, $cod_usuario) {
		$query = "UPDATE tb_usuario_cards
					SET predeterminada = 0
					WHERE cod_usuario = $cod_usuario";
		$resp = Conexion::ejecutar($query, null);

		$query = "UPDATE tb_usuario_cards
					SET predeterminada = 1
					WHERE cod_usuario_cards = $cod_usuario_cards
					AND cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query, null);
	}

	public function getNombreTarjeta($type) {
		$nombre = "Unknown";
		$query = "SELECT * 
					FROM tb_tarjetas 
					WHERE id1 = '$type'";
		$resp = Conexion::buscarRegistro($query);
		if($resp) {
			$nombre = $resp["nombre"];
		}
		return $nombre;
	}

	public function getByToken($token, $cod_usuario) {
		$query = "SELECT uc.*, IFNULL(t.nombre, 'Unknown') as nombre, IFNULL(t.imagen, 'url_imagen_default') as imagen  
				FROM tb_usuario_cards uc
				LEFT JOIN tb_tarjetas t
					ON uc.type = t.id1
				WHERE uc.token = '$token'
				AND uc.cod_usuario = $cod_usuario";
		$card = Conexion::buscarRegistro($query,NULL);
		if($card) {
			$card["expired"] = false;
			if($card["expiry_year"] < date("Y")) {
				$card["expired"] = true;
			}
			else {
				if($card["expiry_month"] < date("m") && $card["expiry_year"] == date("Y")) {
					$card["expired"] = true;
				}
			}
		}
		return $card;
	}
}
?>