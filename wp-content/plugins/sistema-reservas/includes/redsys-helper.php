<?php
require_once __DIR__ . '/redsys-api.php';

function generar_formulario_redsys($reserva_data) {
    $miObj = new RedsysAPI();

    // ⚠️ IMPORTANTE: Configurar estos valores con tus datos reales
    $clave = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; // Tu clave de firma
    $codigo_comercio = '014591697'; // Tu código FUC
    $terminal = '001'; // Tu terminal
    
    // Generar número de pedido único
    $timestamp = time();
    $pedido = str_pad($timestamp % 999999, 6, '0', STR_PAD_LEFT);
    
    // Convertir importe a céntimos
    $importe = intval(floatval($reserva_data['total_price']) * 100);
    
    // Configurar parámetros del pedido
    $miObj->setParameter("DS_MERCHANT_AMOUNT", $importe);
    $miObj->setParameter("DS_MERCHANT_ORDER", $pedido);
    $miObj->setParameter("DS_MERCHANT_MERCHANTCODE", $codigo_comercio);
    $miObj->setParameter("DS_MERCHANT_CURRENCY", "978"); // EUR
    $miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", "0"); // Autorización
    $miObj->setParameter("DS_MERCHANT_TERMINAL", $terminal);
    
    // URLs de respuesta
    $base_url = home_url();
    $miObj->setParameter("DS_MERCHANT_MERCHANTURL", $base_url . '/wp-admin/admin-ajax.php?action=redsys_notification');
    $miObj->setParameter("DS_MERCHANT_URLOK", $base_url . '/confirmacion-reserva/?status=ok&order=' . $pedido);
    $miObj->setParameter("DS_MERCHANT_URLKO", $base_url . '/reserva-fallida/?status=ko&order=' . $pedido);
    
    // Información adicional
    $descripcion = "Reserva Medina Azahara - " . $reserva_data['fecha'];
    $miObj->setParameter("DS_MERCHANT_PRODUCTDESCRIPTION", $descripcion);
    
    // Datos del titular (opcional)
    if (isset($reserva_data['nombre']) && isset($reserva_data['apellidos'])) {
        $miObj->setParameter("DS_MERCHANT_TITULAR", $reserva_data['nombre'] . ' ' . $reserva_data['apellidos']);
    }

    // Generar parámetros y firma
    $params = $miObj->createMerchantParameters();
    $signature = $miObj->createMerchantSignature($clave);
    $version = "HMAC_SHA256_V1";

    // URL del entorno (producción vs pruebas)
    $redsys_url = is_production_environment() ? 
        'https://sis.redsys.es/sis/realizarPago' : 
        'https://sis-t.redsys.es:25443/sis/realizarPago';

    // Generar formulario HTML
    $html = '<form id="formulario_redsys" action="' . $redsys_url . '" method="POST">';
    $html .= '<input type="hidden" name="Ds_SignatureVersion" value="' . $version . '">';
    $html .= '<input type="hidden" name="Ds_MerchantParameters" value="' . $params . '">';
    $html .= '<input type="hidden" name="Ds_Signature" value="' . $signature . '">';
    
    // Datos adicionales para el callback
    $html .= '<input type="hidden" name="reservation_id" value="' . ($reserva_data['id'] ?? '') . '">';
    $html .= '<input type="hidden" name="order_id" value="' . $pedido . '">';
    
    $html .= '</form>';
    $html .= '<script>document.getElementById("formulario_redsys").submit();</script>';

    // Guardar datos del pedido para verificación posterior
    guardar_datos_pedido($pedido, $reserva_data);

    return $html;
}

function is_production_environment() {
    // Detectar si estamos en producción
    $site_url = site_url();
    return !strpos($site_url, 'localhost') && !strpos($site_url, '.local') && !strpos($site_url, 'dev.');
}

function guardar_datos_pedido($order_id, $reserva_data) {
    // Guardar en sessión o base de datos temporal para verificar después
    if (!session_id()) {
        session_start();
    }
    
    $_SESSION['pending_orders'][$order_id] = array(
        'reservation_data' => $reserva_data,
        'timestamp' => time(),
        'status' => 'pending'
    );
    
    // También guardar en transient de WordPress por si falla la sesión
    set_transient('redsys_order_' . $order_id, $reserva_data, 3600); // 1 hora
}