<?php

/**
 * Clase para procesar reservas - VERSIÓN CON DEBUG MEJORADO
 * Archivo: wp-content/plugins/sistema-reservas/includes/class-reservas-processor.php
 */

class ReservasProcessor
{

    public function __construct()
    {
        // Hooks AJAX para procesar reservas
        add_action('wp_ajax_process_reservation', array($this, 'process_reservation'));
        add_action('wp_ajax_nopriv_process_reservation', array($this, 'process_reservation'));
    }

    /**
     * Procesar una nueva reserva - CON DEBUG MEJORADO
     */
    public function process_reservation()
    {
        // Limpiar cualquier output buffer que pueda interferir
        if (ob_get_level()) {
            ob_clean();
        }

        // Headers para asegurar JSON correcto
        header('Content-Type: application/json');

        try {
            error_log('=== INICIANDO PROCESS_RESERVATION ===');
            error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
            error_log('POST data: ' . print_r($_POST, true));

            // Verificar que es una petición POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                error_log('ERROR: No es petición POST');
                wp_send_json_error('Método no permitido');
                return;
            }

            // Verificar que tenemos datos POST
            if (empty($_POST)) {
                error_log('ERROR: POST está vacío');
                wp_send_json_error('No hay datos POST');
                return;
            }

            // Verificar nonce
            if (!isset($_POST['nonce'])) {
                error_log('ERROR: No hay nonce en la petición');
                wp_send_json_error('Falta nonce de seguridad');
                return;
            }

            if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
                error_log('ERROR: Nonce inválido - Recibido: ' . $_POST['nonce']);
                error_log('ERROR: Nonce esperado: ' . wp_create_nonce('reservas_nonce'));
                wp_send_json_error('Error de seguridad - nonce inválido');
                return;
            }

            error_log('SUCCESS: Nonce verificado correctamente');

            // Verificar que tenemos la acción correcta
            if (!isset($_POST['action']) || $_POST['action'] !== 'process_reservation') {
                error_log('ERROR: Acción incorrecta - Recibida: ' . ($_POST['action'] ?? 'ninguna'));
                wp_send_json_error('Acción incorrecta');
                return;
            }

            // Validar y sanitizar datos del formulario
            $datos_personales = $this->validar_datos_personales();
            if (!$datos_personales['valido']) {
                error_log('ERROR: Datos personales inválidos - ' . $datos_personales['error']);
                wp_send_json_error($datos_personales['error']);
                return;
            }
            error_log('SUCCESS: Datos personales validados');

            // Validar y sanitizar datos de la reserva
            $datos_reserva = $this->validar_datos_reserva();
            if (!$datos_reserva['valido']) {
                error_log('ERROR: Datos de reserva inválidos - ' . $datos_reserva['error']);
                wp_send_json_error($datos_reserva['error']);
                return;
            }
            error_log('SUCCESS: Datos de reserva validados');

            // Verificar disponibilidad del servicio
            $servicio = $this->verificar_disponibilidad($datos_reserva['datos']['service_id'], $datos_reserva['datos']['total_personas']);
            if (!$servicio['disponible']) {
                error_log('ERROR: Servicio no disponible - ' . $servicio['error']);
                wp_send_json_error($servicio['error']);
                return;
            }
            error_log('SUCCESS: Servicio disponible');

            // Recalcular precios
            $calculo_precio = $this->recalcular_precio($datos_reserva['datos']);
            if (!$calculo_precio['valido']) {
                error_log('ERROR: Error en cálculo de precio - ' . $calculo_precio['error']);
                wp_send_json_error($calculo_precio['error']);
                return;
            }
            error_log('SUCCESS: Precio calculado');

            // Crear la reserva en la base de datos
            $resultado_reserva = $this->crear_reserva($datos_personales['datos'], $datos_reserva['datos'], $calculo_precio['precio']);
            if (!$resultado_reserva['exito']) {
                error_log('ERROR: Error creando reserva - ' . $resultado_reserva['error']);
                wp_send_json_error($resultado_reserva['error']);
                return;
            }
            error_log('SUCCESS: Reserva creada con ID: ' . $resultado_reserva['reserva_id']);

            // Actualizar plazas disponibles
            $actualizacion = $this->actualizar_plazas_disponibles($datos_reserva['datos']['service_id'], $datos_reserva['datos']['total_personas']);
            if (!$actualizacion['exito']) {
                error_log('ERROR: Error actualizando plazas - ' . $actualizacion['error']);
                // Si falla la actualización, eliminar la reserva creada
                $this->eliminar_reserva($resultado_reserva['reserva_id']);
                wp_send_json_error('Error actualizando disponibilidad. Reserva cancelada.');
                return;
            }
            error_log('SUCCESS: Plazas actualizadas');

