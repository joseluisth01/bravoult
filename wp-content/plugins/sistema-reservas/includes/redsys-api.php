<?php
class RedsysAPI {
    private $params;
    private $clave;

    public function __construct() {
        $this->params = array();
    }

    public function setParameter($key, $value) {
        $this->params[$key] = $value;
    }

    public function getParameter($key) {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    public function createMerchantParameters() {
        $json = json_encode($this->params);
        $base64 = base64_encode($json);
        return $base64;
    }

    public function createMerchantSignature($key) {
        $order = $this->params["DS_MERCHANT_ORDER"] ?? $this->params["Ds_Merchant_Order"];
        $decodedKey = base64_decode($key, true);
        $keyDerivada = $this->encrypt_3DES($order, $decodedKey);
        $data = $this->createMerchantParameters();
        $signature = base64_encode(hash_hmac('sha256', $data, $keyDerivada, true));
        return $signature;
    }

    private function encrypt_3DES($message, $key) {
        return openssl_encrypt(
            $message,
            'des-ede3-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_NO_PADDING,
            "\0\0\0\0\0\0\0\0"
        );
    }
}
