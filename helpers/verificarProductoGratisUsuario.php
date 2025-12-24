<?php

require_once "clases/cl_ordenes.php";
require_once "clases/cl_usuarios.php";


function getProductFreeAvailableUser($office_id, $user_id, $tipo = "WEB"){
    $Clordenes = new cl_ordenes();
    $Clusuarios = new cl_usuarios();

    //Productos Gratis
    $freeProduct = $Clordenes->getFreePromo($office_id, $tipo);
    if($freeProduct){
        $typesAvailables = ['FIRST_ORDER', 'PURCHASE'];
        if(in_array($freeProduct['tipo'], $typesAvailables)){
            if($freeProduct['tipo'] == 'FIRST_ORDER'){
                if($Clusuarios->getNumOrders($user_id) > 0) 
                    return null;
            }
            $freeProduct['imagen'] = url.$freeProduct['imagen'];
            return $freeProduct;
        }
    }
    return null;
}

function getFreePromoAvailable(){

}


?>