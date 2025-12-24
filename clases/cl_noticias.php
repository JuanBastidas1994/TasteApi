<?php

class cl_noticias
{
		var $cod_noticia, $cod_empresa, $estado;
		
		public function __construct($pcod_noticia=null)
		{
			if($pcod_noticia != null)
				$this->cod_noticia = $pcod_noticia;
		}

		public function lista($end = 999){
			$cod_empresa = cod_empresa;
			$query = "SELECT s.*
				FROM tb_noticias as s
				WHERE s.estado IN('A')
				AND s.cod_empresa = $cod_empresa LIMIT 0,$end";
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $noticias) {
                $resp[$key]['image_min'] = url.$noticias['image_min'];
                $resp[$key]['imagen_max'] = url.$noticias['imagen_max'];
                $resp[$key]['fecha_create_iso'] = $noticias['fecha_create'];
                $resp[$key]['fecha_create'] = fechaLatino($noticias['fecha_create']);
                $resp[$key]['desc_larga'] = editor_decode($noticias['desc_larga']);
            }
            return $resp;
		}

		public function listaByCategoria($categoria, $end = 999){
			$cod_empresa = cod_empresa;
			$query = "SELECT s.*
				FROM tb_noticias as s, tb_noticias_categoria nc
				WHERE s.cod_noticia = nc.cod_noticia
				AND s.estado IN('A')
				AND nc.cod_categoria = $categoria
				AND s.cod_empresa = $cod_empresa
				ORDER BY s.fecha_create DESC
				LIMIT 0,$end";
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $noticias) {
                $resp[$key]['image_min'] = url.$noticias['image_min'];
                $resp[$key]['imagen_max'] = url.$noticias['imagen_max'];
                $resp[$key]['fecha_create_iso'] = $noticias['fecha_create'];
                $resp[$key]['fecha_create'] = fechaLatino($noticias['fecha_create']);
                $resp[$key]['desc_larga'] = editor_decode($noticias['desc_larga']);
            }
            return $resp;
		}
		
		public function getListaCategorias(){
		    $query = "SELECT * FROM tb_categorias_noticias WHERE cod_empresa = ".cod_empresa;
		    $resp = Conexion::buscarVariosRegistro($query);
		    foreach ($resp as $key => $categorias) {
		        $resp[$key]['cantidad'] = $this->numBlogsByCategory($categorias['cod_categorias_noticias']);
		    }
		    return $resp;
		}
		
		public function numBlogsByCategory($cod_category){
		    $query = "SELECT COUNT(*) as cantidad
                        FROM tb_noticias_categoria 
                        WHERE cod_categoria = $cod_category";
            $resp = Conexion::buscarRegistro($query);
            if($resp){
                return $resp['cantidad'];
            }else{
                return 0;
            }
            
		}
		
		public function get($alias){
			$query = "SELECT s.*
				FROM tb_noticias as s
				WHERE s.estado IN('A')
				AND s.alias = '$alias'
				AND s.cod_empresa = ".cod_empresa;
            $resp = Conexion::buscarRegistro($query);
            if($resp) {
                $resp['image_min'] = url.$resp['image_min'];
                $resp['imagen_max'] = url.$resp['imagen_max'];
                $resp['fecha_create_iso'] = $resp['fecha_create'];
                $resp['fecha_create'] = fechaLatino($resp['fecha_create']);
                $resp['desc_larga'] = editor_decode($resp['desc_larga']);
				$resp['galeria'] = $this->getGaleria($resp['cod_noticia']);
				$resp['categorias'] = $this->getCategorias($resp['cod_noticia']);
				if(count($resp['categorias']) > 0)
				    $resp['similares'] = $this->similaresByCategory($resp['categorias'][0]['id'], $resp['cod_noticia'], 3);
				else  
				    $resp['similares'] = null;
            }
            return $resp;
		}
		
		public function getCategorias($cod_noticia){
			$query = "SELECT c.cod_categorias_noticias as id, c.nombre
                    FROM tb_categorias_noticias c
                    INNER JOIN tb_noticias_categoria nc ON nc.cod_categoria = c.cod_categorias_noticias 
                    AND nc.cod_noticia = $cod_noticia";
			return Conexion::buscarVariosRegistro($query);
		}
		
		public function similaresByCategory($categoria, $cod_noticia, $end = 5){
			$cod_empresa = cod_empresa;
			$query = "SELECT s.alias, s.titulo, s.desc_corta, s.image_min, s.fecha_create
				FROM tb_noticias as s, tb_noticias_categoria nc
				WHERE s.cod_noticia = nc.cod_noticia
				AND s.estado IN('A')
				AND nc.cod_categoria = $categoria
				AND s.cod_empresa = $cod_empresa
				AND s.cod_noticia NOT IN($cod_noticia)
				ORDER BY s.fecha_create DESC
				LIMIT 0,$end";
            $resp = Conexion::buscarVariosRegistro($query);
            foreach ($resp as $key => $noticias) {
                $resp[$key]['image_min'] = url.$noticias['image_min'];
                $resp[$key]['fecha_create'] = fechaLatinoShort($noticias['fecha_create']);
            }
            return $resp;
		}
		
		

		public function getGaleria($cod_noticia){
			$query = "SELECT  nombre_img as imagen
			FROM tb_noticias_imagenes 
			WHERE cod_noticia = $cod_noticia
			ORDER BY posicion";
			$resp = Conexion::buscarVariosRegistro($query);
			foreach ($resp as $key => $noticias) {
                $resp[$key]['imagen'] = url.$noticias['imagen'];
            }
            return $resp;
		}
		
		public function getPolitica($alias){
			$query = "SELECT s.*
				FROM tb_noticias as s
				WHERE s.estado = 'A'
				AND s.alias = '$alias'
				AND s.cod_empresa = 1";
            $resp = Conexion::buscarRegistro($query);
            if($resp) {
                $resp['image_min'] = url.$resp['image_min'];
                $resp['imagen_max'] = url.$resp['imagen_max'];
                $resp['fecha_create'] = fechaLatino($resp['fecha_create']);
                $resp['desc_larga'] = str_replace("name_site", name_site ,editor_decode($resp['desc_larga']));
				$resp['desc_larga'] = str_replace("url_web", url_web ,$resp['desc_larga']);
				$resp['galeria'] = $this->getGaleria($resp['cod_noticia']);
            }    
            return $resp;
		}

		public function getByBusqueda($termino){
			$query = "SELECT * 
						FROM tb_noticias 
						WHERE cod_empresa = ".cod_empresa." 
						AND estado = 'A' 
						AND MATCH(titulo, desc_corta, desc_larga) AGAINST ('$termino')";
			$resp = Conexion::buscarVariosRegistro($query);
			foreach ($resp as $key => $noticias) {
                $resp[$key]['image_min'] = url.$noticias['image_min'];
                $resp[$key]['imagen_max'] = url.$noticias['imagen_max'];
            }
            return $resp;
		}
}
?>