<?php

class cl_clientes
{
		var $cod_cliente, $qr, $secuencia, $cod_nivel, $nombre, $cedula, $fecha_nac, $cod_orden;

		public function __construct($pcedula=null)
		{
			if($pcedula != null){
				$this->cedula = $pcedula;
				$this->get();
			}
		}

		public function create($cod_usuario, $nombre, &$id, $num_documento=''){
			$cod_empresa = cod_empresa;
			$query = "INSERT INTO tb_clientes(cod_empresa,cod_usuario,cod_nivel,tipo_documento,num_documento,nombre,estado) 
					VALUES ($cod_empresa, $cod_usuario, 0, 1, '$num_documento', '$nombre', 'A')";
			if(Conexion::ejecutar($query,NULL)){
				$id = Conexion::lastId();
				return true;
			}
			return false;
		}

		public function get(){
			$query = "SELECT * FROM tb_clientes c WHERE c.num_documento = '$this->cedula' AND c.cod_empresa = ".cod_empresa;
			$row = Conexion::buscarRegistro($query);
			if($row){
				$this->cod_cliente = $row['cod_cliente'];
				$this->nombre = $row['nombre'];
				$this->cedula = $row['num_documento'];
				$this->cod_nivel = $row['cod_nivel'];
				$this->fecha_nac = $row['fecha_nac'];
			}
            return $row;
		}

		public function getByUser($user_id){
			$query = "SELECT * FROM tb_clientes c 
				WHERE c.cod_usuario = $user_id 
				AND c.cod_empresa = ".cod_empresa;
			$row = Conexion::buscarRegistro($query);
			if($row){
				$this->cod_cliente = $row['cod_cliente'];
				$this->nombre = $row['nombre'];
				$this->cedula = $row['num_documento'];
				$this->cod_nivel = $row['cod_nivel'];
				$this->fecha_nac = $row['fecha_nac'];
			}
            return $row;
		}
		
		public function getbyNumDocumento($num_documento){
			$query = "SELECT * FROM tb_clientes WHERE num_documento = '$num_documento' AND cod_empresa = ".cod_empresa;
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}

		public function GetSaldo(){
			$query = "SELECT * FROM tb_clientes_saldos WHERE cod_cliente = $this->cod_cliente AND estado = 'A' AND fecha_caducidad >= NOW() LIMIT 0,1";
			$resp = Conexion::buscarRegistro($query);
			return ($resp) ? $resp['dinero'] : 0;
		}

		public function GetPuntos(){
			$query = "SELECT SUM(cp.puntos) as puntos 
                    FROM tb_clientes_puntos cp
                    WHERE cp.cod_cliente = $this->cod_cliente
                    AND cp.estado = 'A'
                    AND cp.fecha_caducidad > NOW()
                    GROUP BY cp.cod_cliente";
			$resp = Conexion::buscarRegistro($query);
			return ($resp) ? $resp['puntos'] : 0;
		}

		public function GetDinero(){
			$query = "SELECT SUM(cp.saldo) as saldo 
                    FROM tb_cliente_dinero cp
                    WHERE cp.cod_cliente = $this->cod_cliente
                    AND cp.estado = 'A'
                    AND cp.fecha_caducidad > NOW()
                    GROUP BY cp.cod_cliente";
			$resp = Conexion::buscarRegistro($query);
			return ($resp) ? $resp['saldo'] : 0;
		}

		public function getDineroDesglose(){
			$query = "SELECT * FROM tb_cliente_dinero cd 
			    	WHERE cd.cod_cliente = $this->cod_cliente
					AND cd.estado = 'A' 
					AND cd.saldo > 0 
					AND cd.fecha_caducidad > NOW() 
			    	ORDER BY cd.fecha_caducidad ASC";
			return Conexion::buscarVariosRegistro($query);
		}

