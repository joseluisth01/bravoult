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
     if (ob_get_level()) {
        ob_clean();
    }

    // Headers para JSON
    header('Content-Type: application/json');

    try {
        // ✅ VERIFICACIÓN DE NONCE MÁS PERMISIVA
        if (!isset($_POST['nonce'])) {
            wp_send_json_error('Nonce no proporcionado');
            exit;
        }

        // ✅ VERIFICAR NONCE CON MEJOR MANEJO DE ERRORES
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'reservas_nonce');
        if (!$nonce_valid) {
            error_log('NONCE INVÁLIDO: ' . $_POST['nonce']);
            wp_send_json_error('Error de seguridad - nonce inválido');
            exit;
        }

        // ✅ MEJORAR VERIFICACIÓN DE SESIÓN
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user'])) {
            error_log('SESIÓN NO ENCONTRADA');
            wp_send_json_error('Usuario no logueado - por favor inicia sesión nuevamente');
            exit;
        }

        // ✅ VERIFICAR PERMISOS DEL USUARIO
        $user_role = $_SESSION['reservas_user']['role'];
        if (!in_array($user_role, ['super_admin', 'admin'])) {
            wp_send_json_error('Sin permisos para acceder al calendario');
            exit;
        }

            // Obtener datos reales de la base de datos
            global $wpdb;
            $table_name = $wpdb->prefix . 'reservas_servicios';

            $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
            $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

            error_log("CALENDAR: Consultando mes $month del año $year");

            // Calcular primer y último día del mes
            $first_day = sprintf('%04d-%02d-01', $year, $month);
            $last_day = date('Y-m-t', strtotime($first_day));

            // ✅ VERIFICAR QUE LA TABLA EXISTE
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            if (!$table_exists) {
                error_log('CALENDAR: Tabla de servicios no existe');
                wp_send_json_error('Tabla de servicios no encontrada');
                exit;
            }

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

            if ($wpdb->last_error) {
                error_log('CALENDAR: Error SQL: ' . $wpdb->last_error);
                wp_send_json_error('Error en consulta SQL: ' . $wpdb->last_error);
                exit;
            }

            error_log('CALENDAR: Encontrados ' . count($servicios) . ' servicios');

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

            error_log('CALENDAR: Datos organizados correctamente');
            wp_send_json_success($calendar_data);
            exit;
            
        } catch (Exception $e) {
            error_log('CALENDAR EXCEPTION: ' . $e->getMessage());
            wp_send_json_error('Error interno: ' . $e->getMessage());
            exit;
        }
    }

    public function save_service() {
        // ✅ VALIDACIÓN MEJORADA
        if (!ReservasAuth::verify_ajax_nonce()) {
            return;
        }
        
        ReservasAuth::require_permission('super_admin');

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        try {
            $fecha = sanitize_text_field($_POST['fecha'] ?? '');
            $hora = sanitize_text_field($_POST['hora'] ?? '');
            $plazas_totales = intval($_POST['plazas_totales'] ?? 0);
            $precio_adulto = floatval($_POST['precio_adulto'] ?? 0);
            $precio_nino = floatval($_POST['precio_nino'] ?? 0);
            $precio_residente = floatval($_POST['precio_residente'] ?? 0);
            $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
            
            // Campos de descuento
            $tiene_descuento = isset($_POST['tiene_descuento']) ? 1 : 0;
            $porcentaje_descuento = floatval($_POST['porcentaje_descuento'] ?? 0);

            // Validaciones básicas
            if (empty($fecha) || empty($hora) || $plazas_totales <= 0) {
                wp_send_json_error('Datos incompletos o inválidos');
                return;
            }

            // ✅ VALIDAR DÍAS DE ANTICIPACIÓN MÍNIMA
            if (!class_exists('ReservasConfigurationAdmin')) {
                require_once RESERVAS_PLUGIN_PATH . 'includes/class-configuration-admin.php';
            }
            
            $dias_anticipacion = ReservasConfigurationAdmin::get_dias_anticipacion_minima();
            $fecha_minima = date('Y-m-d', strtotime("+$dias_anticipacion days"));
            
            if ($fecha < $fecha_minima) {
                wp_send_json_error("No se puede crear servicios para fechas anteriores a $fecha_minima (mínimo $dias_anticipacion días de anticipación)");
                return;
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
                    return;
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
                error_log('CALENDAR: Error SQL al guardar: ' . $wpdb->last_error);
                wp_send_json_error('Error al guardar el servicio: ' . $wpdb->last_error);
            }
            
        } catch (Exception $e) {
            error_log('CALENDAR: Exception en save_service: ' . $e->getMessage());
            wp_send_json_error('Error interno: ' . $e->getMessage());
        }
    }

    public function delete_service() {
        if (!ReservasAuth::verify_ajax_nonce()) {
            return;
        }
        
        ReservasAuth::require_permission('super_admin');

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        try {
            $service_id = intval($_POST['service_id'] ?? 0);
            
            if ($service_id <= 0) {
                wp_send_json_error('ID de servicio inválido');
                return;
            }

            $result = $wpdb->delete($table_name, array('id' => $service_id));

            if ($result !== false) {
                wp_send_json_success('Servicio eliminado correctamente');
            } else {
                wp_send_json_error('Error al eliminar el servicio: ' . $wpdb->last_error);
            }
            
        } catch (Exception $e) {
            error_log('CALENDAR: Exception en delete_service: ' . $e->getMessage());
            wp_send_json_error('Error interno: ' . $e->getMessage());
        }
    }

    public function get_service_details() {
        if (!ReservasAuth::verify_ajax_nonce()) {
            return;
        }
        
        ReservasAuth::require_permission('super_admin');

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        try {
            $service_id = intval($_POST['service_id'] ?? 0);
            
            if ($service_id <= 0) {
                wp_send_json_error('ID de servicio inválido');
                return;
            }

            $servicio = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $service_id
            ));

            if ($servicio) {
                wp_send_json_success($servicio);
            } else {
                wp_send_json_error('Servicio no encontrado');
            }
            
        } catch (Exception $e) {
            error_log('CALENDAR: Exception en get_service_details: ' . $e->getMessage());
            wp_send_json_error('Error interno: ' . $e->getMessage());
        }
    }

    public function bulk_add_services() {
        if (!ReservasAuth::verify_ajax_nonce()) {
            return;
        }
        
        ReservasAuth::require_permission('super_admin');

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        try {
            $fecha_inicio = sanitize_text_field($_POST['fecha_inicio'] ?? '');
            $fecha_fin = sanitize_text_field($_POST['fecha_fin'] ?? '');
            $horarios_json = stripslashes($_POST['horarios'] ?? '[]');
            $horarios = json_decode($horarios_json, true);
            $plazas_totales = intval($_POST['plazas_totales'] ?? 0);
            $precio_adulto = floatval($_POST['precio_adulto'] ?? 0);
            $precio_nino = floatval($_POST['precio_nino'] ?? 0);
            $precio_residente = floatval($_POST['precio_residente'] ?? 0);
            $dias_semana = isset($_POST['dias_semana']) ? $_POST['dias_semana'] : array();
            
            // Campos de descuento para bulk
            $tiene_descuento = isset($_POST['bulk_tiene_descuento']) ? 1 : 0;
            $porcentaje_descuento = floatval($_POST['bulk_porcentaje_descuento'] ?? 0);

            // Validaciones
            if (empty($fecha_inicio) || empty($fecha_fin) || empty($horarios) || $plazas_totales <= 0) {
                wp_send_json_error('Datos incompletos para creación masiva');
                return;
            }

            // ✅ VALIDAR DÍAS DE ANTICIPACIÓN MÍNIMA PARA BULK
            if (!class_exists('ReservasConfigurationAdmin')) {
                require_once RESERVAS_PLUGIN_PATH . 'includes/class-configuration-admin.php';
            }
            
            $dias_anticipacion = ReservasConfigurationAdmin::get_dias_anticipacion_minima();
            $fecha_minima = date('Y-m-d', strtotime("+$dias_anticipacion days"));
            
            if ($fecha_inicio < $fecha_minima) {
                wp_send_json_error("La fecha de inicio no puede ser anterior a $fecha_minima (mínimo $dias_anticipacion días de anticipación)");
                return;
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
                        $hora = sanitize_text_field($horario['hora'] ?? '');
                        
                        if (empty($hora)) {
                            continue;
                        }

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
                                error_log("CALENDAR: Error insertando servicio bulk: " . $wpdb->last_error);
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
            
        } catch (Exception $e) {
            error_log('CALENDAR: Exception en bulk_add_services: ' . $e->getMessage());
            wp_send_json_error('Error interno: ' . $e->getMessage());
        }
    }
}