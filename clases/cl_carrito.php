<?php

require_once __DIR__ . '/../helpers/calcularPrecioEnvio.php';
require_once __DIR__ . '/../helpers/cotizacionPrecio.php';
require_once __DIR__ . '/../helpers/couriers.php';

class cl_carrito
{
    public $cod_usuario;
    public $quitarCupon = false, $motivoCupon = "", $infoCupon = null;
    public $productos = null, $subtotal, $iva, $total, $service = 0, $descuento = 0, $descuento_no_tax, $envio = 0, $peso = 0;
    public $base0 = 0, $base12 = 0, $desxitem = 0;
    public $tarifa_id = 0;

    // Envio real calculado internamente cuando el request trae 'entrega' (lat/lng).
    // Unica fuente de verdad del precio de envio - ver plan "cl_carrito como
    // unica fuente del precio de envio". Si 'entrega' llega, $array['envio']
    // nunca se usa (salvo la unica excepcion: courier externo fuera de
    // checkout, donde tampoco se le paga por una consulta - ahi se calcula un
    // estimado interno igual, sin tocar el envio del cliente).
    public $cotizacion_id = null;
    public $distancia_envio = null;
    public $courier_envio = null;
    // Se llena solo si 'entrega' llega marcada como delivery pero sin lat/lng
    // (estado erroneo, no deberia pasar nunca) - el llamador (Carrito.php /
    // Ordenes.php) debe rechazar con success:0 si esto no es null.
    public $envioError = null;
    public $num_items = 0;
    public $tiempo_preparacion = 0;
    public $tipo_descuento = 0; //0 PORCENTAJE - 1 EFECTIVO
    public $valor_descuento = 0; //VALOR A DESCONTAR
    public $metodoEnvio = null, $metodoPago = null, $infoDescuento = null, $promo_envio=null;
    public $percentIva = 15;
    public $DivitIva = 1.15;
    public $service_percentage = 0;
    public $numDecimals = 2;
    public $observacion = "";

    public $promociones = [], $promocionesResp = [], $productos_free = [], $progreso_promo_free = [];
    public $descuentoAux = 0;

    public $desc_envio, $subtotal_only_products, $subtotal_without_envio;
    
    public $sucursal_cobra_iva = 0;

    public $officeTaxable = true;
    public $tesst = 0;
    
    public $logs = [];

    public function __construct($array, $cod_sucursal)
    {
        $this->getBusinessData();
        $this->calcular($array, $cod_sucursal);
    }
    
    public function getBusinessData(){
        $query = "SELECT impuesto, service_percentage FROM tb_empresas WHERE cod_empresa = " . cod_empresa;
        $resp = Conexion::buscarRegistro($query);
        if ($resp){
            $this->service_percentage = $resp['service_percentage'];
            $this->percentIva = $resp['impuesto'];
            $this->DivitIva = 1 + ($this->percentIva / 100);
        }
    }

