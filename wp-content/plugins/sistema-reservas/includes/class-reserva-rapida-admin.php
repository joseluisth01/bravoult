<?php

/**
 * Clase para gestionar Reserva Rápida para administradores
 * Archivo: wp-content/plugins/sistema-reservas/includes/class-reserva-rapida-admin.php
 */
class ReservasReservaRapidaAdmin
{

    public function __construct()
    {
        // Hooks AJAX para reserva rápida
        add_action('wp_ajax_get_reserva_rapida_form', array($this, 'get_reserva_rapida_form'));
        add_action('wp_ajax_nopriv_get_reserva_rapida_form', array($this, 'get_reserva_rapida_form'));

        add_action('wp_ajax_process_reserva_rapida', array($this, 'process_reserva_rapida'));
        add_action('wp_ajax_nopriv_process_reserva_rapida', array($this, 'process_reserva_rapida'));

        add_action('wp_ajax_get_available_services_rapida', array($this, 'get_available_services_rapida'));
        add_action('wp_ajax_nopriv_get_available_services_rapida', array($this, 'get_available_services_rapida'));

        add_action('wp_ajax_calculate_price_rapida', array($this, 'calculate_price_rapida'));
        add_action('wp_ajax_nopriv_calculate_price_rapida', array($this, 'calculate_price_rapida'));
    }

    /**
     * Obtener formulario de reserva rápida
     */
    public function get_reserva_rapida_form()
    {
        error_log('=== GET RESERVA RAPIDA FORM START ===');
        header('Content-Type: application/json');

        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
                wp_send_json_error('Error de seguridad');
                return;
            }

            if (!session_id()) {
                session_start();
            }

            if (!isset($_SESSION['reservas_user'])) {
                wp_send_json_error('Sesión expirada. Recarga la página e inicia sesión nuevamente.');
                return;
            }

            $user = $_SESSION['reservas_user'];

            // Solo super_admin y admin pueden usar reserva rápida
            if (!in_array($user['role'], ['super_admin', 'admin'])) {
                wp_send_json_error('Sin permisos para usar reserva rápida');
                return;
            }

            // Obtener servicios disponibles para los próximos 30 días
            $servicios_disponibles = $this->get_upcoming_services();

            // Generar HTML del formulario
            ob_start();
            include RESERVAS_PLUGIN_PATH . 'templates/reserva-rapida-form.php';
            $form_html = ob_get_clean();

