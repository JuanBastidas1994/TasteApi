<?php

class cl_usuarios
{
	public $cod_usuario, $cod_empresa, $cod_rol, $nombre, $apellido, $telefono, $imagen, $correo, $usuario, $password, $fecha_nacimiento, $estado, $num_documento, $fecha_create, $direccion, $esExtranjero, $tipoDocumento;
	
	public function __construct($pcod_usuario=null)
	{
		if($pcod_usuario != null)
			$this->cod_usuario = $pcod_usuario;
	}

	public function direcciones($cod_usuario){
		$query = "SELECT cod_usuario_direccion as id, nombre, direccion, referencia, latitud, longitud 
		FROM tb_usuario_direcciones WHERE cod_usuario =".$cod_usuario;
		$resp = Conexion::buscarVariosRegistro($query);
		return $resp;
	}

	public function save_direcciones($cod_usuario, $nombre, $direccion, $lat, $lon, $referencia = ""){
		$query = "INSERT INTO tb_usuario_direcciones(cod_usuario, nombre, direccion, referencia, latitud, longitud)";
		$query.= "VALUES($cod_usuario, '$nombre', '$direccion', '$referencia', '$lat', '$lon')";
		return Conexion::ejecutar($query,NULL);
	}

	public function get_direccion($id){
		$query = "SELECT * FROM tb_usuario_direcciones WHERE cod_usuario_direccion = $id";
		$resp = Conexion::buscarVariosRegistro($query);
		return $resp;
	}

	public function delete_direccion($id){
		$query = "DELETE FROM tb_usuario_direcciones WHERE cod_usuario_direccion = $id";
		return Conexion::ejecutar($query,NULL);
	}

	public function Login($usuario, $password)
	{
		$query = "SELECT cod_usuario as id, nombre, apellido, num_documento, correo, direccion, telefono, fecha_nacimiento
				FROM tb_usuarios u
				WHERE u.usuario = '$usuario' 
				AND u.password = MD5('$password') 
				AND u.estado = 'A'
				AND u.cod_rol = 4
				AND u.cod_empresa = ".cod_empresa;
		$return = Conexion::buscarRegistro($query);
		return $return;
	}

	public function usuarioDisponible($usuario){
		$query = "SELECT * FROM tb_usuarios WHERE usuario = '$usuario' AND estado IN('A','I') AND cod_empresa = ".cod_empresa;
		$row = Conexion::buscarRegistro($query, NULL);
		if($row)
			return false;
		else
			return true;
	}

	public function registro(&$id){
		$query = "INSERT INTO tb_usuarios(cod_empresa, cod_rol, nombre, apellido, telefono, correo, usuario, password, estado, num_documento)";
		$query.= "VALUES($this->cod_empresa, $this->cod_rol, '$this->nombre', '$this->apellido', '$this->telefono', '$this->correo', '$this->correo', MD5('$this->password'), 'A','$this->num_documento')";
		$resp = Conexion::ejecutar($query,NULL);
		if($resp)
			$id = Conexion::lastId();
		
		return $resp;
	}

	public function get($cod_usuario){  //SE USA EN MUCHAS SERVICIOS QUE REQUIEREN CAMPOS NO VISIBLES PARA EL USUARIO
		$cod_empresa = cod_empresa;
		$query = "SELECT * FROM tb_usuarios WHERE cod_usuario = $cod_usuario AND cod_empresa = $cod_empresa";
		$resp = Conexion::buscarRegistro($query);
		return $resp;
	}
	
	public function get2($cod_usuario){ //SE USA EN EL SERVICIO DE REGISTRO
		$query = "SELECT cod_usuario as id, nombre, apellido, num_documento, correo, direccion, telefono, telefono_verificado, fecha_nacimiento, estado FROM tb_usuarios 
		        WHERE estado = 'A' AND cod_usuario = $cod_usuario AND cod_empresa = ".cod_empresa;
		$resp = Conexion::buscarRegistro($query);
		if($resp){
			$resp['birthday'] = ($resp['fecha_nacimiento'] !== '0000-00-00') ? fechaLatinoShort($resp['fecha_nacimiento']) : '';

		    $resp['direcciones'] = $this->direcciones($cod_usuario);
		    
		    $datos_facturacion = $this->getDatosFacturacion($cod_usuario);
		    $resp['datos_facturacion'] = ($datos_facturacion) ? $datos_facturacion : null;
		}
		return $resp;
	}
	
