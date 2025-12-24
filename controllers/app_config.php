<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */
require_once "clases/cl_usuarios.php";
require_once "clases/cl_productos.php";
require_once "clases/cl_categorias.php";
$Clproductos = new cl_productos();
$Clcategorias = new cl_categorias();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 2){
			$first = $request[1];
			if($first=="home2"){ //HOME CON CODIGO SUCURSAL
				$return = infoHome2();
				showResponse($return);
			}
			if($first=="categorias"){
				$return = infoCategorias();
				showResponse($return);
			}
			if($first=="banners"){
				$return = lstBanners();
				showResponse($return);
			}
			if($first=="menu-digital"){
			   $return = menuDigital(); 
			   showResponse($return);
			}
		}
		if($num_variables == 3){
			$first = $request[1];
			if($first=="pagina"){ //PAGINA INDICAR ALIAS
			    $aliasPagina = $request[2];
				$return = infoHome2(0, $aliasPagina);
				showResponse($return);
			}
			if($first=="anuncios-web"){
				$return = anuncioWebDetalle($request[2]);
				showResponse($return);
			}
			if($first=="sugerencias-checkout"){
			    $cod_sucursal = $request[2];
			   $return = SugerenciasCheckout($cod_sucursal);
			   showResponse($return);
			}
		}
		
		$return['success']= 0;
		$return['mensaje']= "Url no valida para configuracion, por favor revisar los parametros";
		showResponse($return);
	}
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para configuracion aun no esta disponible.";
		showResponse($return);
	}
	
	
/*FUNCIONES*/
function infoHome2($cod_sucursal = 0, $aliasPage = ''){
    global $Clproductos;
	global $Clcategorias;
	$cod_empresa = cod_empresa;
	$usuario = null;
    
    if($cod_sucursal == 0){
        $cod_sucursal = sucursaldefault;
    }
	$Clproductos->cod_sucursal = $cod_sucursal;
	
	$data = [];
	$x=0;
	
	$where = ($aliasPage === '') ? 'AND f.home = 1' : "AND f.alias = '$aliasPage'";

	//OTRAS OPCIONES
	$query = "SELECT fd.cod_front_pagina_detalle as id, fd.cod_tipo as tipo, fd.titulo, fd.forma, fd.detalle, fd.detalle2, fd.cod_detalle, fd.html, fd.showTitle, fd.extra_params  
			FROM tb_front_paginas f, tb_front_pagina_detalle fd
			WHERE f.cod_front_pagina = fd.cod_front_pagina
			AND fd.forma != 'banner'
			AND f.cod_empresa = $cod_empresa
			$where 
			ORDER BY fd.posicion ASC";
	$resp = Conexion::buscarVariosRegistro($query);
	foreach ($resp as $secciones) {
	    $data[$x] = $secciones;
	    
	    $id = $secciones['id'];
		$tipo = $secciones['tipo'];
		$forma = $secciones['forma'];
		$cod = $secciones['cod_detalle'];
		
        if($tipo == "banner"){
		    $banners = getBanners();
		    $data[$x]['items'] = $banners;
		  //  $device = getUserDevice();
		  //  if($device == 'ANDROID' || $device == "IOS"){
		  //      $data[$x]['tipo'] = 'anuncios';
		  //      $data[$x]['forma'] = 'banner';
		  //      $items = [];
		  //      foreach($banners as $banner){
		  //          $items[] = [
		  //              'titulo' => '',
		  //              'subtitulo' => '',
		  //              'image_min' => $banner['imagen'],
		  //              'text_boton' => '',
		  //              'accion_id' => 0,
		  //              'accion_desc' => '',
		  //          ]; 
		  //      }
		  //      $data[$x]['items'] = $items;
		  //  }else{
		  //      $data[$x]['items'] = $banners;
		  //  }
		}
		if($tipo == "ordenar"){
		    $data[$x]['items'] = $Clproductos->listaFromPaginaDetalle($id);
		}
		if($tipo == "anuncios" ){
		    $query = "SELECT * FROM tb_front_pagina_detalle_contenido WHERE cod_front_pagina_detalle = $id";
		    $resp = Conexion::buscarVariosRegistro($query);
		    foreach ($resp as $key => $anuncio) {
            	$resp[$key]['imagen'] = url.$anuncio['imagen'];
            	$resp[$key]['image_min'] = $resp[$key]['imagen'];
            }
		    
			$data[$x]['items'] = $resp;

		}
		if($tipo == "blog"){
		    $query = "SELECT n.alias, n.titulo, n.desc_corta, n.image_min as imagen, DATE(n.fecha_create) as fecha 
		            FROM tb_front_pagina_detalle_contenido f INNER JOIN tb_noticias n ON f.accion_desc = n.cod_noticia AND n.estado = 'A' AND n.cod_empresa = $cod_empresa 
		            WHERE f.cod_front_pagina_detalle = $id ORDER BY f.posicion ASC;";
		    $resp = Conexion::buscarVariosRegistro($query);
		    foreach ($resp as $key => $anuncio) {
            	$resp[$key]['imagen'] = url.$anuncio['imagen'];
            	$resp[$key]['fechasplit'] = fechaLatinoSplit($anuncio['fecha']);
            }
		    
			$data[$x]['items'] = $resp;

		}
		if($tipo == "categorias"){
		    $data[$x]['items'] = getCategorias($cod_sucursal);
		}
		if($tipo == 'custom'){
		    $extra_params = $secciones['extra_params'];
		    $extra_params = ($extra_params !== "") ? json_decode($extra_params, true) : "";
		    $data[$x]['extra_params'] = $extra_params; 
		}
		$x++;
	}	

	$return['success'] = 1;
	$return['sucursal'] = $cod_sucursal;
	$return['mensaje'] = "App Home";
	$return['data'] = $data;
    return $return;
}

