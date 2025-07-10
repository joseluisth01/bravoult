<?php

/**
 * Clase para gestionar los informes y reservas del sistema - ACTUALIZADA CON EMAILS
 * Archivo: wp-content/plugins/sistema-reservas/includes/class-reports-admin.php
 */
class ReservasReportsAdmin
{

    public function __construct()
    {
        // Hooks AJAX para informes
        add_action('wp_ajax_get_reservations_report', array($this, 'get_reservations_report'));
        add_action('wp_ajax_nopriv_get_reservations_report', array($this, 'get_reservations_report')); // ✅ AÑADIR

        add_action('wp_ajax_search_reservations', array($this, 'search_reservations'));
        add_action('wp_ajax_get_reservation_details', array($this, 'get_reservation_details'));
        add_action('wp_ajax_update_reservation_email', array($this, 'update_reservation_email'));
        add_action('wp_ajax_resend_confirmation_email', array($this, 'resend_confirmation_email'));
        add_action('wp_ajax_get_date_range_stats', array($this, 'get_date_range_stats'));
        add_action('wp_ajax_get_quick_stats', array($this, 'get_quick_stats'));
        add_action('wp_ajax_cancel_reservation', array($this, 'cancel_reservation'));
    }

    /**
     * Obtener informe de reservas por fechas
     */
    public function get_reservations_report()
    {
        // ✅ DEBUGGING MEJORADO
        error_log('=== REPORTS AJAX REQUEST START ===');
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
    if (!in_array($user['role'], ['super_admin', 'admin'])) {
        wp_send_json_error('Sin permisos');
        return;
    }

            global $wpdb;
            $table_reservas = $wpdb->prefix . 'reservas_reservas';

            $fecha_inicio = sanitize_text_field($_POST['fecha_inicio'] ?? date('Y-m-d'));
            $fecha_fin = sanitize_text_field($_POST['fecha_fin'] ?? date('Y-m-d'));
            $page = intval($_POST['page'] ?? 1);
            $per_page = 20;
            $offset = ($page - 1) * $per_page;

            // Obtener reservas del rango de fechas
            $reservas = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, s.hora as servicio_hora 
             FROM $table_reservas r
             LEFT JOIN {$wpdb->prefix}reservas_servicios s ON r.servicio_id = s.id
             WHERE r.fecha BETWEEN %s AND %s 
             ORDER BY r.fecha DESC, r.hora DESC
             LIMIT %d OFFSET %d",
                $fecha_inicio,
                $fecha_fin,
                $per_page,
                $offset
            ));

            if ($wpdb->last_error) {
                error_log('❌ Database error in reports: ' . $wpdb->last_error);
                die(json_encode(['success' => false, 'data' => 'Database error: ' . $wpdb->last_error]));
            }

            // Contar total de reservas
            $total_reservas = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_reservas 
             WHERE fecha BETWEEN %s AND %s",
                $fecha_inicio,
                $fecha_fin
            ));

            // Obtener estadísticas del período
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                COUNT(*) as total_reservas,
                SUM(adultos) as total_adultos,
                SUM(residentes) as total_residentes,
                SUM(ninos_5_12) as total_ninos_5_12,
                SUM(ninos_menores) as total_ninos_menores,
                SUM(total_personas) as total_personas_con_plaza,
                SUM(precio_final) as ingresos_totales,
                SUM(descuento_total) as descuentos_totales
             FROM $table_reservas 
             WHERE fecha BETWEEN %s AND %s 
             AND estado = 'confirmada'",
                $fecha_inicio,
                $fecha_fin
            ));

            $response_data = array(
                'reservas' => $reservas,
                'stats' => $stats,
                'pagination' => array(
                    'current_page' => $page,
                    'total_pages' => ceil($total_reservas / $per_page),
                    'total_items' => $total_reservas,
                    'per_page' => $per_page
                ),
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin
            );

            error_log('✅ Reports data loaded successfully');
            die(json_encode(['success' => true, 'data' => $response_data]));
        } catch (Exception $e) {
            error_log('❌ REPORTS EXCEPTION: ' . $e->getMessage());
            die(json_encode(['success' => false, 'data' => 'Server error: ' . $e->getMessage()]));
        }
    }

    /**
     * Buscar reservas por diferentes criterios
     */
    public function search_reservations()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || !in_array($_SESSION['reservas_user']['role'], ['super_admin', 'admin'])) {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        $search_type = sanitize_text_field($_POST['search_type']);
        $search_value = sanitize_text_field($_POST['search_value']);

        $where_clause = '';
        $search_params = array();

        switch ($search_type) {
            case 'localizador':
                $where_clause = "WHERE r.localizador LIKE %s";
                $search_params[] = '%' . $search_value . '%';
                break;

            case 'email':
                $where_clause = "WHERE r.email LIKE %s";
                $search_params[] = '%' . $search_value . '%';
                break;

            case 'telefono':
                $where_clause = "WHERE r.telefono LIKE %s";
                $search_params[] = '%' . $search_value . '%';
                break;

            case 'fecha_emision':
                $where_clause = "WHERE DATE(r.created_at) = %s";
                $search_params[] = $search_value;
                break;

            case 'fecha_servicio':
                $where_clause = "WHERE r.fecha = %s";
                $search_params[] = $search_value;
                break;

            case 'nombre':
                $where_clause = "WHERE (r.nombre LIKE %s OR r.apellidos LIKE %s)";
                $search_params[] = '%' . $search_value . '%';
                $search_params[] = '%' . $search_value . '%';
                break;

            default:
                wp_send_json_error('Tipo de búsqueda no válido');
        }

        $query = "SELECT r.*, s.hora as servicio_hora 
                  FROM $table_reservas r
                  LEFT JOIN {$wpdb->prefix}reservas_servicios s ON r.servicio_id = s.id
                  $where_clause
                  ORDER BY r.created_at DESC
                  LIMIT 50";

        $reservas = $wpdb->get_results($wpdb->prepare($query, ...$search_params));

        wp_send_json_success(array(
            'reservas' => $reservas,
            'search_type' => $search_type,
            'search_value' => $search_value,
            'total_found' => count($reservas)
        ));
    }

    /**
     * Obtener detalles de una reserva específica
     */
    public function get_reservation_details()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || !in_array($_SESSION['reservas_user']['role'], ['super_admin', 'admin'])) {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        $reserva_id = intval($_POST['reserva_id']);

        $reserva = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, s.hora as servicio_hora, s.precio_adulto, s.precio_nino, s.precio_residente
             FROM $table_reservas r
             LEFT JOIN {$wpdb->prefix}reservas_servicios s ON r.servicio_id = s.id
             WHERE r.id = %d",
            $reserva_id
        ));

        if (!$reserva) {
            wp_send_json_error('Reserva no encontrada');
        }

        // Decodificar regla de descuento si existe
        if ($reserva->regla_descuento_aplicada) {
            $reserva->regla_descuento_aplicada = json_decode($reserva->regla_descuento_aplicada, true);
        }

        wp_send_json_success($reserva);
    }

    /**
     * Actualizar email de una reserva
     */
    public function update_reservation_email()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || !in_array($_SESSION['reservas_user']['role'], ['super_admin', 'admin'])) {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        $reserva_id = intval($_POST['reserva_id']);
        $new_email = sanitize_email($_POST['new_email']);

        if (!is_email($new_email)) {
            wp_send_json_error('Email no válido');
        }

        $result = $wpdb->update(
            $table_reservas,
            array('email' => $new_email),
            array('id' => $reserva_id)
        );

        if ($result !== false) {
            wp_send_json_success('Email actualizado correctamente');
        } else {
            wp_send_json_error('Error actualizando el email: ' . $wpdb->last_error);
        }
    }

    /**
     * ✅ REENVIAR EMAIL DE CONFIRMACIÓN - FUNCIÓN IMPLEMENTADA
     */
    public function resend_confirmation_email()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || !in_array($_SESSION['reservas_user']['role'], ['super_admin', 'admin'])) {
            wp_send_json_error('Sin permisos');
        }

        $reserva_id = intval($_POST['reserva_id']);

        // ✅ CARGAR CLASE DE EMAILS
        if (!class_exists('ReservasEmailService')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-email-service.php';
        }

        // ✅ REENVIAR EMAIL USANDO LA CLASE DE EMAILS
        $result = ReservasEmailService::resend_confirmation($reserva_id);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Obtener estadísticas por rango de fechas
     */
    public function get_date_range_stats()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || !in_array($_SESSION['reservas_user']['role'], ['super_admin', 'admin'])) {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        $range_type = sanitize_text_field($_POST['range_type']);

        $fecha_inicio = '';
        $fecha_fin = date('Y-m-d');

        switch ($range_type) {
            case '7_days':
                $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30_days':
                $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
                break;
            case '60_days':
                $fecha_inicio = date('Y-m-d', strtotime('-60 days'));
                break;
            case '90_days':
                $fecha_inicio = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'this_month':
                $fecha_inicio = date('Y-m-01');
                break;
            case 'last_month':
                $fecha_inicio = date('Y-m-01', strtotime('first day of last month'));
                $fecha_fin = date('Y-m-t', strtotime('last day of last month'));
                break;
            case 'this_year':
                $fecha_inicio = date('Y-01-01');
                break;
            case 'custom':
                $fecha_inicio = sanitize_text_field($_POST['fecha_inicio']);
                $fecha_fin = sanitize_text_field($_POST['fecha_fin']);
                break;
            default:
                wp_send_json_error('Rango de fechas no válido');
        }

        // Obtener estadísticas del período
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_reservas,
                SUM(adultos) as total_adultos,
                SUM(residentes) as total_residentes,
                SUM(ninos_5_12) as total_ninos_5_12,
                SUM(ninos_menores) as total_ninos_menores,
                SUM(total_personas) as total_personas_con_plaza,
                SUM(precio_final) as ingresos_totales,
                SUM(descuento_total) as descuentos_totales,
                AVG(precio_final) as precio_promedio
             FROM $table_reservas 
             WHERE fecha BETWEEN %s AND %s 
             AND estado = 'confirmada'",
            $fecha_inicio,
            $fecha_fin
        ));

        // Obtener reservas por día para gráfico
        $reservas_por_dia = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                fecha,
                COUNT(*) as reservas_dia,
                SUM(total_personas) as personas_dia,
                SUM(precio_final) as ingresos_dia
             FROM $table_reservas 
             WHERE fecha BETWEEN %s AND %s 
             AND estado = 'confirmada'
             GROUP BY fecha
             ORDER BY fecha",
            $fecha_inicio,
            $fecha_fin
        ));

        wp_send_json_success(array(
            'stats' => $stats,
            'reservas_por_dia' => $reservas_por_dia,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'range_type' => $range_type
        ));
    }

    /**
     * Método estático para obtener estadísticas rápidas
     */
    public function get_quick_stats()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || !in_array($_SESSION['reservas_user']['role'], ['super_admin', 'admin'])) {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_reservas = $wpdb->prefix . 'reservas_reservas';
        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $today = date('Y-m-d');
        $this_month_start = date('Y-m-01');
        $last_month_start = date('Y-m-01', strtotime('first day of last month'));
        $last_month_end = date('Y-m-t', strtotime('last day of last month'));

        // 1. RESERVAS DE HOY
        $reservas_hoy = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_reservas WHERE fecha = %s AND estado = 'confirmada'",
            $today
        ));

        // 2. INGRESOS DEL MES ACTUAL
        $ingresos_mes_actual = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(precio_final) FROM $table_reservas 
         WHERE fecha >= %s AND estado = 'confirmada'",
            $this_month_start
        )) ?: 0;

        // 3. INGRESOS DEL MES PASADO (para comparar)
        $ingresos_mes_pasado = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(precio_final) FROM $table_reservas 
         WHERE fecha BETWEEN %s AND %s AND estado = 'confirmada'",
            $last_month_start,
            $last_month_end
        )) ?: 0;

        // 4. CRECIMIENTO PORCENTUAL
        $crecimiento = 0;
        if ($ingresos_mes_pasado > 0) {
            $crecimiento = (($ingresos_mes_actual - $ingresos_mes_pasado) / $ingresos_mes_pasado) * 100;
        } elseif ($ingresos_mes_actual > 0) {
            $crecimiento = 100; // Si mes pasado = 0 y este mes > 0, es 100% crecimiento
        }

        // 5. TOP 3 DÍAS CON MÁS RESERVAS ESTE MES
        $top_dias = $wpdb->get_results($wpdb->prepare(
            "SELECT fecha, COUNT(*) as total_reservas, SUM(total_personas) as total_personas 
         FROM $table_reservas 
         WHERE fecha >= %s AND estado = 'confirmada'
         GROUP BY fecha 
         ORDER BY total_reservas DESC 
         LIMIT 3",
            $this_month_start
        ));

        // 6. OCUPACIÓN PROMEDIO (este mes)
        $ocupacion_data = $wpdb->get_row($wpdb->prepare(
            "SELECT 
            SUM(s.plazas_totales) as plazas_totales,
            SUM(s.plazas_totales - s.plazas_disponibles) as plazas_ocupadas
         FROM $table_servicios s
         WHERE s.fecha >= %s AND s.status = 'active'",
            $this_month_start
        ));

        $ocupacion_porcentaje = 0;
        if ($ocupacion_data && $ocupacion_data->plazas_totales > 0) {
            $ocupacion_porcentaje = ($ocupacion_data->plazas_ocupadas / $ocupacion_data->plazas_totales) * 100;
        }

        // 7. CLIENTE MÁS FRECUENTE (último mes)
        $cliente_frecuente = $wpdb->get_row($wpdb->prepare(
            "SELECT email, CONCAT(nombre, ' ', apellidos) as nombre_completo, COUNT(*) as total_reservas
         FROM $table_reservas 
         WHERE created_at >= %s AND estado = 'confirmada'
         GROUP BY email 
         ORDER BY total_reservas DESC 
         LIMIT 1",
            date('Y-m-d', strtotime('-30 days'))
        ));

        // 8. PRÓXIMOS SERVICIOS CON ALTA OCUPACIÓN (>80%)
        $servicios_alta_ocupacion = $wpdb->get_results($wpdb->prepare(
            "SELECT fecha, hora, plazas_totales, plazas_disponibles,
                ((plazas_totales - plazas_disponibles) / plazas_totales * 100) as ocupacion
         FROM $table_servicios 
         WHERE fecha >= %s AND status = 'active'
         AND ((plazas_totales - plazas_disponibles) / plazas_totales * 100) > 80
         ORDER BY fecha, hora 
         LIMIT 5",
            $today
        ));

        // 9. ESTADÍSTICAS DE TIPOS DE CLIENTE (este mes)
        $tipos_cliente = $wpdb->get_row($wpdb->prepare(
            "SELECT 
            SUM(adultos) as total_adultos,
            SUM(residentes) as total_residentes,
            SUM(ninos_5_12) as total_ninos,
            SUM(ninos_menores) as total_bebes
         FROM $table_reservas 
         WHERE fecha >= %s AND estado = 'confirmada'",
            $this_month_start
        ));

        // PREPARAR RESPUESTA
        $stats = array(
            'hoy' => array(
                'reservas' => intval($reservas_hoy),
                'fecha' => $today
            ),
            'ingresos' => array(
                'mes_actual' => floatval($ingresos_mes_actual),
                'mes_pasado' => floatval($ingresos_mes_pasado),
                'crecimiento' => round($crecimiento, 1),
                'mes_nombre' => date('F Y', strtotime($this_month_start))
            ),
            'top_dias' => $top_dias,
            'ocupacion' => array(
                'porcentaje' => round($ocupacion_porcentaje, 1),
                'plazas_totales' => intval($ocupacion_data->plazas_totales ?? 0),
                'plazas_ocupadas' => intval($ocupacion_data->plazas_ocupadas ?? 0)
            ),
            'cliente_frecuente' => $cliente_frecuente,
            'servicios_alta_ocupacion' => $servicios_alta_ocupacion,
            'tipos_cliente' => $tipos_cliente
        );

        wp_send_json_success($stats);
    }



    public function cancel_reservation()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_send_json_error('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || !in_array($_SESSION['reservas_user']['role'], ['super_admin', 'admin'])) {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_reservas = $wpdb->prefix . 'reservas_reservas';
        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $reserva_id = intval($_POST['reserva_id']);
        $motivo_cancelacion = sanitize_text_field($_POST['motivo_cancelacion'] ?? 'Cancelación administrativa');

        // Obtener datos de la reserva
        $reserva = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_reservas WHERE id = %d AND estado != 'cancelada'",
            $reserva_id
        ));

        if (!$reserva) {
            wp_send_json_error('Reserva no encontrada o ya cancelada');
        }

        // Iniciar transacción
        $wpdb->query('START TRANSACTION');

        try {
            // 1. Actualizar estado de la reserva
            $update_reserva = $wpdb->update(
                $table_reservas,
                array(
                    'estado' => 'cancelada',
                    'motivo_cancelacion' => $motivo_cancelacion,
                    'fecha_cancelacion' => current_time('mysql')
                ),
                array('id' => $reserva_id)
            );

            if ($update_reserva === false) {
                throw new Exception('Error actualizando reserva');
            }

            // 2. Liberar las plazas en el servicio
            $update_plazas = $wpdb->query($wpdb->prepare(
                "UPDATE $table_servicios 
             SET plazas_disponibles = plazas_disponibles + %d 
             WHERE id = %d",
                $reserva->total_personas,
                $reserva->servicio_id
            ));

            if ($update_plazas === false) {
                throw new Exception('Error liberando plazas');
            }

            // 3. Enviar email de cancelación al cliente
            if (!class_exists('ReservasEmailService')) {
                require_once RESERVAS_PLUGIN_PATH . 'includes/class-email-service.php';
            }

            $reserva_array = (array) $reserva;
            $reserva_array['motivo_cancelacion'] = $motivo_cancelacion;

            // ✅ LLAMAR CORRECTAMENTE A LA CLASE DE EMAILS
            $email_result = ReservasEmailService::send_cancellation_email($reserva_array);

            // Confirmar transacción
            $wpdb->query('COMMIT');

            $message = 'Reserva cancelada correctamente';
            if ($email_result['success']) {
                $message .= ' y email enviado al cliente';
            } else {
                $message .= ' (email no enviado: ' . $email_result['message'] . ')';
            }

            wp_send_json_success($message);
        } catch (Exception $e) {
            // Rollback en caso de error
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Error cancelando reserva: ' . $e->getMessage());
        }
    }
}
