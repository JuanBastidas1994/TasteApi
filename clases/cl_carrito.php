<?php

class cl_carrito
{
    public $cod_usuario;
    public $quitarCupon = false, $motivoCupon = "", $infoCupon = null;
    public $productos = null, $subtotal, $iva, $total, $descuento = 0, $descuento_no_tax, $envio = 0;
    public $base0 = 0, $base12 = 0, $desxitem = 0;
    public $num_items;
    public $tipo_descuento = 0; //0 PORCENTAJE - 1 EFECTIVO
    public $valor_descuento = 0; //VALOR A DESCONTAR
    public $metodoEnvio = null, $metodoPago = null, $infoDescuento = null, $promo_envio=null;
    public $percentIva = 15;
    public $DivitIva = 1.15;
    public $numDecimals = 2;

    public $promociones = [], $promocionesResp = [];
    public $descuentoAux = 0;

    public $desc_envio, $subtotal_only_products, $subtotal_without_envio;
    
    public $sucursal_cobra_iva = 0;

    public $officeTaxable = true;
    public $tesst = 0;
    
    public $logs = [];

    public function __construct($array, $cod_sucursal)
    {
        $this->percentIva = $this->getPercentIva();
        $this->DivitIva = 1 + ($this->percentIva / 100);
        $this->calcular($array, $cod_sucursal);
    }

    public function getPercentIva()
    {
        $query = "SELECT impuesto FROM tb_empresas WHERE cod_empresa = " . cod_empresa;
        $resp = Conexion::buscarRegistro($query);
        if ($resp)
            return $resp['impuesto'];
        else
            return 15;
    }

    public function calcular($array, $cod_sucursal)
    { //getSucursalEnvioGravaIVA
        $Clempresas = new cl_empresas();
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

                    $promocion = $Clproductos->isPromocion($p['id'], $cod_sucursal);
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
                    $totalOrderWithoutDiscount = $totalOrderWithoutDiscount + $producto['total_no_discount'];
                    $totalOrderWithTax += $producto['total'];
                } else {
                    $items[$k]['total'] = 0;
                }
            }
        }
        
        $this->logs[0] = [ 'base0' => $base0 ];
        if($this->officeTaxable)
            $base12 += $this->noRound($adicionalTotal, false, 2);
        else
            $base0 += $this->noRound($adicionalTotal, false, 2);
        
        $this->logs[1] = [ 'base0' => $base0 ];
        
        $promoEnvio = null;
        //Calcular Envio
        $envio = $array['envio'];

        $getIsEnvioGrabaIva = true;
        if ($Clsucursales->getSucursalEnvioGravaIVA($cod_sucursal) == 1) {
            $getIsEnvioGrabaIva = false;
            $envioWithoutTax = $this->noRound($envio / $this->DivitIva, false, 2);
            $base12 = $base12 + $envioWithoutTax;
        } 
        
        if ($envio > 0)
            $descDataEnvio = $this->getDescuentoEnvio($envio, $totalOrderWithTax, $cod_sucursal, $promoEnvio);
        else
            $descDataEnvio = null;
         
        if($this->officeTaxable){
            if($getIsEnvioGrabaIva) {
                if ($Clempresas->getIsEnvioGrabaIva() == 1) {
                    $base12 = $base12 + $envio;
                } else
                    $base0 = $base0 + $envio;
            }
        }
        else
            $base0 = $base0 + $envio;
            
        $this->logs[2] = [ 'base0' => $base0 ];

        
        $subtotal = $base0 + $base12;
        $iva = $this->noRound($base12 * ($this->percentIva / 100), false, 2);
        
        $subtotal_without_envio = $totalOrderWithoutDiscount;
        $total = $base0 + $base12 + $iva; //BASE 12 CON IVA 


        $this->quitarCupon = $quitarCupon;
        $this->motivoCupon = $motivo;
        $this->productos = $items;
        $this->base0 = $this->noRound($base0, false, 2);
        $this->base12 = $this->noRound($base12, false, 2);
        $this->subtotal_only_products = $this->noRound($totalOrderWithoutDiscount, false, 2);
        $this->subtotal_without_envio = $this->noRound($subtotal_without_envio, false, 2);
        $this->subtotal = $this->noRound($subtotal, false, 2);
        $this->envio = $this->noRound($envio, false, 2);
        $this->desc_envio = $descDataEnvio;
        $this->promo_envio = $promoEnvio;
        $this->desxitem = $desxitem;
        $this->descuento = $this->noRound($descuento, false, 2);
        $this->descuento_no_tax = $this->noRound($descuento_no_tax, false, 2);
        $this->iva = $this->noRound($iva, false, 2);
        $this->total = $this->noRound($total, false, 2);
        $this->num_items = $num_items;
    }

    public function getArray()
    {
        $car['productos'] = $this->productos;
        $car['base0'] = $this->base0;
        $car['base12'] = $this->base12;
        $car['subtotal'] = $this->subtotal;
        $car['subtotal_without_envio'] = $this->subtotal_without_envio;
        $car['subtotal_only_products'] = $this->subtotal_only_products;
        $car['envio'] = $this->envio;
        $car['desc_envio'] = $this->desc_envio;
        $car['promo_envio'] = $this->promo_envio;
        $car['desxitem'] = $this->desxitem;
        $car['descuento'] = $this->descuento;
        $car['descuento_no_tax'] = $this->descuento_no_tax;
        $car['iva'] = $this->iva;
        $car['total'] = $this->total;
        $car['num_items'] = $this->num_items;
        $car['promociones'] = $this->promocionesResp;
        $car['promocionesPruebas'] = $this->promociones;
        $car['quitarCupon'] = $this->quitarCupon;
        $car['motivoCupon'] = $this->motivoCupon;
        
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
                $porcentajeItem = $this->noRound(($descuentoItem * 100) / ($cantItem * $precio),false, 2);
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
}
