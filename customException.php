<?php

class customException extends Exception {
    private $extraData;

    public function __construct($message, $extraData = null, $code = 0, Exception $previous = null) {
        // Asegúrate de pasar los parámetros correctos al constructor de la clase base Exception
        parent::__construct($message, $code, $previous);

        // Almacena el objeto o los datos adicionales
        $this->extraData = $extraData;
    }

    // Método para obtener el objeto o los datos adicionales
    public function getData() {
        return $this->extraData;
    }
}