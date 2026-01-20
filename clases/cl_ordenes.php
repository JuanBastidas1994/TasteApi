<?php

class cl_ordenes
{
		var $session;
		var $cod_producto, $cod_producto_padre, $cod_empresa, $alias, $nombre, $desc_corta, $desc_larga, $image_min, $image_max, $fecha_create, $user_create, $estado, $precio, $codigo;

		var $porcentajeIva = 15;
		var $sucursal_envio_grava_iva = 1;

		public function __construct($pcod_producto=null)
		{
			if($pcod_producto != null)
				$this->cod_producto = $pcod_producto;
		}

		public function lista(){
			$query = "SELECT ca.*, u.nombre, u.apellido
						FROM tb_orden_cabecera ca, tb_usuarios u
						WHERE ca.cod_usuario = u.cod_usuario 
						AND ca.cod_usuario = ".$this->session['cod_usuario']."
						AND ca.cod_empresa = ".cod_empresa." ORDER BY ca.cod_orden DESC";
            $resp = Conexion::buscarVariosRegistro($query);
            return $resp;
		}

		public function listaByUser($cod_usuario){
		    $tipoEntrega = [0=>'Pickup', 1=> 'Delivery', 2=>'En mesa'];
            $query = "SELECT ca.cod_orden, ca.fecha, ca.total, ca.estado, ca.is_envio,
                            u.nombre, u.apellido, u.correo, u.telefono, u.imagen, u.num_documento, s.nombre as sucursal
						FROM tb_orden_cabecera ca, tb_usuarios u, tb_sucursales s
						WHERE ca.cod_usuario = u.cod_usuario
                        AND ca.cod_sucursal = s.cod_sucursal 
                        AND ca.estado NOT IN ('CANCELADA', 'RECHAZADA', 'CREADA')
                        AND ca.cod_usuario = ".$cod_usuario."
                        AND ca.cod_empresa = ".cod_empresa."
                        ORDER BY ca.cod_orden DESC LIMIT 0,20";            
            $resp = Conexion::buscarVariosRegistro($query);
            if ($resp){
	             foreach ($resp as $key => $detalle) {
	             	$resp[$key]['fecha'] = hoursAgo($detalle['fecha']);
	             	$resp[$key]['num_items'] = $this->numItemsOrder($detalle['cod_orden']);
	             	$resp[$key]['calificacion'] = $this->calificationOrder($detalle['cod_orden']);
	             	$resp[$key]['tipo_envio'] = $tipoEntrega[$detalle['is_envio']] ?? 'Desconocido';
				}	
            }        				     
            return $resp;
		}
		
		public function numItemsOrder($cod_orden){
		    $query = "SELECT COUNT(cod_orden_detalle) as num_items FROM tb_orden_detalle WHERE cod_orden = $cod_orden";
		    $resp = Conexion::buscarRegistro($query);
		    if($resp){
		        return intval($resp['num_items']);
		    }
		    else
		        return 0;
		}
		
		public function calificationOrder($cod_orden){
		    $query = "SELECT * FROM tb_orden_calificacion WHERE cod_orden = $cod_orden";
		    $resp = Conexion::buscarRegistro($query);
		    if($resp){
		        return $resp['calificacion'];
		    }else
		        return 0;
		}
		
		
		
		public function listaDetalle($cod_orden){		
			$query = "SELECT d.*, p.nombre, p.image_min FROM tb_orden_cabecera ca,tb_orden_detalle d,tb_productos p
					WHERE ca.cod_orden = d.cod_orden AND d.cod_producto= p.cod_producto AND d.cod_orden =".$cod_orden;
            $resp = Conexion::buscarVariosRegistro($query);   
            foreach ($resp as $key => $item) {
                $resp[$key]['image_min'] = url.$item['image_min'];
                $resp[$key]['opciones'] = json_decode($item['descripcion'], true);
                unset($resp[$key]['descripcion']);
            }    
            return $resp;
		}