	public function getCliente($cod_usuario){  
		$cod_empresa = cod_empresa;
		$query = "SELECT c.cod_cliente, c.cod_nivel, c.num_documento 
                    FROM tb_clientes c
                    INNER JOIN tb_usuario_cliente uc ON c.cod_cliente = uc.cod_cliente AND uc.cod_usuario = $cod_usuario
                    AND c.cod_empresa = $cod_empresa";
		$resp = Conexion::buscarRegistro($query);
		return $resp;
	}
	
	public function addClient($cod_usuario, $cod_cliente, $num_documento){
	    $query = "INSERT INTO tb_usuario_cliente(cod_usuario, cod_cliente) VALUES($cod_usuario, $cod_cliente)";
	    Conexion::ejecutar($query,NULL);
	    
	    $query = "UPDATE tb_usuarios SET num_documento='$num_documento' WHERE cod_usuario = $cod_usuario";
	    Conexion::ejecutar($query,NULL);
	}
	
	public function getUserRegistrado($cod_usuario){
		$query = "SELECT * FROM tb_usuarios WHERE cod_rol = 4 AND estado = 'A' AND cod_usuario = $cod_usuario";
		$resp = Conexion::buscarRegistro($query);
		return $resp;
	}
	
	public function getbyNumDocumento($num_documento){
		$query = "SELECT * FROM tb_usuarios 
				WHERE num_documento = '$num_documento' 
				AND cod_rol = 4 
				AND cod_empresa = ".cod_empresa;
		$resp = Conexion::buscarRegistro($query);
		return $resp;
	}

	public function getbyEmail($correo){
		$query = "SELECT * FROM tb_usuarios WHERE cod_rol = 4 AND correo = '$correo' AND estado IN('A','I') AND cod_empresa = ".cod_empresa;
		$resp = Conexion::buscarRegistro($query);
		return $resp;
	}

	public function set_password($cod_usuario, $password){
		$query = "UPDATE tb_usuarios SET password = MD5('$password') WHERE cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query,NULL);
	}

	function set_numdocumento($cod_usuario, $cedula){
		$query = "UPDATE tb_usuarios SET num_documento = '$cedula' WHERE cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query,NULL);
	}

	function set_telefono($cod_usuario, $telefono){
		$query = "UPDATE tb_usuarios SET telefono = '$telefono' WHERE cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query,NULL);
	}

	function set_telefono_verificado($cod_usuario, $telefono){
		$query = "UPDATE tb_usuarios SET telefono = '$telefono', telefono_verificado=1 WHERE cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query,NULL);
	}

	function set_fecha_nacimiento($cod_usuario, $fecha_nacimiento){
		$query = "UPDATE tb_usuarios SET fecha_nacimiento = '$fecha_nacimiento' WHERE cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query,NULL);
	}
	
	public function getNotificaciones($cod_usuario){
		$cod_empresa = cod_empresa;
		Conexion::ejecutar("SET NAMES 'utf8mb4'", NULL);
		$query = "SELECT n.cod_usuario, n.titulo, n.detalle, n.fecha, n.tipo
					FROM tb_notificaciones n, tb_empresa_notificaciones en
					WHERE n.cod_empresa_notificacion = en.cod_empresa_notificacion 
					AND n.cod_usuario IN(0, $cod_usuario)
					AND en.cod_empresa = $cod_empresa
					AND n.fecha >= '$this->fecha_create'
					ORDER BY n.fecha DESC 
					LIMIT 0,50";
		$resp = Conexion::buscarVariosRegistro($query);
		$notificaciones=[];
		foreach ($resp as $noti){
			$noti['fecha'] = hoursAgo($noti['fecha'], 3);
			$noti['titulo'] = html_entity_decode($noti['titulo']);
			$noti['tipo'] = strtoupper($noti['tipo']);
			unset($noti['cod_usuario']);
			$notificaciones[] = $noti;
		}    
		
		return $notificaciones;
	}

	public function getPedidosProgramados($cod_usuario){
		$fecha = fecha();
		$query = "SELECT oc.cod_orden, oc.is_envio, oc.hora_retiro as fecha_retiro, s.nombre as sucursal, s.direccion, s.latitud, s.longitud
			FROM tb_orden_cabecera oc, tb_usuarios u, tb_sucursales s
			WHERE oc.cod_usuario = u.cod_usuario
			AND oc.cod_sucursal = s.cod_sucursal
			AND oc.is_programado = 1
			AND oc.hora_retiro > '$fecha'
			AND oc.estado NOT IN('CANCELADA', 'ANULADA')
			AND u.cod_usuario = $cod_usuario
			ORDER BY oc.hora_retiro ASC
			LIMIT 0,1";
		return Conexion::buscarRegistro($query);

	}
	
