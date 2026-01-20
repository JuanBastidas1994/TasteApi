<?php

class cl_categorias
{
		var $session;
		var $cod_categoria, $cod_categoria_padre, $cod_empresa, $alias, $nombre, $desc_corta, $desc_larga, $image_min, $image_max, $estado;
		
		public function __construct($pcod_categoria=null)
		{
			if($pcod_categoria != null)
				$this->cod_categoria = $pcod_categoria;
		}

		public function lista($end = 100){
			$query = "SELECT cod_categoria, alias, categoria, image_min, image_max, estado
					FROM tb_categorias WHERE estado = 'A' AND cod_categoria_padre = 0 AND cod_empresa = ".cod_empresa." ORDER BY posicion ASC LIMIT 0,$end";
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $categoria) {
            	$resp[$key]['image_min'] = url.$categoria['image_min'];
            	$resp[$key]['image_max'] = url.$categoria['image_max'];
				$resp[$key]['subcategorias'] = $this->subcategorias($categoria['cod_categoria']); 
            }
            return $resp;
		}
		
		public function listaBasica($end = 100){
			$query = "SELECT cod_categoria, alias, categoria, image_min, image_max
					FROM tb_categorias WHERE estado = 'A' AND cod_categoria_padre = 0 AND cod_empresa = ".cod_empresa." ORDER BY posicion LIMIT 0,$end";
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $categoria) {
            	$resp[$key]['image_min'] = url.$categoria['image_min'];
            	$resp[$key]['image_max'] = url.$categoria['image_max'];
            }
            return $resp;
		}

		public function subcategorias($cod_categoria_padre, $end = 100){
			$query = "SELECT c.cod_categoria, c.alias, c.categoria, c.image_min, c.image_max, c.estado 
			FROM tb_categorias_dependientes cd, tb_categorias c 
			WHERE cd.cod_categoria = c.cod_categoria
			AND cd.cod_categoria_padre = $cod_categoria_padre
			AND c.estado = 'A'
			LIMIT 0,$end";
		    $resp = Conexion::buscarVariosRegistro($query);
			foreach ($resp as $key => $categoria) {
            	$resp[$key]['image_min'] = url.$categoria['image_min'];
            	$resp[$key]['image_max'] = url.$categoria['image_max'];
				$resp[$key]['subcategorias'] = $this->subcategorias($categoria['cod_categoria']); 
            }
            return $resp;
		}
		
		public function getbyAlias($alias){
		    $query = "SELECT * FROM tb_categorias WHERE alias = '$alias' AND estado='A' AND cod_empresa = ".cod_empresa;
			return Conexion::buscarRegistro($query);
		}

		public function getArray($cod_categoria, &$array)
		{
			$query = "SELECT * FROM tb_categorias where cod_categoria = $cod_categoria AND estado IN('A')";
			$row = Conexion::buscarRegistro($query);
			if($row){
				$array = $row;
				return true;
			}
			else
			{
				return false;
			}
		}

		public function getAdicionalesByCategoria($alias_categoria){
			$query = "SELECT a.* FROM tb_web_adicionales a, tb_categorias c
					WHERE a.cod_categoria = c.cod_categoria
					AND c.alias = '$alias_categoria' ORDER BY a.posicion ASC";
			return Conexion::buscarVariosRegistro($query);
		}
}
?>