<?php
	/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_productos.php";
$Clproductos = new cl_productos();

	if($method == "GET"){
		$num_variables = count($request);
		if($num_variables == 1){
			$productos = $Clproductos->lista();
			if(count($productos)>0){
				$return['success'] = 1;
				$return['mensaje'] = "Correcto";
				$return['data'] = $productos;
			}else{
				$return['success'] = 0;
				$return['mensaje'] = "No hay datos";
			}
			showResponse($return);
		}
		if($num_variables == 2){
		    $cod_producto = $request[1];
			$cod_sucursal = sucursaldefault;
			$Clproductos->setSucursal($cod_sucursal);
			if(is_numeric($request[1])){    //INFORMACION DE UN PRODUCTO
		        $producto = $Clproductos->get($cod_producto);
		    }else{
		        $producto = $Clproductos->getInfoByAlias($cod_producto);
		    }
			if($producto){
				$return['success'] = 1;
				$return['mensaje'] = "Correcto";
				$return['data'] = $producto;
			}else{
				$return['success'] = 0;
				$return['mensaje'] = "No hay datos";
			}
			showResponse($return);
		}
		if($num_variables == 3){
			
			$first = $request[1];
		    if(is_numeric($request[1]) && is_numeric($request[2])){
		        $cod_producto = $request[1];
		        $cod_sucursal = $request[2];
				$Clproductos->setSucursal($cod_sucursal);
		        $resp = $Clproductos->getInfo($cod_producto);
		        if($resp)
    			{
    				$return['success'] = 1;
    				$return['mensaje'] = "Correcto";
    				$return['data'] = $resp;
    			}else{
    				$return['success'] = 0;
    				$return['mensaje'] = "No hay datos";
    			}
    			showResponse($return);	
		    }
		    if(is_numeric($request[2])){
		        $alias = $request[1];
		        $cod_sucursal = $request[2];
				$Clproductos->setSucursal($cod_sucursal);
		        $resp = $Clproductos->getInfoByAlias($alias);
		        if($resp)
    			{
    				$return['success'] = 1;
    				$return['mensaje'] = "Correcto";
    				$return['data'] = $resp;
    			}else{
    				$return['success'] = 0;
    				$return['mensaje'] = "No hay datos";
    			}
    			showResponse($return);	
		    }
		}
		if($num_variables == 4){
		    if($request[1] == "detalle"){
		        $cod_producto = $request[2];
		        $cod_producto = $request[3];
		    } 
		    if($request[1] == "search"){
		        $filtro = $request[2];
		        $cod_sucursal = $request[3];
				$Clproductos->setSucursal($cod_sucursal);
		        $data = $Clproductos->listaByFilter($filtro);
		        if($data){
		            $return['success'] = 1;
    				$return['mensaje'] = "Correcto";
    				$return['data'] = $data;
		        }else{
		            $return['success'] = 0;
    				$return['mensaje'] = "No hay datos";
		        }
		        showResponse($return);
		    }
			if($request[1] == "search_tag"){
		        $filtro = $request[2];
		        $cod_sucursal = $request[3];
				$Clproductos->setSucursal($cod_sucursal);
		        $data = $Clproductos->listaByFilterTag($filtro);
		        if($data){
		            $return['success'] = 1;
    				$return['mensaje'] = "Correcto";
    				$return['data'] = $data;
		        }else{
		            $return['success'] = 0;
    				$return['mensaje'] = "No hay datos";
		        }
		        showResponse($return);
		    } 
		    if($request[1] == "opciones"){
		        $cod_producto = $request[2];
		        $cod_sucursal = $request[3];
		        $Clproductos->cod_sucursal = $cod_sucursal;
		        $data = $Clproductos->opciones($cod_producto);
		        if($data){
		            $return['success'] = 1;
    				$return['mensaje'] = "Correcto";
    				$return['data'] = $data;
		        }else{
		            $return['success'] = 0;
    				$return['mensaje'] = "No hay opciones";
		        }
		        showResponse($return);
		    }
		}
	}else{
		$return['success']= 0;
		$return['mensaje']= "El metodo ".$method." para Productos aun no esta disponible.";
		showResponse($return);
	}


?>