	public function getTotalCompras($cod_usuario){
		$query = "SELECT SUM(oc.total) as total FROM tb_orden_cabecera oc
				WHERE oc.cod_usuario = $cod_usuario AND oc.estado = 'ENTREGADA'
				GROUP BY oc.cod_usuario";
		$resp = Conexion::buscarRegistro($query);
		if($resp){
			return $resp['total'];
		}else
			return 0;
	}

	public function getNumOrders($cod_usuario){
	    $query = "SELECT COUNT(cod_orden) as total
                FROM tb_orden_cabecera WHERE cod_usuario = $cod_usuario AND estado NOT IN('ANULADA')";
        $resp = Conexion::buscarRegistro($query);
		if($resp){
			return $resp['total'];
		}else
			return 0;
	}

	public function setLogPago($cod_usuario, $proveedor, $monto, $origen, $tipo, $fraude, $json, $estadoFraude){
		$fecha = fecha();
		$query = "INSERT INTO tb_usuario_intento_pago 
				SET 
				cod_usuario = $cod_usuario, 
				cod_proveedor_botonpagos = $proveedor, 
				fecha = '$fecha',
				monto = '$monto', 
				origen = '$origen', 
				tipo = '$tipo', 
				fraude = '$fraude', 
				json = '$json', 
				estado = '$estadoFraude'";
		return Conexion::ejecutar($query,NULL);
	}

	public function lista_cumpleaneros(){
		$fecha = fechaFormat("-m-d");
		$query = "SELECT c.cod_cliente, c.cod_usuario, c.nombre, c.cod_empresa, e.nombre as nom_empresa
				FROM tb_usuarios u,tb_clientes c, tb_empresas e
				WHERE u.cod_usuario = c.cod_usuario
				AND c.cod_empresa = e.cod_empresa
				AND c.cod_empresa = 24
				AND u.fecha_nacimiento LIKE '%$fecha%' 
				AND c.estado = 'A'";
		return Conexion::buscarVariosRegistro($query);
	}

	public function getPurchaseCodeActive($cod_usuario){
		$fecha = fecha();
		$query = "SELECT * from tb_usuario_purchase_code WHERE cod_usuario  = $cod_usuario AND estado = 'CREADO' AND fecha_expiracion > '$fecha'";
		return Conexion::buscarRegistro($query);
	}
	
	public function createPurchaseCodeActive($cod_usuario, $codigo, $intervalo = '00:05:00'){
		$fecha = fecha();
		$fecha_expiracion = AddIntervalo($fecha, $intervalo);
		$query = "INSERT INTO tb_usuario_purchase_code(cod_usuario, codigo, fecha_create, fecha_expiracion, estado, cod_orden) ";
		$query.= "VALUES($cod_usuario, '$codigo', '$fecha', '$fecha_expiracion', 'CREADO', 0)";
		return Conexion::ejecutar($query,NULL);
	}

	public function getCouponUsed($cod_usuario, $cupon){
		$query = "SELECT * 
					FROM tb_orden_cabecera
					WHERE cod_usuario = $cod_usuario
					AND cod_descuento = '$cupon'";
		return Conexion::buscarRegistro($query);
	}

	// NUEVO LOGIN
	public function getUserActiveByEmail($correo) {
		$cod_empresa = cod_empresa;
		$query = "SELECT *
					FROM tb_usuarios 
					WHERE correo = '$correo'
					AND cod_empresa = $cod_empresa
					AND cod_rol = 4
					AND estado IN ('A','I')";
		return Conexion::buscarRegistro($query);
	}

	public function setUserCodeLogin($cod_usuario, $codigo) {
		$fecha = fecha();
		$query = "SELECT * 
					FROM tb_usuario_codigo_login
					WHERE cod_usuario = $cod_usuario";
		$resp = Conexion::buscarRegistro($query);
		if($resp) {
			$query = "UPDATE tb_usuario_codigo_login
						SET codigo = '$codigo',
						fecha_creacion = '$fecha',
						fecha_expiracion = DATE_ADD('$fecha', INTERVAL 5 MINUTE),
						estado = 'A'
						WHERE cod_usuario = $cod_usuario";
			return Conexion::ejecutar($query, null);
		}
		else {
			$query = "INSERT INTO tb_usuario_codigo_login
						SET codigo = '$codigo',
						fecha_creacion = '$fecha',
						fecha_expiracion = DATE_ADD('$fecha', INTERVAL 5 MINUTE),
						cod_usuario = $cod_usuario";
			return Conexion::ejecutar($query, null);
		}
	}

