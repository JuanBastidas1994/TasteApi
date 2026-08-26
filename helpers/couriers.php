<?php

// Unica fuente de verdad para los couriers externos (cobran por consulta y su
// precio ya incluye IVA). Para agregar/quitar un courier externo, cambiar
// solo aqui - cl_carrito.php, calcularPrecioEnvio.php, etc. leen de esta
// misma lista.
define('COURIER_GACELA', 1);
define('COURIER_PICKER', 3);
define('COURIER_PEDIDOS_YA', 5);

define('COURIERS_EXTERNOS', [
    COURIER_GACELA     => 'GACELA',
    COURIER_PICKER     => 'PICKER',
    COURIER_PEDIDOS_YA => 'PEDIDOS_YA',
]);
