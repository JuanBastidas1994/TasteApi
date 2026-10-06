<?php
/**
 * Correos del API front. Punto de entrada único: se incluye este archivo y se llama a la función que toque.
 * Todas retornan true/false y NUNCA lanzan excepciones (un correo caído no debe romper un pedido ni un login).
 *
 *   enviarCorreoOrdenCompleta($cod_orden)      pedido confirmado al cliente
 *   enviarCorreoCuponera($cod_orden)           códigos de cupones comprados
 *   enviarCodigoLogin($cod_usuario, $codigo)   código para iniciar sesión (login express)
 *   enviarCodigoRegistro($correo, $codigo)     código para registrarse (login express)
 *
 * Requiere el contexto del API: constantes cod_empresa, alias y url (las define index.php).
 * Los diseños están en email_template/views/*.php; el marco común (logo, color, pie) en views/layout.php.
 */
require_once __DIR__ . '/EmailService.php';

function emailEsc($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Renderiza una vista y la envuelve en el marco común
function emailRender($vista, array $vars, array $empresa, array $redes) {
    $contenido = emailVista($vista, $vars + ['empresa' => $empresa]);
    return emailVista('layout', [
        'empresa' => $empresa,
        'redes' => $redes,
        'titulo' => isset($vars['titulo']) ? $vars['titulo'] : $empresa['nombre'],
        'contenido' => $contenido,
    ]);
}

function emailVista($vista, array $vars) {
    extract($vars, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/views/' . $vista . '.php';
    return ob_get_clean();
}

// Datos de marca de la empresa actual (logo, color, web, redes)
function emailMarca() {
    require_once __DIR__ . '/../clases/cl_empresas.php';
    $Clempresas = new cl_empresas(NULL);
    $empresa = $Clempresas->getByAlias(alias);

    $color = isset($empresa['color']) ? $empresa['color'] : '';
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        $color = '#333333';
    }

    $redes = [];
    foreach ((array) $Clempresas->getRedesSociales() as $red) {
        if (isset($red['link']) && preg_match('#^https?://#i', $red['link'])) {
            $redes[] = ['nombre' => ucfirst($red['code']), 'link' => $red['link']];
        }
    }

    $marca = [
        'nombre' => (string) $empresa['nombre'],
        'web' => (string) $empresa['url_web'],
        'logo' => url . $empresa['logo'],
        'color' => $color,
        'correo' => (string) $empresa['correo'],
    ];
    return [$marca, $redes];
}

function emailEjecutar($nombreCorreo, callable $accion) {
    try {
        return (bool) $accion();
    } catch (Throwable $e) {
        EmailService::error("$nombreCorreo: " . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine());
        return false;
    }
}

function enviarCorreoOrdenCompleta($cod_orden) {
    return emailEjecutar('orden_complete', function () use ($cod_orden) {
        require_once __DIR__ . '/../clases/cl_ordenes.php';
        $Clordenes = new cl_ordenes(NULL);
        $orden = $Clordenes->get_orden_array(intval($cod_orden));
        if (!$orden) {
            EmailService::error("orden_complete: la orden $cod_orden no existe");
            return false;
        }
        list($empresa, $redes) = emailMarca();

        // Productos
        $detalle = [];
        foreach ($orden['detalle'] as $item) {
            $opciones = [];
            foreach ($item['opciones'] as $grupo) {
                $nombres = [];
                foreach (isset($grupo['detalles']) ? $grupo['detalles'] : [] as $d) {
                    $cant = isset($d['cantidad']) && $d['cantidad'] > 1 ? $d['cantidad'] . ' x ' : '';
                    $nombres[] = $cant . (isset($d['nombre']) ? $d['nombre'] : '');
                }
                if (count($nombres) > 0) {
                    $opciones[] = (isset($grupo['nombre']) ? $grupo['nombre'] . ': ' : '') . implode(', ', $nombres);
                }
            }
            $detalle[] = [
                'cantidad' => $item['cantidad'],
                'nombre' => $item['nombre'],
                'precio' => number_format($item['precio'], 2),
                // image_min viene como url.<archivo>; si el producto no tiene imagen queda solo la carpeta
                'imagen' => ($item['image_min'] !== url && $item['image_min'] !== '') ? $item['image_min'] : '',
                'opciones' => $opciones,
            ];
        }

        // Totales
        $dinero = [
            ['titulo' => 'SUBTOTAL', 'valor' => number_format($orden['subtotal'], 2), 'fuerte' => false],
            ['titulo' => 'DESCUENTO', 'valor' => number_format($orden['descuento'], 2), 'fuerte' => false],
            ['titulo' => 'ENVIO', 'valor' => number_format($orden['envio'], 2), 'fuerte' => false],
        ];
        if ($orden['service'] > 0) {
            $dinero[] = ['titulo' => 'SERVICIO', 'valor' => number_format($orden['service'], 2), 'fuerte' => false];
        }
        $dinero[] = ['titulo' => 'IVA', 'valor' => number_format($orden['iva'], 2), 'fuerte' => false];
        $dinero[] = ['titulo' => 'TOTAL', 'valor' => number_format($orden['total'], 2), 'fuerte' => true];

        // Pagos
        $pagos = [];
        $transacciones = [];
        foreach ($orden['pagos'] as $pago) {
            $pagos[] = $pago['descripcion'] . ': $' . $pago['monto'];
            if ($pago['forma_pago'] == 'T') {
                $transacciones[] = 'Tu número de transacción es: ' . $pago['observacion'];
                if ($pago['observacion2'] !== '') {
                    $transacciones[] = 'Tu número de autorización es: ' . $pago['observacion2'];
                }
            }
        }

        // Entrega
        $esEnvio = $orden['is_envio'] == 1;
        if ($esEnvio) {
            $direccion = trim($orden['referencia'] . ' ' . $orden['referencia2']);
            $entrega = 'Envío a domicilio' . ($direccion !== '' ? ': ' . $direccion : '');
        } else {
            $entrega = 'Retiro en sucursal ' . $orden['sucursal'] . ($orden['sucursal_direccion'] ? ' - ' . $orden['sucursal_direccion'] : '');
        }

        $retiro = '';
        if ($orden['is_programado'] == 1 && strtotime($orden['hora_retiro'])) {
            $cuando = fechaLatino($orden['hora_retiro']) . ' a las ' . date('H:i', strtotime($orden['hora_retiro']));
            $retiro = $esEnvio ? "Tu pedido está programado para el $cuando" : "Recuerda retirar tu pedido el $cuando";
        }

        $html = emailRender('orden_complete', [
            'titulo' => 'Pedido confirmado',
            'nombre' => trim($orden['nombre'] . ' ' . $orden['apellido']),
            'numOrden' => str_pad($orden['cod_orden'], 6, '0', STR_PAD_LEFT),
            'fecha' => fechaLatino($orden['fecha']),
            'retiro' => $retiro,
            'detalle' => $detalle,
            'dinero' => $dinero,
            'pagos' => $pagos,
            'transacciones' => $transacciones,
            'entrega' => $entrega,
        ], $empresa, $redes);

        $r = EmailService::send($orden['correo'], 'Orden recibida en ' . $empresa['nombre'], $html, $empresa['nombre']);
        return $r['ok'];
    });
}

function enviarCorreoCuponera($cod_orden) {
    return emailEjecutar('orden_cuponera', function () use ($cod_orden) {
        require_once __DIR__ . '/../clases/cl_ordenes.php';
        $Clordenes = new cl_ordenes(NULL);
        $orden = $Clordenes->get_orden_array(intval($cod_orden));
        if (!$orden) {
            EmailService::error("orden_cuponera: la orden $cod_orden no existe");
            return false;
        }
        list($empresa, $redes) = emailMarca();

        $filas = Conexion::buscarVariosRegistro("SELECT codigo FROM tb_orden_cuponera WHERE cod_orden = :cod_orden ORDER BY id", [':cod_orden' => intval($cod_orden)]);
        $cupones = [];
        foreach ((array) $filas as $fila) {
            $cupones[] = $fila['codigo'];
        }
        if (count($cupones) == 0) {
            EmailService::error("orden_cuponera: la orden $cod_orden no tiene cupones");
            return false;
        }

        $html = emailRender('orden_cuponera', [
            'titulo' => 'Tus cupones',
            'nombre' => trim($orden['nombre'] . ' ' . $orden['apellido']),
            'numOrden' => str_pad($orden['cod_orden'], 6, '0', STR_PAD_LEFT),
            'fecha' => fechaLatino($orden['fecha']),
            'cupones' => $cupones,
        ], $empresa, $redes);

        $r = EmailService::send($orden['correo'], 'Cupones comprados en ' . $empresa['nombre'], $html, $empresa['nombre']);
        return $r['ok'];
    });
}

function enviarCodigoLogin($cod_usuario, $codigo) {
    return emailEjecutar('codigo_login', function () use ($cod_usuario, $codigo) {
        require_once __DIR__ . '/../clases/cl_usuarios.php';
        $Clusuarios = new cl_usuarios(NULL);
        $usuario = $Clusuarios->get2(intval($cod_usuario));
        if (!$usuario) {
            EmailService::error("codigo_login: el usuario $cod_usuario no existe o está inactivo");
            return false;
        }
        list($empresa, $redes) = emailMarca();

        $html = emailRender('codigo_acceso', [
            'titulo' => 'Inicio de sesión',
            'saludo' => 'Hola ' . trim($usuario['nombre'] . ' ' . $usuario['apellido']) . '!',
            'instruccion' => 'Utiliza esta contraseña para iniciar sesión.',
            'codigo' => $codigo,
        ], $empresa, $redes);

        $r = EmailService::send($usuario['correo'], $empresa['nombre'] . ' - Inicio de sesión', $html, $empresa['nombre']);
        return $r['ok'];
    });
}

function enviarCodigoRegistro($correo, $codigo) {
    return emailEjecutar('codigo_registro', function () use ($correo, $codigo) {
        list($empresa, $redes) = emailMarca();

        $html = emailRender('codigo_acceso', [
            'titulo' => 'Registro',
            'saludo' => 'Hola!',
            'instruccion' => 'Utiliza esta contraseña para registrarte.',
            'codigo' => $codigo,
        ], $empresa, $redes);

        $r = EmailService::send($correo, $empresa['nombre'] . ' - Registro', $html, $empresa['nombre']);
        return $r['ok'];
    });
}
