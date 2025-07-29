<?php
require_once __DIR__ . '/redsys-api.php';

function generar_formulario_redsys($reserva) {
    require_once __DIR__ . '/redsys-api.php';

    $miObj = new RedsysAPI();

    $clave = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; // 🔐 Clave de firma de pruebas
    $codigo_comercio = '014591697'; // 🏪 Tu código FUC
    $terminal = '001'; // Número de terminal
    $pedido = strval(time()); // Pedido único
    $importe = intval(floatval($reserva['total_price']) * 100);

    $miObj->setParameter("DS_MERCHANT_AMOUNT", $importe);
    $miObj->setParameter("DS_MERCHANT_ORDER", $pedido);
    $miObj->setParameter("DS_MERCHANT_MERCHANTCODE", $codigo_comercio);
    $miObj->setParameter("DS_MERCHANT_CURRENCY", "978");
    $miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", "0");
    $miObj->setParameter("DS_MERCHANT_TERMINAL", $terminal);
    $merchant_url = site_url('/api/notificacion-redsys');
$url_ok = site_url('/confirmacion-reserva');
$url_ko = site_url('/reserva-fallida');

$miObj->setParameter("DS_MERCHANT_MERCHANTURL", $merchant_url);
$miObj->setParameter("DS_MERCHANT_URLOK", $url_ok);
$miObj->setParameter("DS_MERCHANT_URLKO", $url_ko);

    $params = $miObj->createMerchantParameters();
    $signature = $miObj->createMerchantSignature($clave);
    $version = "HMAC_SHA256_V1";

    $html = '<form id="formulario_redsys" action="https://sis-t.redsys.es:25443/sis/realizarPago" method="POST">';
    $html .= '<input type="hidden" name="Ds_SignatureVersion" value="' . $version . '">';
    $html .= '<input type="hidden" name="Ds_MerchantParameters" value="' . $params . '">';
    $html .= '<input type="hidden" name="Ds_Signature" value="' . $signature . '">';
    $html .= '</form>';
    $html .= '<script>document.getElementById("formulario_redsys").submit();</script>';

    return $html;
}

