<?php

require_once "strategies/ClientFirstOrderInterface.php";

//Strategias
require_once "strategies/ClientFirstOrder/FreeProduct.php";
require_once "strategies/ClientFirstOrder/NoAward.php";

class ClientFirstOrderValues {
    const STRATEGY = [
        0 => NoAward::class,
        1 => FreeProduct::class
    ];
}