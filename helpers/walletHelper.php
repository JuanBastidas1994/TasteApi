<?php

function getWallet($user_id){
    require_once "clases/cl_clientes.php";
    $dinero = 0;
    $puntos = 0;
    $saldo = 0;
    $nivel = "";
    
    $clientes = new cl_clientes();
    if($clientes->getByUser($user_id)){
        $dinero = number_format($clientes->GetDinero(),2);
        $puntos= $clientes->GetPuntos();
        $saldo = number_format($clientes->GetSaldo(),2);
        $nivel = $clientes->getNivel($puntos);
    }
    
    return [
        "client_id" => $clientes->cod_cliente,
        "puntos" => $puntos,
        "dinero" => $dinero,
        "saldo" => $saldo,
        "nivel" => $nivel
    ];
}

?>