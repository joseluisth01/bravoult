<?php
class ReservasRedsysHandler {
    
    public function __construct() {
        add_action('wp_ajax_redsys_notification', array($this, 'handle_notification'));
        add_action('wp_ajax_nopriv_redsys_notification', array($this, 'handle_notification'));
        add_action('init', array($this, 'handle_redsys_response'));
    }

    public function handle_notification() {
        error_log('=== NOTIFICACIÓN REDSYS RECIBIDA ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        if (!isset($_POST['Ds_MerchantParameters']) || !isset($_POST['Ds_Signature'])) {
            error_log('❌ Faltan parámetros de Redsys');
            http_response_code(400);
            exit('KO');
        }

        try {
            require_once RESERVAS_PLUGIN_PATH . 'includes/redsys-api.php';
            
            $redsys = new RedsysAPI();
            $clave = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; // Tu clave
            
            $signature = $_POST['Ds_Signature'];
            $parameters = $_POST['Ds_MerchantParameters'];
            
            // Verificar firma
            if (!$redsys->verifySignature($signature, $parameters, $clave)) {
                error_log('❌ Firma de Redsys inválida');
                http_response_code(400);
                exit('KO');
            }

            // Obtener parámetros de la respuesta
            $params = $redsys->getParametersFromResponse($parameters);
            
            $order_id = $params['Ds_Order'];
            $response_code = $params['Ds_Response'];
            $amount = $params['Ds_Amount'];
            
            error_log("Pedido: $order_id, Respuesta: $response_code, Importe: $amount");
            
            // Verificar si el pago fue exitoso
            if ($this->is_payment_successful($response_code)) {
                $this->process_successful_payment($order_id, $params);
                error_log('✅ Pago procesado exitosamente');
                exit('OK');
            } else {
                $this->process_failed_payment($order_id, $params);
                error_log('❌ Pago falló con código: ' . $response_code);
                exit('OK'); // Respondemos OK aunque el pago haya fallado
            }
            
        } catch (Exception $e) {
            error_log('❌ Error procesando notificación Redsys: ' . $e->getMessage());
            http_response_code(500);
            exit('KO');
        }
    }

    public function handle_redsys_response() {
        // Manejar respuestas de URLs de OK/KO si es necesario
        if (isset($_GET['status']) && isset($_GET['order'])) {
            $status = sanitize_text_field($_GET['status']);
            $order_id = sanitize_text_field($_GET['order']);
            
            error_log("Usuario regresó del banco - Status: $status, Order: $order_id");
            
            // Aquí podrías hacer alguna acción adicional si es necesario
        }
    }

    private function is_payment_successful($response_code) {
        // Códigos de respuesta exitosa en Redsys
        $response_code = intval($response_code);
        return $response_code >= 0 && $response_code <= 99;
    }

    private function process_successful_payment($order_id, $params) {
        // Recuperar datos de la reserva
        $reservation_data = get_transient('redsys_order_' . $order_id);
        
        if (!$reservation_data) {
            if (!session_id()) {
                session_start();
            }
            $reservation_data = $_SESSION['pending_orders'][$order_id]['reservation_data'] ?? null;
        }
        
        if (!$reservation_data) {
            error_log('❌ No se encontraron datos de reserva para pedido: ' . $order_id);
            return false;
        }

        try {
            // Procesar la reserva usando tu sistema existente
            if (!class_exists('ReservasProcessor')) {
                require_once RESERVAS_PLUGIN_PATH . 'includes/class-reservas-processor.php';
            }

            $processor = new ReservasProcessor();
            
            // Preparar datos para el procesador
            $processed_data = array(
                'nombre' => $reservation_data['nombre'] ?? '',
                'apellidos' => $reservation_data['apellidos'] ?? '',
                'email' => $reservation_data['email'] ?? '',
                'telefono' => $reservation_data['telefono'] ?? '',
                'reservation_data' => json_encode($reservation_data),
                'metodo_pago' => 'redsys',
                'transaction_id' => $params['Ds_AuthorisationCode'] ?? '',
                'order_id' => $order_id
            );

            // Procesar la reserva
            $result = $processor->process_reservation_payment($processed_data);
            
            if ($result['success']) {
                error_log('✅ Reserva procesada exitosamente: ' . $result['data']['localizador']);
                
                // Limpiar datos temporales
                delete_transient('redsys_order_' . $order_id);
                if (isset($_SESSION['pending_orders'][$order_id])) {
                    unset($_SESSION['pending_orders'][$order_id]);
                }
                
                return true;
            } else {
                error_log('❌ Error procesando reserva: ' . $result['message']);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('❌ Excepción procesando pago exitoso: ' . $e->getMessage());
            return false;
        }
    }

    private function process_failed_payment($order_id, $params) {
        error_log('❌ Procesando pago fallido para pedido: ' . $order_id);
        
        // Limpiar datos temporales
        delete_transient('redsys_order_' . $order_id);
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['pending_orders'][$order_id])) {
            unset($_SESSION['pending_orders'][$order_id]);
        }
        
        // Aquí podrías enviar un email de notificación de pago fallido si es necesario
    }
}