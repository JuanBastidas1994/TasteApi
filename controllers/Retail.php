<?php
/*	Variables Heredadas del Index
		$method - POST, GET, PUT, DELETE, etc.
		$request - Url y variables GET
		$input - Solo metodo POST, PUT */

require_once "clases/cl_empresas.php";
$ClEmpresas = new cl_empresas();

if($method == "GET"){
	$num_variables = count($request);
	if($num_variables == 1){
		showResponse(getConfiguracion());
	}
	
	showResponse([ 'success' => 0, 'message' => 'url no valida']);
}
else if($method == "POST"){
    $num_variables = count($request);
    if($num_variables == 2){
        $metodo = $request[1];
        if($metodo == "cotizar"){
            $resp = CostoEnvio();
			showResponse($resp);
        }
    }
}
else{
    showResponse([ 'success' => 0, 'message' => "El metodo ".$method." para configuracion aun no esta disponible."]);
}


function getConfiguracion(){
    $query = "SELECT provincia FROM tb_ciudades WHERE cod_courier = 2 AND estado = 'A' GROUP by provincia order by provincia ASC";
    $provincias = Conexion::buscarVariosRegistro($query);
    foreach($provincias as $key => $provincia){
        $provincias[$key]['ciudades'] = Conexion::buscarVariosRegistro(
                "SELECT cod_ciudad as id, nombre, codigo FROM tb_ciudades where provincia = '".$provincia['provincia']."' AND estado = 'A' AND cod_courier = 2 order by nombre ASC");
    }
    return [
        'success' => 1,
        'message' => 'Informacion correcta',
        'data' => $provincias
    ];
}	


function CostoEnvio(){
    global $input;

    extract($input);
	$datosObligatorios = array("destino","productos");
	foreach ($datosObligatorios as $key => $value) {
		if (!array_key_exists($value, $input)) {
		    $return['success'] = 0;
    		$return['mensaje'] = "Falta informacion, Error: Campo $value es obligatorio";
			return $return;
		}
	}

    require_once "clases/cl_laar.php";
    $cod_sucursal = sucursaldefault;
    $ClLaar = new cl_laar(cod_empresa, $cod_sucursal);
    if($ClLaar->IsInitialized){
        require_once "clases/cl_productos.php";
        $Clproductos = new cl_productos();
        $pesoTotal = 0;
        $cantTotal = 0;
        /*PRODUCTOS*/
        if(count($productos)==0){
            $return['success'] = 0;
    		$return['mensaje'] = "No hay productos";
    		return $return;
        }
	    foreach($productos as $item){
	        $id = $item['id'];
            $cant = $item['cantidad'];

            $producto = $Clproductos->first($id);
            if($producto){
                $peso = $producto['peso'];
                $cantTotal = $cantTotal + $cant;
                $pesoTotal = number_format($pesoTotal + ($peso*$cant),2);
            }else{
                $return['success'] = 0;
    		    $return['mensaje'] = "Producto con id $id no existe";
    		    return $return;
            }
	    }
	    /*PRODUCTOS*/

        $idDestino = $ClLaar->getCodeCiudadById($destino);
        if(!$idDestino){
            $return['success'] = 0;
            $return['mensaje'] = "Sucursal con id $destino no tiene una relacion con Laar";
            return $return;
        }

        $envLaar = null;
        $costo = $ClLaar->costoEnvio($idDestino, $cantTotal, $pesoTotal, $envLaar);
        if(isset($costo['Flete']))
            $precio = number_format($costo['Flete'], 2);
        else    
            $precio = number_format(0, 2);

        $return['success'] = 1;
        $return['mensaje'] = "Costo de envio";
        $return['precio'] = $precio;
        $return['piezas'] = $cantTotal;
        $return['peso'] = floatval($pesoTotal);
        $return['laarResp'] = $costo;
        $return['laarEnv'] = $envLaar;

    }else{
        $return['success'] = 0;
        $return['mensaje'] = $ClLaar->msgError;
        $return['mensaje'] = "Empresa no tiene configurado Laar";
    }
    return $return;
}
?>