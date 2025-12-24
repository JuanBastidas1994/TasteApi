<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_noticias.php";
$ClNoticias = new cl_noticias();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 1){
			$limit = (isset($_GET['limit'])) ? $_GET['limit'] : 999;
			$noticias = $ClNoticias->lista($limit);
			if($noticias){
				$return['success'] = 1;
				$return['mensaje'] = "Correcto";
				$return['limit'] = $limit;
				$return['data'] = $noticias;
			}else{
				$return['success'] = 0;
				$return['mensaje'] = "No hay datos";
			}
			showResponse($return);
		}else if($num_variables == 2){
		    $first = $request[1];
			if($first == "categoria-list"){
			    $categorias = $ClNoticias->getListaCategorias();
			    if($categorias){
    				$return['success'] = 1;
    				$return['mensaje'] = "Lista de categorías de blog";
    				$return['data'] = $categorias;
    			}else{
    				$return['success'] = 0;
    				$return['mensaje'] = "No hay categorías";
    			}
    			showResponse($return);
			}else{
    		    $noticias = $ClNoticias->get($request[1]);
    			if($noticias){
    				$return['success'] = 1;
    				$return['mensaje'] = "Correcto";
    				$return['data'] = $noticias;
    			}else{
    				$return['success'] = 0;
    				$return['mensaje'] = "No hay noticias";
    			}
    			showResponse($return);
			}
		}else if($num_variables == 3){
			$first = $request[1];
			if($first == "categoria"){
				$limit = (isset($_GET['limit'])) ? $_GET['limit'] : 999;
				$noticias = $ClNoticias->listaByCategoria($request[2], $limit);
				if($noticias){
					$return['success'] = 1;
					$return['mensaje'] = "Correcto";
					$return['limit'] = $limit;
					$return['data'] = $noticias;
				}else{
					$return['success'] = 0;
					$return['mensaje'] = "No hay noticias";
				}
				showResponse($return);
			}
			else if($first == "politicas"){
				$noticias = $ClNoticias->getPolitica($request[2]);
				if($noticias){
					$return['success'] = 1;
					$return['mensaje'] = "Correcto";
					$return['data'] = $noticias;
				}else{
					$return['success'] = 0;
					$return['mensaje'] = "No hay datos";
				}
				showResponse($return);
			}
			else if($first == "busqueda"){
				$noticias = $ClNoticias->getByBusqueda($request[2]);
				if($noticias){
					$return['success'] = 1;
					$return['mensaje'] = "Correcto";
					$return['data'] = $noticias;
				}else{
					$return['success'] = 0;
					$return['mensaje'] = "No hay datos";
				}
				showResponse($return);
			}
		}else{
		    $return['success']= 0;
			$return['mensaje']= "Url no valida para Noticias, por favor revisar los parametros";
			showResponse($return);
		}
	}
	else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para noticias aun no esta disponible.";
		showResponse($return);
	}
?>