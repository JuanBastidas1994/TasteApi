<?php
/*  Variables heredadas del Index
        $method  - POST, GET, PUT, DELETE
        $request - segmentos de URL
        $input   - body JSON (solo POST/PUT) */

require_once "clases/cl_carrito_sesion.php";

$ClcarritoSesion = new cl_carrito_sesion();

// index.php solo parsea body para POST; leerlo aquí para PUT
if ($method === 'PUT' && empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

// ---------------------------------------------------------------------------
// Routing
// ---------------------------------------------------------------------------

if ($method === 'GET') {
    // GET /cart/recover/{cart_token}
    if (count($request) === 3 && $request[1] === 'recover') {
        $return = recuperarCarrito($request[2]);
        showResponse($return);
    }

    $return['success'] = 0;
    $return['mensaje'] = "Endpoint GET no encontrado";
    showResponse($return);
}

if ($method === 'POST') {
    // POST /cart  →  upsert al abrir checkout
    if (count($request) === 1) {
        $return = upsertCarrito();
        showResponse($return);
    }

    $return['success'] = 0;
    $return['mensaje'] = "Endpoint POST no encontrado";
    showResponse($return);
}

if ($method === 'PUT') {
    $num = count($request);

    // PUT /cart/{cart_token}              →  actualizar actividad
    if ($num === 2) {
        $return = actualizarActividad($request[1]);
        showResponse($return);
    }

    // PUT /cart/{cart_token}/convertido   →  marcar convertido al crear orden
    if ($num === 3 && $request[2] === 'convertido') {
        $return = marcarConvertido($request[1]);
        showResponse($return);
    }

    // PUT /cart/{cart_token}/recovery     →  registrar canal de recuperación
    if ($num === 3 && $request[2] === 'recovery') {
        $return = registrarRecovery($request[1]);
        showResponse($return);
    }

    $return['success'] = 0;
    $return['mensaje'] = "Endpoint PUT no encontrado";
    showResponse($return);
}

$return['success'] = 0;
$return['mensaje'] = "El método $method para Cart no está disponible";
showResponse($return);

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

/**
 * POST /cart
 *
 * Body obligatorio: cart_token, json
 * Body opcional:    cod_usuario, email, telefono, origen
 *
 * Si el token ya existe y está ACTIVO → actualiza json.
 * Si no existe → crea registro nuevo.
 */
function upsertCarrito()
{
    global $ClcarritoSesion, $input;

    validateInputs(['cart_token', 'json']);

    $cart_token = trim($input['cart_token']);

    if (empty($cart_token)) {
        return ['success' => 0, 'mensaje' => 'cart_token no puede estar vacío', 'errorCode' => 'CART_TOKEN_INVALIDO'];
    }

    $existente = $ClcarritoSesion->getByToken($cart_token);

    if ($existente && $existente['estado'] === 'ACTIVO') {
        // Carrito activo ya existe → actualizar
        $extra = [];
        if (!empty($input['cod_sucursal'])) $extra['cod_sucursal'] = $input['cod_sucursal'];
        if (!empty($input['cod_usuario'])) $extra['cod_usuario'] = $input['cod_usuario'];
        if (array_key_exists('email', $input))    $extra['email']    = $input['email'];
        if (array_key_exists('telefono', $input)) $extra['telefono'] = $input['telefono'];

        $ok = $ClcarritoSesion->actualizar($cart_token, $input['json'], $extra);

        if (!$ok) {
            return ['success' => 0, 'mensaje' => 'Error al actualizar carrito', 'errorCode' => 'ERROR_ACTUALIZAR'];
        }

        return [
            'success'    => 1,
            'mensaje'    => 'Carrito actualizado',
            'cart_token' => $cart_token,
            'accion'     => 'ACTUALIZADO',
        ];
    }

    // No existe o está en estado distinto de ACTIVO → crear nuevo
    $id = $ClcarritoSesion->crear($input);

    if (!$id) {
        return ['success' => 0, 'mensaje' => 'Error al crear carrito', 'errorCode' => 'ERROR_CREAR'];
    }

    return [
        'success'    => 1,
        'mensaje'    => 'Carrito creado',
        'cart_token' => $cart_token,
        'accion'     => 'CREADO',
    ];
}

/**
 * PUT /cart/{cart_token}
 *
 * Body obligatorio: json
 * Body opcional:    cod_usuario, email, telefono
 *
 * Actualiza json y updated_at mientras el carrito permanece ACTIVO.
 */
function actualizarActividad($cart_token)
{
    global $ClcarritoSesion, $input;

    validateInputs(['json']);

    $cart_token = trim($cart_token);
    $carrito    = $ClcarritoSesion->getByToken($cart_token);

    if (!$carrito) {
        return ['success' => 0, 'mensaje' => 'Carrito no encontrado', 'errorCode' => 'CART_NOT_FOUND'];
    }

    if ($carrito['estado'] !== 'ACTIVO') {
        return [
            'success'   => 0,
            'mensaje'   => 'El carrito no está activo',
            'estado'    => $carrito['estado'],
            'errorCode' => 'CART_NOT_ACTIVE',
        ];
    }

    $extra = [];
    if (!empty($input['cod_sucursal'])) $extra['cod_sucursal'] = $input['cod_sucursal'];
    if (!empty($input['cod_usuario'])) $extra['cod_usuario'] = $input['cod_usuario'];
    if (array_key_exists('email', $input))    $extra['email']    = $input['email'];
    if (array_key_exists('telefono', $input)) $extra['telefono'] = $input['telefono'];

    $ok = $ClcarritoSesion->actualizar($cart_token, $input['json'], $extra);

    if (!$ok) {
        return ['success' => 0, 'mensaje' => 'Error al actualizar actividad', 'errorCode' => 'ERROR_ACTUALIZAR'];
    }

    return ['success' => 1, 'mensaje' => 'Actividad registrada', 'cart_token' => $cart_token];
}

/**
 * GET /cart/recover/{cart_token}
 *
 * Devuelve el json del carrito si existe y no está EXPIRADO ni CONVERTIDO.
 * Si viene con query param ?source=EMAIL|PUSH|WHATSAPP|SMS registra el canal.
 */
function recuperarCarrito($cart_token)
{
    global $ClcarritoSesion;

    $cart_token = trim($cart_token);
    $carrito    = $ClcarritoSesion->getParaRecovery($cart_token);

    if (!$carrito) {
        return ['success' => 0, 'mensaje' => 'Carrito no encontrado o expirado', 'errorCode' => 'CART_NOT_RECOVERABLE'];
    }

    // Registrar canal de recuperación si viene en query string
    $source = isset($_GET['source']) ? strtoupper(trim($_GET['source'])) : null;
    $sourcesValidos = ['EMAIL', 'PUSH', 'WHATSAPP', 'SMS'];
    if ($source && in_array($source, $sourcesValidos)) {
        $ClcarritoSesion->registrarRecovery($cart_token, $source);
    }

    $jsonData = $carrito['cart_json'] ? json_decode($carrito['cart_json'], true) : null;

    return [
        'success'    => 1,
        'mensaje'    => 'Carrito recuperado',
        'cart_token' => $cart_token,
        'estado'     => $carrito['estado'],
        'data'       => $jsonData,
    ];
}

/**
 * PUT /cart/{cart_token}/convertido
 *
 * Body opcional: cod_preorden, cod_orden
 *
 * Se llama cuando se crea una orden exitosa para cerrar el ciclo.
 */
function marcarConvertido($cart_token)
{
    global $ClcarritoSesion, $input;

    $cart_token  = trim($cart_token);
    $cod_preorden = $input['cod_preorden'] ?? null;
    $cod_orden    = $input['cod_orden']    ?? null;

    $carrito = $ClcarritoSesion->getByToken($cart_token);

    if (!$carrito) {
        return ['success' => 0, 'mensaje' => 'Carrito no encontrado', 'errorCode' => 'CART_NOT_FOUND'];
    }

    $ok = $ClcarritoSesion->marcarConvertido($cart_token, $cod_preorden, $cod_orden);

    if (!$ok) {
        return ['success' => 0, 'mensaje' => 'Error al marcar como convertido', 'errorCode' => 'ERROR_CONVERTIDO'];
    }

    return ['success' => 1, 'mensaje' => 'Carrito marcado como convertido', 'cart_token' => $cart_token];
}

/**
 * PUT /cart/{cart_token}/recovery
 *
 * Body obligatorio: recovery_source  (EMAIL|PUSH|WHATSAPP|SMS)
 *
 * Registra el canal por el que el usuario llegó desde una campaña.
 * No cambia el estado del carrito.
 */
function registrarRecovery($cart_token)
{
    global $ClcarritoSesion, $input;

    validateInputs(['recovery_source']);

    $cart_token     = trim($cart_token);
    $recovery_source = strtoupper(trim($input['recovery_source']));

    $sourcesValidos = ['EMAIL', 'PUSH', 'WHATSAPP', 'SMS'];
    if (!in_array($recovery_source, $sourcesValidos)) {
        return ['success' => 0, 'mensaje' => 'recovery_source inválido. Valores: EMAIL, PUSH, WHATSAPP, SMS', 'errorCode' => 'SOURCE_INVALIDO'];
    }

    $carrito = $ClcarritoSesion->getByToken($cart_token);
    if (!$carrito) {
        return ['success' => 0, 'mensaje' => 'Carrito no encontrado', 'errorCode' => 'CART_NOT_FOUND'];
    }

    $ok = $ClcarritoSesion->registrarRecovery($cart_token, $recovery_source);

    if (!$ok) {
        return ['success' => 0, 'mensaje' => 'Error al registrar recovery', 'errorCode' => 'ERROR_RECOVERY'];
    }

    return ['success' => 1, 'mensaje' => 'Recovery registrado', 'cart_token' => $cart_token, 'source' => $recovery_source];
}
