<?php

class cl_productos
{
		var $cod_producto = 0, $cod_sucursal = 0, $officeTaxable = 1;
		
		public function __construct($pcod_producto=null)
		{
			if($pcod_producto != null)
				$this->cod_producto = $pcod_producto;
			$this->cod_sucursal = sucursaldefault;
		}

		public function setSucursal($cod_sucursal){
		    $this->cod_sucursal = $cod_sucursal;
			//Verificar si la sucursal grava iva
			$this->officeTaxable = $this->sucursalGravaIva($cod_sucursal);
		}
		
		public function first($id){
		    $query = "SELECT * FROM tb_productos WHERE cod_producto = $id AND estado = 'A' AND cod_empresa = ".cod_empresa;
		    return Conexion::buscarRegistro($query);
		}
		
		//UN PRODUCTO
		public function getInfo($cod_producto){
		    $cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior, p.open_detalle
					FROM vw_producto_sucursal p
					WHERE p.estado IN ('A')
					AND p.cod_sucursal IN(0, $cod_sucursal)
					AND p.cod_producto = $cod_producto
					AND p.cod_empresa = ".cod_empresa;
            $producto = Conexion::buscarRegistro($query);
            if($producto){
                $producto = $this->infoProductComplete($producto);
            }
            return $producto;
		}
		
		public function get($cod_producto, $cod_sucursal){
		    // $cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior, p.open_detalle, p.cobra_iva
					FROM vw_producto_sucursal p
					WHERE p.estado IN ('A')
					AND p.cod_sucursal IN(0, $cod_sucursal)
					AND p.cod_producto = $cod_producto
					AND p.cod_empresa = ".cod_empresa;
            $producto = Conexion::buscarRegistro($query);
			// var_dump($producto);
            if($producto){
                $producto = $this->infoProductComplete($producto);
            }
			// var_dump($producto);
            return $producto;
		}

		public function getInfoBasic($cod_producto){ //FUNCION USADA EN CARRITO
		    $cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior, p.cobra_iva
					FROM vw_producto_sucursal p
					WHERE p.estado IN ('A')
					AND p.cod_sucursal IN(0, $cod_sucursal)
					AND p.cod_producto = $cod_producto
					AND p.cod_empresa = ".cod_empresa;
            $producto = Conexion::buscarRegistro($query);
            if($producto){
				$precio = $producto['precio'];
				$precio_no_tax = $producto['precio_no_tax'];
                $producto = $this->infoProductHalf($producto, false);
				$producto['precio'] = number_format($precio,2);
				$producto['precio_no_tax'] = number_format($precio_no_tax,2);
				unset($producto['agotado_inicio']);
				unset($producto['agotado_fin']);
				unset($producto['cod_producto_padre']);
            }
            return $producto;
		}
		
		public function getInfoByAlias($alias){
			$cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior, p.open_detalle
					FROM vw_producto_sucursal p
					WHERE p.estado IN ('A')
					AND p.cod_sucursal IN(0, $cod_sucursal)
					AND p.alias = '$alias'
					AND p.cod_empresa = ".cod_empresa;
            $producto = Conexion::buscarRegistro($query);
            if($producto){
                $producto = $this->infoProductComplete($producto);
            }
            return $producto;
		}
		
		function isVisibleDate($cod_producto){
		    $dia = date('N', strtotime(fecha())); //1 Lunes - 7 Domingo
		    $query = "SELECT * FROM tb_productos_dias WHERE cod_producto = $cod_producto";
		    $resp = Conexion::buscarVariosRegistro($query);
		    if($resp){
		        $query = "SELECT * FROM tb_productos_dias WHERE cod_producto = $cod_producto AND dia = $dia";
		        $resp = Conexion::buscarRegistro($query);
		        if($resp){
		            //si tiene registro, el producto se mostrara
		            return true;
		        }else{
		            //si no tiene registro el producto no debe mostrarse
		            return false;
		        }
		    }else{
		        //Si no tiene registros el producto se muestra siempre
		        return true;
		    }
		}
		
