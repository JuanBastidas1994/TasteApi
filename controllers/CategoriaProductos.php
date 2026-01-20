<?php
	/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_categorias.php";
require_once "clases/cl_productos.php";
$Clcategorias = new cl_categorias();
$Clproductos = new cl_productos();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 1){
			$return = lista();
			showResponse($return);
		}
		if($num_variables == 2){
			if($request[1]=="productos"){
				$return = productos();
				showResponse($return);
			}else{
				$return = get($request[1]);
				showResponse($return);
			}
		}
		if($num_variables == 3){
			if($request[1]=="productos"){
				$return = productos($request[2]);
				showResponse($return);
			}else if($request[1]=="productos-low"){
				$return = productosBasico($request[2]);
				showResponse($return);
			}else{
			    $return = get($request[2]);
				showResponse($return);
			}
		}
		if($num_variables == 4){
			if($request[1]=="adicionales"){
				$return = adicionales($request[2],$request[3]);
				showResponse($return);
			}
		}
	}
	else if($method == "POST"){
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Login aun no esta disponible.";
		showResponse($return);
	}else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Login aun no esta disponible.";
		showResponse($return);
	}


/*FUNCIONES*/
function lista(){
	global $Clcategorias;
	$productos = $Clcategorias->lista();
	if(count($productos)>0){
		$return['success'] = 1;
		$return['mensaje'] = "Correcto";
		$return['data'] = $productos;
	}else{
		$return['success'] = 0;
		$return['mensaje'] = "No hay datos";
	}
	return $return;
}

function get($cod_sucursal = 0){
    global $Clcategorias;
	global $Clproductos;
	global $request;
	$alias = $request[1];
	
	if(!is_numeric($cod_sucursal))
	    $cod_sucursal = sucursaldefault;
	
	$categoria = $Clcategorias->getbyAlias($alias);
	if($categoria){
		$Clproductos->cod_sucursal = $cod_sucursal;
		$productos = $Clproductos->listaByCategoriaAlias($alias);
		if(!$productos){
		    $return['success'] = 0;
		    $return['mensaje'] = "No hay datos";
		    return $return;
		}
		$return['success'] = 1;
		$return['mensaje'] = "Correcto";
		$return['alias'] = $alias;
		$return['categoria'] = $categoria;
		$return['data'] = $productos;
    }else{
        $return['success'] = 0;
		$return['mensaje'] = "Categoria $alias no existe";
    }
	return $return;
}

function adicionales($cod_categoria, $cod_sucursal){
	global $Clcategorias;
	global $Clproductos;

	$Clproductos->cod_sucursal = $cod_sucursal;
	$respAdicionales = $Clcategorias->getAdicionalesByCategoria($cod_categoria);
	if($respAdicionales){
		foreach($respAdicionales as $key => $adicional){
			$respAdicionales[$key]['productos']=$Clproductos->listaBycategoria($adicional['cod_categoria_items']);
		}
	}

	$return['success'] = 1;
	$return['mensaje'] = "Correcto";
	$return['data'] = $respAdicionales;
	return $return;
}

function productos($cod_sucursal = 0){
	global $Clcategorias;
	global $Clproductos;

	$Clproductos->cod_sucursal = $cod_sucursal;
	$respCategorias = $Clcategorias->lista();
	foreach ($respCategorias as $key => $categoria) {
	    $productos = $Clproductos->listaBycategoria($categoria['cod_categoria']);
		$respCategorias[$key]['productos']=$productos;
		$respCategorias[$key]['count']=count($productos);
	    if(count($productos)==0){
	            unset($respCategorias[$key]);
	    }
	}

	$return['success'] = 1;
	$return['mensaje'] = "Correcto, Lista de Categorías con sus productos";
	$return['data'] = $respCategorias;
	return $return;
}

function productosBasico($cod_sucursal = 0){
	if($cod_sucursal == 0) {
		$cod_sucursal = getFirstSucursal();
	}
	if($cod_sucursal == 0) {
		$return['success'] = 0;
		$return['mensaje'] = "No se pudo obtener la sucursal por defecto";
	}
	global $Clcategorias;
	global $Clproductos;
    
    
	$Clproductos->cod_sucursal = $cod_sucursal;
	$categorias = $Clcategorias->listaBasica();

	$respCategorias = [];
	$productosPromo = [];
    $x=0;

	foreach ($categorias as $key => $categoria) {
		$productos = $Clproductos->listaBasicaByCategoria($categoria['cod_categoria']);

        if (count($productos) > 0) {
			//Productos con promoción
			foreach ($productos as $producto) {
                if (!empty($producto['promocion'])) {
                    $productosPromo[] = $producto;
                }
            }
			$categoria['productos'] = $productos;
			$respCategorias[$x] = $categoria;
			$x++;
		}
	}

	if (count($productosPromo) > 0) {
        $categoriaPromo = [
            'cod_categoria' => 'PROMO',
            'alias'          => 'promociones',
            'categoria'      => 'Promociones',
            'image_min'      => '',
            'image_max'      => '',
            'productos'      => $productosPromo
        ];
        array_unshift($respCategorias, $categoriaPromo); // Insertar al inicio
    }

	return [
        'success' => 1,
        'mensaje' => 'Lista de productos con informacion basica',
        'data'    => $respCategorias
    ];
}
?>