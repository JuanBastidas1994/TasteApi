<?php
require_once "clases/cl_productos.php";
$Clproductos = new cl_productos();

if($method == "GET"){
    $num_variables = count($request);
	if($num_variables == 3){
	    if($request[1]=="promocion"){
	        $return = getPromo($request[2]);
		    showResponse($return);
	    }
	}
}else{
	$return['success']= 0;
	$return['mensaje']= "El metodo ".$method." para Osole aun no esta disponible.";
	showResponse($return);
}

function getPromo($alias_categoria){
    global $Clproductos;
    
    $return['categoria'] = $alias_categoria;
    
    $day = dayOfWeek(fecha()); //1 LUNES | 7 DOMINGO
    if($day != 8){ //SI NO ES MARTES NO HAY PROMO
        $return['success'] = 0;
    	$return['mensaje'] = "No es día de promociones";
    	return $return;
    }
    
    //VALIDAR CATEGORIA CON PRODUCTO QUEMADO!!
    $alias_producto_promo = "";
    switch($alias_categoria){
        case 'pizza-gigante':
            $alias_producto_promo = "base-solo-queso33";
            break;
        case 'pizza-grande':
            $alias_producto_promo = "base-solo-queso33";
            break;
        case 'pizza-mediana':
            $alias_producto_promo = "base-solo-queso-mediana";
            break;
        /*case 'pizza-pequena':
            $alias_producto_promo = "base-solo-queso-pequena";
            break;   */ 
        default:
            $return['success'] = 0;
        	$return['mensaje'] = "No es una pizza";
        	return $return;
    }
    
    $Clproductos->cod_sucursal = sucursaldefault;
	$resp = $Clproductos->getInfoByAlias($alias_producto_promo);
	if($resp){
	    //$resp['opciones'][] = addOpcionGratis($resp);
	    foreach($resp['opciones'] as $key => $opcion){
	        $resp['opciones'][$key]['titulo'] = $opcion['titulo']." de la promo"; 
	    }
	    array_unshift($resp['opciones'], addOpcionGratis($resp));
	    $return['success'] = 1;
    	$return['mensaje'] = "Promoción activa";
    	$return['data'] = $resp;
    	return $return;
	}else{
	    $return['success'] = 0;
    	$return['mensaje'] = "No hay promociones activas";
    	$return['data'] = $resp;
    	$return['sucursal'] = sucursaldefault;
    	return $return;
	}
}

function addOpcionGratis($producto){
    $opciones=["Jamón","Chorizo","Salame","Extra queso","Lomo canadiense","Pepperoni","Hongos","Tomate","Choclo","Aceitunas","Piña","Cebolla","Albahaca","Pimineto verde",
                "Ajo","Tocino","Carne ahumada","Jamón glaseado"];
    
    /*ARMAR OPCION QUEMADA GRATIS*/
    $opcion['cod_producto_opcion'] = 9999999;
    $opcion['cod_producto'] = $producto['cod_producto'];
    $opcion['titulo'] = "Promo martes 2x1 - Escoge un ingrediente gratis";
    $opcion['cantidad'] = 1;
    $opcion['cantidad_min'] = 1;
    $opcion['isCheck'] = "1";
    $opcion['isDatabase'] = 0;
    $opcion['posicion'] = 1;
    $items = [];
    
    foreach($opciones as $op){
        addItemOpcion($op, $items);    
    }
    $opcion['items'] = $items;
    
    return $opcion;
}

function addItemOpcion($texto, &$items){
    $id = 9999999;
    $id = $id + count($items);
    $item['cod_producto_opciones_detalle'] = $id;
    $item['item'] = $texto;
    $item['aumentar_precio'] = 0;
    $item['precio'] = 0;
    $item['disponible'] = true;
    $items[] = $item;
}