		public function getTiempoPreparacion($ids){
		    if (empty($ids)) {
                return 0;
            }
            
		    $allIds = implode(",",$ids);
		    $query = "SELECT MAX(tiempo_preparacion) as total_tiempo FROM tb_productos WHERE cod_producto IN ($allIds)";
		    $resp = Conexion::buscarRegistro($query);
		    return $resp && $resp['total_tiempo'] !== null
                ? (int)$resp['total_tiempo']
                : 0;
		}

		//LISTAS
		public function lista(){
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior, p.open_detalle
					FROM vw_producto_sucursal p, tb_productos_categorias pc
					WHERE p.cod_producto = pc.cod_producto
					AND p.estado IN ('A')
					AND p.cod_producto_padre = 0
					AND p.cod_sucursal IN(0, ".sucursaldefault.")
					AND p.cod_empresa = ".cod_empresa;
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $producto) {
            	$resp[$key] = $this->infoProductComplete($producto);
            }
            return $resp;
		}

		public function listaByCategoria($cod_categoria){
			$cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior, p.open_detalle
					FROM vw_producto_sucursal p, tb_productos_categorias pc
					WHERE p.cod_producto = pc.cod_producto
					AND p.estado IN ('A')
					AND pc.cod_categoria = $cod_categoria
					AND p.cod_producto_padre = 0
					AND p.cod_sucursal IN(0, $cod_sucursal)
					AND p.cod_empresa = ".cod_empresa."
					ORDER BY p.posicion";
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $producto) {
            	if(!$this->isVisibleDate($producto['cod_producto'])){
            	    unset($resp[$key]);
            	}else{
            	    $resp[$key] = $this->infoProductComplete($producto);
            	    
            	    if(cod_empresa == 121){ //Oahu
                	    $resp[$key]['oahu_arma_bowl'] = $this->getArmaBowl($producto['cod_producto']);
                	}else{
                	    $resp[$key]['oahu_arma_bowl'] = false;
                	}
            	}
            }
            return $resp;
		}
		
		public function listaBasicaByCategoria($cod_categoria){
			$cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior, p.open_detalle
					FROM vw_producto_sucursal p, tb_productos_categorias pc
					WHERE p.cod_producto = pc.cod_producto
					AND p.estado IN ('A')
					AND pc.cod_categoria = $cod_categoria
					AND p.cod_producto_padre = 0
					AND p.cod_sucursal IN(0, $cod_sucursal)
					AND p.cod_empresa = ".cod_empresa."
					ORDER BY p.posicion";
            $resp = Conexion::buscarVariosRegistro($query);
            $respAux = [];
			if($resp) {
				foreach ($resp as $key => $producto) {
					if($this->isVisibleDate($producto['cod_producto'])) {
						$respAux[] = $this->infoProductHalf($producto, false);
					}
				}
			}
			return $respAux;
		}
		
		public function getNumProductsByCategoria($cod_categoria){
		    $cod_sucursal = $this->cod_sucursal;
		    $dia_hoy = date('N');
		    $condiciones_dia = "(
                    (pd.dia IS NULL) OR
                    (pd.dia = $dia_hoy)
                )";
                        
		    $query = "SELECT COUNT(p.cod_producto) as cantidad
                      FROM vw_producto_sucursal p
                      LEFT JOIN tb_productos_dias pd ON p.cod_producto = pd.cod_producto
                      JOIN tb_productos_categorias pc ON p.cod_producto = pc.cod_producto
                      WHERE p.estado IN ('A')
                        AND pc.cod_categoria = $cod_categoria
                        AND p.cod_producto_padre = 0
                        AND p.cod_sucursal IN (0, $cod_sucursal)
                        AND p.cod_empresa = ".cod_empresa."
                        AND $condiciones_dia";
            $resp = Conexion::buscarRegistro($query);
            if($resp){
                return $resp['cantidad'];
            }
            return 0;
		}
		
		public function listaByFilter($busqueda){
		    $cod_sucursal = $this->cod_sucursal;
		    $busqueda = str_replace(" ","%",$busqueda);
			$query = "SELECT p.*
					FROM vw_producto_sucursal p
					WHERE p.estado = 'A'
					AND p.cod_producto_padre = 0
					AND p.nombre LIKE '%$busqueda%'
					AND p.cod_sucursal IN(0,".$cod_sucursal.")
					AND p.cod_empresa = ".cod_empresa;
			
			$query .= " UNION SELECT p.*
					FROM vw_producto_sucursal p, tb_productos_tags pt
					WHERE p.cod_producto = pt.cod_producto 
					AND p.estado = 'A'
					AND p.cod_producto_padre = 0
					AND pt.tag = '$busqueda'
					AND p.cod_sucursal IN(0,".$cod_sucursal.")
					AND p.cod_empresa = ".cod_empresa;		
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $producto) {
            	$resp[$key]=$producto = $this->infoProductComplete($producto);
            }
            return $resp;
		}
		
		public function listaByFilterTag($busqueda){
			$cod_sucursal = $this->cod_sucursal;
			$ar1 = explode(",", $busqueda);
			$busqueda = "'" . implode ( "', '", $ar1 ) . "'";
			$query = "SELECT DISTINCT p.*
					FROM vw_producto_sucursal p, tb_productos_tags t
					WHERE p.cod_producto = t.cod_producto
					AND p.estado = 'A'
					AND p.cod_producto_padre = 0
					AND t.tag IN ($busqueda)
					AND p.cod_sucursal IN(0,".$cod_sucursal.")
					AND p.cod_empresa = ".cod_empresa;
			$resp = Conexion::buscarVariosRegistro($query);
			foreach ($resp as $key => $producto) {
				$resp[$key]=$producto = $this->infoProductComplete($producto);
			}
			return $resp;
		}

		public function listaByCategoriaAlias($alias){	
			$cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior, p.open_detalle
					FROM vw_producto_sucursal p, tb_productos_categorias pc, tb_categorias c
					WHERE p.cod_producto = pc.cod_producto
					AND pc.cod_categoria = c.cod_categoria
					AND p.estado IN ('A')
					AND c.alias = '$alias'
					AND p.cod_producto_padre = 0
					AND p.cod_sucursal IN(0, $cod_sucursal)
					AND p.cod_empresa = ".cod_empresa;
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $producto) {
            	$resp[$key]=$producto = $this->infoProductHalf($producto);
            }
            return $resp;
		}

		public function listaModuloWeb($cod_modulo){
			$cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.precio_no_tax, p.precio, p.precio_anterior, p.open_detalle
					FROM vw_producto_sucursal p, tb_web_modulos_productos_detalle w 
					WHERE p.cod_producto = w.cod_producto 
					AND p.estado ='A' 
					AND p.cod_sucursal IN(0,".$cod_sucursal.") 
					AND w.cod_web_modulos_producto = $cod_modulo
					AND p.cod_empresa = ".cod_empresa." ORDER BY w.posicion ASC";
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $producto) {
                $item = $this->infoProductHalf($producto);
                if(isset($item['categoria'])){
                    $item['categoryName'] = $item['categoria']['categoria'];
                }
            	$resp[$key] = $item;
            }
            return $resp;
		}
		
		public function listaFromPaginaDetalle($cod_pagina_detalle){
			$cod_sucursal = $this->cod_sucursal;
			$query = "SELECT c.id, p.cod_producto, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.precio_no_tax, p.precio, p.precio_anterior, p.open_detalle
                    FROM vw_producto_sucursal p, tb_front_pagina_detalle_contenido c 
                    WHERE p.cod_producto = c.accion_desc 
                    AND p.estado ='A' 
                    AND p.cod_sucursal IN(0,".$cod_sucursal.") 
                    AND c.cod_front_pagina_detalle = $cod_pagina_detalle
                    AND p.cod_empresa = ".cod_empresa." ORDER BY c.posicion ASC";
            $resp = Conexion::buscarVariosRegistro($query);
            $respAux = [];
            foreach ($resp as $key => $producto) {
                if($this->isVisibleDate($producto['cod_producto'])) {
                    if(isset($producto['categoria'])){
                        $producto['categoryName'] = $producto['categoria']['categoria'];
                    }
					$respAux[] = $this->infoProductHalf($producto, false);
				}
            }
            return $respAux;
		}
		
		public function listaVariantes($cod_producto){		
			$cod_sucursal = $this->cod_sucursal;
			$query = "SELECT p.cod_producto, p.cod_producto_padre, p.alias, p.nombre, p.desc_corta, p.image_min, p.image_max, p.agotado_inicio, p.agotado_fin, p.cod_sucursal, p.precio_no_tax, p.iva_valor, p.precio, p.precio_anterior
					FROM vw_producto_sucursal p
					WHERE p.estado IN ('A')
					AND p.cod_producto_padre = $cod_producto
					AND p.cod_sucursal IN(0, $cod_sucursal)
					AND p.cod_empresa = ".cod_empresa;
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $producto) {
            	$resp[$key]=$producto = $this->infoProductHalf($producto);
            }
            return $resp;
		}

        //OPCIONES
		public function listaOpciones($tipo){
			$query = "SELECT p.nombre,p.precio,p.image_max,p.image_min
				FROM tb_productos p, tb_productos_categorias pc , tb_categorias c
				WHERE c.cod_categoria = pc.cod_categoria and pc.cod_producto = p.cod_producto and p.estado IN('A') 
				AND c.cod_empresa = ".cod_empresa." AND c.alias ='".$tipo."' " ;
            $resp = Conexion::buscarVariosRegistro($query);
            return $resp;
		}

		public function opciones($cod_producto){
			$query = "SELECT * FROM tb_productos_opciones WHERE cod_producto = $cod_producto ORDER BY posicion ASC";
			$resp = Conexion::buscarVariosRegistro($query);
			if($resp){
			    foreach ($resp as $key => $opciones) {
					$resp[$key]['titulo'] = html_entity_decode($opciones['titulo']);
			        if($opciones['isDatabase'] == 1)
			            $resp[$key]['items'] = $this->detalle_opciones_Productos($opciones['cod_producto_opcion']);
			        else       
			            $resp[$key]['items'] = $this->detalle_opciones_noProductos($opciones['cod_producto_opcion']);
			    }       
			}
			return $resp;
		}
		
		public function opcionById($cod_producto, $cod_opcion){
			$query = "SELECT * FROM tb_productos_opciones WHERE cod_producto = $cod_producto AND cod_producto_opcion = $cod_opcion ORDER BY posicion ASC";
			$resp = Conexion::buscarVariosRegistro($query);
			if($resp){
			    foreach ($resp as $key => $opciones) {
					$resp[$key]['titulo'] = html_entity_decode($opciones['titulo']);
			        if($opciones['isDatabase'] == 1)
			            $resp[$key]['items'] = $this->detalle_opciones_Productos($opciones['cod_producto_opcion']);
			        else       
			            $resp[$key]['items'] = $this->detalle_opciones_noProductos($opciones['cod_producto_opcion']);
			    }       
			}
			return $resp;
		}
		
		public function detalle_opciones_noProductos($cod_producto_opcion){
			$query = "SELECT cod_producto_opciones_detalle,item,aumentar_precio,precio FROM tb_productos_opciones_detalle WHERE cod_producto_opcion = $cod_producto_opcion ORDER BY posicion ASC";
			$resp = Conexion::buscarVariosRegistro($query);
			if($resp){
			    foreach ($resp as $key => $opciones) {
			        $resp[$key]['disponible'] = true;
			    }       
			}
            return $resp;
		}

		public function detalle_opciones_Productos($cod_producto_opcion){
		    $cod_sucursal = $this->cod_sucursal;
			$query = "SELECT po.cod_producto_opciones_detalle, p.nombre as item, po.aumentar_precio, po.precio, p.agotado_fin, p.precio as precio_real
					FROM tb_productos_opciones_detalle po, vw_producto_sucursal p
					WHERE po.item = p.cod_producto
                    AND p.cod_sucursal = ".$cod_sucursal."
					AND po.cod_producto_opcion = $cod_producto_opcion
					ORDER BY po.posicion ASC";
			$resp = Conexion::buscarVariosRegistro($query);
			if($resp){
			    foreach ($resp as $key => $opciones) {
			        if(cod_empresa == 24){  //SOLO DANILO
			            $resp[$key]['precio'] = $opciones['precio_real'];
			        }
					$resp[$key]['disponible'] = $this->disponible($opciones['agotado_fin']);
			    }       
			}
            return $resp;
		}
		
		//GET OPCIONES
		public function getOpcion($cod_opcion){
		    $query = "SELECT * FROM tb_productos_opciones WHERE cod_producto_opcion = $cod_opcion";
		    return Conexion::buscarRegistro($query);
		}

		public function getVarianteByCaracteristicas($caracteristicas){
			$atributos = implode(",", $caracteristicas);
			$numItems = count($caracteristicas);
			$query = "SELECT cod_producto, count(cod_producto) as total
					FROM tb_variante_caracteristica
					WHERE cod_caracteristica_detalle IN($atributos)
					GROUP BY cod_producto
					HAVING total = $numItems";
			return Conexion::buscarRegistro($query);
		}

		public function getFirstCategory($cod_producto){
			$query = "SELECT c.cod_categoria, c.alias, c.categoria
					FROM tb_categorias c, tb_productos_categorias pc
					WHERE pc.cod_categoria = c.cod_categoria
					AND pc.cod_producto = $cod_producto 
					ORDER BY c.cod_categoria DESC";
			return Conexion::buscarRegistro($query);		
		}

		/*CARACTERISTICAS*/
		public function getCaracteristicas($cod_producto){
		    $query = "SELECT cod_producto_caracteristica, caracteristica, tipo 
					FROM tb_producto_caracteristica WHERE cod_producto = $cod_producto";
			$resp = Conexion::buscarVariosRegistro($query);
			foreach($resp as $key => $item){
			    $resp[$key]['detalle'] = $this->getCaracteristicasDetalle($item['cod_producto_caracteristica']);
			}
			return $resp;
		}
		
		public function getCaracteristicasDetalle($cod_caracteristica){
		    $query = "SELECT cod_producto_caracteristica_detalle as id, detalle, detalle2 
					FROM tb_producto_caracteristica_detalle WHERE cod_producto_caracteristica = $cod_caracteristica";
			return Conexion::buscarVariosRegistro($query);
		}


		/*PROMOCIONES*/
		public function isPromocionOld($cod_producto){
			$cod_sucursal = $this->cod_sucursal;
			$fecha = fecha();
			$query = "SELECT * 
					FROM tb_producto_descuento pd
					WHERE pd.cod_producto = $cod_producto
					AND pd.cod_sucursal = $cod_sucursal
					AND pd.fecha_inicio <= '$fecha'
					AND pd.fecha_fin >= '$fecha'
					AND pd.estado = 'A'";
			$row = Conexion::buscarRegistro($query);	
			return $row;	
		}

		public function isPromocion($cod_producto)
		{
			$cod_sucursal = $this->cod_sucursal;
			$fecha = date('Y-m-d H:i:s');
			$hora  = date('H:i:s');
			$diaNumero   = date('N'); // 1 = lunes ... 7 = domingo
			$dias = [
				1 => 'lunes',
				2 => 'martes',
				3 => 'miercoles',
				4 => 'jueves',
				5 => 'viernes',
				6 => 'sabado',
				7 => 'domingo'
			];
			$dia  = $dias[$diaNumero];

			$query = "
				SELECT 
					p.cod_promocion,
					p.descripcion,
					p.is_porcentaje,
					p.valor,
					p.texto,
					p.fecha_inicio,
					p.fecha_fin,
					p.cantidad
				FROM promociones p
				INNER JOIN promocion_producto pp 
					ON p.cod_promocion = pp.cod_promocion
				INNER JOIN promocion_sucursal ps 
					ON p.cod_promocion = ps.cod_promocion
				WHERE
					pp.cod_producto = :cod_producto
					AND ps.cod_sucursal = :cod_sucursal
					AND p.cod_empresa = :cod_empresa
					AND p.fecha_inicio <= :fecha
					AND p.fecha_fin >= :fecha
					AND (
						NOT EXISTS (
							SELECT 1
							FROM promocion_recurrente pr2
							WHERE pr2.cod_promocion = p.cod_promocion
						)
						OR
						EXISTS (
							SELECT 1
							FROM promocion_recurrente pr
							WHERE pr.cod_promocion = p.cod_promocion
							AND pr.dia_semana = :dia
							AND :hora BETWEEN pr.hora_inicio AND pr.hora_fin
						)
					)
				LIMIT 1
			";

			$params = [
				':cod_producto' => $cod_producto,
				':cod_sucursal' => $cod_sucursal,
				':cod_empresa'  => cod_empresa,
				':fecha'        => $fecha,
				':hora'         => $hora,
				':dia'          => $dia
			];

			return Conexion::buscarRegistro($query, $params);
		}
		
		private function sucursalGravaIva($cod_sucursal) {
			$query = "SELECT grava_iva FROM tb_sucursales WHERE cod_sucursal = $cod_sucursal";
			$resp = Conexion::buscarRegistro($query);
			if($resp) {
				return $resp["grava_iva"];
			}
			return 0;
		}

		/*FUNCIONES MAS IMPORTANTES DE PRODUCTOS*/
		public function disponible($agotado){
		    if($agotado != NULL){
        		$fecha = fecha();
        		if(strtotime($agotado) > strtotime($fecha)){
        			return false;
        		}
        		return true;
        	}
        	return true;
		}
		
		public function infoProductComplete($producto){
        	$producto['precio'] = number_format($producto['precio'],2);
			$producto['image_min'] = ($producto['image_min'] !== "") ? url.$producto['image_min'] : "";
			$producto['image_max'] = ($producto['image_max'] !== "") ? url.$producto['image_max'] : "";
			$producto['precio_anterior'] = number_format($producto['precio_anterior'],2);
			$producto['cobra_iva'] = isset($producto['cobra_iva']) ? $producto['cobra_iva'] : 1;
			$producto['cobra_iva'] = ($this->officeTaxable == 0) ? 0 : $producto['cobra_iva'];
			
			if(!$this->isVisibleDate($producto['cod_producto'])){
				$producto['disponible'] = false;
			}else{
				$producto['disponible'] = $this->disponible($producto['agotado_fin']);
			}
			
			$cantidadPromo = 0;
			$producto['promocion'] = "";
        	$producto['nxm'] = false;
			if($producto['disponible'] == true){
				$promocion = $this->isPromocion($producto['cod_producto']);
				if($promocion){
					$precio = number_format($producto['precio'],2);
					$texto = $promocion['texto'];
					$producto['promocion'] = $texto;
					if($promocion['is_porcentaje']==1){
						$producto['precio_anterior'] = $precio;
						$valor = $promocion['valor'];
			
						$descuento = $precio * ($valor/100);
						$precio = $precio - $descuento;
						$precio = number_format($precio, 2);
			
						$producto['precio'] = $precio;
						$producto['nxm'] = false;
					}else{
						$producto['nxm'] = true;
						if($texto == '2x1')
							$cantidadPromo = 2;
						else if($texto == '3x2')
							$cantidadPromo = 3;
						else if($texto == '4x3')
							$cantidadPromo = 4;
						else if($texto == '5x4')
							$cantidadPromo = 5;
					}
					$producto['promo_fin'] = $promocion['fecha_fin'];
				}
			}
			$producto['cantidadPromo'] = $cantidadPromo;
        	
        	$variantes = $this->listaVariantes($producto['cod_producto']);
        	if($variantes){
        	    $producto['variantes'] = $variantes;
        	    $producto['opciones'] = [];
        	    $producto['addcart'] = false;
        	}else{
        	    $producto['variantes'] = $variantes;
        	    $producto['opciones'] = $this->opciones($producto['cod_producto']);
        	    $producto['addcart'] = true;
        	}
        	
        	if($producto['cod_producto_padre'] > 0)
        	    $producto['categoria'] = $this->getFirstCategory($producto['cod_producto_padre']);
        	else
        	    $producto['categoria'] = $this->getFirstCategory($producto['cod_producto']);
        	    
        	if(service_percentage > 0){
        	    $producto['precio'] = number_format($producto['precio'] + ($producto['precio_no_tax'] * (service_percentage / 100)),2);
        	}
        	return $producto;
        }
        
        public function infoProductHalf($producto, $infoAdicional = true){
			$producto['precio'] = number_format($producto['precio'],2);
        	$producto['image_min'] = ($producto['image_min'] !== "") ? url.$producto['image_min'] : "";
			$producto['image_max'] = ($producto['image_max'] !== "") ? url.$producto['image_max'] : "";
			$producto['precio_anterior'] = number_format($producto['precio_anterior'],2);
			$producto['cobra_iva'] = isset($producto['cobra_iva']) ? $producto['cobra_iva'] : 1;
			$producto['cobra_iva'] = ($this->officeTaxable == 0) ? 0 : $producto['cobra_iva'];
			
			if(!$this->isVisibleDate($producto['cod_producto'])){
				$producto['disponible'] = false;
			}else{
				$producto['disponible'] = $this->disponible($producto['agotado_fin']);
			}
			
			$cantidadPromo = 0;
			$producto['promocion'] = "";
			$producto['nxm'] = false;
			$producto['promo_dias'] = "";
			if($producto['disponible'] == true){
				$promocion = $this->isPromocion($producto['cod_producto']);
				if($promocion){
					$precio = number_format($producto['precio'],2);
					$texto = $promocion['texto'];
					$producto['promocion'] = $texto;
	
					if($promocion['is_porcentaje']==1){
						$producto['precio_anterior'] = $precio;
						$valor = $promocion['valor'];
	
						$descuento = $precio * ($valor/100);
						$precio = $precio - $descuento;
						$precio = number_format($precio, 2);
			
						$producto['precio'] = $precio;
						$producto['nxm'] = false;
					}else{
						$producto['nxm'] = true;
						if($texto == '2x1')
							$cantidadPromo = 2;
						else if($texto == '3x2')
							$cantidadPromo = 3;
						else if($texto == '4x3')
							$cantidadPromo = 4;
						else if($texto == '5x4')
							$cantidadPromo = 5;
					}
					$producto['promo_fin'] = $promocion['fecha_fin'];
					$promo_dias = diasRestantes($promocion['fecha_fin']);
					if($promo_dias <= 7)
						$producto['promo_dias'] = $promo_dias;
					 else
						$producto['promo_dias'] = "";
				}
			}
			$producto['cantidadPromo'] = $cantidadPromo;
        	
        	if($infoAdicional){
        	    $producto['categoria'] = $this->getFirstCategory($producto['cod_producto']);
        	}
        	
        	if(service_percentage > 0){
        	    $producto['precio'] = number_format($producto['precio'] + ($producto['precio_no_tax'] * (service_percentage / 100)),2);
        	}
        	
        	$producto['empaque'] = $this->getEmpaque($producto['cod_producto']);
        	
        	return $producto;
        }
        
        public function getEmpaque($cod_producto){
            $query = "SELECT * FROM tb_producto_empaque_detalle WHERE cod_producto = $cod_producto";
            $resp = Conexion::buscarRegistro($query);
            if($resp){
                $alto = $resp['alto'];
                $resp['alto'] = ($alto == intval($alto)) ? intval($alto) : $alto;
            }
            return $resp;
        }
        
        public function getArmaBowl($cod_producto){
			$query = "SELECT oahu_arma_bowl FROM tb_productos WHERE cod_producto = $cod_producto";
			$resp = Conexion::buscarRegistro($query);
			if($resp){
				$arma_bowl = $resp['oahu_arma_bowl'];
				if($arma_bowl == 1)
					return true;
				else
					return false;
			}else
				return false;
		}
}
?>