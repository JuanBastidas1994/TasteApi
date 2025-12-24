<?php

class cl_motorizado
{
   
		public function Loginmotorizado($usuario, $password)
		{
			$query = "SELECT u.cod_usuario as id, concat(u.nombre,' ',u.apellido) as nombres, u.correo, u.usuario, u.estado, u.imagen,
					    e.nombre as empresa, e.fecha_caducidad, e.logo, e.folder, e.api_key as apikey, e.alias 
    					FROM tb_usuarios u, tb_empresas e 
    					WHERE u.cod_empresa = e.cod_empresa 
    					AND u.usuario = '$usuario' 
    					and u.password = MD5('$password') 
    					AND u.estado = 'A' 
    					AND u.cod_rol = 17";
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}
		
		public function get($cod_usuario){
			$query = "SELECT * FROM tb_usuarios WHERE cod_usuario = $cod_usuario";
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}
		
		public function set_ubicacion($cod_usuario, $lat, $lon){
		    $fecha = fecha();
			$query = "UPDATE tb_usuarios SET latitud='$lat', longitud='$lon', fecha_ubicacion='$fecha' WHERE cod_usuario = $cod_usuario";
			if(Conexion::ejecutar($query,NULL)){
        		return true;
        	}else{
        		return false;
        	}
		}
}
?>