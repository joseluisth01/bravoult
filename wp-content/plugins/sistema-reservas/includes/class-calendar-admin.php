<?php
/**
 * Clase ReservasCalendarAdmin actualizada para sincronizar con configuración
 * Archivo: wp-content/plugins/sistema-reservas/includes/class-calendar-admin.php
 */
class ReservasCalendarAdmin {
    
    public function __construct() {
        // Hooks AJAX para el calendario
        add_action('wp_ajax_get_calendar_data', array($this, 'get_calendar_data'));
        add_action('wp_ajax_save_service', array($this, 'save_service'));
        add_action('wp_ajax_delete_service', array($this, 'delete_service'));
        add_action('wp_ajax_get_service_details', array($this, 'get_service_details'));
        add_action('wp_ajax_bulk_add_services', array($this, 'bulk_add_services'));
    }

    public function get_calendar_data() {
        // Limpiar cualquier output buffer
        if (ob_get_level()) {
            ob_clean();
        }

        // Headers para JSON
        header('Content-Type: application/json');

        try {
            // Verificar nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
                wp_send_json_error('Error de seguridad');
                exit;
            }

            // Verificar sesión
            if (!session_id()) {
                session_start();
            }

            if (!isset($_SESSION['reservas_user'])) {
                wp_send_json_error('Usuario no logueado');
                exit;
            }

            // Obtener datos reales de la base de datos
            global $wpdb;
            $table_name = $wpdb->prefix . 'reservas_servicios';

            $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
            $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

            // Calcular primer y último día del mes
            $first_day = sprintf('%04d-%02d-01', $year, $month);
            $last_day = date('Y-m-t', strtotime($first_day));

            // Incluir campos de descuento en la consulta
            $servicios = $wpdb->get_results($wpdb->prepare(
                "SELECT id, fecha, hora, plazas_totales, plazas_disponibles, 
                        precio_adulto, precio_nino, precio_residente,
                        tiene_descuento, porcentaje_descuento
                FROM $table_name 
                WHERE fecha BETWEEN %s AND %s 
                AND status = 'active'
                ORDER BY fecha, hora",
                $first_day,
                $last_day
            ));

            // Organizar por fecha
            $calendar_data = array();
            foreach ($servicios as $servicio) {
                if (!isset($calendar_data[$servicio->fecha])) {
                    $calendar_data[$servicio->fecha] = array();
                }

                $calendar_data[$servicio->fecha][] = array(
                    'id' => $servicio->id,
                    'hora' => substr($servicio->hora, 0, 5),
                    'plazas_totales' => $servicio->plazas_totales,
                    'plazas_disponibles' => $servicio->plazas_disponibles,
                    'precio_adulto' => $servicio->precio_adulto,
                    'precio_nino' => $servicio->precio_nino,
                    'precio_residente' => $servicio->precio_residente,
                    'tiene_descuento' => $servicio->tiene_descuento,
                    'porcentaje_descuento' => $servicio->porcentaje_descuento
                );
            }

            wp_send_json_success($calendar_data);
            exit;
        } catch (Exception $e) {
            error_log('ERROR EXCEPTION: ' . $e->getMessage());
            wp_send_json_error('Error: ' . $e->getMessage());
            exit;
        }
    }

    public function save_service() {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_die('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || $_SESSION['reservas_user']['role'] !== 'super_admin') {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        $fecha = sanitize_text_field($_POST['fecha']);
        $hora = sanitize_text_field($_POST['hora']);
        $plazas_totales = intval($_POST['plazas_totales']);
        $precio_adulto = floatval($_POST['precio_adulto']);
        $precio_nino = floatval($_POST['precio_nino']);
        $precio_residente = floatval($_POST['precio_residente']);
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        
        // Campos de descuento
        $tiene_descuento = isset($_POST['tiene_descuento']) ? 1 : 0;
        $porcentaje_descuento = floatval($_POST['porcentaje_descuento']) ?: 0;

        // ✅ VALIDAR DÍAS DE ANTICIPACIÓN MÍNIMA
        if (!class_exists('ReservasConfigurationAdmin')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-configuration-admin.php';
        }
        
        $dias_anticipacion = ReservasConfigurationAdmin::get_dias_anticipacion_minima();
        $fecha_minima = date('Y-m-d', strtotime("+$dias_anticipacion days"));
        
        if ($fecha < $fecha_minima) {
            wp_send_json_error("No se puede crear servicios para fechas anteriores a $fecha_minima (mínimo $dias_anticipacion días de anticipación)");
        }

        // Validar que no exista ya un servicio en esa fecha y hora
        if ($service_id == 0) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE fecha = %s AND hora = %s",
                $fecha,
                $hora
            ));

            if ($existing > 0) {
                wp_send_json_error('Ya existe un servicio en esa fecha y hora');
            }
        }

        $data = array(
            'fecha' => $fecha,
            'hora' => $hora,
            'plazas_totales' => $plazas_totales,
            'plazas_disponibles' => $plazas_totales,
            'precio_adulto' => $precio_adulto,
            'precio_nino' => $precio_nino,
            'precio_residente' => $precio_residente,
            'tiene_descuento' => $tiene_descuento,
            'porcentaje_descuento' => $porcentaje_descuento,
            'status' => 'active'
        );

        if ($service_id > 0) {
            // Actualizar
            $result = $wpdb->update($table_name, $data, array('id' => $service_id));
        } else {
            // Insertar
            $result = $wpdb->insert($table_name, $data);
        }

        if ($result !== false) {
            wp_send_json_success('Servicio guardado correctamente');
        } else {
            wp_send_json_error('Error al guardar el servicio: ' . $wpdb->last_error);
        }
    }

    public function delete_service() {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_die('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || $_SESSION['reservas_user']['role'] !== 'super_admin') {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        $service_id = intval($_POST['service_id']);

        $result = $wpdb->delete($table_name, array('id' => $service_id));

        if ($result !== false) {
            wp_send_json_success('Servicio eliminado correctamente');
        } else {
            wp_send_json_error('Error al eliminar el servicio');
        }
    }

    public function get_service_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_die('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || $_SESSION['reservas_user']['role'] !== 'super_admin') {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        $service_id = intval($_POST['service_id']);

        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $service_id
        ));

        if ($servicio) {
            wp_send_json_success($servicio);
        } else {
            wp_send_json_error('Servicio no encontrado');
        }
    }

    public function bulk_add_services() {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_die('Error de seguridad');
        }

        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user']) || $_SESSION['reservas_user']['role'] !== 'super_admin') {
            wp_send_json_error('Sin permisos');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        $fecha_inicio = sanitize_text_field($_POST['fecha_inicio']);
        $fecha_fin = sanitize_text_field($_POST['fecha_fin']);
        $horarios = json_decode(stripslashes($_POST['horarios']), true);
        $plazas_totales = intval($_POST['plazas_totales']);
        $precio_adulto = floatval($_POST['precio_adulto']);
        $precio_nino = floatval($_POST['precio_nino']);
        $precio_residente = floatval($_POST['precio_residente']);
        $dias_semana = isset($_POST['dias_semana']) ? $_POST['dias_semana'] : array();
        
        // Campos de descuento para bulk
        $tiene_descuento = isset($_POST['bulk_tiene_descuento']) ? 1 : 0;
        $porcentaje_descuento = floatval($_POST['bulk_porcentaje_descuento']) ?: 0;

        // ✅ VALIDAR DÍAS DE ANTICIPACIÓN MÍNIMA PARA BULK
        if (!class_exists('ReservasConfigurationAdmin')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-configuration-admin.php';
        }
        
        $dias_anticipacion = ReservasConfigurationAdmin::get_dias_anticipacion_minima();
        $fecha_minima = date('Y-m-d', strtotime("+$dias_anticipacion days"));
        
        if ($fecha_inicio < $fecha_minima) {
            wp_send_json_error("La fecha de inicio no puede ser anterior a $fecha_minima (mínimo $dias_anticipacion días de anticipación)");
        }

        $fecha_actual = strtotime($fecha_inicio);
        $fecha_limite = strtotime($fecha_fin);
        $servicios_creados = 0;
        $servicios_existentes = 0;
        $servicios_bloqueados = 0;
        $errores = 0;

        while ($fecha_actual <= $fecha_limite) {
            $fecha_str = date('Y-m-d', $fecha_actual);
            $dia_semana = date('w', $fecha_actual);

            // ✅ VERIFICAR DÍAS DE ANTICIPACIÓN PARA CADA FECHA
            if ($fecha_str < $fecha_minima) {
                $servicios_bloqueados++;
                $fecha_actual = strtotime('+1 day', $fecha_actual);
                continue;
            }

            if (empty($dias_semana) || in_array($dia_semana, $dias_semana)) {

                foreach ($horarios as $horario) {
                    $hora = sanitize_text_field($horario['hora']);

                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE fecha = %s AND hora = %s",
                        $fecha_str,
                        $hora
                    ));

                    if ($existing == 0) {
                        $result = $wpdb->insert($table_name, array(
                            'fecha' => $fecha_str,
                            'hora' => $hora,
                            'plazas_totales' => $plazas_totales,
                            'plazas_disponibles' => $plazas_totales,
                            'precio_adulto' => $precio_adulto,
                            'precio_nino' => $precio_nino,
                            'precio_residente' => $precio_residente,
                            'tiene_descuento' => $tiene_descuento,
                            'porcentaje_descuento' => $porcentaje_descuento,
                            'status' => 'active'
                        ));

                        if ($result !== false) {
                            $servicios_creados++;
                        } else {
                            $errores++;
                            error_log("Error insertando servicio: " . $wpdb->last_error);
                        }
                    } else {
                        $servicios_existentes++;
                    }
                }
            }

            $fecha_actual = strtotime('+1 day', $fecha_actual);
        }

        $mensaje = "Se crearon $servicios_creados servicios.";
        if ($servicios_existentes > 0) {
            $mensaje .= " $servicios_existentes ya existían.";
        }
        if ($servicios_bloqueados > 0) {
            $mensaje .= " $servicios_bloqueados fueron bloqueados por días de anticipación.";
        }
        if ($errores > 0) {
            $mensaje .= " Hubo $errores errores.";
        }

        wp_send_json_success(array(
            'creados' => $servicios_creados,
            'existentes' => $servicios_existentes,
            'bloqueados' => $servicios_bloqueados,
            'errores' => $errores,
            'mensaje' => $mensaje
        ));
    }
}