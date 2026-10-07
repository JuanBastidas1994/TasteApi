<?php

/**
 * Cliente de la API de Deuna (https://apis-merchant.{qa|pdn}.deunalab.com/merchant/v1).
 * Credenciales por sucursal en tb_empresa_sucursal_deuna. Cada cobro queda en tb_preorden_deuna.
 *
 * ambiente = 'mock' no llama a Deuna: responde con el mismo formato que la documentación, para probar el flujo sin
 * credenciales. En mock el pago "se aprueba" cuando la fila del cobro tiene transfer_number (ver controllers/Deuna.php, mock-pay).
 */
class cl_deuna
{
    const URL_QA = 'https://apis-merchant.qa.deunalab.com/merchant/v1';
    const URL_PRODUCCION = 'https://apis-merchant.pdn.deunalab.com/merchant/v1';

    public $isInitialized = false;
    public $isMock = false;
    public $cod_sucursal = 0;
    public $pointOfSale = "";
    private $apiKey = "";
    private $apiSecret = "";
    private $url = "";

    public function __construct($cod_sucursal)
    {
        $this->cod_sucursal = (int)$cod_sucursal;
        $tokens = Conexion::buscarRegistro(
            "SELECT * FROM tb_empresa_sucursal_deuna WHERE estado = 'A' AND cod_sucursal = :cod_sucursal",
            [':cod_sucursal' => $this->cod_sucursal]
        );
        if(!$tokens) return;

        $this->apiKey = $tokens['api_key'];
        $this->apiSecret = $tokens['api_secret'];
        $this->pointOfSale = $tokens['point_of_sale'];
        $this->isMock = ($tokens['ambiente'] == 'mock');
        $this->url = ($tokens['ambiente'] == 'production') ? self::URL_PRODUCCION : self::URL_QA;
        $this->isInitialized = true;
    }

    /* ---------- Cobros guardados (tb_preorden_deuna) ---------- */

    public static function getCobro($transactionId){
        return Conexion::buscarRegistro("SELECT * FROM tb_preorden_deuna WHERE transaction_id = :id", [':id' => $transactionId]);
    }

    /** Último cobro de la preorden (el más reciente que no venció, o el más reciente a secas). */
    public static function getUltimoCobro($cod_preorden){
        return Conexion::buscarRegistro(
            "SELECT * FROM tb_preorden_deuna WHERE cod_preorden = :cod ORDER BY id DESC LIMIT 1",
            [':cod' => $cod_preorden]
        );
    }

    public static function getCobroAprobado($cod_preorden){
        return Conexion::buscarRegistro(
            "SELECT * FROM tb_preorden_deuna WHERE cod_preorden = :cod AND estado = 'APROBADO' ORDER BY id DESC LIMIT 1",
            [':cod' => $cod_preorden]
        );
    }

    public static function contarCobros($cod_preorden){
        return (int)Conexion::buscarRegistro("SELECT COUNT(*) AS n FROM tb_preorden_deuna WHERE cod_preorden = :cod", [':cod' => $cod_preorden])['n'];
    }

    public static function marcarAprobado($transactionId, $transferNumber){
        return Conexion::ejecutar(
            "UPDATE tb_preorden_deuna SET estado = 'APROBADO', transfer_number = :transfer WHERE transaction_id = :id",
            [':transfer' => $transferNumber, ':id' => $transactionId]
        );
    }

    public static function marcarVencido($transactionId){
        return Conexion::ejecutar("UPDATE tb_preorden_deuna SET estado = 'VENCIDO' WHERE estado = 'PENDIENTE' AND transaction_id = :id", [':id' => $transactionId]);
    }

    /* ---------- API de Deuna ---------- */

    /**
     * Pide un cobro dinámico. $format: 0 link, 1 QR, 2 QR+link, 3 código, 4 QR+código, 5 todo.
     * @return array ['success' => bool, 'data' => respuesta de Deuna | 'error' => texto]
     */
    public function createPayment($amount, $reference, $detail, $format = 2, $expiredTime = 15, $callbackUrl = null){
        $body = [
            'pointOfSale' => $this->pointOfSale,
            'qrType' => 'dynamic',
            'amount' => round((float)$amount, 2),
            'detail' => mb_substr($detail, 0, 50),
            'internalTransactionReference' => $reference,
            'format' => (string)$format,
        ];
        // El código único (format 3) dura 3 min fijos; qrFormat solo aplica si hay QR
        if((string)$format !== '3') $body['expiredTime'] = (int)$expiredTime;
        if(in_array((string)$format, ['1', '2', '4', '5'], true)) $body['qrFormat'] = 'svgQr300x300_color';
        if($callbackUrl) $body['callbackUrl'] = $callbackUrl;

        if($this->isMock){
            return ['success' => true, 'data' => $this->mockCreate($format)];
        }

        $resp = $this->request('payment/request', $body);
        if(!$resp['success']) return $resp;
        if(!isset($resp['data']['transactionId']) || ($resp['data']['status'] ?? '') != '1'){
            return ['success' => false, 'error' => 'Deuna no devolvió el cobro', 'data' => $resp['data']];
        }
        return $resp;
    }