function getCategorias($cod_sucursal){
    global $Clcategorias;
    global $Clproductos;
    
    if($cod_sucursal == 0){
        return  $Clcategorias->listaBasica();
    }else{
        $respCategorias = null;
        $x=0;
        
    	$categorias = $Clcategorias->listaBasica();
    	foreach ($categorias as $key => $categoria) {
    	    $cantidad = $Clproductos->getNumProductsByCategoria($categoria['cod_categoria'], $cod_sucursal);
    	    if($cantidad>0){
    		    $respCategorias[$x] = $categorias[$key]; 
    		    $x++;
    	    }
    	}
    	return $respCategorias;
    }
}

function getBanners(){
    $query = "SELECT titulo, subtitulo, descuento as text_adicional, text_boton, url_boton, image_min as imagen, posicion
			FROM tb_banner 
			WHERE estado IN('A') AND cod_empresa = ".cod_empresa." ORDER BY posicion ASC LIMIT 0,5";
	$resp = Conexion::buscarVariosRegistro($query);
	foreach ($resp as $key=>$banner) {
		$resp[$key]['imagen'] = url.$banner['imagen'];
	}
	return $resp;
}

function infoCategorias(){
    require_once "clases/cl_categorias.php";
    $Clcategorias = new cl_categorias();
    
    $respCategorias = $Clcategorias->lista();
    
    $return['success'] = 1;
	$return['mensaje'] = "Correcto";
	$return['data'] = $respCategorias;
	return $return;
}

function lstBanners(){
	$query = "SELECT titulo, subtitulo, descuento as text_adicional, text_boton, url_boton, image_min as imagen, posicion
			FROM tb_banner 
			WHERE estado IN('A') AND cod_empresa = ".cod_empresa." ORDER BY posicion ASC LIMIT 0,5";
	$resp = Conexion::buscarVariosRegistro($query);
	foreach ($resp as $key=>$banner) {
		$resp[$key]['imagen'] = url.$banner['imagen'];
	}

	$return['success'] = 1;
	$return['mensaje'] = "Correcto";
	$return['data'] = $resp;
	return $return; 
}

function menuDigital(){
	$cod_empresa = cod_empresa;
	$query = "SELECT mi.imagen
                FROM tb_menu_digital m, tb_menu_digital_imagenes mi
                WHERE m.cod_menu_digital = mi.cod_menu_digital
                AND m.cod_empresa = $cod_empresa
                AND mi.estado = 'A'
                ORDER BY mi.posicion";
	$resp = Conexion::buscarVariosRegistro($query);
	foreach ($resp as $key => $anuncio) {
		$resp[$key]['imagen'] = url.$anuncio['imagen'];
		$info = getimagesize(urlUpload.$anuncio['imagen']);
		if($info){
		    $resp[$key]['ancho'] = $info[0];
		    $resp[$key]['alto'] = $info[1];
		}
		
	}
	$return['success'] = 1;
	$return['mensaje'] = "Lista imágenes menu digital";
	$return['data'] = $resp;
	return $return;
}

function anuncioWebDetalle($id){
	$categoriasGenerales = [];
	$cod_empresa = cod_empresa;
	
	$limit = (isset($_GET['limit'])) ? $_GET['limit'] : 999;
	$query = "SELECT titulo, subtitulo, imagen as image_min, text_boton, accion_id, url_boton as accion_desc, categorias, descripcion 
				FROM tb_anuncio_detalle WHERE cod_anuncio_cabecera = $id AND cod_empresa = $cod_empresa AND estado = 'A' ORDER BY posicion LIMIT 0,$limit";
	$resp = Conexion::buscarVariosRegistro($query);
	foreach ($resp as $key => $anuncio) {
		$categorias = $anuncio['categorias'];
		unset($resp[$key]['categorias']);

		$resp[$key]['image_min'] = url.$anuncio['image_min'];
		if($categorias !== ""){
			$resp[$key]['categorias'] = explode(",",$categorias);
			fillArrayItemsNoRepeat($categoriasGenerales, $resp[$key]['categorias']);
		}
		else
			$resp[$key]['categorias'] = [];
	}
	$return['success'] = 1;
	$return['mensaje'] = "Lista Anuncio web";
	$return['categorias'] = $categoriasGenerales;
	$return['data'] = $resp;
	return $return;
}

function ordenarItems($id, $cod_sucursal){
    global $Clproductos;
	$Clproductos->cod_sucursal = $cod_sucursal;
    return $Clproductos->listaModuloWeb($id);
}

function SugerenciasCheckout($cod_sucursal){
    $cod_empresa = cod_empresa;
    $query = "SELECT * FROM tb_web_modulos_productos WHERE cod_empresa = $cod_empresa AND modulo = 'SUGERENCIAS'";
    $resp = Conexion::buscarRegistro($query);
    if($resp){
        $return['success']= 1;
		$return['mensaje']= "Lista de sugerencias";
		$return['data']= ordenarItems($resp['cod_web_modulos_producto'], $cod_sucursal);
		showResponse($return);
    }else{
        $return['success']= 0;
		$return['mensaje']= "No hay sugerencias activas";
		showResponse($return);
    }
}

function fillArrayItemsNoRepeat(&$fill, $newArray){
	foreach($newArray as $key => $item){
		if(!in_array($item, $fill)){
			$fill[] = $item;
		}
	}
}


?>