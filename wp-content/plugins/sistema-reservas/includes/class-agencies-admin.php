<?php

/**
 * Clase para gestionar las agencias del sistema de reservas
 * Archivo: wp-content/plugins/sistema-reservas/includes/class-agencies-admin.php
 */
class ReservasAgenciesAdmin
{

    public function __construct()
    {
        // Hooks AJAX para gestión de agencias
        add_action('wp_ajax_get_agencies_list', array($this, 'get_agencies_list'));
        add_action('wp_ajax_nopriv_get_agencies_list', array($this, 'get_agencies_list'));

        add_action('wp_ajax_save_agency', array($this, 'save_agency'));
        add_action('wp_ajax_nopriv_save_agency', array($this, 'save_agency'));

        add_action('wp_ajax_delete_agency', array($this, 'delete_agency'));
        add_action('wp_ajax_nopriv_delete_agency', array($this, 'delete_agency'));

        add_action('wp_ajax_get_agency_details', array($this, 'get_agency_details'));
        add_action('wp_ajax_nopriv_get_agency_details', array($this, 'get_agency_details'));

        add_action('wp_ajax_toggle_agency_status', array($this, 'toggle_agency_status'));
        add_action('wp_ajax_nopriv_toggle_agency_status', array($this, 'toggle_agency_status'));

        // Hook para crear tabla
        add_action('init', array($this, 'maybe_create_table'));
    }

    /**
     * Crear tabla de agencias si no existe
     */
    public function maybe_create_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reservas_agencies';

        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