		public function listaTracker($cod_usuario){
			$query = "SELECT ca.*, u.nombre, u.apellido, s.nombre as sucursal
						FROM tb_orden_cabecera ca, tb_usuarios u, tb_sucursales s
						WHERE ca.cod_usuario = u.cod_usuario 
						AND ca.cod_sucursal = s.cod_sucursal
						AND ca.cod_usuario = $cod_usuario
						AND ca.cod_empresa = ".cod_empresa." 
						AND ca.estado NOT IN('RECHAZADA') ORDER BY ca.cod_orden DESC LIMIT 0,5";
            $resp = Conexion::buscarVariosRegistro($query);
            return $resp;
		}

		public function getOrdenTracker($cod_orden){
			$query = "SELECT c.cod_orden, c.cod_sucursal, c.cod_usuario, s.nombre as sucursal, s.direccion, s.telefono, s.transferencia_img, s.latitud as latitud_sucursal, s.longitud as longitud_sucursal, 
			                c.total, c.estado, c.fecha, c.latitud, c.longitud, c.is_envio, c.hora_retiro as fecha_retiro, c.cod_courier as courier, c.order_token as token_courier, c.pago, c.is_altademanda 
		            FROM tb_orden_cabecera c, tb_sucursales s 
		            WHERE c.cod_sucursal = s.cod_sucursal
		            AND c.cod_orden = $cod_orden
					AND c.cod_empresa = ".cod_empresa;
			$resp = Conexion::buscarRegistro($query);
			return $resp;
		}
		
		public function getMotorizadoByOrder($cod_orden){
		    $query = "SELECT u.nombre, u.apellido, u.telefono, u.imagen, u.latitud, u.longitud, u.fecha_ubicacion, u.placa
			              FROM tb_motorizado_asignacion m, tb_usuarios u
			              WHERE m.cod_motorizado = u.cod_usuario
			              AND m.cod_orden = ".$cod_orden;
		     $tracking = Conexion::buscarRegistro($query);
		     if($tracking){
		         $tracking['imagen'] = url.$tracking['imagen'];
		     }else   
		        return null;
		        
		    return $tracking;
		}
		
		public function getPaymentsShowUser($cod_orden){
		    $query = "SELECT cod_orden, forma_pago, monto FROM tb_orden_pagos WHERE cod_orden = $cod_orden AND forma_pago IN('E','TB', 'T')";
		    return Conexion::buscarRegistro($query);
		}
		
		public function getHistorialByOrder($cod_orden){
		    $query = "SELECT h.estado, h.fecha
		            FROM tb_orden_historial h,tb_orden_cabecera o 
		            WHERE o.cod_orden = h.cod_orden 
		            AND h.estado IN ('ENTRANTE','ASIGNADA','ACEPTADA', 'PREPARANDO','ENVIANDO','ENTREGADA','NO_ENTREGADA', 'CANCELADA') 
		            AND h.cod_orden = $cod_orden"." ORDER BY h.fecha ASC";
			$resp = Conexion::buscarVariosRegistro($query);
			return $resp;
		}
		
		public function getMotivoAnulacion($cod_orden){
		    $query = "SELECT *
			              FROM tb_orden_cancelacion
			              WHERE cod_orden = ".$cod_orden;
		     $cancelacion = Conexion::buscarRegistro($query);
		     if($cancelacion){
		         return $cancelacion['motivo'];
		     }else
		        return "";
		}
		
		//Get Preorden
		public function getPreOrden($PreOrdenId){
		    $query = "SELECT pj.* 
                        FROM tb_preorden_json pj, tb_usuarios u
                        WHERE pj.cod_usuario = u.cod_usuario
                        AND pj.cod_preorden = $PreOrdenId
                        AND u.cod_empresa = ".cod_empresa;
		    return Conexion::buscarRegistro($query);
		}
		
		//Get Preorden
		public function getPreOrdenByPaymentId($paymentId){
		    $query = "SELECT pj.* 
                        FROM tb_preorden_json pj, tb_usuarios u
                        WHERE pj.cod_usuario = u.cod_usuario
                        AND pj.paymentId = '$paymentId'
                        AND u.cod_empresa = ".cod_empresa;
		    return Conexion::buscarRegistro($query);
		}
		