		function getNivel($puntaje){
		    $query = "SELECT * FROM tb_niveles n WHERE cod_empresa = ".cod_empresa." AND ($puntaje BETWEEN punto_inicial AND punto_final)";
		    $resp = Conexion::buscarRegistro($query);
			if($resp){
				$resp['posicion'] = $resp['posicion'] + 1;
			}
			return $resp;
		}

	/*ADICIONES*/
		public function AddDinero($monto, $cod_cliente, $tipo, $dias, $cod_orden = 0){
			$query = "INSERT INTO tb_cliente_dinero(cod_cliente, cod_tipo_pago, dinero, saldo, fecha, fecha_caducidad, estado, cod_orden) 
					VALUES($cod_cliente, $tipo, $monto, $monto, NOW(), DATE_ADD(NOW(), INTERVAL $dias DAY), 'A', $cod_orden)";
			return Conexion::ejecutar($query,NULL);
		}	

		public function AddPuntos($cod_cliente, $nivel, $puntos, $amount, $dias, $cod_orden = 0){
			$query = "INSERT INTO tb_clientes_puntos(cod_cliente,cod_nivel,puntos,dinero,fecha_create,fecha_caducidad,estado,cod_orden)
		    		VALUES($cod_cliente,$nivel,$puntos,$amount,NOW(),DATE_ADD(NOW(), INTERVAL $dias DAY),'A',$cod_orden)";
			return Conexion::ejecutar($query,NULL);
		}
		
		public function ActualizarSaldo($cod_cliente, $saldo, $saldo_old, $dias, $cod_orden = 0){
		    $query = "UPDATE tb_clientes_saldos SET estado = 'I' WHERE cod_cliente = $cod_cliente";
		    if(Conexion::ejecutar($query,NULL)){
		        $query = "INSERT INTO tb_clientes_saldos(cod_cliente,dinero,saldo_anterior,fecha_create,fecha_caducidad,estado,cod_orden) ";
		        $query.= "VALUES($this->cod_cliente,$saldo,$saldo_old,NOW(),DATE_ADD(NOW(), INTERVAL $dias DAY),'A',$cod_orden)";
		        return Conexion::ejecutar($query,NULL);
		    }else{
		        return false;
		    }
		}

		public function ActualizarDinero($id, $saldo, $status = 'A'){
			$query = "UPDATE tb_cliente_dinero SET saldo=$saldo, estado='$status' 
				WHERE cod_cliente_dinero = $id";
			return Conexion::ejecutar($query,NULL);
		}

	//FUNCIONES MAS IMPORTANTES

	/*ORDENES DEL CLIENTE*/
	public function ordenes_faltantes($cod_usuario){
		$query = "SELECT op.*
					FROM tb_orden_puntos op, tb_orden_cabecera c, tb_usuarios u
					WHERE c.cod_orden = op.cod_orden
					AND c.cod_usuario = u.cod_usuario
					AND u.cod_usuario = $cod_usuario
					AND c.estado IN('ENTREGADA','CREADA')
					AND op.estado = 0";
		$resp = Conexion::buscarVariosRegistro($query);
        return $resp;			
	}
	
	public function getPagosAumentar($cod_orden){
		$query = "SELECT SUM(monto) as monto FROM tb_orden_pagos WHERE cod_orden = $cod_orden AND forma_pago NOT IN('P')";
		$resp = Conexion::buscarRegistro($query);
		if($resp){
			return $resp['monto'];
		}
        return 0;
	}
	
	public function getPagosDecrementar($cod_orden){
		$query = "SELECT monto FROM tb_orden_pagos WHERE cod_orden = $cod_orden AND forma_pago = 'P'";
		$resp = Conexion::buscarRegistro($query);
        if($resp){
			return $resp['monto'];
		}
        return 0;
	}

	public function orden_complete($cod_orden){
		$query = "UPDATE tb_orden_puntos SET estado = 1 WHERE cod_orden = $cod_orden";
		return Conexion::ejecutar($query,NULL);
	}
}
?>