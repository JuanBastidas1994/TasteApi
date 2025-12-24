<?php

class cl_sucursales
{
		var $cod_sucursal, $cod_empresa, $estado, $motivo_cierre = "";
		
		public function __construct($pcod_sucursal=null)
		{
			if($pcod_sucursal != null)
				$this->cod_sucursal = $pcod_sucursal;
		}

		public function lista($tipo=""){
			$filter = "";
			if($tipo=="pickup")
				$filter = " AND s.pickup = 1";
			if($tipo=="delivery")
				$filter = " AND s.delivery = 1";
			$query = "SELECT s.*
				FROM tb_sucursales as s
				WHERE s.estado IN('A') 
				AND s.cod_empresa = ".cod_empresa.$filter;
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $sucursal) {
                /*DISPONIBILIDAD*/
                $abierto = $this->disponibilidad($sucursal['cod_sucursal']);
                if($abierto){
                    $resp[$key]['hora_ini'] = horaFormat($abierto['hora_ini']);
                    $resp[$key]['hora_fin'] = horaFormat($abierto['hora_fin']);
                    $resp[$key]['abierto'] = true;
                }else{
                    $resp[$key]['prox_apertura'] = $this->proximaApertura($sucursal['cod_sucursal']);
					$resp[$key]['abierto'] = false;	
                }
                
				$resp[$key]['image'] = url.$sucursal['image'];
				$resp[$key]['banner_xl'] = ($sucursal['banner_xl'] !== "") ? url.$sucursal['banner_xl'] : "";
            }
            return $resp;
		}
		
		public function get($cod_sucursal){
		    $query = "SELECT cod_sucursal as id, nombre, direccion, latitud, longitud, distancia_km, hora_ini, hora_fin, telefono, correo, image, delivery, pickup, programar_pedido, transferencia_img, banner_xl, show_banner, estado, envio_grava_iva, cod_empresa, intervalo
		            FROM tb_sucursales WHERE estado = 'A' AND cod_sucursal = $cod_sucursal AND cod_empresa = ".cod_empresa;
		    $resp = Conexion::buscarRegistro($query);
		    if($resp){
		        /*DISPONIBILIDAD*/
		        $resp['cod_sucursal'] = $resp['id'];
				$resp['horarios'] = $this->getHorarios($cod_sucursal);
                $abierto = $this->disponibilidad($cod_sucursal);
                if($abierto){
                    $resp['hora_ini'] = $abierto['hora_ini'];
                    $resp['hora_fin'] = $abierto['hora_fin'];
                    $resp['abierto'] = true;
                }else    
                    $resp['abierto'] = false;
                    
                $resp['image'] = url.$resp['image'];
                $resp['transferencia_img'] = ($resp['transferencia_img'] !== "") ? url.$resp['transferencia_img'] : "";
                $resp['banner_xl'] = ($resp['banner_xl'] !== "") ? url.$resp['banner_xl'] : "";
		    }
		    return $resp;
		}

		public function getCourier($cod_sucursal){
			$query = "SELECT * FROM tb_sucursal_courier
					WHERE cod_sucursal = $cod_sucursal
					AND estado = 'A'
					ORDER BY prioridad ASC";
			return Conexion::buscarRegistro($query);
		}

		//Sucursales que estan dentro de un poligono o que estan dentro de la linea recta
		public function listaCobertura($latitud, $longitud){
			$cod_empresa = cod_empresa;
			$point = "Point($latitud $longitud)";
			/*is_inside
				1: Dentro del poligono
				2: No tiene poligonos, depende de los km a la redonda
			*/
			$query = "SELECT s.*, CONCAT(UCASE(LEFT(c.nombre, 1)), LCASE(SUBSTRING(c.nombre, 2))) as ciudad,
					IFNULL(st_distance(so.zone, ST_POINTFROMTEXT('$point')), 99) AS inside_polygon,
					IFNULL(MBRCONTAINS(so.zone, ST_POINTFROMTEXT('$point')),2) AS is_inside,
					(6378*acos(cos(radians($latitud))*cos(radians(s.latitud))*cos(radians(s.longitud)-radians($longitud))
					+sin(radians($latitud))*sin(radians(s.latitud))))AS distance 
					FROM tb_sucursales s
					LEFT JOIN tb_sucursal_cobertura so ON so.cod_sucursal=s.cod_sucursal
					LEFT JOIN tb_ciudades c ON s.cod_ciudad = c.cod_ciudad
					WHERE s.cod_empresa=$cod_empresa
					AND s.delivery = 1 
					AND s.estado='A'
					HAVING (inside_polygon <= 0) OR
							(is_inside = 2 AND distance < s.distancia_km)
					ORDER BY distance LIMIT 0,3";	
			$data = Conexion::buscarVariosRegistro($query);
			foreach ($data as $key => $sucursal) {
				$data[$key]['image'] = url.$sucursal['image'];
				$data[$key]['banner_xl'] = ($sucursal['banner_xl'] !== "") ? url.$sucursal['banner_xl'] : "";
				$data[$key]['distance'] = number_format($sucursal['distance'],3);
				$data[$key]['precio'] = number_format($this->getPrecio($sucursal['distance'], $sucursal['cod_sucursal']),2);
				$data[$key]['metodo_cobertura'] = "LINEA_RECTA";

				/*DISPONIBILIDAD*/
                $abierto = $this->disponibilidad($sucursal['cod_sucursal']);
                if($abierto){
                    $data[$key]['hora_ini'] = $abierto['hora_ini'];
                    $data[$key]['hora_fin'] = $abierto['hora_fin'];
                    $data[$key]['abierto'] = true;
                }else    
                    $data[$key]['abierto'] = false;
			}
			return $data;
		}

		public function getPrecio($distancia, $cod_sucursal){
			//Esquema de rangos
			$query = "SELECT sc.distancia_ini, sc.distancia_fin, sc.precio, s.envio_grava_iva, e.impuesto 
						FROM tb_sucursal_costo_envio_rango sc
						INNER JOIN tb_sucursales s ON sc.cod_sucursal = s.cod_sucursal
						INNER JOIN tb_empresas e ON s.cod_empresa = e.cod_empresa
						WHERE sc.cod_sucursal = $cod_sucursal";
			$data = Conexion::buscarVariosRegistro($query);
			if($data){
				foreach($data as $rango){
					if($distancia >= $rango['distancia_ini'] && $distancia < $rango['distancia_fin']){
						return $rango['envio_grava_iva'] == 1 ? $rango['precio'] * (1 + ($rango['impuesto'] / 100)) : $rango['precio'];
						// return $rango['precio'];
					}
				}
				return 0;
			}

			//Esquema basico
			$query = "SELECT * FROM tb_sucursal_costo_envio WHERE cod_sucursal = $cod_sucursal";
			$data = Conexion::buscarRegistro($query);
			if(!$data){
				$query = "SELECT * FROM tb_empresa_costo_envio WHERE cod_empresa = ".cod_empresa;
				$data = Conexion::buscarRegistro($query);
			}

			if($data){
				$base_km = floatval($data['base_km']);
				$base_dinero = floatval($data['base_dinero']);
				$adicional_km = floatval($data['adicional_km']);
				$distancia = floatval($distancia);

				if($distancia <= $base_km){
					return $base_dinero;
				}else{
					$kmExtras = $distancia - $base_km;
					return $base_dinero + ($kmExtras * $adicional_km);
				}
			}else{
				return 0;
			}
		}

		public function getConPrecio($cod_sucursal, $latitud, $longitud){

			$cod_empresa = cod_empresa;
			$point = "Point($latitud $longitud)";
			/*is_inside
				1: Dentro del poligono
				2: No tiene poligonos, depende de los km a la redonda
			*/
			$query = "SELECT s.*,
					IFNULL(st_distance(so.zone, ST_POINTFROMTEXT('$point')), 99) AS inside_polygon,
					IFNULL(MBRCONTAINS(so.zone, ST_POINTFROMTEXT('$point')),2) AS is_inside,
					(6378*acos(cos(radians($latitud))*cos(radians(s.latitud))*cos(radians(s.longitud)-radians($longitud))
					+sin(radians($latitud))*sin(radians(s.latitud))))AS distance 
					FROM tb_sucursales s
					LEFT JOIN tb_sucursal_cobertura so ON so.cod_sucursal=s.cod_sucursal
					WHERE s.cod_empresa=$cod_empresa
					AND s.cod_sucursal = $cod_sucursal
					AND s.delivery = 1 
					AND s.estado='A'
					HAVING (inside_polygon <= 0) OR
							(is_inside = 2 AND distance < s.distancia_km)";
			$data = Conexion::buscarRegistro($query);
			if($data){
			    $data['image'] = url.$data['image'];
				$data['distance'] = number_format($data['distance'],3);
				$data['precio'] = number_format($this->getPrecio($data['distance'], $cod_sucursal),2);
				$data['metodo_cobertura'] = "LINEA_RECTA";
				
				/*DISPONIBILIDAD*/
                $abierto = $this->disponibilidad($data['cod_sucursal']);
                if($abierto){
                    $data['hora_ini'] = $abierto['hora_ini'];
                    $data['hora_fin'] = $abierto['hora_fin'];
                    $data['abierto'] = true;
                }else    
                    $data['abierto'] = false;
                    
                //PROGRAMAR PEDIDO
                
                if($data['programar_pedido'] == 1){
                    $programar = $this->programarPedidos();
                    if($programar){
                        if($programar['programar_pedido'] == 0){
                            $data['programar_pedido'] = 0;
                        }else{
                            $data['programar_pedido_dias'] = $programar['dias'];
                        }
                    }
                }
			}
			return $data;
		}
		
		public function programarPedidos(){
		    $query = "SELECT programar_pedido, cant_dias_programar_pedido as dias FROM tb_empresas WHERE cod_empresa = ".cod_empresa;
		    return Conexion::buscarRegistro($query);
		}

		public function getHorarios($cod_sucursal){
			$query = "SELECT dia, hora_ini, hora_fin 
			FROM tb_sucursal_disponibilidad 
			WHERE cod_sucursal = $cod_sucursal";
			$data = Conexion::buscarVariosRegistro($query);
			foreach ($data as $key => $sucursal) {
				$nombreWeek = str_replace(array("0","1","2","3","4","5","6"),array("Lunes","Martes","Miércoles","Jueves","Viernes","Sábado","Domingo"), $sucursal['dia']);
				$data[$key]['dia'] = $nombreWeek;
				$data[$key]['hora_ini'] = fechaToFormat($sucursal['hora_ini'], "H:i");
				$data[$key]['hora_fin'] = fechaToFormat($sucursal['hora_fin'], "H:i");
			}
			return $data;
		}
		
		public function disponibilidad($cod_sucursal, $fecha=""){
		    if($fecha == ""){
			    $fecha = fecha();
			    $hora = hora();
		    }else{
		        list($dia, $hora) = explode(' ', $fecha);
		    }    
		    
			$query = "SELECT * FROM tb_sucursal_festivos
					WHERE cod_sucursal = $cod_sucursal
					AND fecha_inicio <= '$fecha' 
					AND fecha_fin >= '$fecha'";
			$row = Conexion::buscarRegistro($query);
			if($row){
			    $this->motivo_cierre = "El comercio ha pausado pedidos por el momento";
				return false;
			}

			
			$dia = dayOfWeek($fecha) - 1;
			$query = "SELECT * FROM tb_sucursal_disponibilidad
					WHERE cod_sucursal = $cod_sucursal
					AND dia = $dia
					AND hora_ini <= '$hora'
					AND hora_fin >= '$hora'";
			$row = Conexion::buscarRegistro($query);
			if(!$row){
				$this->motivo_cierre = "no disponible en este horario";
				if(fechaToFormat($fecha, 'Y-m-d') === fecha_only()){
					$this->motivo_cierre = "El comercio está cerrado";
				}
			    return false;
			}
			return $row;
		}
		
		public function getHorarioFecha($cod_sucursal, $fecha=""){
		    if($fecha == "")
			    $fecha = fecha();
			
			 $dia = dayOfWeek($fecha) - 1;
			 $query = "SELECT dia, hora_ini, hora_fin FROM tb_sucursal_disponibilidad
					WHERE cod_sucursal = $cod_sucursal
					AND dia = $dia";
			 return Conexion::buscarRegistro($query);
		}
		
		public function datetimeDisponibilidad($cod_sucursal, $fecha, $hora){
		    $fecha = $fecha." ".$hora;
		    $query = "SELECT * FROM tb_sucursal_festivos
					WHERE cod_sucursal = $cod_sucursal
					AND fecha_inicio <= '$fecha' 
					AND fecha_fin >= '$fecha'";
			$row = Conexion::buscarRegistro($query);
			if(!$row)
			    return true;
			return false;
		}
		
		public function proximaApertura($cod_sucursal){
		    $fecha = fecha();
		    $hora = hora();
		    $dia = dayOfWeek($fecha) - 1;
		    $query = "SELECT * FROM tb_sucursal_festivos
					WHERE cod_sucursal = $cod_sucursal
					AND fecha_inicio <= '$fecha' 
					AND fecha_fin >= '$fecha'";
			$restriccion = Conexion::buscarRegistro($query);
			if($restriccion){
			    list($fecha_restriccion, $hora_restriccion) = explode(' ', $restriccion['fecha_fin']);
			    $query = "SELECT * FROM tb_sucursal_disponibilidad
					WHERE cod_sucursal = $cod_sucursal
					AND dia = $dia
					AND hora_ini <= '$hora_restriccion'
					AND hora_fin >= '$hora_restriccion'";
			    if(Conexion::buscarRegistro($query)){
			        list($hora, $minuto, $segundo) = explode(':', $hora_restriccion);
			        return $hora.":".$minuto;
			    }
			}
			
			$query = "SELECT * FROM tb_sucursal_disponibilidad 
                    WHERE dia = $dia 
                    AND cod_sucursal = $cod_sucursal
                    AND hora_ini > '$hora'";
            $row = Conexion::buscarRegistro($query);
            if($row){
                
                $hora_apertura = $row['hora_ini'];
			    list($hora, $minuto, $segundo) = explode(':', $row['hora_ini']);
			    
			 //   $format = ($hora >= 12) ? "PM" : "AM";
			    return "Hoy ".$hora.":".$minuto;
            }else{
                $query = "SELECT * FROM tb_sucursal_disponibilidad 
                            WHERE cod_sucursal = $cod_sucursal
                            AND dia > $dia
                            UNION 
                            SELECT * FROM tb_sucursal_disponibilidad 
                            WHERE cod_sucursal = $cod_sucursal";
    			$row = Conexion::buscarRegistro($query);
    			if($row){
    			    $dia_apertura = $row['dia'];
    			    $hora_apertura = $row['hora_ini'];
    			    list($hora, $minuto, $segundo) = explode(':', $row['hora_ini']);
    			    
    			 //   $format = ($hora >= 12) ? "PM" : "AM";
    			    
    			    $textDate = "Mañana";
    			    $dia = ($dia == 6) ? -1 : $dia; //Reiniciar si es domingo
    			    if(($dia + 1) != $dia_apertura){
    			        $dias_ES = array("Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado", "Domingo");
    			        $textDate = $dias_ES[$dia_apertura];
    			    }
    			    
    			    return $textDate." ".$hora.":".$minuto;
    			}
            }
		}


		/*FUNCIONES GOOGLE MAPS*/
		 
		 public function getDistanciaRutaGoogle($suc_lat, $suc_lon, $des_lat, $des_lon){
			 $origen = "&origin=".$suc_lat.",".$suc_lon;
			 $destino = "&destination=".$des_lat.",".$des_lon; 
			 
			 $url = "https://maps.googleapis.com/maps/api/directions/json?mode=driving&key=AIzaSyAWo6DXlAmrqEiKiaEe9UyOGl3NJ208lI8".$origen.$destino;
			 //echo $url;
			 $ch = curl_init($url);
			 $headers = array();
			 $headers[] = 'Content-Type: application/json';
		   
			 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
			 curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
			 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
			 $response = curl_exec($ch);
			 curl_close($ch);
			 $data = json_decode($response, true);
			 if(isset($data['routes'][0]['legs'])){
				 $legs = $data['routes'][0]['legs'];
				 if(count($legs)>0){
					 $resp['distancia'] = $legs[0]['distance']['value'];
					 $resp['tiempo'] = $legs[0]['duration']['text'];
					 return $resp;
				 }
				 else
					 return false;
			 }else{
				 return false;
			 }
		 }

		public function getSucursalTiempoProgramar($cod_sucursal, $type) {
		    $type = in_array(strtolower($type), ['delivery', 'd', 'envio']) ? "DELIVERY" : "PICKUP";
			$query = "SELECT * 
						FROM tb_sucursal_tiempo_programar
						WHERE cod_sucursal = $cod_sucursal";
			return Conexion::buscarRegistro($query);
		}

		public function withDelivery() {
			$cod_empresa = cod_empresa;
			$query = "SELECT *
						FROM tb_sucursales
						WHERE estado = 'A'
						AND delivery = 1
						AND cod_empresa = $cod_empresa";
			return Conexion::buscarVariosRegistro($query);
		}

		public function getSucursal($cod_sucursal) {
			$query = "SELECT *
						FROM tb_sucursales WHERE cod_sucursal = $cod_sucursal 
						AND estado = 'A'";
			$data = Conexion::buscarRegistro($query);
			$data['image'] = url.$data['image'];

			$abierto = $this->disponibilidad($data['cod_sucursal']);
			if($abierto){
				$data['hora_ini'] = $abierto['hora_ini'];
				$data['hora_fin'] = $abierto['hora_fin'];
				$data['abierto'] = true;
			}else    
				$data['abierto'] = false;


			//PROGRAMAR PEDIDO
			if($data['programar_pedido'] == 1){
				$programar = $this->programarPedidos();
				if($programar){
					if($programar['programar_pedido'] == 0){
						$data['programar_pedido'] = 0;
					}else{
						$data['programar_pedido_dias'] = $programar['dias'];
					}
				}
			}

			return $data;
		}
		
		public function getProgramarPedido($cod_sucursal){
			$programar = $this->programarPedidos();
			if($programar){
				if($programar['programar_pedido'] == 0 || $programar['dias'] == 0)
					return false;
			}
			$diasMax = $programar['dias'];
			$dias=[];
			$fechaProg = fecha_only();
			for($x=0; $x<$diasMax; $x++){
			    $d['dia'] = $fechaProg;
                $d['diaTexto'] = fechaLatinoShortWeekday($fechaProg);
                $d['num_dia'] = dayOfWeek($fechaProg) - 1;
                // $d['diaTexto'] = $fechaProg;
                $dias[$x] = $d;
                $fechaProg = $this->fechaXDiasMasDate($fechaProg, 1);
			}
			
			return $dias;
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
        
        public function getPaymentTokens($cod_sucursal, &$proveedor){
            //Paymentez
            $proveedor = 2;
            $query = "SELECT ambiente, client_code, client_key, save_card FROM tb_empresa_sucursal_paymentez WHERE cod_sucursal = $cod_sucursal";
            $resp = Conexion::buscarRegistro($query);
            if($resp) return $resp;
            
            //TODO: Se requiere que esta tabla ya no se use
            $query = "SELECT ambiente, client_code, client_key, save_card FROM tb_empresa_paymentez WHERE cod_empresa = ".cod_empresa;
            $resp = Conexion::buscarRegistro($query);
            if($resp) return $resp;
            
            //Datafast
            $proveedor = 1;
            $query = "SELECT *, 0 AS save_card FROM tb_empresa_sucursal_datafast WHERE estado='A' AND cod_sucursal = $cod_sucursal";
            $resp = Conexion::buscarRegistro($query);
            if($resp) return $resp;
            
            //Payphone
            $proveedor = 3;
            $query = "SELECT *, 0 AS save_card FROM tb_empresa_sucursal_payphone WHERE estado='A' AND cod_sucursal = $cod_sucursal";
            $resp = Conexion::buscarRegistro($query);
            if($resp) return $resp;
            
            $proveedor = 0;
            return null;
        }
		
		function fechaXDiasMasDate($fecha, $num){
             return date("Y-m-d",strtotime($fecha."+ ".$num." days"));
        }

		public function getBasic($cod_sucursal){
		    $query = "SELECT cod_sucursal, nombre, direccion, latitud, longitud, distancia_km, hora_ini, hora_fin, telefono, correo, image, delivery, pickup, programar_pedido, transferencia_img, banner_xl, show_banner, estado
		            FROM tb_sucursales WHERE estado = 'A' AND cod_sucursal = $cod_sucursal AND cod_empresa = ".cod_empresa;
		    return Conexion::buscarRegistro($query);
		}

		public function getSucursalEnvioGravaIVA($cod_sucursal) {
			$query = "SELECT envio_grava_iva 
				FROM tb_sucursales 
				WHERE cod_sucursal = $cod_sucursal";
			$resp = Conexion::buscarRegistro($query);
			// return 0;
			if($resp) 
				return $resp['envio_grava_iva'];
			return 0;
		}
		
		public function getAltaDemanda($cod_sucursal, $pfecha = ""){
		    $fecha = ($pfecha === "") ? fecha() : $pfecha;
		    $query = "SELECT * FROM tb_sucursal_alta_demanda
					WHERE cod_sucursal = $cod_sucursal
					AND fecha_inicio <= '$fecha' 
					AND fecha_fin >= '$fecha'";
			$row = Conexion::buscarRegistro($query);
			if($row){
			    if(cod_empresa ==141 || cod_empresa == 70){
			        $row['texto'] = "Puede haber una ligera demora, gracias por tu paciencia codicioso.";
			    }else{
			        $row['texto'] = "Puede haber una ligera demora, gracias por tu paciencia.";
			    }
			}
			
			return $row;
			
		}
}
?>