    public function calcular($array, $cod_sucursal)
    { //getSucursalEnvioGravaIVA
        $Clproductos = new cl_productos();
        $Clsucursales = new cl_sucursales();
        $Clproductos->setSucursal($cod_sucursal);
        $this->officeTaxable = ($Clproductos->officeTaxable == 1) ? true : false;
        $num_items = 0;
        $base0 = 0;
        $base12 = 0;
        $subtotal = 0;
        $subtotal_without_envio = 0;
        $descuento = 0;
        $descuento_no_tax = 0;
        $adicionalTotal = 0;
        $totalOrderWithTax = 0;
        $tiempo_preparacion = 0;
        $peso = 0;
        $tarifa_id = 0;
        $items = [];

        $desxitem = 0;
        $productos = null;
        if (isset($array['productos'])) {
            $productos = $array['productos'];
        }
        
        $cupon = "";
        if (isset($array['cupon'])) {
            if($array['cupon'] !== "")
                $cupon = $array['cupon'];
        }
        $promoData = $Clproductos->getPromocionesActivas($cod_sucursal);
        $Clproductos->promosByProducto = $promoData['normales'];   // igual que antes
        $promosAvanzadas = $promoData['avanzadas'];                 // nuevo

        $x = 0;
        if (is_array($productos))
            foreach ($productos as $p) {
                $elemento = $Clproductos->getInfoBasic($p['id'], $cod_sucursal);
                if ($elemento) {
                    $items[$x] = $elemento;
                    $items[$x]['cantidad'] = $p['cantidad'];
                    $items[$x]['identificador'] = isset($p["identificador"]) ? $p["identificador"] : $p["time"];
                    $items[$x]['descripcion'] = isset($p["descripcion"]) ? $p["descripcion"] : "";
                    $items[$x]['comentarios'] = isset($p["comentarios"]) ? $p["comentarios"] : "";
                    $items[$x]['eventDay'] = isset($p["eventDay"]) ? $p["eventDay"] : "";
                    $items[$x]['opciones'] = isset($p["opciones"]) ? $p["opciones"] : [];
                    $precio = $items[$x]['precio'];
                    $precio_no_tax = ($elemento['cobra_iva'] == 0) ? $precio : $this->noRound($precio / $this->DivitIva, false);
                    if(!$this->officeTaxable){ //Si la sucursal no cobra iva en general
                        //  $precio_no_tax = $this->noRound($precio / $this->DivitIva, false);
                         $precio_no_tax = $precio;
                         $items[$x]['cobra_iva'] == 0;
                    }
                    
                    $items[$x]['precio_no_tax'] = $precio_no_tax;
                    $totalItemWithoutTax = $precio_no_tax * $p['cantidad'];
                    $totalItem = $precio * $p['cantidad']; //Precio total no Tax
                    $totalNoDiscount = $totalItem;
                    //Base 0 | Base 12 => Precio x cantidad
                    if($items[$x]['cobra_iva'] == 1) {
                        $items[$x]['base0'] =  $this->noRound(0, false,2);
                        $items[$x]['base12'] = $this->noRound($totalItemWithoutTax, false,2);
                    }else{
                        $items[$x]['base0'] = $this->noRound($totalItemWithoutTax, false,2);
                        $items[$x]['base12'] = $this->noRound(0, false,2);
                    }

                    /*CALCULAR PROMOCIONES*/
                    $items[$x]['2x1'] = false;
                    $descuentoItem = 0;
                    $descuentoItemWithoutTax = 0;
                    $descuentoPorcentaje = 0;
                    
                    // $promocion = $Clproductos->isPromocion($p['id']);
                    $promocion = $Clproductos->promosByProducto[$p['id']] ?? null;
                    if ($promocion) {
                        if ($promocion['is_porcentaje'] == 1) {
                            $valor = $promocion['valor'];
                            $descuentoPorcentaje = $valor;
                            //SIN IMPUESTO
                            $descuentoItemWithoutTax = $this->noRound($totalItemWithoutTax * ($valor / 100), false);
                            $totalItemWithoutTax = $this->noRound($totalItemWithoutTax - $descuentoItemWithoutTax, false);

                            //CON IMPUESTOS
                            $descuentoItem = $this->noRound($totalItem * ($valor / 100), false);
                            $totalItem = $this->noRound($totalItem - $descuentoItem, false);
                            
                        } else {
                            $items[$x]['2x1'] = true;
                            $numDescuentos = 0;
                            $this->PromocionNxM($p['id'], $p['cantidad'], $precio, $promocion, $elemento['nombre'], $elemento['image_min'], $descuentoItem, $descuentoPorcentaje, $numDescuentos);
                            
                            //SIN IMPUESTO
                            $descuentoItemWithoutTax = $this->noRound($precio_no_tax * $numDescuentos, false);
                            $totalItemWithoutTax = $this->noRound(($precio_no_tax * $p['cantidad']) - $descuentoItemWithoutTax, false);
                            
                            //CON IMPUESTOS
                            //$descuentoItem = $this->noRound($totalItem * ($valor / 100), false);
                            $totalNoDiscount = $this->noRound(($precio * $p['cantidad']), false);
                            $totalItem = $this->noRound($totalNoDiscount - $descuentoItem, false);
                        }
                    }
                    $items[$x]['cod_promocion'] = $promocion ? $promocion['cod_promocion'] : null;
                    $descuento = $this->noRound($descuento + $descuentoItem, false);
                    $descuento_no_tax = $this->noRound($descuento_no_tax + $descuentoItemWithoutTax, false);
                    $items[$x]['descuento'] = $this->noRound($descuentoItem, false);
                    $items[$x]['descuento_no_tax'] = $this->noRound($descuentoItemWithoutTax, false);
                    $items[$x]['descuentoPorcentaje'] = $descuentoPorcentaje;
                    //DEMO
                    $items[$x]['descuentoNumeroAplicados'] = isset($numDescuentos) ? $numDescuentos : 0;
                    /*FIN CALCULAR PROMOCIONES*/

                    //Precio Adicional
                    $precio_adicional = 0;
                    $opciones = $this->getOpcionesYPrecioAdicional($p['id'], $cod_sucursal, $items[$x]['opciones'], $p['cantidad'], $precio_adicional);
                    $items[$x]['opciones'] = $opciones;
                    
                    $precio_adicional_no_tax = (!$this->officeTaxable) ? $precio_adicional : $this->noRound($precio_adicional / $this->DivitIva, false);
                    // $precio_adicional_no_tax_total = $this->noRound($precio_adicional_no_tax * $p['cantidad'], false);
                    $precio_adicional_no_tax_total = $this->noRound($precio_adicional_no_tax, false);
                    $adicionalTotal += $precio_adicional_no_tax_total;
                    $totalItem = $totalItem + $precio_adicional;
                    $totalNoDiscount = $totalNoDiscount + $precio_adicional;
                    
                    $items[$x]['precio_no_tax'] = $this->noRound($items[$x]['precio_no_tax'], false);
                    $items[$x]['precio'] = $this->noRound($precio, false);
                    $items[$x]['precio_adicional'] = $this->noRound($precio_adicional, false);
                    $items[$x]['precio_adicional_no_tax'] = $this->noRound($precio_adicional_no_tax, false);
                    $items[$x]['precio_adicional_no_tax_total'] = $this->noRound($precio_adicional_no_tax_total, false);
                    $items[$x]['precio_anterior'] = $this->noRound($items[$x]['precio_anterior'], false);
                    $items[$x]['cantidad'] = $p['cantidad'];
                    $items[$x]['total_no_discount'] = $this->noRound($totalNoDiscount, false);
                    $items[$x]['total_without_tax'] = $this->noRound($totalItemWithoutTax, false);
                    $items[$x]['total'] = $this->noRound($totalItem, false);
                    if($items[$x]['cobra_iva'] == 1) {
                        $items[$x]['subtotal0'] =  $this->noRound(0, false,2);
                        $items[$x]['subtotal12'] = $items[$x]['total_without_tax'];
                    }else{
                        $items[$x]['subtotal0'] =  $items[$x]['total_without_tax'];
                        $items[$x]['subtotal12'] = $this->noRound(0, false,2);
                    }
                    $totalOrderWithTax += $totalItem;
                    $x++;
                } else {
                }
            }

        //CUPON
        $quitarCupon = false;
        $motivo = "";
        if ($cupon !== "") {
            $descCuponItem = $this->verifyCuponDiscount($array['cupon'], $totalOrderWithTax, $descuento, $motivo);
            if (!$descCuponItem) {
                $quitarCupon = true;
            } else {
                $descApliAcu = 0;
                for ($k = 0; $k < count($items); $k++) {
                    $itemPrecioNoTax = $items[$k]['total_without_tax'];
                    $itemPrecio = $items[$k]['total'];
                    if ($k == count($items) - 1) { //SI ES EL ULTIMO ITEM RESTAR DESCUENTO - ACUMULADOR DESCUENTO PARA QUE SIEMPRE CUADRE
                        $descItem = $this->noRound($this->descuentoAux - $descApliAcu, false);
                        $descItemNoTax = $this->noRound($itemPrecioNoTax * $descCuponItem, false);
                    } else{
                        $descItem = $this->noRound($itemPrecio * $descCuponItem, false);
                        $descItemNoTax = $this->noRound($itemPrecioNoTax * $descCuponItem, false);
                    }

                    $descApliAcu = $descApliAcu + $descItem;
                    $descuento = $descuento + $descItem;
                    $items[$k]['descuento'] = $descItem;
                    $items[$k]['descuento_no_tax'] = $descItemNoTax;
                    $items[$k]['descuentoPorcentaje'] = $descCuponItem * 100;
                    $items[$k]['total_without_tax'] = $this->noRound($itemPrecioNoTax - $descItemNoTax, false);
                    $items[$k]['total'] = $this->noRound($itemPrecio - $descItem, false);
                }
            }
        }
        //FIN DESCUENTO

        /*==========================================================
        CALCULAR PRODUCTOS FREE (promos avanzadas)
        Se evalúa después de tener todos los items procesados
        ==========================================================*/
        $productos_free = [];
        $progresos_promo_free = [];

        foreach ($promosAvanzadas as $promoAv) {

            if (!$promoAv['cod_producto_regalo']) continue;

            $tipo          = $promoAv['texto'];
            $participantes = $promoAv['productos_participantes']; // array de cod_producto
            $cantidadRegalo = 0;

            if ($tipo === 'compra_x_lleva_y') {
                foreach ($items as $item) {
                    if (in_array($item['cod_producto'], $participantes)) {
                        $cantidadRegalo += $item['cantidad'];
                    }
                }

            } elseif ($tipo === 'monto_minimo') {
                $subtotalParticipantes = 0;
                foreach ($items as $item) {
                    if (in_array($item['cod_producto'], $participantes)) {
                        $subtotalParticipantes += $item['total'];
                    }
                }
                $montoMinimo = floatval($promoAv['valor']);
                $cantidadRegalo = ($subtotalParticipantes >= $montoMinimo) ? 1 : 0;

                if ($cantidadRegalo <= 0 && $montoMinimo > 0) {
                    $infoProgreso = $Clproductos->getInfoBasic($promoAv['cod_producto_regalo'], $cod_sucursal);
                    if ($infoProgreso) {
                        $porcentaje = min(100, round($subtotalParticipantes / $montoMinimo * 100, 2));
                        $valorParaLlegar = number_format($montoMinimo - $subtotalParticipantes, 2);
                        $progresos_promo_free[] = [
                            'cod_promocion'     => $promoAv['cod_promocion'],
                            'nombre_producto'   => $infoProgreso['nombre'],
                            'imagen_producto'   => $infoProgreso['image_min'],
                            'precio_producto'   => $infoProgreso['precio'],
                            'motivo'            => $promoAv['descripcion'],
                            'monto_minimo'      => $montoMinimo,
                            'subtotal_actual'   => round($subtotalParticipantes, 2),
                            'valor_para_llegar' => $valorParaLlegar,
                            'porcentaje'        => $porcentaje,
                            'texto' => "para llevarte gratis un ".$infoProgreso['nombre']
                        ];
                    }
                }
            }

            if ($cantidadRegalo <= 0) continue;

            $infoRegalo = $Clproductos->getInfoBasic($promoAv['cod_producto_regalo'], $cod_sucursal);

            if (!$infoRegalo) continue;

            $productos_free[] = [
                'cod_producto'   => $promoAv['cod_producto_regalo'],
                'nombre'         => $infoRegalo['nombre'],
                'imagen'         => $infoRegalo['image_min'],
                'cantidad'       => $cantidadRegalo,
                'precio_normal'  => $infoRegalo['precio'],
                'motivo'         => $promoAv['descripcion'],
                'tipo_promo'     => $tipo,
                'cod_promocion'  => $promoAv['cod_promocion'],
                'fecha_fin'      => $promoAv['fecha_fin'],
            ];
        }

        usort($progresos_promo_free, fn($a, $b) => $b['porcentaje'] <=> $a['porcentaje']);
        $this->productos_free = $productos_free;
        $this->progreso_promo_free = (count($progresos_promo_free) > 0) ? $progresos_promo_free[0] : null;
        /*========================================================== FIN */
        
        $idsProductosDisponibles = [];
        $productosNoDisponibles = 0;

        //Calcular Desglose
        $base0 = 0;
        $base12 = 0;
        $subtotal = 0;
        $totalOrderWithoutDiscount = 0;
        $totalOrderWithTax = 0;

        if (count($items) > 0) {
            for ($k = 0; $k < count($items); $k++) {
                $items[$k]['total_without_tax'] = $this->noRound($items[$k]['total_without_tax'], false, 2);
                $producto = $items[$k];

                if ($producto['disponible']) {
                    if ($producto['cobra_iva'] == 1) {
                        $base12 = $base12 + $producto['total_without_tax'];
                        $items[$k]['subtotal0'] =  $this->noRound(0, false,2);
                        $items[$k]['subtotal12'] = $producto['total_without_tax'];
                    } else {
                        $base0 = $base0 + $producto['total_without_tax'];
                        $items[$k]['subtotal0'] =  $producto['total_without_tax'];
                        $items[$k]['subtotal12'] = $this->noRound(0, false,2);
                    }
                    $num_items = $num_items + $producto['cantidad'];
                    $idsProductosDisponibles[] = [
                        'cod_producto' => $producto['cod_producto'],
                        'cantidad'     => $producto['cantidad'],
                        'peso'         => $producto['peso'] ?? 0,  // si ya viene en $items
                    ];
                    $totalOrderWithoutDiscount = $totalOrderWithoutDiscount + $producto['total_no_discount'];
                    $totalOrderWithTax += $producto['total'];
                } else {
                    $items[$k]['total'] = 0;
                    $productosNoDisponibles++;
                }
            }
        }
        
        $this->logs[] = [ 'productosDisponibles' => $idsProductosDisponibles ];
        if(count($idsProductosDisponibles)>0){
            //Tiempo de preparacion
            $idsProducts = array_column($idsProductosDisponibles, 'cod_producto');
            $tiempo_preparacion = $Clproductos->getTiempoPreparacion($idsProducts);
            $this->logs[] = [ 'tiempo_preparacion' => $tiempo_preparacion ];

            $tarifa_id = $this->getTarifaEnvio($idsProductosDisponibles, $cod_sucursal, $peso);
        }
        
        //OBSERVACIONES
        $observacion = "";
        if($productosNoDisponibles > 0){
            $observacion = "1 o más productos están agotados";
        }else if($num_items == 0){
            $observacion = "No hay productos en el carrito";
        }
        else if($num_items > 0){
            if(cod_empresa == 204){ //400 GRADOS
                if(($num_items%2) !== 0){
                    $observacion = "Los productos en el carrito deben ser par, agrega otro para continuar";
                }
            }
        }
        
        $this->logs[] = [ 'base0' => $base0 ];
        if($this->officeTaxable)
            $base12 += $this->noRound($adicionalTotal, false, 2);
        else
            $base0 += $this->noRound($adicionalTotal, false, 2);
        
        $this->logs[] = [ 'base0' => $base0 ];
        
        $promoEnvio = null;
        //Calcular Envio
        $envio = $array['envio'] ?? 0;

        //Si llega 'entrega' con ubicacion real, el carrito calcula el envio el mismo - $array['envio'] deja de usarse (ver calcularEnvioReal() para
        //el detalle completo, incluida la unica excepcion).
        $envio = $this->calcularEnvioReal($array, $cod_sucursal, $tarifa_id, $envio, $Clsucursales);

        if ($envio > 0)
            $descDataEnvio = $this->getDescuentoEnvio($envio, $totalOrderWithTax, $cod_sucursal, $promoEnvio);
        else
            $descDataEnvio = null;

        //Couriers externos (Gacela/Picker/PedidosYa) cotizan un precio final que ya
        //incluye IVA - hay que sacar la base neta. Todo lo demas (tarifa propia,
        //linea recta, o el envio del cliente cuando no llega 'entrega') no incluye
        //IVA, se suma directo y el impuesto se calcula despues sobre base12.
        $courierExterno = in_array($this->courier_envio, COURIERS_EXTERNOS, true);

        //Si el envio graba IVA no depende de si la sucursal grava IVA en productos
        //(officeTaxable / tb_sucursales.grava_iva) - es un flag propio de la sucursal:
        //tb_sucursales.envio_grava_iva.
        $envioGravaIva = $Clsucursales->getSucursalEnvioGravaIVA($cod_sucursal) == 1;

        if ($envioGravaIva) {
            if ($courierExterno) {
                $envioWithoutTax = $this->noRound($envio / $this->DivitIva, false, 2);
                $base12 = $base12 + $envioWithoutTax;
            } else {
                $base12 = $base12 + $envio;
            }
        } else {
            $base0 = $base0 + $envio;
        }

        $this->logs[] = [ 'base0' => $base0 ];

        
        $subtotal = $base0 + $base12;
        $iva = $this->noRound($base12 * ($this->percentIva / 100), false, 2);
        
        $subtotal_without_envio = $totalOrderWithoutDiscount;
        
        //Porcentaje servicio solo a productos
        $service = 0;
        if($this->service_percentage > 0){
            $service = $this->noRound(($subtotal - $envio) * ($this->service_percentage / 100),2);
        }
        
        $total = $base0 + $base12 + $iva + $service;


        $this->quitarCupon = $quitarCupon;
        $this->motivoCupon = $motivo;
        $this->productos = $items;
        $this->base0 = $this->noRound($base0, false, 2);
        $this->base12 = $this->noRound($base12, false, 2);
        $this->subtotal_only_products = $this->noRound($totalOrderWithoutDiscount, false, 2);
        $this->subtotal_without_envio = $this->noRound($subtotal_without_envio, false, 2);
        $this->subtotal = $this->noRound($subtotal, false, 2);

        //Envio mostrado (web/app) siempre con IVA incluido, para que subtotal + envio + iva
        //cuadre visualmente con el total. No afecta base0/base12/iva/total: esos ya se
        //calcularon arriba con el envio real (con o sin IVA segun corresponda).
        $envioConIva = $envio;
        if ($envioGravaIva && !$courierExterno) {
            $envioConIva = $this->noRound($envio * $this->DivitIva, false, 2);
        }
        $this->envio = $this->noRound($envioConIva, false, 2);

        $this->desc_envio = $descDataEnvio;
        $this->promo_envio = $promoEnvio;
        $this->desxitem = $desxitem;
        $this->descuento = $this->noRound($descuento, false, 2);
        $this->descuento_no_tax = $this->noRound($descuento_no_tax, false, 2);
        $this->iva = $this->noRound($iva, false, 2);
        $this->service = $this->noRound($service, false, 2);
        $this->total = $this->noRound($total, false, 2);
        $this->num_items = $num_items;
        $this->peso = $peso;
        $this->tarifa_id = $tarifa_id;
        $this->tiempo_preparacion = $tiempo_preparacion;
        $this->observacion = $observacion;
    }