		//Guardar PreOrden
		public function saveJson($cod_usuario, $json, $amount){
		    $fecha = fecha();
		    $query = "INSERT INTO tb_preorden_json (cod_usuario,json,fecha_create, amount, estado)
		        values ('$cod_usuario','$json','$fecha', $amount, 'VALIDADA')";
        	if(Conexion::ejecutar($query,NULL)){
        		$id = Conexion::lastId();
        		return $id;
        	}else{
        		return false;
        	}
		}
		
		//PreOrden set PaymentId
		public function setPaymentIdPreOrden($PreOrdenId, $paymentId){
		    $query = "UPDATE tb_preorden_json SET paymentId = '$paymentId' WHERE cod_preorden = $PreOrdenId";
		    return Conexion::ejecutar($query,NULL);
		}
		
		//PreOrden creando orden
		public function convertingPreOrden($PreOrdenId, $paymentId, $paymentAuth){
		    $fecha = fecha();
		    $query = "UPDATE tb_preorden_json SET estado = 'CREANDO_ORDEN', paymentId = '$paymentId', paymentAuth = '$paymentAuth', fecha_update = '$fecha' WHERE cod_preorden = $PreOrdenId";
		    return Conexion::ejecutar($query,NULL);
		}
		
		//Cambiar Estado PreOrden
		public function setStatusPreorden($PreOrdenId, $status, $cod_orden = 0, $motivo = ""){
		    $fecha = fecha();
		    $query = "UPDATE tb_preorden_json SET estado = '$status', cod_orden = $cod_orden, fecha_update = '$fecha' WHERE cod_preorden = $PreOrdenId";
		    return Conexion::ejecutar($query,NULL);
		}
		
		//Falla PreOrden
		public function failurePreOrden($PreOrdenId, $paymentId, $paymentAuth, $motivo){
		    $fecha = fecha();
		    $query = "UPDATE tb_preorden_json SET estado = 'FALLADA', paymentId = '$paymentId', paymentAuth = '$paymentAuth', motivo_fallo = '$motivo', fecha_update = '$fecha' WHERE cod_preorden = $PreOrdenId";
		    return Conexion::ejecutar($query,NULL);
		}
		
		//Cerro modal PreOrden
		public function closePreOrden($PreOrdenId){
		    $fecha = fecha();
		    $query = "UPDATE tb_preorden_json SET estado = 'CERRADA', fecha_update = '$fecha' WHERE cod_preorden = $PreOrdenId";
		    return Conexion::ejecutar($query,NULL);
		}
        