    /**
     * Estado de un pago. $idType: 0 transactionId, 1 internalTransactionReference, 2 transferNumber.
     * data.status: APPROVED | PENDING | REVERSED | REVERSED_FAILED | NOT_FOUND
     */
    public function getStatus($idTransaction, $idType = 0){
        if($this->isMock){
            $cobro = self::getCobro($idTransaction);
            return ['success' => true, 'data' => $this->mockInfo($cobro)];
        }
        return $this->request('payment/info', ['idTransacionReference' => (string)$idTransaction, 'idType' => (string)$idType]);
    }

    /** Devolución total. $idType: 0 transactionId, 1 transferNumber. Solo el mismo día de la venta. */
    public function refund($idTransaction, $idType = 0){
        if($this->isMock){
            return ['success' => true, 'data' => ['status' => true, 'message' => 'Refund executed successfully (mock)', 'transactionReverseId' => $this->uuid()]];
        }
        return $this->request('payment/refund', ['idTransacionReference' => (string)$idTransaction, 'idType' => (string)$idType]);
    }

    private function request($path, $body){
        $curl = curl_init($this->url.'/'.$path);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: '.$this->apiKey,
                'x-api-secret: '.$this->apiSecret,
            ],
        ]);
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        // Nunca se loguean las credenciales, solo la trama de negocio
        logAdd(json_encode(['path' => $path, 'request' => $body, 'http' => $httpCode, 'response' => $result]), "deuna", "deuna-api");

        if($result === false){
            return ['success' => false, 'error' => 'No se pudo conectar con Deuna: '.$curlError];
        }
        $data = json_decode($result, true);
        if($httpCode < 200 || $httpCode >= 300 || !is_array($data)){
            return ['success' => false, 'error' => $this->errorMessage($httpCode, $data), 'http_code' => $httpCode, 'data' => $data];
        }
        return ['success' => true, 'data' => $data];
    }

    private function errorMessage($httpCode, $data){
        if($httpCode == 401) return 'Credenciales de Deuna inválidas';
        if(is_array($data)){
            if(isset($data['message']['response']['errors'][0]['reason'])) return $data['message']['response']['errors'][0]['reason'];
            if(isset($data['message']['response']['message'])){
                $m = $data['message']['response']['message'];
                return is_array($m) ? implode(', ', $m) : $m;
            }
            if(isset($data['error']['response']['message'])) return $data['error']['response']['message'];
            if(isset($data['message']) && is_string($data['message'])) return $data['message'];
        }
        return "Error de Deuna (HTTP $httpCode)";
    }

    /* ---------- Simulación ---------- */

    private function mockCreate($format){
        $id = $this->uuid();
        $resp = ['transactionId' => $id, 'status' => '1'];
        if(in_array((string)$format, ['0', '2', '5'], true))
            $resp['deeplink'] = "https://pagar.deuna.app/mock/merchant?id=".strtoupper(substr(str_replace('-', '', $id), 0, 12));
        if(in_array((string)$format, ['1', '2', '4', '5'], true)){
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><rect width="300" height="300" fill="#fff"/><rect x="20" y="20" width="260" height="260" fill="none" stroke="#000" stroke-width="6"/><text x="150" y="150" font-size="22" text-anchor="middle" font-family="sans-serif">QR SIMULADO</text><text x="150" y="180" font-size="14" text-anchor="middle" font-family="sans-serif">Deuna (mock)</text></svg>';
            $resp['qr'] = 'data:image/svg+xml;base64,'.base64_encode($svg);
        }
        if(in_array((string)$format, ['3', '4', '5'], true))
            $resp['numericCode'] = (string)random_int(100000, 999999);
        return $resp;
    }

    private function mockInfo($cobro){
        $vacio = ['status' => 'PENDING', 'internalTransactionReference' => '', 'amount' => 0, 'transactionId' => $cobro ? $cobro['transaction_id'] : '',
                  'transferNumber' => '', 'date' => '', 'branchId' => '', 'posId' => '', 'currency' => 'USD', 'description' => '',
                  'ordererName' => '', 'ordererIdentification' => ''];
        if(!$cobro) { $vacio['status'] = 'NOT_FOUND'; return $vacio; }
        if(empty($cobro['transfer_number'])) return $vacio;
        return array_merge($vacio, [
            'status' => 'APPROVED',
            'internalTransactionReference' => $cobro['reference'],
            'amount' => (float)$cobro['monto'],
            'transferNumber' => $cobro['transfer_number'],
            'date' => date('n/j/Y, g:i:s A'),
            'branchId' => 'MOCK', 'posId' => $this->pointOfSale,
            'description' => 'mock', 'ordererName' => 'CLIENTE SIMULADO', 'ordererIdentification' => '0000000000',
        ]);
    }

    private function uuid(){
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