    // Precio real de envio cuando llega 'entrega' (lat/lng) - unica fuente de
    // verdad, ver plan "cl_carrito como unica fuente del precio de envio".
    // Si 'entrega' no llega (cliente viejo, pickup/onsite), devuelve $envio
    // tal cual sin tocar nada.
    //
    // Reglas, en orden:
    // 1. Ticket exacto vigente (cotizacion_id de una llamada anterior a
    //    /carrito) -> se usa tal cual, sin recalcular. Aplica a interno o externo.
    // 2. Externo, fuera de checkout (no confirmarPrecioExterno) -> cobra por
    //    consulta, no se le pregunta nada en vivo. Se respeta $envio del
    //    cliente (unica excepcion real a "nunca usar $envio si entrega llega"),
    //    solo con deteccion pasiva contra la ultima cotizacion reciente.
    // 3. Cualquier otro caso (interno siempre, o externo confirmando en
    //    checkout) -> $envio del cliente ya no se usa en absoluto:
    //    a. Externo confirmando: intenta el precio real pagado primero (cache
    //       corto para no duplicar consultas).
    //    b. Si (a) no aplica o fallo: calculo interno de siempre
    //       (GOOGLE_MAPS/LINEA_RECTA, getPrecioCourier con cod_courier=0).
    //    c. Si ni eso se pudo (no se pudo resolver cobertura del punto):
    //       linea recta por matematica pura desde las coordenadas base de la
    //       sucursal - siempre calculable, nunca depende de nada externo.
    public function calcularEnvioReal($array, $cod_sucursal, $tarifa_id, $envio, $Clsucursales)
    {
        $entrega = $array['entrega'] ?? null;
        if (!$entrega) {
            //Cliente viejo, nunca manda 'entrega' - unico caso donde se confia en $array['envio'].
            return $envio;
        }

        //Mismos alias que ya acepta el checkout para "es delivery".
        $metodo = strtolower($entrega['metodo'] ?? '');
        $esDelivery = in_array($metodo, ['delivery', 'd', 'envio']);
        if (!$esDelivery) {
            return 0;
        }

        if (empty($entrega['lat']) || empty($entrega['lng'])) {
            $this->envioError = 'No se pudo determinar el costo de envío: falta la ubicación de entrega';
            return 0;
        }

        $entregaLat = $entrega['lat'];
        $entregaLng = $entrega['lng'];
        $confirmarExterno = !empty($entrega['confirmarPrecioExterno']);
        $cotizacionIdRecibido = $array['cotizacion_id'] ?? null;

        $cod_courier = 0;
        $courierAsignado = $Clsucursales->getCourier($cod_sucursal);
        if ($courierAsignado) {
            $cod_courier = $courierAsignado['cod_courier'];
        }
        $esExterno = isset(COURIERS_EXTERNOS[$cod_courier]);

        //1. Ticket exacto vigente - se usa tal cual, sin recalcular nada.
        $ticket = $cotizacionIdRecibido ? buscarCotizacionValida($cotizacionIdRecibido, $cod_sucursal, $tarifa_id, $entregaLat, $entregaLng) : null;
        if ($ticket) {
            $this->distancia_envio = $ticket['distancia_km'];
            $this->courier_envio = $ticket['courier_nombre'];
            $this->cotizacion_id = $ticket['id'];
            return floatval($ticket['precio']);
        }

        //2. Externo fuera de checkout - unica excepcion, se respeta $envio.
        if ($esExterno && !$confirmarExterno) {
            $ultimaCotizacion = getUltimaCotizacionReciente($cod_sucursal, $entregaLat, $entregaLng, 15);
            if ($ultimaCotizacion && floatval($ultimaCotizacion['precio']) > 0 && $envio > 0) {
                $diffPct = abs($envio - floatval($ultimaCotizacion['precio'])) / floatval($ultimaCotizacion['precio']);
                if ($diffPct > 0.15) {
                    logCotizacionEnvio('ALERTA_PRECIO_EXTERNO', $cod_sucursal, $entregaLat, $entregaLng, null, 'LINEA_RECTA', COURIERS_EXTERNOS[$cod_courier], $tarifa_id, $envio);
                }
            }
            return $envio;
        }

        //3. Interno siempre, o externo confirmando en checkout - $envio del
        //cliente ya no se usa en ningun caso de aqui en adelante.
        $sucursalPrecio = null;
        $precioEnvio = null;

        //3a. Externo confirmando: precio real pagado primero.
        if ($esExterno) {
            try {
                $sucursalPrecio = $Clsucursales->getConPrecio($cod_sucursal, $entregaLat, $entregaLng);
                if ($sucursalPrecio) {
                    $cacheKey = "precio_ext_{$cod_sucursal}_{$cod_courier}_" . round($entregaLat, CACHE_PRECISION_DECIMALES) . "_" . round($entregaLng, CACHE_PRECISION_DECIMALES);
                    $precioEnvio = getCache($cacheKey);
                    if ($precioEnvio === null) {
                        $precioEnvio = getPrecioCourier($cod_courier, $sucursalPrecio, $entregaLat, $entregaLng, $tarifa_id, false);
                        setCache($cacheKey, $precioEnvio, 180); //3 min - evita duplicar consultas pagadas si /carrito se llama varias veces seguidas
                    }
                }
            } catch (\Throwable $e) {
                $precioEnvio = null; //courier externo inalcanzable - cae al camino interno de 3b, NUNCA a $envio
            }
        }

        //3b. Interno, o el externo de 3a fallo: calculo interno de siempre.
        if (!$precioEnvio) {
            try {
                $sucursalPrecio = $sucursalPrecio ?? $Clsucursales->getConPrecio($cod_sucursal, $entregaLat, $entregaLng);
                if ($sucursalPrecio) {
                    $precioEnvio = getPrecioCourier(0, $sucursalPrecio, $entregaLat, $entregaLng, $tarifa_id, true);
                }
            } catch (\Throwable $e) {
                $precioEnvio = null;
            }
        }

        //3c. Ni siquiera se pudo resolver la cobertura del punto - ultimo
        //recurso: linea recta por matematica pura, nunca $envio del cliente.
        if (!$precioEnvio) {
            $officeBasico = $Clsucursales->get($cod_sucursal);
            if ($officeBasico) {
                $distanciaPura = calcularDistanciaLineaRecta($officeBasico['latitud'], $officeBasico['longitud'], $entregaLat, $entregaLng);
                $precioPuro = $tarifa_id > 0 ? floatval(getPriceWithTariff($tarifa_id, $distanciaPura)) : 0;
                if ($precioPuro <= 0) $precioPuro = floatval(calculatePriceByDistance($officeBasico, $distanciaPura));
                $precioEnvio = ['courierName' => 'LINEA_RECTA', 'precio' => $precioPuro, 'distancia' => $distanciaPura];
            }
        }

        if ($precioEnvio) {
            $envioReal = floatval($precioEnvio['precio']);
            $this->distancia_envio = $precioEnvio['distancia'];
            $this->courier_envio = $precioEnvio['courierName'];
            $this->cotizacion_id = crearCotizacionPrecio($cod_sucursal, $precioEnvio['courierName'], $tarifa_id, $entregaLat, $entregaLng, $precioEnvio['distancia'], $envioReal);
            return $envioReal;
        }

        //Ni siquiera esto funciono (sucursal inexistente, no deberia pasar -
        //ya se valido antes) - $envio queda en lo que ya traiga, lo cual ya
        //dispara el chequeo de "envio invalido" que existe en Ordenes.php.
        return $envio;
    }

