<?php

class cl_empresas
{
		public $cod_usuario, $cod_empresa, $cod_rol, $nombre, $apellido, $imagen, $correo, $usuario, $password, $fecha_nacimiento, $estado;
		
		public function __construct($pcod_usuario=null)
		{
			if($pcod_usuario != null)
				$this->cod_usuario = $pcod_usuario;
		}

		public function get(){
			$query = "SELECT * FROM tb_empresas WHERE cod_empresa = ".cod_empresa;
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}

		public function getByCode($code){
			$query = "SELECT * FROM tb_empresas WHERE cod_empresa = $code";
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}

		public function getByAlias($alias){
			$query = "SELECT * FROM tb_empresas WHERE alias = '$alias'";
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}

		public function getByApiKey($api){
			$query = "SELECT * FROM tb_empresas WHERE api_key = '$api' AND estado = 'A'";
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}
		
		public function getProgramar(){
		    $query = "SELECT programar_pedido, cant_dias_programar_pedido as dias FROM tb_empresas WHERE cod_empresa = ".cod_empresa; 
		    $resp = Conexion::buscarRegistro($query);
			return $resp;
		}

		public function getFidelizacion(){
			$query = "SELECT f.* 
                    FROM tb_empresa_fidelizacion_puntos f
                    INNER JOIN tb_empresas e ON f.cod_empresa = e.cod_empresa AND e.fidelizacion = 1
                    WHERE f.cod_empresa = ".cod_empresa;
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}

		public function getFidelizacionById($id){
			$query = "SELECT * FROM  tb_empresa_fidelizacion_puntos WHERE cod_empresa = $id";
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}
		
		public function getFirstNivel(){
		    $cod_empresa = cod_empresa;
			$query = "SELECT nombre, imagen, punto_inicial, punto_final, dinero_x_punto, posicion FROM tb_niveles WHERE cod_empresa = $cod_empresa ORDER BY posicion";
			$resp = Conexion::buscarRegistro($query);
			if($resp){
			    if($resp['imagen'] == ""){
                    $resp['imagen'] = url_resource."nivel".($resp['posicion']+1).".png";
                }else{
                    $resp['imagen'] = url.$resp['imagen'];
                }
			}
			return $resp;
		}

		public function getNiveles(){
			$cod_empresa = cod_empresa;
			$query = "SELECT nombre, imagen, punto_inicial, punto_final, dinero_x_punto, posicion FROM tb_niveles WHERE cod_empresa = $cod_empresa ORDER BY posicion";
			$resp = Conexion::buscarVariosRegistro($query);
			foreach($resp as $key=>$nivel){
			    $resp[$key]['dinero_x_punto'] = number_format($nivel['dinero_x_punto'],2,".","");
			    if($nivel['imagen'] == ""){
                    $resp[$key]['imagen'] = url_resource."nivel".($nivel['posicion']+1).".png";
                }else{
                    $resp[$key]['imagen'] = url.$nivel['imagen'];
                }
			}
			return $resp;
		}
		
		public function getFaqs(){
			$cod_empresa = cod_empresa;
			$query = "SELECT cod_empresa_faqs, imagen, titulo, descripcion FROM tb_empresa_faqs WHERE estado = 'A' AND cod_empresa = $cod_empresa ORDER BY posicion";
			$resp = Conexion::buscarVariosRegistro($query);
			foreach($resp as $key=>$nivel){
			    if($nivel['imagen'] == ""){
                    $resp[$key]['imagen'] = url_resource."nivel1.png";
                }else{
                    $resp[$key]['imagen'] = url.$nivel['imagen'];
                }
			}
			return $resp;
		}

		public function getRedesSociales(){
			$cod_empresa = cod_empresa;
			$query = "SELECT r.codigo as code, er.descripcion as link
                        FROM tb_empresa_red_social er, tb_red_social r
                        WHERE er.cod_red_social = r.cod_red
                        AND er.cod_empresa = $cod_empresa";
			$resp = Conexion::buscarVariosRegistro($query);
			foreach($resp as $key=>$item)
				$resp[$key]['code'] = strtolower($item['code']);
			return $resp;
		}
		
		public function getIsEnvioGrabaIva(){
		    $query = "SELECT envio_grava_iva FROM tb_empresas WHERE cod_empresa = ".cod_empresa;
		    $resp = Conexion::buscarRegistro($query);
		    if($resp){
		        return $resp['envio_grava_iva'];
		    }else
		        return 1;
		}
		