        //Crear Orden
		public function crear($checkout, $cod_usuario, &$id){
			try {
				$con = Conexion::obtenerConexion();
				$con->beginTransaction();

				$cod_empresa = cod_empresa;
				$cod_sucursal = $checkout['cod_sucursal'];
				$is_envio = 0;
				$telefono = (isset($checkout['telefono'])) ? $checkout['telefono'] : "";
				$cupon = (isset($checkout['cupon'])) ? $checkout['cupon'] : "";
				$comentarios = (isset($checkout['comentarios'])) ? str_replace("'", "", $checkout['comentarios']) : "";
				$origen = (isset($checkout['origen'])) ? $checkout['origen'] : "API";
				$envio = (isset($checkout['envio'])) ? number_format(floatval($checkout['envio']),2) : "";
				$service = (isset($checkout['service'])) ? $checkout['service'] : 0;
				$altaDemanda = (isset($checkout['alta_demanda'])) ? $checkout['alta_demanda'] : 0;
				$base0 = number_format($checkout['base0'],2);
				$base12 = number_format($checkout['base12'],2);
				$subtotal = number_format($checkout['subtotal'],2);
				$descuento = number_format($checkout['descuento'],2);
				$iva = number_format($checkout['iva'],2);
				$total = number_format($checkout['total'],2);
				$fecha = fecha();
				$iva_porcentaje = $this->porcentajeIva;
				$envio_iva = $this->sucursal_envio_grava_iva == 1 ? number_format(floatval($checkout['envio'] - ($checkout['envio'] - ($checkout['envio'] / (1 + ($iva_porcentaje / 100))))),2) : 0;

				//ENVIO
				$MetodoEnvio = $checkout['metodoEnvio'];
				$latitud = "";
				$longitud = "";
				$hora = "";
				$programado = 0;
				$direccion = "";
				$referencia = "";
				$ciudad = 0;
				$distancia=0;
				$tipoEnvio = $MetodoEnvio['tipo'];
				$express = 0;
				if($tipoEnvio=="delivery"){
					$is_envio = 1;
					$latitud = $MetodoEnvio['lat'];
					$longitud = $MetodoEnvio['lng'];
				// 	$hora = (isset($MetodoEnvio['hora'])) ? $MetodoEnvio['hora'] : $fecha;
					$hora = $MetodoEnvio['hora'] ?? $fecha;
					$programado = (isset($MetodoEnvio['programado'])) ? $MetodoEnvio['programado'] : 0;
					$express = (isset($MetodoEnvio['express'])) ? $MetodoEnvio['express'] : 0;
					$direccion = (isset($MetodoEnvio['direccion'])) ? str_replace("'", "", $MetodoEnvio['direccion']) : "";
					$referencia = (isset($MetodoEnvio['referencia'])) ? str_replace("'", "", $MetodoEnvio['referencia']) : "";
					if($envio == "")
						$envio = number_format(floatval($MetodoEnvio['precio']),2);
					$distancia = (isset($MetodoEnvio['distancia'])) ? $MetodoEnvio['distancia'] : 0;
						
					//Ciudad
					$ciudad = (isset($MetodoEnvio['city_id'])) ? $MetodoEnvio['city_id'] : 0;
				}else if($tipoEnvio=="onsite"){
				    $is_envio = 2; //Para pedidos en mesa
					$envio = 0;
					$hora = $fecha;
					$programado = 0;
				}else{
				    $envio = 0;
				    $hora = $MetodoEnvio['hora'] ?? $fecha;
					$programado = (isset($MetodoEnvio['programado'])) ? $MetodoEnvio['programado'] : 0;
				}

				//FORMA DE PAGO
				$forma_pago = $checkout['metodoPago'][0]['tipo']; 	//PRIMERA FORMA DE PAGO
				$is_suelto = 0;
				$monto_suelto = 0;
				$MetodoPago = $checkout['metodoPago'];
				foreach ($MetodoPago as $pago) {
					if($pago['tipo'] == "E"){
						$is_suelto = (isset($MetodoPago['is_suelto'])) ? $MetodoPago['is_suelto'] : 0;
						$monto_suelto = (isset($MetodoPago['monto_suelto'])) ? $MetodoPago['monto_suelto'] : 0;
					}
				}

				$api_version = api_version;
				$query = "INSERT INTO tb_orden_cabecera(cod_empresa, cod_sucursal, cod_usuario, fecha, subtotal0, subtotal12, subtotal, descuento, envio, iva, service, total, cod_descuento, is_envio, pago, telefono, referencia, referencia2, estado, latitud, longitud, distancia, is_suelto, monto_suelto, hora_retiro, is_programado, is_express, observacion, medio_compra, api_version, iva_porcentaje, envio_iva, is_altademanda) ";
				$query.= "VALUES($cod_empresa, $cod_sucursal, $cod_usuario, '$fecha', $base0, $base12, $subtotal, $descuento, $envio, $iva, $service, $total, '$cupon', $is_envio, '$forma_pago', '$telefono','$direccion', '$referencia', 'ENTRANTE','$latitud', '$longitud', '$distancia', $is_suelto, $monto_suelto, '$hora', '$programado', $express, '$comentarios', '$origen', '$api_version', $iva_porcentaje, $envio_iva, $altaDemanda)";
				if(Conexion::ejecutar($query,NULL)){
					$id = Conexion::lastId();
					
					if(fidelizacion){
						$queryPunto = "INSERT INTO tb_orden_puntos(cod_orden) VALUES($id)";
						Conexion::ejecutar($queryPunto,NULL);
					}

					/*GUARDAR FORMAS DE PAGO*/
					$MetodoPago = $checkout['metodoPago'];
					foreach ($MetodoPago as $pago) {
						$tipo = $pago['tipo'];
						$monto = $pago['monto'];
						$ob = "";
						$auth = "";
						$pro = 0;
						$lote = "";
						if($tipo == "T"){
							if(isset($checkout['paymentId'])){
								$ob = $checkout['paymentId'];
								$pro = $checkout['paymentProveedor'];
								$auth = (isset($checkout['paymentAuth'])) ? $checkout['paymentAuth'] : "";
							}
							if(isset($checkout['lot_number'])) {
								$lote = $checkout['lot_number'];
							}
						}
						$queryPago = "INSERT INTO tb_orden_pagos(cod_orden, forma_pago, monto, observacion, observacion2, cod_proveedor_botonpagos, lote)
									VALUES($id, '$tipo', $monto, '$ob', '$auth', $pro, '$lote')";
						Conexion::ejecutar($queryPago,NULL);	
					}

					/*GUARDAR PRODUCTOS*/
					$items = $checkout['productos'];
					foreach ($items as $producto) {
						if(isset($producto['id']))
							$codigo=$producto['id'];
						else
							$codigo=$producto['cod_producto'];
						$cantidad=$producto['cantidad'];
						$descuento = 0;
						if(isset($producto['descuento']))
							$descuento = $producto['descuento'];
						$descuentoPorcentaje = 0;
						if(isset($producto['descuentoPorcentaje']))
							$descuentoPorcentaje = $producto['descuentoPorcentaje'];
						$descripcion="";
						if(isset($producto['opciones']))
							$descripcion = json_encode($producto['opciones'], JSON_UNESCAPED_UNICODE);
						$comentario_producto = "";
						if(isset($producto['comentarios']))
							$comentario_producto = $producto['comentarios'];
						$adicional_total = (isset($producto['adicional_total'])) ? $producto['adicional_total'] : 0;
						$adicional_no_tax = (isset($producto['adicional_no_tax'])) ? $producto['adicional_no_tax'] : 0;
						$adicional_no_tax_total = (isset($producto['adicional_no_tax_total'])) ? $producto['adicional_no_tax_total'] : 0;
						
						$desc_text = "";
						$precio = $producto['precio'];
						$precio_no_tax = $producto['precio_no_tax'];
						$base0 = $producto['base0'];
						$base12 = $producto['base12'];
						$subtotal0 = $producto['subtotal0'];
						$subtotal12 = $producto['subtotal12'];

						$precio_total = $cantidad * $precio;
						if($descuento > 0){
							$precio_total = $precio_total - $descuento;
							$desc_text = (isset($producto['promocion'])) ? $producto['promocion'] : "";
						}
						
						Conexion::ejecutar("SET NAMES 'utf8mb4'", NULL);
				// 		$queryDetalle = "INSERT INTO tb_orden_detalle(cod_orden, cod_producto, descripcion, comentarios, precio, precio_no_tax, cantidad, base_0, base_12, descuento_porcentaje, descuento, subtotal_12, subtotal_0, desc_text, precio_final, adicional_total, adicional_no_tax_unidad, adicional_no_tax_total) ";
				// 		$queryDetalle.= "VALUES($id, $codigo, '$descripcion', '$comentario_producto', $precio, $precio_no_tax, $cantidad, $base0, $base12, $descuentoPorcentaje, $descuento, $subtotal12, $subtotal0, '$desc_text', $precio_total, $adicional_total, $adicional_no_tax, $adicional_no_tax_total)";
				// 		Conexion::ejecutar($queryDetalle,NULL);
        				$sql = "INSERT INTO tb_orden_detalle(
                            cod_orden, cod_producto, descripcion, comentarios, precio, precio_no_tax,
                            cantidad, base_0, base_12, descuento_porcentaje, descuento,
                            subtotal_12, subtotal_0, desc_text, precio_final, adicional_total, 
                            adicional_no_tax_unidad, adicional_no_tax_total
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                        
                        $data = [
                            $id,
                            $codigo,
                            $descripcion,
                            $comentario_producto,
                            $precio,
                            $precio_no_tax,
                            $cantidad,
                            $base0,
                            $base12,
                            $descuentoPorcentaje,
                            $descuento,
                            $subtotal12,
                            $subtotal0,
                            $desc_text,
                            $precio_total,
                            $adicional_total,
                            $adicional_no_tax,
                            $adicional_no_tax_total
                        ];
                        
                        Conexion::ejecutar($sql, $data);
						//mylog($queryDetalle, "SAVE_ORDEN_DETALLE");

					}
					
					/*GUARDAR CIUDAD*/
					if($latitud == 0 && $longitud == 0 && $ciudad !== 0){
					    $queryCiudad = "INSERT INTO tb_orden_destino(cod_orden, cod_ciudad) VALUES($id, $ciudad)";
					    Conexion::ejecutar($queryCiudad,NULL);
					}

					/*DISMINUIR CUPON*/
					if($cupon !== ""){
						$queryCupon = "UPDATE tb_codigo_promocional
						SET usos_restantes = usos_restantes - 1
						WHERE codigo = '$cupon' AND cod_empresa = ".cod_empresa;
						Conexion::ejecutar($queryCupon, NULL);
					}

					$con->commit();
					return true;
				}else{
					return false;
				}
			} catch (\Throwable $th) {
				$con->rollBack();
				return false;
			}
		}