    public function getArray()
    {
        $car['productos'] = $this->productos;
        $car['productos_free'] = $this->productos_free;
        $car['progreso_promo_free'] = $this->progreso_promo_free;
        $car['base0'] = $this->base0;
        $car['base12'] = $this->base12;
        $car['subtotal'] = $this->subtotal;
        $car['subtotal_without_envio'] = $this->subtotal_without_envio;
        $car['subtotal_only_products'] = $this->subtotal_only_products;
        $car['envio'] = $this->envio;
        $car['cotizacion_id'] = $this->cotizacion_id;
        $car['envio_error'] = $this->envioError;
        $car['distancia_envio'] = $this->distancia_envio;
        $car['courier_envio'] = $this->courier_envio;
        $car['desc_envio'] = $this->desc_envio;
        $car['promo_envio'] = $this->promo_envio;
        $car['desxitem'] = $this->desxitem;
        $car['descuento'] = $this->descuento;
        $car['descuento_no_tax'] = $this->descuento_no_tax;
        $car['iva'] = $this->iva;
        $car['service'] = $this->service;
        $car['total'] = $this->total;
        $car['num_items'] = $this->num_items;
        $car['peso'] = $this->peso;
        $car['tarifa_id'] = $this->tarifa_id;
        $car['tiempo_preparacion'] = $this->tiempo_preparacion;
        $car['promociones'] = $this->promocionesResp;
        $car['promocionesPruebas'] = $this->promociones;
        $car['quitarCupon'] = $this->quitarCupon;
        $car['motivoCupon'] = $this->motivoCupon;
        $car['observacion'] = $this->observacion;
        
        $car['percentIva'] = $this->percentIva;
        $car['DivitIva'] = $this->DivitIva;
        
        $car['OFFICE_TAXABLE'] = $this->officeTaxable;
        $car['LOGS'] = $this->logs;
        return $car;
    }


