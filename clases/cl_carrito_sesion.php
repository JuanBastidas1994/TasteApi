<?php

require_once "conexion.php";

class cl_carrito_sesion
{
    /**
     * Busca un carrito por su token dentro de la empresa actual.
     */
    public function getByToken($cart_token)
    {
        $sql = "SELECT * FROM carrito_sesion
                WHERE cart_token = ? AND cod_empresa = ?
                LIMIT 1";
        return Conexion::buscarRegistro($sql, [$cart_token, cod_empresa]);
    }

    /**
     * Recupera un carrito para el endpoint de recovery.
     * Retorna el registro si existe y no está EXPIRADO ni CONVERTIDO.
     */
    public function getParaRecovery($cart_token)
    {
        $sql = "SELECT * FROM carrito_sesion
                WHERE cart_token = ? AND cod_empresa = ?
                AND estado NOT IN ('EXPIRADO', 'CONVERTIDO')
                LIMIT 1";
        return Conexion::buscarRegistro($sql, [$cart_token, cod_empresa]);
    }

    /**
     * Inserta un nuevo registro de carrito_sesion.
     * Retorna el id insertado o false en fallo.
     */
    public function crear($data)
    {
        $sql = "INSERT INTO carrito_sesion
                    (cart_token, cod_empresa, cod_sucursal, cod_usuario, email, telefono, origen, cart_json, estado, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', NOW(), NOW())";

        $params = [
            $data['cart_token'],
            cod_empresa,
            $data['cod_sucursal'] ?? null,
            $data['cod_usuario']  ?? null,
            $data['email']        ?? null,
            $data['telefono']     ?? null,
            $data['origen']       ?? 'WEB',
            isset($data['json']) ? json_encode($data['json']) : null,
        ];

        $ok = Conexion::ejecutar($sql, $params);
        return $ok ? Conexion::lastId() : false;
    }

    /**
     * Actualiza json y updated_at mientras el carrito está ACTIVO.
     * Acepta campos opcionales: cod_sucursal, cod_usuario, email, telefono.
     */
    public function actualizar($cart_token, $json, $extra = [])
    {
        $sets   = ['cart_json = ?', 'updated_at = NOW()'];
        $params = [json_encode($json)];

        if (!empty($extra['cod_sucursal'])) {
            $sets[]   = 'cod_sucursal = ?';
            $params[] = $extra['cod_sucursal'];
        }
        if (!empty($extra['cod_usuario'])) {
            $sets[]   = 'cod_usuario = ?';
            $params[] = $extra['cod_usuario'];
        }
        if (array_key_exists('email', $extra)) {
            $sets[]   = 'email = ?';
            $params[] = $extra['email'];
        }
        if (array_key_exists('telefono', $extra)) {
            $sets[]   = 'telefono = ?';
            $params[] = $extra['telefono'];
        }

        $params[] = $cart_token;
        $params[] = cod_empresa;

        $sql = "UPDATE carrito_sesion
                SET " . implode(', ', $sets) . "
                WHERE cart_token = ? AND cod_empresa = ? AND estado = 'ACTIVO'";

        return Conexion::ejecutar($sql, $params);
    }

    /**
     * Marca el carrito como CONVERTIDO al crearse una orden exitosa.
     */
    public function marcarConvertido($cart_token, $cod_preorden = null, $cod_orden = null)
    {
        $sql = "UPDATE carrito_sesion
                SET estado = 'CONVERTIDO', converted_at = NOW(),
                    cod_preorden = ?, cod_orden = ?, updated_at = NOW()
                WHERE cart_token = ? AND cod_empresa = ?";

        return Conexion::ejecutar($sql, [$cod_preorden, $cod_orden, $cart_token, cod_empresa]);
    }

    /**
     * Registra el canal por el que el usuario entró desde una campaña de recovery.
     * No cambia el estado del carrito.
     */
    public function registrarRecovery($cart_token, $recovery_source)
    {
        $sql = "UPDATE carrito_sesion
                SET recovery_source = ?, recovered_at = NOW(), updated_at = NOW()
                WHERE cart_token = ? AND cod_empresa = ?";

        return Conexion::ejecutar($sql, [$recovery_source, $cart_token, cod_empresa]);
    }

}