	public function LoginExpress($correo, $codigo) {
		$cod_empresa = cod_empresa;
		$fecha = fecha();
		$query = "SELECT u.cod_usuario as id, nombre, apellido, num_documento, correo, direccion, telefono, fecha_nacimiento
					FROM tb_usuarios u, tb_usuario_codigo_login uc
					WHERE u.cod_usuario = uc.cod_usuario
					AND u.correo = '$correo'
					AND u.estado = 'A'
					AND u.cod_rol = 4
					AND u.cod_empresa = $cod_empresa
					AND uc.codigo = '$codigo'
					AND uc.estado = 'A'
					AND uc.fecha_expiracion > '$fecha'";
		return Conexion::buscarRegistro($query);
	}

	public function setEstadoCodigoTempLogin($cod_usuario, $codigo, $estado) {
		$query = "UPDATE tb_usuario_codigo_login
					SET estado = '$estado'
					WHERE cod_usuario = $cod_usuario
					AND codigo = '$codigo'";
		return Conexion::ejecutar($query, null);
	}

	// NUEVO REGISTRO
	public function setUserCodeRegister($correo, $codigo) {
		$fecha = fecha();
		$cod_empresa = cod_empresa;
		$query = "SELECT * 
					FROM tb_usuario_codigo_registro
					WHERE correo = '$correo'
					AND cod_empresa = $cod_empresa";
		$resp = Conexion::buscarRegistro($query);
		if($resp) {
			$query = "UPDATE tb_usuario_codigo_registro
						SET codigo = '$codigo',
						fecha_creacion = '$fecha',
						fecha_expiracion = DATE_ADD('$fecha', INTERVAL 5 MINUTE),
						estado = 'A'
						WHERE correo = '$correo'
						AND cod_empresa = $cod_empresa";
			return Conexion::ejecutar($query, null);
		}
		else {
			$query = "INSERT INTO tb_usuario_codigo_registro
						SET codigo = '$codigo',
						fecha_creacion = '$fecha',
						fecha_expiracion = DATE_ADD('$fecha', INTERVAL 5 MINUTE),
						correo = '$correo',
						cod_empresa = $cod_empresa";
			return Conexion::ejecutar($query, null);
		}
	}

	public function getCodeRegisterExpress($correo, $codigo) {
		$cod_empresa = cod_empresa;
		$fecha = fecha();
		$query = "SELECT *
					FROM tb_usuario_codigo_registro
					WHERE cod_empresa = $cod_empresa
					AND codigo = '$codigo'
					AND correo = '$correo'
					AND estado = 'A'
					AND fecha_expiracion > '$fecha'";
		return Conexion::buscarRegistro($query);
	}

	public function setEstadoCodigoTempRegister($correo, $codigo, $estado) {
		$cod_empresa = cod_empresa;
		$query = "UPDATE tb_usuario_codigo_registro
					SET estado = '$estado'
					WHERE correo = '$correo'
					AND codigo = '$codigo'
					AND cod_empresa = $cod_empresa";
		return Conexion::ejecutar($query, null);
	}
	
	//SMS
	public function setUserCodePhone($cod_usuario, $codigo) {
		$fecha = fecha();
		$query = "SELECT * 
					FROM tb_usuario_codigo_telefono
					WHERE cod_usuario = $cod_usuario";
		$resp = Conexion::buscarRegistro($query);
		if($resp) {
			$query = "UPDATE tb_usuario_codigo_telefono
						SET codigo = '$codigo',
						fecha_creacion = '$fecha',
						fecha_expiracion = DATE_ADD('$fecha', INTERVAL 5 MINUTE),
						estado = 'A'
						WHERE cod_usuario = $cod_usuario";
			return Conexion::ejecutar($query, null);
		}
		else {
			$query = "INSERT INTO tb_usuario_codigo_telefono
						SET codigo = '$codigo',
						fecha_creacion = '$fecha',
						fecha_expiracion = DATE_ADD('$fecha', INTERVAL 5 MINUTE),
						cod_usuario = $cod_usuario";
			return Conexion::ejecutar($query, null);
		}
	}
	