    //GET PRECIO ADICIONAL
    public function getPrecioAdicional($opciones, $cantidad, &$adicional_unidad)
    {
        $precio_adicional = 0;
        foreach ($opciones as $opcion) {
            $detalles = $opcion['detalles'];
            foreach ($detalles as $detalle) {
                if (isset($detalle['precio_adicional']) && ($detalle['precio_adicional'] > 0)) {
                    $adicional_item = $detalle['precio_adicional'] * $detalle['cantidad'];
                    $precio_adicional += $adicional_item;
                }
            }
        }

        if ($precio_adicional > 0) {
            $precio_adicional = floatval($precio_adicional);
            $adicional_unidad = $precio_adicional;
            $precio_adicional = $precio_adicional * $cantidad;
        }
        return $precio_adicional;
    }
    
    //Get PRECIO ADICIONAL Con info
    public function getOpcionesYPrecioAdicional($id, $cod_sucursal, $optionsSelected, $cantidad, &$adicional_unidad)
    {
        $precio_adicional = 0;
        $Clproductos = new cl_productos();
        $Clproductos->setSucursal($cod_sucursal);
        $opciones_real = $Clproductos->opciones($id);
        
        /*Opciones*/
        foreach ($optionsSelected as $key => $option) {
            $opcionProducto = $this->findOption($opciones_real, $option);
            if($opcionProducto){
                $optionsSelected[$key]['nombre'] = $opcionProducto['titulo'];
                
                /*Detalles*/
                $DetailsSelected = $option['detalles'];
                foreach ($DetailsSelected as $key2 => $detail) {
                    $detalleProducto = $this->findDetail($opcionProducto['items'], $detail);
                    if($detalleProducto){
                        $DetailsSelected[$key2]['nombre'] = $detalleProducto['item'];
                        $DetailsSelected[$key2]['precio'] = $detalleProducto['precio'];
                        $DetailsSelected[$key2]['precio_real'] = isset($detalleProducto['precio_real']) ? $detalleProducto['precio_real'] : $detalleProducto['precio'];
                        $DetailsSelected[$key2]['disponible'] = $detalleProducto['disponible'];
                        $DetailsSelected[$key2]['aumentar_precio'] = $detalleProducto['aumentar_precio'];
                        $DetailsSelected[$key2]['precio_adicional'] = 0;
                        if($detalleProducto['aumentar_precio'] == "1" && $DetailsSelected[$key2]['disponible']){
                            $precio_adicional_item = ($detalleProducto['precio'] * $detail['cantidad']);
                            $precio_adicional += $precio_adicional_item * $cantidad;
                            $DetailsSelected[$key2]['precio_adicional'] = $precio_adicional_item;
                        }
                    }
                }
                $optionsSelected[$key]['detalles'] = $DetailsSelected;
            }else{
                //return false;
            }
        }
        $adicional_unidad = $precio_adicional;
        return $optionsSelected;
    }
    