            wp_send_json_success($form_html);
        } catch (Exception $e) {
            error_log('❌ RESERVA RAPIDA FORM EXCEPTION: ' . $e->getMessage());
            wp_send_json_error('Error del servidor: ' . $e->getMessage());
        }
    }

    /**
     * Obtener servicios disponibles para los próximos días
     */
    private function get_upcoming_services()
    {
        global $wpdb;

        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d', strtotime('+30 days'));

        $servicios = $wpdb->get_results($wpdb->prepare(
            "SELECT id, fecha, hora, plazas_disponibles, precio_adulto, precio_nino, precio_residente
             FROM $table_servicios 
             WHERE fecha BETWEEN %s AND %s 
             AND status = 'active'
             AND plazas_disponibles > 0
             ORDER BY fecha, hora",
            $fecha_inicio,
            $fecha_fin
        ));

        return $servicios;
    }

    /**
     * Obtener servicios disponibles vía AJAX
     */
    public function get_available_services_rapida()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
            return;
        }

        $servicios = $this->get_upcoming_services();

        // Organizar por fecha
        $servicios_organizados = array();
        foreach ($servicios as $servicio) {
            if (!isset($servicios_organizados[$servicio->fecha])) {
                $servicios_organizados[$servicio->fecha] = array();
            }

            $servicios_organizados[$servicio->fecha][] = array(
                'id' => $servicio->id,
                'hora' => substr($servicio->hora, 0, 5),
                'plazas_disponibles' => $servicio->plazas_disponibles,
                'precio_adulto' => $servicio->precio_adulto,
                'precio_nino' => $servicio->precio_nino,
                'precio_residente' => $servicio->precio_residente
            );
        }

        wp_send_json_success($servicios_organizados);
    }

    /**
     * Calcular precio para reserva rápida
     */
    public function calculate_price_rapida()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
            return;
        }

        $service_id = intval($_POST['service_id']);
        $adultos = intval($_POST['adultos']);
        $residentes = intval($_POST['residentes']);
        $ninos_5_12 = intval($_POST['ninos_5_12']);
        $ninos_menores = intval($_POST['ninos_menores']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        // Obtener datos del servicio
        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $service_id
        ));

        if (!$servicio) {
            wp_send_json_error('Servicio no encontrado');
            return;
        }

        // Calcular precio usando la misma lógica que el frontend
        $total_personas_con_plaza = $adultos + $residentes + $ninos_5_12;

        // Precio base
        $precio_base = 0;
        $precio_base += $adultos * $servicio->precio_adulto;
        $precio_base += $residentes * $servicio->precio_adulto;
        $precio_base += $ninos_5_12 * $servicio->precio_adulto;

        // Descuentos individuales
        $descuento_total = 0;
        $descuento_residentes = $residentes * ($servicio->precio_adulto - $servicio->precio_residente);
        $descuento_ninos = $ninos_5_12 * ($servicio->precio_adulto - $servicio->precio_nino);
        $descuento_total += $descuento_residentes + $descuento_ninos;

        // Descuento por grupo
        $descuento_grupo = 0;
        $regla_aplicada = null;

        if ($total_personas_con_plaza > 0) {
            if (!class_exists('ReservasDiscountsAdmin')) {
                require_once RESERVAS_PLUGIN_PATH . 'includes/class-discounts-admin.php';
            }

            $subtotal = $precio_base - $descuento_total;
            $discount_info = ReservasDiscountsAdmin::calculate_discount($total_personas_con_plaza, $subtotal, 'total');

            if ($discount_info['discount_applied']) {
                $descuento_grupo = $discount_info['discount_amount'];
                $descuento_total += $descuento_grupo;
                $regla_aplicada = $discount_info;
            }
        }

        // Precio final
        $precio_final = $precio_base - $descuento_total;
        if ($precio_final < 0) $precio_final = 0;

        $response_data = array(
            'precio_base' => round($precio_base, 2),
            'descuento_total' => round($descuento_total, 2),
            'descuento_residentes' => round($descuento_residentes, 2),
            'descuento_ninos' => round($descuento_ninos, 2),
            'descuento_grupo' => round($descuento_grupo, 2),
            'precio_final' => round($precio_final, 2),
            'total_personas_con_plaza' => $total_personas_con_plaza,
            'regla_descuento_aplicada' => $regla_aplicada
        );

        wp_send_json_success($response_data);
    }

    /**
     * Procesar reserva rápida
     */
    public function process_reserva_rapida()
    {
        // Limpiar cualquier output buffer
        if (ob_get_level()) {
            ob_clean();
        }

        header('Content-Type: application/json');

        try {
            error_log('=== INICIANDO PROCESS_RESERVA_RAPIDA ===');

            // Verificar nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
                wp_send_json_error('Error de seguridad');
                return;
            }

            // Verificar sesión y permisos
            if (!session_id()) {
                session_start();
            }

            if (!isset($_SESSION['reservas_user'])) {
                wp_send_json_error('Sesión expirada');
                return;
            }

            $user = $_SESSION['reservas_user'];

            if (!in_array($user['role'], ['super_admin', 'admin'])) {
                wp_send_json_error('Sin permisos para crear reservas rápidas');
                return;
            }

            // Validar datos del formulario
            $validation_result = $this->validate_reserva_rapida_data();
            if (!$validation_result['valid']) {
                wp_send_json_error($validation_result['error']);
                return;
            }

            $datos = $validation_result['data'];

            // Verificar disponibilidad
            $availability_check = $this->check_service_availability($datos['service_id'], $datos['total_personas']);
            if (!$availability_check['available']) {
                wp_send_json_error($availability_check['error']);
                return;
            }

            // Recalcular precio final
            $price_calculation = $this->calculate_final_price($datos);
            if (!$price_calculation['valid']) {
                wp_send_json_error($price_calculation['error']);
                return;
            }

            // Crear reserva
            $reservation_result = $this->create_reservation($datos, $price_calculation['price_data'], $user);
            if (!$reservation_result['success']) {
                wp_send_json_error($reservation_result['error']);
                return;
            }

            // Actualizar plazas disponibles
            $update_result = $this->update_available_seats($datos['service_id'], $datos['total_personas']);
            if (!$update_result['success']) {
                // Rollback: eliminar reserva creada
                $this->delete_reservation($reservation_result['reservation_id']);
                wp_send_json_error('Error actualizando disponibilidad. Reserva cancelada.');
                return;
            }

            // Enviar emails de confirmación
            $this->send_confirmation_emails($reservation_result['reservation_id'], $user);

            // Respuesta exitosa
            $response_data = array(
                'mensaje' => 'Reserva rápida procesada correctamente',
                'localizador' => $reservation_result['localizador'],
                'reserva_id' => $reservation_result['reservation_id'],
                'admin_user' => $user['username'],
                'detalles' => array(
                    'fecha' => $datos['fecha'],
                    'hora' => $datos['hora'],
                    'personas' => $datos['total_personas'],
                    'precio_final' => $price_calculation['price_data']['precio_final']
                )
            );

            error_log('✅ RESERVA RAPIDA COMPLETADA EXITOSAMENTE');
            wp_send_json_success($response_data);

        } catch (Exception $e) {
            error_log('❌ RESERVA RAPIDA EXCEPTION: ' . $e->getMessage());
            wp_send_json_error('Error interno del servidor: ' . $e->getMessage());
        }
    }

    /**
     * Validar datos de reserva rápida
     */
    private function validate_reserva_rapida_data()
    {
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $apellidos = sanitize_text_field($_POST['apellidos'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $telefono = sanitize_text_field($_POST['telefono'] ?? '');
        $service_id = intval($_POST['service_id'] ?? 0);
        $adultos = intval($_POST['adultos'] ?? 0);
        $residentes = intval($_POST['residentes'] ?? 0);
        $ninos_5_12 = intval($_POST['ninos_5_12'] ?? 0);
        $ninos_menores = intval($_POST['ninos_menores'] ?? 0);

        // Validaciones
        if (empty($nombre) || strlen($nombre) < 2) {
            return array('valid' => false, 'error' => 'El nombre es obligatorio (mínimo 2 caracteres)');
        }

        if (empty($apellidos) || strlen($apellidos) < 2) {
            return array('valid' => false, 'error' => 'Los apellidos son obligatorios (mínimo 2 caracteres)');
        }

        if (empty($email) || !is_email($email)) {
            return array('valid' => false, 'error' => 'Email no válido');
        }

        if (empty($telefono) || strlen($telefono) < 9) {
            return array('valid' => false, 'error' => 'Teléfono no válido (mínimo 9 dígitos)');
        }

        if ($service_id <= 0) {
            return array('valid' => false, 'error' => 'Debe seleccionar un servicio válido');
        }

        $total_personas = $adultos + $residentes + $ninos_5_12;

        if ($total_personas <= 0) {
            return array('valid' => false, 'error' => 'Debe haber al menos una persona que ocupe plaza');
        }

        if ($ninos_5_12 > 0 && ($adultos + $residentes) <= 0) {
            return array('valid' => false, 'error' => 'Debe haber al menos un adulto si hay niños');
        }

        // Obtener datos del servicio
        global $wpdb;
        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT fecha, hora FROM $table_servicios WHERE id = %d AND status = 'active'",
            $service_id
        ));

        if (!$servicio) {
            return array('valid' => false, 'error' => 'Servicio seleccionado no válido');
        }

        return array(
            'valid' => true,
            'data' => array(
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'email' => $email,
                'telefono' => $telefono,
                'service_id' => $service_id,
                'fecha' => $servicio->fecha,
                'hora' => $servicio->hora,
                'adultos' => $adultos,
                'residentes' => $residentes,
                'ninos_5_12' => $ninos_5_12,
                'ninos_menores' => $ninos_menores,
                'total_personas' => $total_personas,
                'total_viajeros' => $total_personas + $ninos_menores
            )
        );
    }

    /**
     * Verificar disponibilidad del servicio
     */
    private function check_service_availability($service_id, $personas_necesarias)
    {
        global $wpdb;

        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_servicios WHERE id = %d AND status = 'active'",
            $service_id
        ));

        if (!$servicio) {
            return array('available' => false, 'error' => 'Servicio no encontrado');
        }

        if ($servicio->plazas_disponibles < $personas_necesarias) {
            return array(
                'available' => false,
                'error' => "Solo quedan {$servicio->plazas_disponibles} plazas disponibles, necesitas {$personas_necesarias}"
            );
        }

        return array('available' => true, 'service' => $servicio);
    }

    /**
     * Calcular precio final
     */
    private function calculate_final_price($datos)
    {
        global $wpdb;

        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_servicios WHERE id = %d",
            $datos['service_id']
        ));

        if (!$servicio) {
            return array('valid' => false, 'error' => 'Servicio no encontrado para cálculo');
        }

        // Usar la misma lógica de cálculo que el método calculate_price_rapida
        $total_personas_con_plaza = $datos['adultos'] + $datos['residentes'] + $datos['ninos_5_12'];

        $precio_base = 0;
        $precio_base += $datos['adultos'] * $servicio->precio_adulto;
        $precio_base += $datos['residentes'] * $servicio->precio_adulto;
        $precio_base += $datos['ninos_5_12'] * $servicio->precio_adulto;

        $descuento_total = 0;
        $descuento_residentes = $datos['residentes'] * ($servicio->precio_adulto - $servicio->precio_residente);
        $descuento_ninos = $datos['ninos_5_12'] * ($servicio->precio_adulto - $servicio->precio_nino);
        $descuento_total += $descuento_residentes + $descuento_ninos;

        $descuento_grupo = 0;
        $regla_aplicada = null;

        if ($total_personas_con_plaza > 0) {
            if (!class_exists('ReservasDiscountsAdmin')) {
                require_once RESERVAS_PLUGIN_PATH . 'includes/class-discounts-admin.php';
            }

            $subtotal = $precio_base - $descuento_total;
            $discount_info = ReservasDiscountsAdmin::calculate_discount($total_personas_con_plaza, $subtotal, 'total');

            if ($discount_info['discount_applied']) {
                $descuento_grupo = $discount_info['discount_amount'];
                $descuento_total += $descuento_grupo;
                $regla_aplicada = $discount_info;
            }
        }

        $precio_final = $precio_base - $descuento_total;
        if ($precio_final < 0) $precio_final = 0;

        return array(
            'valid' => true,
            'price_data' => array(
                'precio_base' => round($precio_base, 2),
                'descuento_total' => round($descuento_total, 2),
                'precio_final' => round($precio_final, 2),
                'regla_descuento_aplicada' => $regla_aplicada
            )
        );
    }

    /**
     * Crear reserva en la base de datos
     */
    private function create_reservation($datos, $price_data, $admin_user)
    {
        global $wpdb;

        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        // Generar localizador único
        $localizador = $this->generate_localizador();

        $reserva_data = array(
            'localizador' => $localizador,
            'servicio_id' => $datos['service_id'],
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'],
            'nombre' => $datos['nombre'],
            'apellidos' => $datos['apellidos'],
            'email' => $datos['email'],
            'telefono' => $datos['telefono'],
            'adultos' => $datos['adultos'],
            'residentes' => $datos['residentes'],
            'ninos_5_12' => $datos['ninos_5_12'],
            'ninos_menores' => $datos['ninos_menores'],
            'total_personas' => $datos['total_personas'],
            'precio_base' => $price_data['precio_base'],
            'descuento_total' => $price_data['descuento_total'],
            'precio_final' => $price_data['precio_final'],
            'regla_descuento_aplicada' => $price_data['regla_descuento_aplicada'] ? json_encode($price_data['regla_descuento_aplicada']) : null,
            'estado' => 'confirmada',
            'metodo_pago' => 'reserva_rapida_admin'
        );

        $resultado = $wpdb->insert($table_reservas, $reserva_data);

        if ($resultado === false) {
            return array('success' => false, 'error' => 'Error guardando la reserva: ' . $wpdb->last_error);
        }

        $reserva_id = $wpdb->insert_id;

        return array(
            'success' => true,
            'reservation_id' => $reserva_id,
            'localizador' => $localizador
        );
    }

    /**
     * Actualizar plazas disponibles
     */
    private function update_available_seats($service_id, $personas_ocupadas)
    {
        global $wpdb;

        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $resultado = $wpdb->query($wpdb->prepare(
            "UPDATE $table_servicios 
             SET plazas_disponibles = plazas_disponibles - %d 
             WHERE id = %d AND plazas_disponibles >= %d",
            $personas_ocupadas,
            $service_id,
            $personas_ocupadas
        ));

        if ($resultado === false) {
            return array('success' => false, 'error' => 'Error actualizando plazas disponibles');
        }

        if ($resultado === 0) {
            return array('success' => false, 'error' => 'No hay suficientes plazas disponibles');
        }

        return array('success' => true);
    }

    /**
     * Eliminar reserva (rollback)
     */
    private function delete_reservation($reservation_id)
    {
        global $wpdb;

        $table_reservas = $wpdb->prefix . 'reservas_reservas';
        $wpdb->delete($table_reservas, array('id' => $reservation_id));
    }

    /**
     * Enviar emails de confirmación
     */
    private function send_confirmation_emails($reservation_id, $admin_user)
    {
        error_log('=== ENVIANDO EMAILS DE RESERVA RAPIDA ===');

        if (!class_exists('ReservasEmailService')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-email-service.php';
        }

        // Obtener datos de la reserva
        global $wpdb;
        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        $reserva = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_reservas WHERE id = %d",
            $reservation_id
        ));

        if (!$reserva) {
            error_log('❌ No se encontró la reserva para enviar emails');
            return;
        }

        // Obtener datos del servicio para precios
        $table_servicios = $wpdb->prefix . 'reservas_servicios';
        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT precio_adulto, precio_nino, precio_residente FROM $table_servicios WHERE id = %d",
            $reserva->servicio_id
        ));

        // Preparar datos para emails
        $reserva_array = (array) $reserva;
        if ($servicio) {
            $reserva_array['precio_adulto'] = $servicio->precio_adulto;
            $reserva_array['precio_nino'] = $servicio->precio_nino;
            $reserva_array['precio_residente'] = $servicio->precio_residente;
        }

        // 1. Email al cliente
        $customer_result = ReservasEmailService::send_customer_confirmation($reserva_array);
        if ($customer_result['success']) {
            error_log('✅ Email enviado al cliente correctamente');
        } else {
            error_log('❌ Error enviando email al cliente: ' . $customer_result['message']);
        }

        // 2. Email al super_admin notificando reserva hecha por administrador
        $admin_result = ReservasEmailService::send_admin_agency_reservation_notification($reserva_array, $admin_user);
        if ($admin_result['success']) {
            error_log('✅ Email enviado al super_admin correctamente');
        } else {
            error_log('❌ Error enviando email al super_admin: ' . $admin_result['message']);
        }
    }

    /**
     * Generar localizador único
     */
    private function generate_localizador()
    {
        global $wpdb;

        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        do {
            $localizador = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_reservas WHERE localizador = %s",
                $localizador
            ));
        } while ($exists > 0);

        return $localizador;
    }
}