        if (!$table_exists) {
            $this->create_agencies_table();
        }
    }

    /**
     * Crear tabla de agencias
     */
    private function create_agencies_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reservas_agencies';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            agency_name varchar(100) NOT NULL,
            contact_person varchar(100) NOT NULL,
            email varchar(100) NOT NULL UNIQUE,
            phone varchar(20),
            address text,
            username varchar(50) NOT NULL UNIQUE,
            password varchar(255) NOT NULL,
            commission_percentage decimal(5,2) DEFAULT 0.00,
            max_credit_limit decimal(10,2) DEFAULT 0.00,
            current_balance decimal(10,2) DEFAULT 0.00,
            status enum('active', 'inactive', 'suspended') DEFAULT 'active',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY username (username),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        error_log('✅ Tabla de agencias creada correctamente');
    }

    /**
     * Obtener lista de agencias
     */
    public function get_agencies_list()
    {
        error_log('=== AGENCIES LIST AJAX REQUEST START ===');
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
            if ($user['role'] !== 'super_admin') {
                wp_send_json_error('Sin permisos para gestionar agencias');
                return;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'reservas_agencies';

            $agencies = $wpdb->get_results(
                "SELECT id, agency_name, contact_person, email, phone, username, 
                        commission_percentage, max_credit_limit, current_balance, 
                        status, created_at, updated_at 
                 FROM $table_name 
                 ORDER BY agency_name ASC"
            );

            if ($wpdb->last_error) {
                error_log('❌ Database error in agencies list: ' . $wpdb->last_error);
                die(json_encode(['success' => false, 'data' => 'Database error: ' . $wpdb->last_error]));
            }

            error_log('✅ Found ' . count($agencies) . ' agencies');
            die(json_encode(['success' => true, 'data' => $agencies]));

        } catch (Exception $e) {
            error_log('❌ AGENCIES LIST EXCEPTION: ' . $e->getMessage());
            die(json_encode(['success' => false, 'data' => 'Server error: ' . $e->getMessage()]));
        }
    }

    /**
     * Guardar agencia (crear o actualizar)
     */
    public function save_agency()
    {
        error_log('=== SAVE AGENCY AJAX REQUEST START ===');
        header('Content-Type: application/json');

        try {
            if (!session_id()) {
                session_start();
            }

            if (!isset($_SESSION['reservas_user'])) {
                wp_send_json_error('Sesión expirada. Recarga la página e inicia sesión nuevamente.');
                return;
            }

            $user = $_SESSION['reservas_user'];
            if ($user['role'] !== 'super_admin') {
                wp_send_json_error('Sin permisos para gestionar agencias');
                return;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'reservas_agencies';

            // Sanitizar datos
            $agency_id = isset($_POST['agency_id']) ? intval($_POST['agency_id']) : 0;
            $agency_name = sanitize_text_field($_POST['agency_name']);
            $contact_person = sanitize_text_field($_POST['contact_person']);
            $email = sanitize_email($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            $address = sanitize_textarea_field($_POST['address']);
            $username = sanitize_text_field($_POST['username']);
            $password = $_POST['password']; // Solo sanitizar si no está vacío
            $commission_percentage = floatval($_POST['commission_percentage']);
            $max_credit_limit = floatval($_POST['max_credit_limit']);
            $status = sanitize_text_field($_POST['status']);
            $notes = sanitize_textarea_field($_POST['notes']);

            // Validaciones
            if (empty($agency_name)) {
                wp_send_json_error('El nombre de la agencia es obligatorio');
            }

            if (empty($contact_person)) {
                wp_send_json_error('El nombre del contacto es obligatorio');
            }

            if (empty($email) || !is_email($email)) {
                wp_send_json_error('Email no válido');
            }

            if (empty($username) || strlen($username) < 3) {
                wp_send_json_error('El nombre de usuario debe tener al menos 3 caracteres');
            }

            if ($commission_percentage < 0 || $commission_percentage > 100) {
                wp_send_json_error('El porcentaje de comisión debe estar entre 0% y 100%');
            }

            if ($max_credit_limit < 0) {
                wp_send_json_error('El límite de crédito no puede ser negativo');
            }

            $valid_statuses = array('active', 'inactive', 'suspended');
            if (!in_array($status, $valid_statuses)) {
                wp_send_json_error('Estado no válido');
            }

            // Verificar duplicados
            if ($agency_id > 0) {
                // Actualización - verificar que no existan duplicados excluyendo el registro actual
                $existing_email = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE email = %s AND id != %d",
                    $email, $agency_id
                ));

                $existing_username = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE username = %s AND id != %d",
                    $username, $agency_id
                ));
            } else {
                // Creación - verificar que no existan duplicados
                $existing_email = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE email = %s",
                    $email
                ));

                $existing_username = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE username = %s",
                    $username
                ));
            }

            if ($existing_email > 0) {
                wp_send_json_error('Ya existe una agencia con ese email');
            }

            if ($existing_username > 0) {
                wp_send_json_error('Ya existe una agencia con ese nombre de usuario');
            }

            // Preparar datos para insertar/actualizar
            $data = array(
                'agency_name' => $agency_name,
                'contact_person' => $contact_person,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'username' => $username,
                'commission_percentage' => $commission_percentage,
                'max_credit_limit' => $max_credit_limit,
                'status' => $status,
                'notes' => $notes
            );

            // Manejar contraseña
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    wp_send_json_error('La contraseña debe tener al menos 6 caracteres');
                }
                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            } elseif ($agency_id == 0) {
                // Nueva agencia requiere contraseña
                wp_send_json_error('La contraseña es obligatoria para nuevas agencias');
            }

            if ($agency_id > 0) {
                // Actualizar agencia existente
                $result = $wpdb->update($table_name, $data, array('id' => $agency_id));

                if ($result !== false) {
                    wp_send_json_success('Agencia actualizada correctamente');
                } else {
                    wp_send_json_error('Error al actualizar la agencia: ' . $wpdb->last_error);
                }
            } else {
                // Crear nueva agencia
                $result = $wpdb->insert($table_name, $data);

                if ($result !== false) {
                    wp_send_json_success('Agencia creada correctamente');
                } else {
                    wp_send_json_error('Error al crear la agencia: ' . $wpdb->last_error);
                }
            }

        } catch (Exception $e) {
            error_log('❌ SAVE AGENCY EXCEPTION: ' . $e->getMessage());
            wp_send_json_error('Server error: ' . $e->getMessage());
        }
    }

    /**
     * Obtener detalles de una agencia específica
     */
    public function get_agency_details()
    {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user'])) {
            wp_send_json_error('Sesión expirada. Recarga la página e inicia sesión nuevamente.');
            return;
        }

        $user = $_SESSION['reservas_user'];
        if ($user['role'] !== 'super_admin') {
            wp_send_json_error('Sin permisos');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_agencies';

        $agency_id = intval($_POST['agency_id']);

        $agency = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $agency_id
        ));

        if ($agency) {
            // No enviar la contraseña por seguridad
            unset($agency->password);
            wp_send_json_success($agency);
        } else {
            wp_send_json_error('Agencia no encontrada');
        }
    }

    /**
     * Eliminar agencia
     */
    public function delete_agency()
    {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user'])) {
            wp_send_json_error('Sesión expirada. Recarga la página e inicia sesión nuevamente.');
            return;
        }

        $user = $_SESSION['reservas_user'];
        if ($user['role'] !== 'super_admin') {
            wp_send_json_error('Sin permisos');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_agencies';

        $agency_id = intval($_POST['agency_id']);

        // Verificar que la agencia no tenga reservas activas
        $table_reservas = $wpdb->prefix . 'reservas_reservas';
        $reservas_activas = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_reservas WHERE agency_id = %d AND estado = 'confirmada'",
            $agency_id
        ));

        if ($reservas_activas > 0) {
            wp_send_json_error('No se puede eliminar la agencia porque tiene reservas activas');
        }

        $result = $wpdb->delete($table_name, array('id' => $agency_id));

        if ($result !== false) {
            wp_send_json_success('Agencia eliminada correctamente');
        } else {
            wp_send_json_error('Error al eliminar la agencia');
        }
    }

    /**
     * Cambiar estado de una agencia
     */
    public function toggle_agency_status()
    {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['reservas_user'])) {
            wp_send_json_error('Sesión expirada. Recarga la página e inicia sesión nuevamente.');
            return;
        }

        $user = $_SESSION['reservas_user'];
        if ($user['role'] !== 'super_admin') {
            wp_send_json_error('Sin permisos');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_agencies';

        $agency_id = intval($_POST['agency_id']);
        $new_status = sanitize_text_field($_POST['new_status']);

        $valid_statuses = array('active', 'inactive', 'suspended');
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error('Estado no válido');
        }

        $result = $wpdb->update(
            $table_name,
            array('status' => $new_status),
            array('id' => $agency_id)
        );

        if ($result !== false) {
            wp_send_json_success('Estado de la agencia actualizado correctamente');
        } else {
            wp_send_json_error('Error actualizando el estado de la agencia');
        }
    }

    /**
     * Método estático para autenticar agencias
     */
    public static function authenticate_agency($username, $password)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reservas_agencies';

        $agency = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE username = %s AND status = 'active'",
            $username
        ));

        if ($agency && password_verify($password, $agency->password)) {
            return array(
                'success' => true,
                'agency' => array(
                    'id' => $agency->id,
                    'username' => $agency->username,
                    'agency_name' => $agency->agency_name,
                    'email' => $agency->email,
                    'role' => 'agencia',
                    'commission_percentage' => $agency->commission_percentage,
                    'max_credit_limit' => $agency->max_credit_limit,
                    'current_balance' => $agency->current_balance
                )
            );
        }

        return array('success' => false, 'message' => 'Credenciales incorrectas');
    }

    /**
     * Método estático para obtener información de una agencia
     */
    public static function get_agency_info($agency_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reservas_agencies';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $agency_id
        ));
    }
}