    function findOption($optionsProduct, $optionSelected){
        if(isset($optionSelected['id'])){
            foreach($optionsProduct as $option){
                if($option['cod_producto_opcion'] == $optionSelected['id']){
                    return $option;
                }
            }
        }
        return false;
    }
    
    function findDetail($detailsOptionProduct, $detailSelected){
        foreach($detailsOptionProduct as $detalle){
            // echo json_encode($detailsOptionProduct).'<br/><br/><br/>';
            if($detalle['cod_producto_opciones_detalle'] == $detailSelected['id']){
                return $detalle;
            }
        }
        return false;
    }
    

    //FUNCIONES CUPONES
    public function verifyCuponDiscount($codigo, $totalOrden, $descuento, &$mensaje = "")
    {
        if ($descuento > 0) {
            $mensaje = "No se puede aplicar descuento sobre descuento";
            return false;
        }

        $cupon = $this->get_cupon_descuento($codigo);
        if ($cupon) {
            if ($totalOrden <= $cupon['restriccion']) {
                $mensaje = html_entity_decode("El cup&oacute;n s&oacute;lo se puede aplicar en compras mayores a $" . $cupon['restriccion']);
                return false;
            } else {
                //Calcular descuento por item
                if ($cupon['por_o_din'] == 0) {        //PORCENTAJE
                    $this->descuentoAux = $totalOrden * ($cupon['monto'] / 100);
                } else {                              //DINERO
                    if ($cupon['monto'] > $totalOrden)
                        $this->descuentoAux = $totalOrden;
                    else
                        $this->descuentoAux = $cupon['monto'];
                }
                $desxitem = $this->noRound($this->descuentoAux / $totalOrden, false);
                return $desxitem;
            }
        } else {
            $mensaje = "El cup&oacute;n ya no existe o caduc&oacute;";
            return false;
        }
    }


