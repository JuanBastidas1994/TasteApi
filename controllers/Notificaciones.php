<?php

if ($method == "POST") {
    $num_variables = count($request);
    if ($num_variables == 2) {
        $metodo = $request[1];
        if ($metodo == "suscribir") {
            $return = suscribir();
            showResponse($return);
        }
        if ($metodo == "desuscribir") {
            $return = desuscribir();
            showResponse($return);
        }
        if ($metodo == "abierta") {
            $return = registrarApertura();
            showResponse($return);
        }
    }
    showResponse(['success' => 0, 'mensaje' => 'Evento no existente']);
} else {
    showResponse(['success' => 0, 'mensaje' => "El método $method no está disponible."]);
}


function suscribir() {
    $usuario    = validateUserAuthenticated();
    $input      = validateInputs(["token", "plataforma"]);
    extract($input);

    $cod_usuario = $usuario['cod_usuario'];

    $sql = "INSERT INTO tb_push_tokens (cod_usuario, token, plataforma)
            VALUES (:cod_usuario, :token, :plataforma)
            ON DUPLICATE KEY UPDATE cod_usuario = VALUES(cod_usuario), plataforma = VALUES(plataforma)";

    $result = Conexion::ejecutar($sql, [
        ':cod_usuario' => $cod_usuario,
        ':token'       => $token,
        ':plataforma'  => $plataforma,
    ]);

    if (!$result) {
        showResponse(['success' => 0, 'mensaje' => 'Error al registrar token']);
    }

    return ['success' => 1, 'mensaje' => 'Token registrado'];
}


/* La app avisa que el usuario tocó una notificación (data.notificacion_id = tb_notificaciones_expo.id).
   Alimenta "Abiertas" y "Tasa de apertura" en taste/notificaciones.php. Una apertura por usuario. */
function registrarApertura() {
    $usuario = validateUserAuthenticated();
    $input   = validateInputs(["notificacion_id"]);

    $notificacion = Conexion::buscarRegistro(
        "SELECT id FROM tb_notificaciones_expo WHERE id = :id AND cod_empresa = :cod_empresa",
        [':id' => (int)$input['notificacion_id'], ':cod_empresa' => $usuario['cod_empresa']]
    );
    if (!$notificacion) {
        return ['success' => 0, 'mensaje' => 'Notificación no encontrada'];
    }

    Conexion::ejecutar(
        "INSERT IGNORE INTO tb_notificaciones_expo_aperturas (notificacion_id, cod_usuario, fecha) VALUES (:id, :cod_usuario, :fecha)",
        [':id' => $notificacion['id'], ':cod_usuario' => $usuario['cod_usuario'], ':fecha' => fecha()]
    );

    return ['success' => 1, 'mensaje' => 'Apertura registrada'];
}


function desuscribir() {
    $usuario = validateUserAuthenticated();
    $input   = validateInputs(["token"]);
    extract($input);

    $sql = "DELETE FROM tb_push_tokens WHERE cod_usuario = :cod_usuario AND token = :token";

    Conexion::ejecutar($sql, [
        ':cod_usuario' => $usuario['cod_usuario'],
        ':token'       => $token,
    ]);

    return ['success' => 1, 'mensaje' => 'Token eliminado'];
}
