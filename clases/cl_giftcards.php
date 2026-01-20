<?php

class cl_giftcards
{
	var $session;
	var $cod_producto, $cod_producto_padre, $cod_empresa, $alias, $nombre, $desc_corta, $desc_larga, $image_min, $image_max, $fecha_create, $user_create, $estado, $precio, $codigo;
	
	public function __construct($pcod_producto=null)
	{
		if($pcod_producto != null)
			$this->cod_producto = $pcod_producto;
	}
	
	public function lista(){
		$query = "SELECT * FROM tb_giftcards WHERE estado='A' AND cod_empresa = ".cod_empresa." ORDER BY posicion ASC";
		$resp = Conexion::buscarVariosRegistro($query);
		$return = [];
		$x=0;
		foreach ($resp as $gift) {
			$gift['imagen'] = url.$gift['imagen'];
			$gift['montos'] = explode(",",$gift['montos']);
			$return[$x]=$gift;
			$x++;
		}    
		return $return;
	}

	public function lista_compradas($cod_usuario){
		$query = "SELECT ug.cod_usuario_giftcard as id, g.imagen, ug.monto, ug.codigo, ug.fecha
		FROM tb_usuario_giftcard ug, tb_giftcards g
		WHERE ug.cod_giftcard = g.cod_giftcard
		AND ug.cod_usuario = $cod_usuario";
		$resp = Conexion::buscarVariosRegistro($query);
		foreach ($resp as $key => $giftcard) {
			$resp[$key]['imagen'] = url.$giftcard['imagen'];
			$resp[$key]['fecha'] = fechaLatinoShort($giftcard['fecha']);
		}
		return $resp;
	}

	public function lista_mis_giftcards($cod_usuario){
		$query = "SELECT ug.cod_usuario_giftcard as id, g.imagen, ug.monto, ug.codigo, ug.fecha
		FROM tb_usuario_giftcard ug, tb_giftcards g
		WHERE ug.cod_giftcard = g.cod_giftcard
		AND ug.cod_usuario_receptor = $cod_usuario;";
		$resp = Conexion::buscarVariosRegistro($query);
		foreach ($resp as $key => $giftcard) {
			$resp[$key]['imagen'] = url.$giftcard['imagen'];
			$resp[$key]['fecha'] = fechaLatinoShort($giftcard['fecha']);
		}
		return $resp;
	}

	public function getUserGiftcardByCode($code){
		$query = "SELECT ug.*
		FROM tb_usuario_giftcard ug, tb_usuarios u
		WHERE ug.cod_usuario = u.cod_usuario
		AND ug.codigo = '$code'
		AND ug.estado = 'A'
		AND u.cod_empresa = ".cod_empresa;
		return Conexion::buscarRegistro($query);
	}

	public function getGitcardEmpresaById($id){
		$query = "SELECT * FROM tb_giftcards WHERE estado='A' AND cod_empresa = ".cod_empresa." AND cod_giftcard = $id";
		return Conexion::buscarRegistro($query);
	}

	public function getGitcardUsuario($cod_usuario, $id){
		$query = "SELECT ug.cod_usuario_giftcard as id, g.imagen, ug.monto, ug.codigo, ug.cod_usuario, ug.cod_usuario_receptor, ug.fecha, ug.fecha_utilizacion, ug.estado
		FROM tb_usuario_giftcard ug, tb_giftcards g
		WHERE ug.cod_giftcard = g.cod_giftcard
		AND ug.cod_usuario_giftcard = $id";
		$resp = Conexion::buscarRegistro($query);
		if($resp){
			if($resp['cod_usuario'] == $cod_usuario)
				$resp['tipo'] = "comprador";
			else
				$resp['tipo'] = "beneficiario";
			$resp['imagen'] = url.$resp['imagen'];
			$resp['fecha'] = fechaLatinoShort($resp['fecha']);
			$resp['fecha_utilizacion'] = fechaLatinoShort($resp['fecha_utilizacion']);
			$resp['comprador'] = $this->getUserInfo($resp['cod_usuario']);
			if(intval($resp['cod_usuario_receptor']) > 0)
				$resp['beneficiario'] = $this->getUserInfo($resp['cod_usuario_receptor']);
			else
				$resp['beneficiario'] = null;	
			unset($resp['cod_usuario']);
			unset($resp['cod_usuario_receptor']);
		}
		return $resp;
	}

	public function setUserGiftcard($cod_beneficiario, $cod_usuario_giftcard){
		$query = "UPDATE tb_usuario_giftcard SET 
			cod_usuario_receptor = $cod_beneficiario,
			fecha_utilizacion = NOW()
			WHERE cod_usuario_giftcard = $cod_usuario_giftcard";
		return Conexion::ejecutar($query,NULL);
	}

	public function getUserInfo($cod_usuario){
		$query = "SELECT cod_usuario as id, nombre, correo, usuario, fecha_nacimiento, telefono
		FROM tb_usuarios WHERE cod_usuario = $cod_usuario";
		return Conexion::buscarRegistro($query);
	}

	public function crear($monto, $cod_giftcard, $cod_usuario, $codigo){
		$cod_empresa = cod_empresa;
		$query = "INSERT INTO tb_usuario_giftcard(cod_usuario, cod_giftcard, codigo, monto, fecha, estado) ";
		$query.= "VALUES($cod_usuario, $cod_giftcard, '$codigo', $monto, NOW(), 'A')";
		return Conexion::ejecutar($query,NULL);
	}

}
?>