    public function get_cupon_descuento($codigo)
    {
        $fecha = fecha();
        $query = "SELECT * FROM tb_codigo_promocional WHERE codigo='$codigo' AND usos_restantes>=1 AND fecha_expiracion>='$fecha' AND estado='A' AND cod_empresa = " . cod_empresa;
        $row = Conexion::buscarRegistro($query);
        return $row;
    }

    //FUNCIONES ENVIO DESCUENTO
    private function getDescuentoEnvio(&$envio, $totalOrden, $cod_sucursal, &$retorno)
    {
        $envio_orig = $envio;
        $fecha = fecha();
        $query = "SELECT * FROM tb_marketing_envios
                    WHERE fecha_inicio <= '$fecha'
                    AND fecha_fin >= '$fecha'
                    AND estado = 'A'
                    AND cod_sucursal = " . $cod_sucursal;
        $descuento = Conexion::buscarRegistro($query);
        if ($descuento) {
            $aplicaPromo = true;

            if($descuento["solo_horario"] == 1) {
                if($descuento["dias"] <> "") {
                    $dias = explode(",", $descuento["dias"]);
                    if(!in_array(date_create($fecha)->format("N"), $dias))
                        return null;
                }
                else {
                    return null;
                }

                $horaInicio = strtotime(date_create($descuento["fecha_inicio"])->format("H:i:s"));
                $horaFin = strtotime(date_create($descuento["fecha_fin"])->format("H:i:s"));
                $horaActual = strtotime(date_create($fecha)->format("H:i:s"));

                if($horaActual >= $horaInicio && $horaActual <= $horaFin)
                    $aplicaPromo = true;
                else 
                    $aplicaPromo = false;
            }

            if($aplicaPromo) {
                
                if ($totalOrden >= floatval($descuento['monto'])) {
                    
                    $descXEnvio = $this->noRound($envio * ($descuento['porcentaje'] / 100), false);
                    $envio = $envio - $descXEnvio;
                    $envio = $this->noRound($envio, false);

                    $descEnvio['precio_anterior'] = $envio_orig;
                    $descEnvio['descuento'] = $descXEnvio;
                    $descEnvio['texto'] = ($descuento['porcentaje'] == 100) 
                                ? "Tu envío será completamente gratis, te ahorraste $".$descXEnvio." dolares" 
                                : "Tu envío tendrá el " . $descuento['porcentaje'] . "% de descuento";
                    
                    $descuento['aplica'] = true;
                    $descuento['texto'] = $descEnvio['texto'];
                    $retorno = $descuento;
                    return $descEnvio;
                }else{
                    $descuento['aplica'] = false;
                    $descuento['texto'] = ($descuento['porcentaje'] == 100) 
                                ? "tu envío sea gratis" 
                                : "obtengas el" . $descuento['porcentaje'] . "% sobre el envío";
                    $descuento['valor_para_llegar'] = number_format(floatval($descuento['monto']) - $totalOrden,2);
                    $retorno = $descuento;
                }
            }
        }else
            $retorno = null;
        
        return null;
    }