	public function getCodePhone($cod_usuario, $codigo) {
		$cod_empresa = cod_empresa;
		$fecha = fecha();
		$query = "SELECT *
					FROM  tb_usuario_codigo_telefono
					WHERE cod_usuario = $cod_usuario
					AND codigo = '$codigo'
					AND estado = 'A'
					AND fecha_expiracion > '$fecha'";
		return Conexion::buscarRegistro($query);
	}

	// INTENTOS DE PAGO
	public function getIntentosPagoDiaActual($cod_usuario) {
		$fechaInicio = fecha_only()." 00:00:00";
		$fechaFin = fecha_only()." 23:59:59";
		$query = "SELECT * FROM tb_usuario_intento_pago
					WHERE cod_usuario = $cod_usuario
					AND tipo = 'failure'
					AND fecha BETWEEN '$fechaInicio' AND '$fechaFin'
					AND fraude = 1
					AND estado = 'A'";
		return Conexion::buscarVariosRegistro($query);
	}
	
	public function getIntentosPagoFraude($cod_usuario) {
		$query = "SELECT * FROM tb_usuario_intento_pago
					WHERE cod_usuario = $cod_usuario
					AND tipo = 'failure'
					AND fraude = 1
					AND estado = 'A'";
		return Conexion::buscarVariosRegistro($query);
	}

	public function removerIntentosPagoFraude($cod_usuario) {
		$query = "UPDATE tb_usuario_intento_pago
					SET estado = 'I' 
					WHERE cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query, null);
	}

	public function getBloqueoUsuario($cod_usuario) {
		$fecha = fecha();
		$query = "SELECT *
					FROM tb_usuario_bloqueo
					WHERE cod_usuario = $cod_usuario
					AND estado = 'A'
					AND fecha_inicio <= '$fecha'
					AND fecha_fin >= '$fecha'
					ORDER BY cod_usuario_bloqueo DESC";
		return Conexion::buscarRegistro($query);
	}
	
	public function setBloqueoUsuario($cod_usuario, $dias, $motivo="") {
		$fecha = fecha();
		$query = "INSERT INTO tb_usuario_bloqueo
					SET cod_usuario = $cod_usuario,
					descripcion = '$motivo',
					fecha_inicio = '$fecha',
					fecha_fin = DATE_ADD('$fecha', INTERVAL $dias DAY),
					estado = 'A'";
		return Conexion::ejecutar($query, null);
	}

	public function removerBloqueUsuario($cod_usuario) {
		$query = "UPDATE tb_usuario_bloqueo
					SET estado = 'I'
					WHERE cod_usuario = $cod_usuario";
		return Conexion::ejecutar($query, null);
	}

	//ACTIVAR INACTIVAR USUARIO
	public function setEstadoUsuario($cod_usuario, $estado) {
		$cod_empresa = cod_empresa;
		$query = "UPDATE tb_usuarios
					SET estado = '$estado'
					WHERE cod_usuario = $cod_usuario
					AND cod_empresa = $cod_empresa";
		return Conexion::ejecutar($query, null);
	}

	public function getDatosFacturacion($cod_usuario) {
		$query = "SELECT nombre, num_documento, direccion, telefono, correo, is_extranjero, tipo_documento 
					FROM tb_usuarios_datos_facturacion
					WHERE cod_usuario = $cod_usuario";
		return Conexion::buscarRegistro($query);
	}

	public function saveDatosFacturacion() {
		$query = "INSERT INTO tb_usuarios_datos_facturacion
					SET 
						cod_usuario = '$this->cod_usuario',
						nombre = '$this->nombre',
						telefono = '$this->telefono',
						correo = '$this->correo',
						num_documento = '$this->num_documento',
						direccion = '$this->direccion',
						is_extranjero = $this->esExtranjero,
						tipo_documento = '$this->tipoDocumento'";
		return Conexion::ejecutar($query, null);
	}
	
	public function editDatosFacturacion() {
		$query = "UPDATE tb_usuarios_datos_facturacion
					SET 
						nombre = '$this->nombre',
						telefono = '$this->telefono',
						correo = '$this->correo',
						num_documento = '$this->num_documento',
						direccion = '$this->direccion',
						is_extranjero = $this->esExtranjero,
						tipo_documento = '$this->tipoDocumento'
					WHERE cod_usuario = '$this->cod_usuario'";
		return Conexion::ejecutar($query, null);
	}
}