		public function addFreeProductToOrder($product_id, $quantity, $order_id){ 
		    $queryAdd = "INSERT INTO tb_orden_detalle(cod_orden, cod_producto, descripcion, comentarios, precio, precio_no_tax, cantidad, base_0, base_12, 
								descuento_porcentaje, descuento, subtotal_12, subtotal_0, desc_text, precio_final, adicional_total, adicional_no_tax_unidad, adicional_no_tax_total) ";
			$queryAdd.= "VALUES($order_id, $product_id, '', '', 0, 0, $quantity, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0)";
			Conexion::ejecutar($queryAdd,NULL);
		}

		public function getFreePromo($office_id, $type='WEB'){
			$filter = ($type == 'WEB') ? 'AND is_web = 1' : 'AND is_app = 1';

			$fecha = fecha();
			$query = "SELECT * FROM tb_promocion_producto_gratis WHERE estado = 'A' 
						AND fecha_inicio <= '$fecha' AND fecha_fin >= '$fecha'
						AND cod_sucursal = $office_id $filter";
			return Conexion::buscarRegistro($query);
		}
		
		public function applyFreePromo($office_id, $totalWithoutEnvio, $type='WEB'){
			$filter = ($type == 'WEB') ? 'AND is_web = 1' : 'AND is_app = 1';

			$fecha = fecha();
			$query = "SELECT * FROM tb_promocion_producto_gratis WHERE estado = 'A' 
						AND fecha_inicio <= '$fecha' AND fecha_fin >= '$fecha'
						AND monto_minimo <= $totalWithoutEnvio 
						AND cod_sucursal = $office_id $filter";
			return Conexion::buscarRegistro($query);
		}

		public function addOrdenFirebase($id, $sucursal, $total, $forma_pago, $envio, $minutos, $sonar = true, $autoAsignar = true) {
			$ProyectId = "ptoventa-3b5ed";
	        $data = '{"estado":"ENTRANTE","id":'.$id.',"sucursal":'.$sucursal.',"total":'.$total.',"forma_pago":"'.$forma_pago.'","envio":"'.$envio.'", "sonar": '.$sonar.', "auto_asignar": '.$autoAsignar.', "minutos": "'.$minutos.'"}';
	        try {
	        	$ch = curl_init("https://".$ProyectId.".firebaseio.com/ordenes/".alias."/".$id.".json");
		        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");                                                                     
		        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);   
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
		        $response = curl_exec($ch);
		        if(curl_errno($ch)){
		        	return curl_errno($ch);
		        }
		        curl_close($ch);
		        return $response;
	        } catch (Exception $e) {
	        	return false;
	        }
		}
		
		/*--NUEVO--*/
		public function set_estado($cod_orden, $estado){
			$query = "UPDATE tb_orden_cabecera SET estado='$estado' WHERE cod_orden = $cod_orden";
        	if(Conexion::ejecutar($query,NULL)){
				/*--NUEVO--*/
        	    $date = date("Y-m-d H:i:s");
        	    $this->orderHistorial($cod_orden, $estado, $date);
        	    /*--NUEVO--*/
        		return true;
        	}else{
        		return false;
        	}
		}
		/*--NUEVO--*/

		public function orderHistorial($cod_orden, $estado,$fecha){
		    $query = "INSERT INTO tb_orden_historial (cod_orden,estado,fecha)
		        values ('$cod_orden','$estado','$fecha')";
        	if(Conexion::ejecutar($query,NULL)){
        		return true;
        	}else{
        		return false;
        	}
		}
		
		
		public function get($cod_orden){
			$query = "SELECT * FROM tb_orden_cabecera WHERE cod_orden = $cod_orden";
			return Conexion::buscarRegistro($query);
		}

		public function get_orden_array($cod_orden){
			$query = "SELECT oc.cod_orden, oc.fecha, oc.subtotal, oc.descuento, oc.envio, oc.service, oc.iva, oc.total, oc.estado, oc.is_envio, oc.is_programado, oc.hora_retiro, oc.referencia, oc.referencia2, u.nombre, u.apellido, u.correo, u.telefono, s.nombre as sucursal, s.direccion as sucursal_direccion
						FROM tb_orden_cabecera oc, tb_usuarios u, tb_sucursales s
						WHERE oc.cod_usuario = u.cod_usuario
						AND oc.cod_sucursal = s.cod_sucursal 
						AND oc.cod_orden = $cod_orden
						AND oc.cod_empresa = ".cod_empresa;
            $resp = Conexion::buscarRegistro($query);
            if($resp){
                $resp['calificacion'] = $this->calificationOrder($resp['cod_orden']);
				$resp['detalle'] = $this->listaDetalle($cod_orden);

				$query = "SELECT p.forma_pago, p.monto, p.observacion, p.observacion2, f.descripcion
							FROM tb_orden_pagos p, tb_formas_pago f
							WHERE p.forma_pago = f.cod_forma_pago
							AND p.cod_orden = $cod_orden";
				$resp['pagos'] = Conexion::buscarVariosRegistro($query, NULL);	

				$resp['fidelizacion']['dinero'] = Conexion::buscarVariosRegistro("SELECT cd.dinero FROM tb_cliente_dinero cd WHERE cd.cod_orden = $cod_orden", NULL); 	

				$resp['fidelizacion']['puntos'] = Conexion::buscarVariosRegistro("SELECT puntos, cod_nivel FROM tb_clientes_puntos WHERE cod_orden = $cod_orden", NULL); 		
				return $resp;
            }else
            	return false;
            return $resp;
		}

		public function getOrderForNotify($cod_orden){
			$query = "SELECT oc.cod_orden, oc.fecha, oc.subtotal, oc.descuento, oc.envio, oc.iva, oc.total, oc.estado, oc.is_envio, oc.is_programado, oc.hora_retiro, oc.referencia, oc.referencia2, u.nombre, u.apellido, u.correo, u.telefono, s.nombre as sucursal, s.direccion as sucursal_direccion, oc.cod_sucursal
			FROM tb_orden_cabecera oc, tb_usuarios u, tb_sucursales s
			WHERE oc.cod_usuario = u.cod_usuario
			AND oc.cod_sucursal = s.cod_sucursal 
			AND oc.cod_orden = $cod_orden
			AND oc.cod_empresa = ".cod_empresa;
			$orden = Conexion::buscarRegistro($query);
			if($orden){
			    $queryPago = "SELECT p.forma_pago as id, p.monto, f.descripcion as nombre
							FROM tb_orden_pagos p, tb_formas_pago f
							WHERE p.forma_pago = f.cod_forma_pago
							AND p.cod_orden = $cod_orden";
				$orden['pagos'] = Conexion::buscarVariosRegistro($queryPago, NULL);	
			}
			return $orden;
		}
		
		public function setCalification($cod_orden, $calificacion, $mensaje) {
			$mensaje = str_replace("'", "", trim($mensaje));
			
			$query = "INSERT INTO tb_orden_calificacion
						SET
							cod_orden = $cod_orden,
							calificacion = $calificacion,
							texto = '$mensaje'";
			return Conexion::ejecutar($query, null);
		}
		
		public function saveOrdenDatosFacturacion($cod_orden, $datosFacturacion) {
			$nombre = str_replace("'", "", trim($datosFacturacion["nombre"]));
			$num_documento = trim($datosFacturacion["num_documento"]);
			$direccion = str_replace("'", "", trim($datosFacturacion["direccion"]));
			$telefono = trim($datosFacturacion["telefono"]);
			$correo = str_replace("'", "", trim($datosFacturacion["correo"]));
			
			$query = "INSERT INTO tb_orden_datos_facturacion
						SET
							cod_orden = $cod_orden,
							nombre = '$nombre',
							num_documento = '$num_documento',
							direccion = '$direccion',
							telefono = '$telefono',
							correo = '$correo'";
			return Conexion::ejecutar($query, null);
		}
		
		
		
		// GUARDAR PREORDEN PAGO CON TOKEN DE TARJETA GUARDADA
		public function saveJsonToken($cod_usuario, $cod_usuario_card, $json, $amount){
		    $fecha = fecha();
			$query = "SELECT * 
						FROM tb_usuario_cards
						WHERE cod_usuario_cards = $cod_usuario_card";
			$resp = Conexion::buscarRegistro($query);

			if(!$resp)
				return null;
			
			$token = $resp["token"];

		    $query = "INSERT INTO tb_preorden_token_json (cod_usuario, token, json, fecha_create, amount, estado)
		        values ('$cod_usuario', '$token', '$json', '$fecha', $amount, 'VALIDADA')";
        	if(Conexion::ejecutar($query,NULL)){
        		$id = Conexion::lastId();
        		return $id;
        	}else{
        		return false;
        	}
		}

		// GET PREORDEN TOKEN
		public function getPreOrdenToken($PreOrdenId){
		    $query = "SELECT pj.* 
                        FROM tb_preorden_token_json pj, tb_usuarios u
                        WHERE pj.cod_usuario = u.cod_usuario
                        AND pj.cod_preorden = $PreOrdenId
                        AND u.cod_empresa = ".cod_empresa;
		    return Conexion::buscarRegistro($query);
		}

		
		// PREORDEN TOKEN CREANDO ORDEN
		public function convertingPreOrdenToken($PreOrdenId, $paymentId, $paymentAuth){
		    $fecha = fecha();
		    $query = "UPDATE tb_preorden_token_json SET estado = 'CREANDO_ORDEN', paymentId = '$paymentId', paymentAuth = '$paymentAuth', fecha_update = '$fecha' WHERE cod_preorden = $PreOrdenId";
		    return Conexion::ejecutar($query,NULL);
		}

		// CAMBIAR ESTADO PREORDEN TOKEN
		public function setStatusPreordenToken($PreOrdenId, $status, $cod_orden = 0, $motivo = ""){
		    $fecha = fecha();
		    $query = "UPDATE tb_preorden_token_json SET estado = '$status', cod_orden = $cod_orden, fecha_update = '$fecha' WHERE cod_preorden = $PreOrdenId";
		    return Conexion::ejecutar($query,NULL);
		}

		// FALLA PREORDEN TOKEN
		public function failurePreOrdenToken($PreOrdenId, $paymentId, $paymentAuth, $motivo){
		    $fecha = fecha();
		    $query = "UPDATE tb_preorden_token_json SET estado = 'FALLADA', paymentId = '$paymentId', paymentAuth = '$paymentAuth', motivo_fallo = '$motivo', fecha_update = '$fecha' WHERE cod_preorden = $PreOrdenId";
		    return Conexion::ejecutar($query,NULL);
		}
}
?>