		public function getFormasPagoEmpresa($tipoEnvio = "", $hideCard = false, $saveCard = false, $imgTransfer="", $office_id = 0) {
			$cod_empresa = cod_empresa;
			$filtro = "";
			$filtro = ($tipoEnvio == "envio") ? " AND efp.is_delivery = 1 " : " AND efp.is_pickup = 1 ";
			
			if($hideCard)
			    $filtro .= " AND fp.cod_forma_pago NOT IN('T') ";
			if($office_id == 345){ //thesmartroll la marquesa
			    $filtro .= " AND fp.cod_forma_pago NOT IN('TB') ";
			}
			if($office_id == 324){ //Rollit quito
			    $filtro .= " AND fp.cod_forma_pago NOT IN('E') ";
			}
			
            $query = "SELECT efp.cod_forma_pago, efp.descripcion, efp.nombre, efp.is_delivery, efp.is_pickup
                        FROM tb_empresa_forma_pago efp, tb_formas_pago fp
                        WHERE efp.cod_forma_pago = fp.cod_forma_pago
                        AND efp.estado = 'A'
                        AND efp.cod_empresa = $cod_empresa
						$filtro 
						ORDER BY efp.posicion ASC";
            $resp = Conexion::buscarVariosRegistro($query);
            foreach($resp as $key => $item){
                $resp[$key]['descripcion'] = "";
				$resp[$key]['imagen'] = "";
				$resp[$key]['tipo'] = "";
				$resp[$key]['save_card'] = false;
                if($item['cod_forma_pago'] == "TB"){
					$resp[$key]['imagen'] = ($imgTransfer == "") ? url."transferencia_bancaria.png" : $imgTransfer;
                }else if($item['cod_forma_pago'] == "T"){
					$resp[$key]['save_card'] = $saveCard;
				}
            }
            return $resp;
        }

		public function getCourierInterprovincial(){
			$query = "SELECT c.cod_courier, c.nombre, c.tipo 
					FROM tb_empresa_courier ec
					INNER JOIN tb_courier c ON ec.cod_courier = c.cod_courier AND c.estado = 'A'
					WHERE cod_empresa = ".cod_empresa;
			return Conexion::buscarRegistro($query);
		}
        
        public function getProveedorBotonPagos(){
            $query = "SELECT p.*
                    FROM tb_empresa_botonpagos eb, tb_proveedor_botonpagos p 
                    WHERE eb.cod_proveedor_botonpagos = p.cod_proveedor_botonpagos
                    AND eb.estado = 'A' AND eb.cod_empresa = ".cod_empresa;
            return Conexion::buscarRegistro($query);
        }
        
        public function getInfoDatafast(){
            $query = "SELECT * FROM tb_empresa_datafast WHERE cod_empresa = ".cod_empresa;
            $resp = Conexion::buscarRegistro($query);
            return $resp;
        }
        
        public function getTokensDatafast(){
            $query = "SELECT * FROM tb_empresa_datafast WHERE cod_empresa = ".cod_empresa;
            $resp = Conexion::buscarRegistro($query);
            return $resp;
        }
        
        public function getTokensPaymentez($cod_sucursal){
            $query = "SELECT * FROM tb_empresa_sucursal_paymentez WHERE cod_sucursal = $cod_sucursal";
            $resp = Conexion::buscarRegistro($query);
            if(!$resp){
                $query = "SELECT * FROM tb_empresa_paymentez WHERE cod_empresa = ".cod_empresa;
                $resp = Conexion::buscarRegistro($query);
            }
            return $resp;
        }
        
		public function getTokensPayphone($cod_sucursal){
            $query = "SELECT * FROM tb_empresa_sucursal_payphone WHERE cod_sucursal = $cod_sucursal";
            $resp = Conexion::buscarRegistro($query);
            if(!$resp){
                $query = "SELECT * FROM tb_empresa_payphone WHERE cod_empresa = ".cod_empresa;
                $resp = Conexion::buscarRegistro($query);
            }
            return $resp;
        }

		public function getAppVersion($aplicacion){
			$query = "SELECT name, code, texto, obligatorio, aplicacion FROM tb_empresas_versiones_app 
					WHERE cod_empresa = ".cod_empresa."
					AND aplicacion = '$aplicacion'
					ORDER BY fecha_modificacion DESC LIMIT 0,1;";
			return Conexion::buscarRegistro($query);
		}

		public function getPermiso($identifier){
			$query = "SELECT * FROM tb_permisos_empresas WHERE identificador = '$identifier' AND cod_empresa = ".cod_empresa;
			return Conexion::buscarRegistro($query);
		}
		
		public function hasSaveCard(){
		    $cod_empresa = cod_empresa;
		    $query = "(
                SELECT 1 AS result FROM tb_empresa_sucursal_paymentez sp
                INNER JOIN tb_sucursales s ON sp.cod_sucursal = s.cod_sucursal
                WHERE s.cod_empresa = $cod_empresa AND sp.save_card = 1 LIMIT 1
            )
            UNION
            ( SELECT 1 AS result FROM tb_empresa_paymentez ep WHERE ep.cod_empresa = $cod_empresa AND ep.save_card = 1 LIMIT 1 )
            LIMIT 1;";
            $resp = Conexion::buscarRegistro($query);
            return $resp ? true : false;
		}
}
?>