    //FUNCIONES PROMOCIONES
    private function PromocionNxM($id, &$cantItem, $precio, $promocion, $nombre, $imagen, &$descuentoItem, &$porcentajeItem, &$numPromocionesAplicadas = 0)
    {
        //INSERTAR LA NUEVA PROMO EN EL ARRAY
        $this->actualizarPromo(array($id, $cantItem, $promocion['cantidad'], $promocion['valor'], $precio, $nombre, $imagen, $promocion['texto']));

        $posicion = 0;
        if ($this->getPromocion($id, $posicion)) { //BUSCAR LA CANTIDAD DE PROMOCIONES AGREGADAS POR UN PRODUCTO
            $cantidad = $this->promociones[$posicion]['cantidad'];
            $promoCantidad = $this->promociones[$posicion]['promoCantidad'];
            $precioPromo = $this->promociones[$posicion]['precio'];



            $numDescuentosAplicables = $cantidad / $promoCantidad;
            $numCantidadSobrante = ($cantidad % $promoCantidad);

            if (($numCantidadSobrante + 1) == $promoCantidad) {
                $numDescuentosAplicables = $numDescuentosAplicables + 1;
                $numCantidadSobrante = 0;
                $cantItem = $cantItem + 1; //SUMAR 1 AL PRODUCTO QUE ESTA ITERANDO
            }

            if (intval($numDescuentosAplicables) > 0){
                $descuentoItem = (intval($numDescuentosAplicables) * floatval($precioPromo));
                $divisor = $cantItem * $precio;
                $porcentajeItem = $divisor != 0 ? $this->noRound(($descuentoItem * 100) / $divisor, false, 2) : 0;
                $numPromocionesAplicadas = intval($numDescuentosAplicables);

            }else
                $descuentoItem = 0;

            $this->promociones[$posicion]['cantidad'] = intval($numCantidadSobrante);
        }
    }

    private function actualizarPromo($array)
    {
        $id = $array[0];
        $cantidad = $array[1];
        $posicion = 0;
        if ($this->getPromocion($id, $posicion)) {
            $this->promociones[$posicion]['cantidad'] = intval($this->promociones[$posicion]['cantidad']) + $cantidad;
        } else {
            $this->promociones[] = $this->addPromocion($array);
        }
    }

    private function addPromocion($promociones)
    {
        $promocion['id'] = $promociones[0];
        $promocion['cantidad'] = $promociones[1];
        $promocion['promoCantidad'] = $promociones[2];
        $promocion['promoValor'] = $promociones[3];
        $promocion['precio'] = $promociones[4];
        $promocion['nombre'] = $promociones[5];
        $promocion['imagen'] = $promociones[6];
        $promocion['texto'] = $promociones[7];
        return $promocion;
    }

    private function getPromocion($id, &$posicion)
    {
        foreach ((array) $this->promociones as $key => $promociones) {
            if ($promociones['id'] == $id) {
                $posicion = $key;
                return true;
            }
        }
        return false;
    }

    private function noRound($value){
        $number = explode(".", $value);
        $decimal = isset($number[1]) ? $number[1] : 0;
    
        if(strlen($decimal) == 3 && substr($decimal, 2, 1) == 5) //Si tiene 3 decimales y el ultimo es 5
            if((substr($decimal, 1, 1) % 2) == 0) // si el decimal anterior al final es par debe truncar
                return $this->truncate($value, 2); 
        
        return number_format($value,2, '.', '');
    }
    
    private function truncate($number, $digits){
        $truncate = 10**$digits;
        return intval($number * $truncate) / $truncate;
    }
    
    //TARIFA
    public function getTarifaEnvio($productos, $cod_sucursal, &$pesoTotal){
        if(empty($productos)) return false;

        $pesoTotal = array_sum(array_map(
            fn($p) => ($p['peso'] ?? 0) * $p['cantidad'], 
            $productos
        ));

        //Si solo es una tarifa retornamos esa
        $query = "SELECT cod_tarifa FROM tb_tarifa WHERE cod_sucursal = ? LIMIT 2";
        $tarifas = Conexion::buscarVariosRegistro($query, [$cod_sucursal]);
        if(!$tarifas) return false;
        $this->logs[] = [ 'tarifas' => $tarifas ];
        if(count($tarifas) === 1){
            return $tarifas[0]['cod_tarifa'];
        }
        
        //Primero detectar productos con tarifa forzada
        $ids = array_column($productos, 'cod_producto');
        $allIds = implode(",",$ids);
        $query = "
            SELECT t.cod_tarifa
            FROM tb_producto_tarifa_forzada ptf
            INNER JOIN tb_tarifa t 
                ON t.cod_tarifa = ptf.cod_tarifa
            WHERE ptf.cod_producto IN ($allIds)
            AND t.cod_sucursal = $cod_sucursal
            ORDER BY 
                t.peso_max_kg IS NULL DESC,  -- prioriza NULL (cuando solo hay una tarifa)
                t.peso_max_kg DESC
            LIMIT 1
        ";
        $tarifaForzada = Conexion::buscarRegistro($query);
        if($tarifaForzada){
            $this->logs[] = [ 'tarifaForzada' => $tarifaForzada ];
            return $tarifaForzada['cod_tarifa'];
        }

        $query = "SELECT cod_tarifa
            FROM tb_tarifa
            WHERE cod_sucursal = ?
            AND (peso_max_kg IS NULL OR peso_max_kg >= ?)
            ORDER BY peso_max_kg ASC
            LIMIT 1";
        $tarifa = Conexion::buscarRegistro($query, [$cod_sucursal, $pesoTotal]);
        return $tarifa ? $tarifa['cod_tarifa'] : null;
    }
}