            // Respuesta exitosa
            $response_data = array(
                'mensaje' => 'Reserva procesada correctamente',
                'localizador' => $resultado_reserva['localizador'],
                'reserva_id' => $resultado_reserva['reserva_id'],
                'detalles' => array(
                    'fecha' => $datos_reserva['datos']['fecha'],
                    'hora' => $datos_reserva['datos']['hora_ida'],
                    'personas' => $datos_reserva['datos']['total_personas'],
                    'precio_final' => $calculo_precio['precio']['precio_final']
                )
            );

            error_log('SUCCESS: Respuesta preparada - ' . print_r($response_data, true));
            wp_send_json_success($response_data);
        } catch (Exception $e) {
            error_log('EXCEPTION: ' . $e->getMessage());
            error_log('STACK TRACE: ' . $e->getTraceAsString());
            wp_send_json_error('Error interno del servidor: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('FATAL ERROR: ' . $e->getMessage());
            error_log('STACK TRACE: ' . $e->getTraceAsString());
            wp_send_json_error('Error fatal del servidor: ' . $e->getMessage());
        }
    }

    /**
     * Validar datos personales del formulario
     */
    private function validar_datos_personales()
    {
        error_log('=== VALIDANDO DATOS PERSONALES ===');

        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $apellidos = sanitize_text_field($_POST['apellidos'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $telefono = sanitize_text_field($_POST['telefono'] ?? '');

        error_log("Datos recibidos - Nombre: '$nombre', Apellidos: '$apellidos', Email: '$email', Teléfono: '$telefono'");

        // Validaciones
        if (empty($nombre) || strlen($nombre) < 2) {
            return array('valido' => false, 'error' => 'El nombre es obligatorio (mínimo 2 caracteres)');
        }

        if (empty($apellidos) || strlen($apellidos) < 2) {
            return array('valido' => false, 'error' => 'Los apellidos son obligatorios (mínimo 2 caracteres)');
        }

        if (empty($email) || !is_email($email)) {
            return array('valido' => false, 'error' => 'Email no válido');
        }

        if (empty($telefono) || strlen($telefono) < 9) {
            return array('valido' => false, 'error' => 'Teléfono no válido (mínimo 9 dígitos)');
        }

        return array(
            'valido' => true,
            'datos' => array(
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'email' => $email,
                'telefono' => $telefono
            )
        );
    }

    /**
     * Validar datos de reserva desde sessionStorage
     */
    private function validar_datos_reserva()
    {
        error_log('=== VALIDANDO DATOS DE RESERVA ===');

        // Verificar que tenemos los datos de reserva
        if (!isset($_POST['reservation_data'])) {
            return array('valido' => false, 'error' => 'Faltan datos de reserva');
        }

        // Decodificar datos de reserva
        $reserva_data_json = stripslashes($_POST['reservation_data']);
        error_log('JSON recibido: ' . $reserva_data_json);

        $reserva_data = json_decode($reserva_data_json, true);

        if (!$reserva_data || json_last_error() !== JSON_ERROR_NONE) {
            error_log('ERROR JSON: ' . json_last_error_msg());
            return array('valido' => false, 'error' => 'Datos de reserva no válidos - JSON corrupto');
        }

        error_log('Datos de reserva decodificados: ' . print_r($reserva_data, true));

        // Validar campos obligatorios
        $campos_requeridos = ['fecha', 'service_id', 'hora_ida', 'adultos', 'residentes', 'ninos_5_12', 'ninos_menores'];
        foreach ($campos_requeridos as $campo) {
            if (!isset($reserva_data[$campo])) {
                return array('valido' => false, 'error' => "Campo '$campo' faltante en datos de reserva");
            }
        }

        // Calcular total de personas que ocupan plaza
        $adultos = intval($reserva_data['adultos']);
        $residentes = intval($reserva_data['residentes']);
        $ninos_5_12 = intval($reserva_data['ninos_5_12']);
        $ninos_menores = intval($reserva_data['ninos_menores']);
        $total_personas = $adultos + $residentes + $ninos_5_12; // Los menores de 5 no ocupan plaza

        error_log("Personas calculadas - Adultos: $adultos, Residentes: $residentes, Niños 5-12: $ninos_5_12, Menores: $ninos_menores, Total con plaza: $total_personas");

        if ($total_personas <= 0) {
            return array('valido' => false, 'error' => 'Debe haber al menos una persona que ocupe plaza');
        }

        if ($ninos_5_12 > 0 && ($adultos + $residentes) <= 0) {
            return array('valido' => false, 'error' => 'Debe haber al menos un adulto si hay niños');
        }

        // Agregar totales calculados
        $reserva_data['total_personas'] = $total_personas;
        $reserva_data['total_viajeros'] = $adultos + $residentes + $ninos_5_12 + $ninos_menores;

        return array('valido' => true, 'datos' => $reserva_data);
    }

    /**
     * Verificar disponibilidad del servicio
     */
    private function verificar_disponibilidad($service_id, $personas_necesarias)
    {
        error_log('=== VERIFICANDO DISPONIBILIDAD ===');
        error_log("Service ID: $service_id, Personas necesarias: $personas_necesarias");

        global $wpdb;

        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_servicios WHERE id = %d AND status = 'active'",
            $service_id
        ));

        if (!$servicio) {
            return array('disponible' => false, 'error' => 'Servicio no encontrado');
        }

        error_log('Servicio encontrado: ' . print_r($servicio, true));

        if ($servicio->plazas_disponibles < $personas_necesarias) {
            return array(
                'disponible' => false,
                'error' => "Solo quedan {$servicio->plazas_disponibles} plazas disponibles, necesitas {$personas_necesarias}"
            );
        }

        // Verificar que la fecha no sea pasada
        $fecha_servicio = strtotime($servicio->fecha);
        $hoy = strtotime(date('Y-m-d'));

        if ($fecha_servicio <= $hoy) {
            return array('disponible' => false, 'error' => 'No se puede reservar para fechas pasadas o de hoy');
        }

        return array('disponible' => true, 'servicio' => $servicio);
    }

    /**
     * Recalcular precio para verificar - VERSIÓN ARREGLADA
     */
    private function recalcular_precio($datos_reserva)
    {
        error_log('=== RECALCULANDO PRECIO ===');

        global $wpdb;

        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_servicios WHERE id = %d",
            $datos_reserva['service_id']
        ));

        if (!$servicio) {
            return array('valido' => false, 'error' => 'Servicio no encontrado para cálculo');
        }

        $adultos = intval($datos_reserva['adultos']);
        $residentes = intval($datos_reserva['residentes']);
        $ninos_5_12 = intval($datos_reserva['ninos_5_12']);
        $ninos_menores = intval($datos_reserva['ninos_menores']);

        // ✅ CALCULAR TOTAL DE PERSONAS QUE OCUPAN PLAZA
        $total_personas_con_plaza = $adultos + $residentes + $ninos_5_12;
        // Los niños menores de 5 años NO ocupan plaza

        error_log("Personas calculadas - Adultos: $adultos, Residentes: $residentes, Niños 5-12: $ninos_5_12, Menores: $ninos_menores");
        error_log("Total personas con plaza: $total_personas_con_plaza");

        // ✅ CALCULAR PRECIO BASE (todos empiezan pagando precio de adulto)
        $precio_base = 0;
        $precio_base += $adultos * $servicio->precio_adulto;
        $precio_base += $residentes * $servicio->precio_adulto;
        $precio_base += $ninos_5_12 * $servicio->precio_adulto;
        // Los niños menores NO se suman al precio base

        error_log("Precio base calculado: $precio_base");

        // ✅ CALCULAR DESCUENTOS INDIVIDUALES
        $descuento_total = 0;

        // Descuento residentes (diferencia entre precio adulto y residente)
        $descuento_residentes = $residentes * ($servicio->precio_adulto - $servicio->precio_residente);
        $descuento_total += $descuento_residentes;
        error_log("Descuento residentes: $descuento_residentes");

        // Descuento niños (diferencia entre precio adulto y niño)
        $descuento_ninos = $ninos_5_12 * ($servicio->precio_adulto - $servicio->precio_nino);
        $descuento_total += $descuento_ninos;
        error_log("Descuento niños: $descuento_ninos");

        // ✅ CALCULAR DESCUENTO POR GRUPO (solo si hay suficientes personas)
        $descuento_grupo = 0;
        $regla_aplicada = null;

        if ($total_personas_con_plaza > 0) {
            if (!class_exists('ReservasDiscountsAdmin')) {
                require_once RESERVAS_PLUGIN_PATH . 'includes/class-discounts-admin.php';
            }

            // Calcular subtotal después de descuentos individuales
            $subtotal = $precio_base - $descuento_total;
            error_log("Subtotal antes de descuento por grupo: $subtotal");

            $discount_info = ReservasDiscountsAdmin::calculate_discount($total_personas_con_plaza, $subtotal, 'total');

            error_log("Información de descuento por grupo: " . print_r($discount_info, true));

            if ($discount_info['discount_applied']) {
                $descuento_grupo = $discount_info['discount_amount'];
                $descuento_total += $descuento_grupo;
                $regla_aplicada = $discount_info;
                error_log("✅ Descuento por grupo aplicado: $descuento_grupo");
            } else {
                error_log("❌ No se aplicó descuento por grupo (insuficientes personas: $total_personas_con_plaza)");
            }
        }

        // ✅ DESCUENTO ESPECÍFICO DEL SERVICIO
        $descuento_servicio = 0;
        if ($servicio->tiene_descuento && floatval($servicio->porcentaje_descuento) > 0) {
            $subtotal_actual = $precio_base - $descuento_total;
            $descuento_servicio = ($subtotal_actual * floatval($servicio->porcentaje_descuento)) / 100;
            $descuento_total += $descuento_servicio;
            error_log("Descuento del servicio: $descuento_servicio");
        }

        // ✅ PRECIO FINAL
        $precio_final = $precio_base - $descuento_total;
        if ($precio_final < 0) $precio_final = 0;

        $precio_info = array(
            'precio_base' => round($precio_base, 2),
            'descuento_total' => round($descuento_total, 2),
            'descuento_residentes' => round($descuento_residentes, 2),
            'descuento_ninos' => round($descuento_ninos, 2),
            'descuento_grupo' => round($descuento_grupo, 2),
            'descuento_servicio' => round($descuento_servicio, 2),
            'precio_final' => round($precio_final, 2),
            'regla_descuento_aplicada' => $regla_aplicada,
            'total_personas_con_plaza' => $total_personas_con_plaza
        );

        error_log('Precio calculado final: ' . print_r($precio_info, true));

        return array('valido' => true, 'precio' => $precio_info);
    }

    /**
     * Crear reserva en la base de datos
     */
    private function crear_reserva($datos_personales, $datos_reserva, $calculo_precio)
    {
        error_log('=== CREANDO RESERVA ===');

        global $wpdb;

        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_reservas'") != $table_reservas) {
            return array('exito' => false, 'error' => 'Tabla de reservas no existe');
        }

        // Generar localizador único
        $localizador = $this->generar_localizador();
        error_log('Localizador generado: ' . $localizador);

        // Preparar datos para insertar
        $reserva_data = array(
            'localizador' => $localizador,
            'servicio_id' => $datos_reserva['service_id'],
            'fecha' => $datos_reserva['fecha'],
            'hora' => $datos_reserva['hora_ida'],
            'nombre' => $datos_personales['nombre'],
            'apellidos' => $datos_personales['apellidos'],
            'email' => $datos_personales['email'],
            'telefono' => $datos_personales['telefono'],
            'adultos' => $datos_reserva['adultos'],
            'residentes' => $datos_reserva['residentes'],
            'ninos_5_12' => $datos_reserva['ninos_5_12'],
            'ninos_menores' => $datos_reserva['ninos_menores'],
            'total_personas' => $datos_reserva['total_personas'],
            'precio_base' => $calculo_precio['precio_base'],
            'descuento_total' => $calculo_precio['descuento_total'],
            'precio_final' => $calculo_precio['precio_final'],
            'regla_descuento_aplicada' => $calculo_precio['regla_descuento_aplicada'] ? json_encode($calculo_precio['regla_descuento_aplicada']) : null,
            'estado' => 'confirmada',
            'metodo_pago' => 'simulado'
        );

        error_log('Datos de reserva a insertar: ' . print_r($reserva_data, true));

        $resultado = $wpdb->insert($table_reservas, $reserva_data);

        if ($resultado === false) {
            error_log('ERROR DB: ' . $wpdb->last_error);
            error_log('QUERY: ' . $wpdb->last_query);
            return array('exito' => false, 'error' => 'Error guardando la reserva: ' . $wpdb->last_error);
        }

        $reserva_id = $wpdb->insert_id;
        error_log('Reserva insertada con ID: ' . $reserva_id);

        return array(
            'exito' => true,
            'reserva_id' => $reserva_id,
            'localizador' => $localizador
        );
    }

    /**
     * Actualizar plazas disponibles del servicio
     */
    private function actualizar_plazas_disponibles($service_id, $personas_ocupadas)
    {
        error_log('=== ACTUALIZANDO PLAZAS DISPONIBLES ===');
        error_log("Service ID: $service_id, Personas: $personas_ocupadas");

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

        error_log('Query ejecutada: ' . $wpdb->last_query);
        error_log('Filas afectadas: ' . $resultado);

        if ($resultado === false) {
            error_log('ERROR actualizando plazas: ' . $wpdb->last_error);
            return array('exito' => false, 'error' => 'Error actualizando plazas disponibles');
        }

        if ($resultado === 0) {
            error_log('ERROR: No hay suficientes plazas disponibles');
            return array('exito' => false, 'error' => 'No hay suficientes plazas disponibles');
        }

        return array('exito' => true);
    }

    /**
     * Eliminar reserva (en caso de error)
     */
    private function eliminar_reserva($reserva_id)
    {
        error_log('=== ELIMINANDO RESERVA POR ERROR ===');
        error_log('ID de reserva a eliminar: ' . $reserva_id);

        global $wpdb;

        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        $wpdb->delete($table_reservas, array('id' => $reserva_id));
        error_log('Reserva eliminada');
    }

    /**
     * Generar localizador único
     */
    private function generar_localizador()
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
