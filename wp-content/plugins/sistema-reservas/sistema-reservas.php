<?php

/**
 * Plugin Name: Sistema de Reservas
 * Description: Sistema completo de reservas para servicios de transporte - CON RECORDATORIOS AUTOMÁTICOS
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RESERVAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RESERVAS_PLUGIN_PATH', plugin_dir_path(__FILE__));

class SistemaReservas
{
    private $dashboard;
    private $calendar_admin;
    private $discounts_admin;
    private $configuration_admin;
    private $reports_admin;
    private $agencies_admin;

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_test_pdf_generation', array($this, 'test_pdf_generation'));
        add_action('wp_ajax_nopriv_test_pdf_generation', array($this, 'test_pdf_generation'));

    }


    public function test_pdf_generation()
    {
        if (!class_exists('ReservasPDFGenerator')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-pdf-generator.php';
        }

        $test_data = array(
            'localizador' => 'TEST1234',
            'fecha' => '2025-07-20',
            'hora' => '10:00:00',
            'nombre' => 'Test',
            'apellidos' => 'Usuario',
            'email' => 'test@test.com',
            'telefono' => '123456789',
            'adultos' => 2,
            'residentes' => 0,
            'ninos_5_12' => 1,
            'ninos_menores' => 0,
            'total_personas' => 3,
            'precio_base' => 25.00,
            'descuento_total' => 0.00,
            'precio_final' => 25.00,
            'precio_adulto' => 10.00,
            'precio_nino' => 5.00,
            'precio_residente' => 5.00,
            'created_at' => date('Y-m-d H:i:s')
        );

        try {
            $pdf_generator = new ReservasPDFGenerator();
            $pdf_path = $pdf_generator->generate_ticket_pdf($test_data);

            wp_send_json_success(array(
                'message' => 'PDF generado correctamente',
                'path' => $pdf_path,
                'exists' => file_exists($pdf_path),
                'size' => file_exists($pdf_path) ? filesize($pdf_path) : 0
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }

    public function init()
    {
        // ✅ VERIFICAR QUE WORDPRESS ESTÁ COMPLETAMENTE CARGADO
        if (!did_action('wp_loaded')) {
            add_action('wp_loaded', array($this, 'init'));
            return;
        }

        // ✅ ASEGURAR QUE LAS SESIONES FUNCIONAN CORRECTAMENTE
        if (!session_id() && !headers_sent()) {
            session_start();
        }

        // Cargar dependencias
        $this->load_dependencies();

        // Inicializar clases
        $this->initialize_classes();

        // Registrar reglas de reescritura
        $this->add_rewrite_rules();

        // Añadir query vars
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Manejar template redirect
        add_action('template_redirect', array($this, 'template_redirect'));

        // ✅ AÑADIR DEBUG PARA AJAX
        add_action('wp_ajax_debug_session', array($this, 'debug_session_info'));
    }

    public function debug_session_info()
    {
        if (!session_id()) {
            session_start();
        }

        $debug_info = array(
            'session_id' => session_id(),
            'session_data' => $_SESSION,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_check' => wp_verify_nonce($_POST['nonce'] ?? '', 'reservas_nonce')
        );

        wp_send_json_success($debug_info);
    }

private function load_dependencies()
{
    $files = array(
        'includes/class-database.php',
        'includes/class-auth.php',
        'includes/class-admin.php',
        'includes/class-dashboard.php',
        'includes/class-calendar-admin.php',
        'includes/class-discounts-admin.php',
        'includes/class-configuration-admin.php',
        'includes/class-reports-admin.php',
        'includes/class-agencies-admin.php',
        'includes/class-agency-profile-admin.php',
        'includes/class-reservas-processor.php',
        'includes/class-email-service.php',
        'includes/class-frontend.php',
        'includes/class-reserva-rapida-admin.php',
        'includes/class-redsys-handler.php', // ✅ CARGAR ESTE ARCHIVO
    );

    foreach ($files as $file) {
        $path = RESERVAS_PLUGIN_PATH . $file;
        if (file_exists($path)) {
            require_once $path;
            error_log("✅ Cargado: $file");
        } else {
            error_log("❌ RESERVAS ERROR: No se pudo cargar $file");
        }
    }
}

    private function initialize_classes()
    {
        // Inicializar clases básicas
        if (class_exists('ReservasAuth')) {
            new ReservasAuth();
        }

        if (class_exists('ReservasDashboard')) {
            $this->dashboard = new ReservasDashboard();
        }

        if (class_exists('ReservasCalendarAdmin')) {
            $this->calendar_admin = new ReservasCalendarAdmin();
        }

        // Inicializar clase de descuentos
        if (class_exists('ReservasDiscountsAdmin')) {
            $this->discounts_admin = new ReservasDiscountsAdmin();
        }


        // Inicializar configuración con recordatorios
        if (class_exists('ReservasConfigurationAdmin')) {
            $this->configuration_admin = new ReservasConfigurationAdmin();
        }

        // Inicializar clase de informes
        if (class_exists('ReservasReportsAdmin')) {
            $this->reports_admin = new ReservasReportsAdmin();
        }

        if (class_exists('ReservasAgenciesAdmin')) {
            $this->agencies_admin = new ReservasAgenciesAdmin();
        }

        // Inicializar procesador de reservas CON EMAILS
        if (class_exists('ReservasProcessor')) {
            new ReservasProcessor();
        }

        // Inicializar servicio de emails con recordatorios
        if (class_exists('ReservasEmailService')) {
            new ReservasEmailService();
        }

        if (class_exists('ReservasFrontend')) {
            new ReservasFrontend();
        }

        if (class_exists('ReservasReservaRapidaAdmin')) {
            new ReservasReservaRapidaAdmin();
        }

        if (class_exists('ReservasAgencyProfileAdmin')) {
            new ReservasAgencyProfileAdmin();
        }
        if (class_exists('ReservasAgencyProfileAdmin')) {
            new ReservasAgencyProfileAdmin();
        }

        if (class_exists('ReservasReservaRapidaAdmin')) {
            new ReservasReservaRapidaAdmin();
        }
    }

    public function add_rewrite_rules()
    {
        add_rewrite_rule('^reservas-login/?$', 'index.php?reservas_page=login', 'top');
        add_rewrite_rule('^reservas-admin/?$', 'index.php?reservas_page=dashboard', 'top');
        add_rewrite_rule('^reservas-admin/([^/]+)/?$', 'index.php?reservas_page=dashboard&reservas_section=$matches[1]', 'top');
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'reservas_page';
        $vars[] = 'reservas_section';
        return $vars;
    }

    public function template_redirect()
    {
        $page = get_query_var('reservas_page');

        // Manejar logout
        if (isset($_GET['logout']) && $_GET['logout'] == '1') {
            if ($this->dashboard) {
                $this->dashboard->handle_logout();
            }
        }

        if ($page === 'login') {
            if ($this->dashboard) {
                $this->dashboard->show_login();
            }
            exit;
        }

        if ($page === 'dashboard') {
            if ($this->dashboard) {
                $this->dashboard->show_dashboard();
            }
            exit;
        }
    }

    public function activate()
    {
        // Crear tablas de base de datos
        $this->create_tables();

        // ✅ FORZAR ACTUALIZACIÓN DE TABLAS EXISTENTES
        $this->maybe_update_existing_tables();

        $this->init_localizador_counter();

        // Flush rewrite rules para activar las nuevas URLs
        flush_rewrite_rules();

        // ✅ PROGRAMAR CRON JOB PARA RECORDATORIOS
        if (!wp_next_scheduled('reservas_send_reminders')) {
            wp_schedule_event(time(), 'hourly', 'reservas_send_reminders');
            error_log('✅ Cron job de recordatorios programado');
        }

        if (!wp_next_scheduled('reservas_reset_localizadores')) {
            $next_year = mktime(0, 0, 0, 1, 1, date('Y') + 1); // 1 de enero del próximo año
            wp_schedule_event($next_year, 'yearly', 'reservas_reset_localizadores');
            error_log('✅ Programado reinicio anual de localizadores para: ' . date('Y-m-d H:i:s', $next_year));
        }
    }

    public function deactivate()
    {
        // Limpiar rewrite rules
        flush_rewrite_rules();

        // ✅ LIMPIAR CRON JOB DE RECORDATORIOS
        wp_clear_scheduled_hook('reservas_send_reminders');
        error_log('✅ Cron job de recordatorios eliminado');
    }

    private function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tabla de usuarios
        $table_users = $wpdb->prefix . 'reservas_users';
        $sql_users = "CREATE TABLE $table_users (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            username varchar(50) NOT NULL UNIQUE,
            email varchar(100) NOT NULL UNIQUE,
            password varchar(255) NOT NULL,
            role varchar(20) NOT NULL DEFAULT 'usuario',
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_users);

        $table_agencies = $wpdb->prefix . 'reservas_agencies';
        $sql_agencies = "CREATE TABLE $table_agencies (
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

        dbDelta($sql_agencies);

        // Tabla de servicios
        $table_servicios = $wpdb->prefix . 'reservas_servicios';
        $sql_servicios = "CREATE TABLE $table_servicios (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        fecha date NOT NULL,
        hora time NOT NULL,
        hora_vuelta time NOT NULL, // Añade esta línea
        plazas_totales int(11) NOT NULL,
        plazas_disponibles int(11) NOT NULL,
        plazas_bloqueadas int(11) DEFAULT 0,
        precio_adulto decimal(10,2) NOT NULL,
        precio_nino decimal(10,2) NOT NULL,
        precio_residente decimal(10,2) NOT NULL,
        tiene_descuento tinyint(1) DEFAULT 0,
        porcentaje_descuento decimal(5,2) DEFAULT 0.00,
        status enum('active', 'inactive') DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY fecha_hora (fecha, hora),
        KEY fecha (fecha),
        KEY status (status)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_servicios);

        // ✅ TABLA DE RESERVAS ACTUALIZADA CON CAMPO DE RECORDATORIO
        $table_reservas = $wpdb->prefix . 'reservas_reservas';
        $sql_reservas = "CREATE TABLE $table_reservas (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            localizador varchar(20) NOT NULL UNIQUE,
            servicio_id mediumint(9) NOT NULL,
            fecha date NOT NULL,
            hora time NOT NULL,
            nombre varchar(100) NOT NULL,
            apellidos varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            telefono varchar(20) NOT NULL,
            adultos int(11) DEFAULT 0,
            residentes int(11) DEFAULT 0,
            ninos_5_12 int(11) DEFAULT 0,
            ninos_menores int(11) DEFAULT 0,
            total_personas int(11) NOT NULL,
            precio_base decimal(10,2) NOT NULL,
            descuento_total decimal(10,2) DEFAULT 0.00,
            precio_final decimal(10,2) NOT NULL,
            regla_descuento_aplicada TEXT NULL,
            recordatorio_enviado tinyint(1) DEFAULT 0,
            estado enum('pendiente', 'confirmada', 'cancelada') DEFAULT 'confirmada',
            metodo_pago varchar(50) DEFAULT 'directo',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY servicio_id (servicio_id),
            KEY fecha (fecha),
            KEY estado (estado),
            KEY localizador (localizador),
            KEY recordatorio_enviado (recordatorio_enviado)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_reservas);

        // Tabla de reglas de descuento
        $table_discounts = $wpdb->prefix . 'reservas_discount_rules';
        $sql_discounts = "CREATE TABLE $table_discounts (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            rule_name varchar(100) NOT NULL,
            minimum_persons int(11) NOT NULL,
            discount_percentage decimal(5,2) NOT NULL,
            apply_to enum('total', 'adults_only', 'all_paid') DEFAULT 'total',
            rule_description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY minimum_persons (minimum_persons)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_discounts);

        // ✅ TABLA DE CONFIGURACIÓN CON NUEVOS CAMPOS
        $table_configuration = $wpdb->prefix . 'reservas_configuration';
        $sql_configuration = "CREATE TABLE $table_configuration (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            config_key varchar(100) NOT NULL UNIQUE,
            config_value longtext,
            config_group varchar(50) DEFAULT 'general',
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY config_key (config_key),
            KEY config_group (config_group)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_configuration);

        // Crear usuario super admin inicial
        $this->create_super_admin();

        // Crear regla de descuento por defecto
        $this->create_default_discount_rule();

        // ✅ CREAR CONFIGURACIÓN CON NUEVOS CAMPOS
        $this->create_default_configuration();

        // ✅ ACTUALIZAR TABLA EXISTENTE SI ES NECESARIO
        $this->maybe_update_existing_tables();
    }

    private function maybe_add_enabled_field()
    {
        global $wpdb;
        $table_servicios = $wpdb->prefix . 'reservas_servicios';

        // Verificar si el campo enabled existe
        $enabled_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_servicios LIKE 'enabled'");

        if (empty($enabled_exists)) {
            // Añadir columna enabled
            $wpdb->query("ALTER TABLE $table_servicios ADD COLUMN enabled TINYINT(1) DEFAULT 1 AFTER status");
            $wpdb->query("ALTER TABLE $table_servicios ADD INDEX enabled (enabled)");
            error_log('✅ Columna enabled añadida a tabla de servicios');
        }
    }

    // ✅ NUEVA FUNCIÓN PARA ACTUALIZAR TABLAS EXISTENTES
    private function maybe_update_existing_tables()
    {
        global $wpdb;

        $table_reservas = $wpdb->prefix . 'reservas_reservas';
        $table_servicios = $wpdb->prefix . 'reservas_servicios';
        $table_configuration = $wpdb->prefix . 'reservas_configuration';

        // ✅ NUEVO: ELIMINAR RESTRICCIÓN ÚNICA FECHA_HORA PARA PERMITIR MÚLTIPLES SERVICIOS
        error_log('=== VERIFICANDO RESTRICCIÓN ÚNICA fecha_hora ===');

        // Verificar si existe la clave única fecha_hora
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_servicios WHERE Key_name = 'fecha_hora'");

        if (!empty($indexes)) {
            $wpdb->query("ALTER TABLE $table_servicios DROP INDEX fecha_hora");
            error_log('✅ Restricción única fecha_hora eliminada - ahora se permiten múltiples servicios por día/hora');
        } else {
            error_log('ℹ️ La restricción única fecha_hora ya no existe o nunca existió');
        }

        // Crear índices separados para optimizar consultas (solo si no existen)
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM $table_servicios");
        $index_names = array_column($existing_indexes, 'Key_name');

        if (!in_array('idx_fecha', $index_names)) {
            $wpdb->query("ALTER TABLE $table_servicios ADD INDEX idx_fecha (fecha)");
            error_log('✅ Índice idx_fecha creado');
        }

        if (!in_array('idx_hora', $index_names)) {
            $wpdb->query("ALTER TABLE $table_servicios ADD INDEX idx_hora (hora)");
            error_log('✅ Índice idx_hora creado');
        }

        if (!in_array('idx_fecha_hora', $index_names)) {
            $wpdb->query("ALTER TABLE $table_servicios ADD INDEX idx_fecha_hora (fecha, hora)");
            error_log('✅ Índice idx_fecha_hora creado');
        }


        // ✅ VERIFICAR Y AÑADIR CAMPO ENABLED A SERVICIOS
        $enabled_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_servicios LIKE 'enabled'");

        if (empty($enabled_exists)) {
            $wpdb->query("ALTER TABLE $table_servicios ADD COLUMN enabled TINYINT(1) DEFAULT 1 AFTER status");
            $wpdb->query("ALTER TABLE $table_servicios ADD INDEX enabled (enabled)");
            error_log('✅ Columna enabled añadida a tabla de servicios');
        }

        // ✅ VERIFICAR Y AÑADIR CAMPOS DE DESCUENTO ESPECÍFICO POR SERVICIO
        $descuento_tipo_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_servicios LIKE 'descuento_tipo'");

        if (empty($descuento_tipo_exists)) {
            $wpdb->query("ALTER TABLE $table_servicios ADD COLUMN descuento_tipo ENUM('fijo', 'por_grupo') DEFAULT 'fijo' AFTER porcentaje_descuento");
            $wpdb->query("ALTER TABLE $table_servicios ADD COLUMN descuento_minimo_personas INT(11) DEFAULT 1 AFTER descuento_tipo");
            error_log('✅ Campos de descuento específico por servicio añadidos');
        }

        $acumulable_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_servicios LIKE 'descuento_acumulable'");

        if (empty($acumulable_exists)) {
            $wpdb->query("ALTER TABLE $table_servicios ADD COLUMN descuento_acumulable TINYINT(1) DEFAULT 0 AFTER descuento_minimo_personas");
            $wpdb->query("ALTER TABLE $table_servicios ADD COLUMN descuento_prioridad ENUM('servicio', 'grupo') DEFAULT 'servicio' AFTER descuento_acumulable");
            error_log('✅ Campos de acumulación de descuentos añadidos');
        }

        // ✅ VERIFICAR Y AÑADIR CAMPO HORA_VUELTA A SERVICIOS
        $hora_vuelta_servicios_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_servicios LIKE 'hora_vuelta'");

        if (empty($hora_vuelta_servicios_exists)) {
            $wpdb->query("ALTER TABLE $table_servicios ADD COLUMN hora_vuelta TIME NULL AFTER hora");
            error_log('✅ Columna hora_vuelta añadida a tabla de servicios');
        }

        // ✅ ACTUALIZAR TABLA DE RESERVAS

        // Verificar campo recordatorio_enviado
        $recordatorio_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_reservas LIKE 'recordatorio_enviado'");

        if (empty($recordatorio_exists)) {
            $wpdb->query("ALTER TABLE $table_reservas ADD COLUMN recordatorio_enviado TINYINT(1) DEFAULT 0");
            $wpdb->query("ALTER TABLE $table_reservas ADD INDEX recordatorio_enviado (recordatorio_enviado)");
            error_log('✅ Columna recordatorio_enviado añadida a tabla de reservas');
        }

        // Verificar campo hora_vuelta en reservas
        $hora_vuelta_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_reservas LIKE 'hora_vuelta'");

        if (empty($hora_vuelta_exists)) {
            $wpdb->query("ALTER TABLE $table_reservas ADD COLUMN hora_vuelta TIME NULL AFTER hora");
            error_log('✅ Columna hora_vuelta añadida a tabla de reservas');
        }

        // Verificar campo agency_id
        $agency_column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_reservas LIKE 'agency_id'");

        if (empty($agency_column_exists)) {
            $wpdb->query("ALTER TABLE $table_reservas ADD COLUMN agency_id MEDIUMINT(9) NULL DEFAULT NULL");
            $wpdb->query("ALTER TABLE $table_reservas ADD INDEX agency_id (agency_id)");
            error_log('✅ Columna agency_id añadida a tabla de reservas');
        }

        // Verificar campos de cancelación
        $cancel_column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_reservas LIKE 'motivo_cancelacion'");

        if (empty($cancel_column_exists)) {
            $wpdb->query("ALTER TABLE $table_reservas ADD COLUMN motivo_cancelacion TEXT NULL");
            $wpdb->query("ALTER TABLE $table_reservas ADD COLUMN fecha_cancelacion DATETIME NULL");
            error_log('✅ Columnas de cancelación añadidas a tabla de reservas');
        }

        // ✅ ACTUALIZAR CONFIGURACIÓN

        // Verificar si existe el campo email_reservas
        $email_reservas_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_configuration WHERE config_key = %s",
            'email_reservas'
        ));

        if ($email_reservas_exists == 0) {
            $wpdb->insert(
                $table_configuration,
                array(
                    'config_key' => 'email_reservas',
                    'config_value' => get_option('admin_email'),
                    'config_group' => 'notificaciones',
                    'description' => 'Email donde se recibirán las notificaciones de nuevas reservas'
                )
            );
            error_log('✅ Configuración email_reservas añadida');
        }

        // Actualizar descripción del email remitente
        $wpdb->update(
            $table_configuration,
            array('description' => 'Email remitente para todas las notificaciones del sistema (NO MODIFICAR sin conocimientos técnicos)'),
            array('config_key' => 'email_remitente')
        );

        // ✅ VERIFICAR Y AÑADIR CAMPO email_notificaciones A AGENCIAS
        $table_agencies = $wpdb->prefix . 'reservas_agencies';

        // Verificar si la tabla de agencias existe
        $agencies_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_agencies'") == $table_agencies;

        if ($agencies_table_exists) {
            $email_notif_column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_agencies LIKE 'email_notificaciones'");

            if (empty($email_notif_column_exists)) {
                $wpdb->query("ALTER TABLE $table_agencies ADD COLUMN email_notificaciones varchar(100) AFTER email");
                error_log('✅ Columna email_notificaciones añadida a tabla de agencias');
            }
        }

        error_log('=== ACTUALIZACIÓN DE TABLAS COMPLETADA ===');
    }

    private function create_super_admin()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reservas_users';

        // Verificar si ya existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE username = %s",
            'superadmin'
        ));

        if ($existing == 0) {
            $wpdb->insert(
                $table_name,
                array(
                    'username' => 'superadmin',
                    'email' => 'admin@' . parse_url(home_url(), PHP_URL_HOST),
                    'password' => password_hash('admin123', PASSWORD_DEFAULT),
                    'role' => 'super_admin',
                    'status' => 'active',
                    'created_at' => current_time('mysql')
                )
            );
        }
    }

    private function create_default_discount_rule()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reservas_discount_rules';

        // Verificar si ya hay reglas
        $existing_rules = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ($existing_rules == 0) {
            $wpdb->insert(
                $table_name,
                array(
                    'rule_name' => 'Descuento Grupo Grande',
                    'minimum_persons' => 10,
                    'discount_percentage' => 15.00,
                    'apply_to' => 'total',
                    'rule_description' => 'Descuento automático para grupos de 10 o más personas',
                    'is_active' => 1
                )
            );
        }
    }

    // ✅ FUNCIÓN CON CONFIGURACIÓN ACTUALIZADA
    private function create_default_configuration()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reservas_configuration';

        $default_configs = array(
            // Precios por defecto
            array(
                'config_key' => 'precio_adulto_defecto',
                'config_value' => '10.00',
                'config_group' => 'precios',
                'description' => 'Precio por defecto para adultos al crear nuevos servicios'
            ),
            array(
                'config_key' => 'precio_nino_defecto',
                'config_value' => '5.00',
                'config_group' => 'precios',
                'description' => 'Precio por defecto para niños (5-12 años) al crear nuevos servicios'
            ),
            array(
                'config_key' => 'precio_residente_defecto',
                'config_value' => '5.00',
                'config_group' => 'precios',
                'description' => 'Precio por defecto para residentes al crear nuevos servicios'
            ),

            // Configuración de servicios
            array(
                'config_key' => 'plazas_defecto',
                'config_value' => '50',
                'config_group' => 'servicios',
                'description' => 'Número de plazas por defecto al crear nuevos servicios'
            ),
            array(
                'config_key' => 'dias_anticipacion_minima',
                'config_value' => '1',
                'config_group' => 'servicios',
                'description' => 'Días de anticipación mínima para poder reservar (bloquea fechas en calendario)'
            ),

            // ✅ CONFIGURACIÓN DE EMAILS ACTUALIZADA
            array(
                'config_key' => 'email_recordatorio_activo',
                'config_value' => '1', // ✅ ACTIVO POR DEFECTO
                'config_group' => 'notificaciones',
                'description' => 'Activar recordatorios automáticos antes del viaje'
            ),
            array(
                'config_key' => 'horas_recordatorio',
                'config_value' => '24',
                'config_group' => 'notificaciones',
                'description' => 'Horas antes del viaje para enviar recordatorio automático'
            ),
            array(
                'config_key' => 'email_remitente',
                'config_value' => get_option('admin_email'),
                'config_group' => 'notificaciones',
                'description' => 'Email remitente para todas las notificaciones del sistema (NO MODIFICAR sin conocimientos técnicos)'
            ),
            array(
                'config_key' => 'nombre_remitente',
                'config_value' => get_bloginfo('name'),
                'config_group' => 'notificaciones',
                'description' => 'Nombre del remitente para notificaciones'
            ),
            // ✅ NUEVO CAMPO: Email de reservas
            array(
                'config_key' => 'email_reservas',
                'config_value' => get_option('admin_email'),
                'config_group' => 'notificaciones',
                'description' => 'Email donde se recibirán las notificaciones de nuevas reservas'
            ),

            // General
            array(
                'config_key' => 'zona_horaria',
                'config_value' => 'Europe/Madrid',
                'config_group' => 'general',
                'description' => 'Zona horaria del sistema'
            ),
            array(
                'config_key' => 'moneda',
                'config_value' => 'EUR',
                'config_group' => 'general',
                'description' => 'Moneda utilizada en el sistema'
            ),
            array(
                'config_key' => 'simbolo_moneda',
                'config_value' => '€',
                'config_group' => 'general',
                'description' => 'Símbolo de la moneda'
            )
        );

        foreach ($default_configs as $config) {
            // Verificar si ya existe
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE config_key = %s",
                $config['config_key']
            ));

            if ($existing == 0) {
                $result = $wpdb->insert($table_name, $config);
                if ($result === false) {
                    error_log("Error insertando configuración: " . $config['config_key'] . " - " . $wpdb->last_error);
                }
            }
        }
    }

    private function init_localizador_counter()
    {
        global $wpdb;

        $table_config = $wpdb->prefix . 'reservas_configuration';
        $año_actual = date('Y');
        $config_key = "ultimo_localizador_$año_actual";

        // Verificar si ya existe configuración para este año
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_config WHERE config_key = %s",
            $config_key
        ));

        if ($exists == 0) {
            // Insertar configuración inicial para el año actual
            $wpdb->insert(
                $table_config,
                array(
                    'config_key' => $config_key,
                    'config_value' => '0',
                    'config_group' => 'localizadores',
                    'description' => "Último número de localizador usado en el año $año_actual (se reinicia cada año)"
                )
            );
            error_log("✅ Inicializado contador de localizadores para el año $año_actual");
        }
    }
}

// ✅ SHORTCODE PARA PÁGINA DE CONFIRMACIÓN ACTUALIZADA - EXACTO AL DISEÑO
add_shortcode('confirmacion_reserva', 'confirmacion_reserva_shortcode');

function confirmacion_reserva_shortcode()
{
    ob_start();
?>
    <style>
        .confirmacion-container {
            margin: 50px auto;
            padding: 0;
            border-radius: 20px;
        }

        .success-banner {
            background: #DB7461;
            color: white;
            text-align: center;
            margin: 0;
            padding: 15px;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .success-banner h1 {
            background: #DB7461;
            color: white;
            text-align: center;
            margin: 0;
            padding: 15px;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            font-family: 'Duran-Regular';
        }

        .success-banner .divider {
            width: 80px;
            height: 4px;
            background: #F4D03F;
            margin: 20px auto;
            border-radius: 2px;
        }

        .success-banner p {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
            opacity: 0.95;
        }

        .content-section {
            background: #FFFFFF;
            padding: 40px 30px;
            text-align: center;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .thank-you-message {
            margin-bottom: 40px;
        }

        .thank-you-message p {
            font-size: 16px;
            color: #2D2D2D;
            line-height: 1.6;
            margin: 0 0 10px 0;
        }

        .remember-text {
            color: #2D2D2D;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            font-family: 'Duran-Regular';
        }


        .boarding-message {
            color: #2D2D2D;
            font-size: 16px;
            margin: 20px 0;
            font-family: 'Duran-Regular';
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 40px 0;
            justify-content: space-between;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 250px;
            gap: 10px;
        }

        .arrival-info {
            display: none;
        }

        .btn-view {
            background: #F4D03F;
            color: #2D2D2D;
            border: 2px solid #F4D03F;
        }

        .btn-view:hover {
            background: #F1C40F;
            border-color: #F1C40F;
            color: #2D2D2D;
            text-decoration: none;
        }

        .btn-download {
            background: transparent;
            color: #2D2D2D;
            border: 2px solid #F4D03F;
        }

        .btn-download:hover {
            background: #F4D03F;
            color: #2D2D2D;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .confirmacion-container {
                margin: 20px;
                max-width: none;
            }

            .success-banner {
                padding: 30px 20px;
            }

            .success-banner h1 {
                font-size: 28px;
            }

            .content-section {
                padding: 30px 20px;
            }

            .btn {
                min-width: 200px;
            }
        }

        /* Loading y error states */
        .loading-state {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #666;
            font-style: italic;
        }

        .complete-btn {
            background: #EFCF4B;
            border: none;
            padding: 15px 100px;
            font-size: 20px;
            font-weight: bold;
            color: #2E2D2C;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 44%;
            font-family: 'Duran-Medium';
            text-transform: uppercase;
            border-radius: 10px;
            letter-spacing: 1px;
            margin: 0 auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .error-state {
            color: #E74C3C;
            font-weight: 600;
        }

        /* Ocultar botones inicialmente */
        .action-buttons {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .action-buttons.loaded {
            opacity: 1;
        }

        .back-btn {
            color: black;
            border: none;
            font-size: 16px;
            cursor: pointer;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: none !important;
            margin-bottom: 10px;
        }
    </style>

    <div class="confirmacion-container container">
        <!-- Banner principal -->
        <button type="button" class="back-btn" onclick="goBackInicio()">
            <img src="https://autobusmedinaazahara.com/wp-content/uploads/2025/07/Vector-15.svg" alt="">VOLVER AL INICIO
        </button>
        <div class="success-banner">
            <h1>¡GRACIAS POR TU RESERVA!</h1>
        </div>

        <!-- Contenido principal -->
        <div class="content-section">
            <div class="thank-you-message">
                <p>En <strong>Autocares Bravo</strong> estamos agradecidos por tu confianza para el viaje a las ruinas de <strong>Medina Azahara</strong>. Nos hace ilusión acompañarte: tú disfruta de la experiencia, nosotros cuidamos del trayecto.</p>
            </div>

            <div class="remember-text">
                Recuerda presentarte en la parada 10 minutos antes de la salida.
            </div>

            <div class="arrival-info" id="arrival-info">
                <span class="loading-state">📍 Cargando información del viaje...</span>
            </div>

            <div class="boarding-message">
                ¡Nos vemos a bordo!
            </div>

            <!-- Botones de acción -->
            <div class="action-buttons" id="action-buttons">
                <button class="complete-btn" onclick="viewTicket()">
                    VER COMPROBANTE
                </button>
                <button class="complete-btn" onclick="downloadTicket()">
                    DESCARGAR COMPROBANTE
                </button>
            </div>
        </div>
    </div>

    <script>
        let reservationData = null;

        // Cargar datos al iniciar la página
        window.addEventListener('DOMContentLoaded', function() {
            loadReservationData();
        });

        function goBackInicio() {
            // Opción 1: Volver a la página de inicio del sitio
            window.location.href = '/';

            // Opción 2: Si tienes una página específica de reservas, descomenta esta línea:
            // window.location.href = '/reservas/';

            // Opción 3: Si quieres usar la URL base de WordPress dinámicamente:
            // window.location.href = '<?php echo home_url('/'); ?>';
        }

        function loadReservationData() {
            try {
                const confirmedData = sessionStorage.getItem('confirmedReservation');

                if (confirmedData) {
                    reservationData = JSON.parse(confirmedData);
                    console.log('✅ Datos de confirmación cargados:', reservationData);

                    updateArrivalInfo();
                    enableActionButtons();

                    // Limpiar sessionStorage después de cargar
                    sessionStorage.removeItem('confirmedReservation');
                } else {
                    console.log('⚠️ No hay datos de confirmación en sessionStorage');
                    showGenericInfo();
                    enableActionButtons(); // Habilitar botones aunque no haya datos específicos
                }
            } catch (error) {
                console.error('❌ Error cargando datos de confirmación:', error);
                showErrorInfo();
                enableActionButtons();
            }
        }

        function updateArrivalInfo() {
            if (!reservationData || !reservationData.detalles) {
                showGenericInfo();
                return;
            }

            const detalles = reservationData.detalles;
            const fecha = detalles.fecha || 'Fecha no disponible';
            const hora = detalles.hora || 'Hora no disponible';

            // Formatear fecha si es posible
            let fechaFormateada = fecha;
            try {
                const fechaObj = new Date(fecha + 'T00:00:00');
                fechaFormateada = fechaObj.toLocaleDateString('es-ES', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                // Capitalizar primera letra
                fechaFormateada = fechaFormateada.charAt(0).toUpperCase() + fechaFormateada.slice(1);
            } catch (e) {
                console.log('No se pudo formatear la fecha, usando formato original');
            }

            const arrivalText = `📍 ${fechaFormateada} a las ${hora}`;
            document.getElementById('arrival-info').innerHTML = arrivalText;
        }

        function showGenericInfo() {
            document.getElementById('arrival-info').innerHTML = '📍 Consulta tu email para ver los detalles del viaje';
        }

        function showErrorInfo() {
            document.getElementById('arrival-info').innerHTML = '<span class="error-state">❌ Error cargando información del viaje</span>';
        }

        function enableActionButtons() {
            const actionButtons = document.getElementById('action-buttons');
            actionButtons.classList.add('loaded');
        }

        function viewTicket() {
            if (!reservationData || !reservationData.localizador) {
                alert('No se encontraron datos de la reserva. Por favor, revisa tu email para ver el comprobante.');
                return;
            }

            // Mostrar modal de carga
            showLoadingModal('Generando comprobante...');

            // Solicitar PDF al servidor
            generateAndViewPDF();
        }

        function downloadTicket() {
            if (!reservationData || !reservationData.localizador) {
                alert('No se encontraron datos de la reserva. Por favor, revisa tu email para descargar el comprobante.');
                return;
            }

            // Mostrar modal de carga
            showLoadingModal('Preparando descarga...');

            // Solicitar PDF para descarga
            generateAndDownloadPDF();
        }

        function generateAndViewPDF() {
            fetch(ajaxurl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'generate_ticket_pdf_view',
                        localizador: reservationData.localizador,
                        nonce: '<?php echo wp_create_nonce('reservas_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoadingModal();

                    if (data.success && data.data.pdf_url) {
                        // Abrir PDF en nueva ventana
                        window.open(data.data.pdf_url, '_blank');
                    } else {
                        alert('Error generando el comprobante: ' + (data.data || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error('Error:', error);
                    alert('Error de conexión al generar el comprobante');
                });
        }

        function generateAndDownloadPDF() {
            fetch(ajaxurl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'generate_ticket_pdf_download',
                        localizador: reservationData.localizador,
                        nonce: '<?php echo wp_create_nonce('reservas_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoadingModal();

                    if (data.success && data.data.pdf_url) {
                        // Crear link de descarga
                        const link = document.createElement('a');
                        link.href = data.data.pdf_url;
                        link.download = `billete_${reservationData.localizador}.pdf`;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        alert('Error preparando la descarga: ' + (data.data || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error('Error:', error);
                    alert('Error de conexión al preparar la descarga');
                });
        }

        function showLoadingModal(message) {
            // Crear modal de carga si no existe
            let modal = document.getElementById('loading-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'loading-modal';
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                `;

                const content = document.createElement('div');
                content.style.cssText = `
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    text-align: center;
                    max-width: 300px;
                `;

                content.innerHTML = `
                    <div style="font-size: 24px; margin-bottom: 15px;">⏳</div>
                    <div id="loading-message" style="font-size: 16px; color: #333;">${message}</div>
                `;

                modal.appendChild(content);
                document.body.appendChild(modal);
            } else {
                document.getElementById('loading-message').textContent = message;
                modal.style.display = 'flex';
            }
        }

        function hideLoadingModal() {
            const modal = document.getElementById('loading-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Variables globales para AJAX
        const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
<?php
    return ob_get_clean();
}

// ✅ AGREGAR NUEVAS FUNCIONES AJAX PARA MANEJAR PDF
add_action('wp_ajax_generate_ticket_pdf_view', 'handle_pdf_view_request');
add_action('wp_ajax_nopriv_generate_ticket_pdf_view', 'handle_pdf_view_request');

add_action('wp_ajax_generate_ticket_pdf_download', 'handle_pdf_download_request');
add_action('wp_ajax_nopriv_generate_ticket_pdf_download', 'handle_pdf_download_request');

function handle_pdf_view_request()
{
    handle_pdf_request('view');
}

function handle_pdf_download_request()
{
    handle_pdf_request('download');
}

function handle_pdf_request($mode = 'view')
{
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
        wp_send_json_error('Error de seguridad');
        return;
    }

    $localizador = sanitize_text_field($_POST['localizador'] ?? '');

    if (empty($localizador)) {
        wp_send_json_error('Localizador no proporcionado');
        return;
    }

    try {
        // Buscar la reserva
        global $wpdb;
        $table_reservas = $wpdb->prefix . 'reservas_reservas';

        $reserva = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_reservas WHERE localizador = %s",
            $localizador
        ));

        if (!$reserva) {
            wp_send_json_error('Reserva no encontrada');
            return;
        }

        // Obtener datos del servicio
        $table_servicios = $wpdb->prefix . 'reservas_servicios';
        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT precio_adulto, precio_nino, precio_residente FROM $table_servicios WHERE id = %d",
            $reserva->servicio_id
        ));

        // Preparar datos para el PDF
        $reserva_array = (array) $reserva;
        if ($servicio) {
            $reserva_array['precio_adulto'] = $servicio->precio_adulto;
            $reserva_array['precio_nino'] = $servicio->precio_nino;
            $reserva_array['precio_residente'] = $servicio->precio_residente;
        }

        // Generar PDF
        if (!class_exists('ReservasPDFGenerator')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-pdf-generator.php';
        }

        $pdf_generator = new ReservasPDFGenerator();
        $pdf_path = $pdf_generator->generate_ticket_pdf($reserva_array);

        if (!$pdf_path || !file_exists($pdf_path)) {
            wp_send_json_error('Error generando el PDF');
            return;
        }

        // Crear URL público para el PDF
        $upload_dir = wp_upload_dir();
        $pdf_url = str_replace($upload_dir['path'], $upload_dir['url'], $pdf_path);

        // Programar eliminación del archivo después de 1 hora
        wp_schedule_single_event(time() + 3600, 'delete_temp_pdf', array($pdf_path));

        wp_send_json_success(array(
            'pdf_url' => $pdf_url,
            'mode' => $mode,
            'localizador' => $localizador
        ));
    } catch (Exception $e) {
        error_log('Error generando PDF para confirmación: ' . $e->getMessage());
        wp_send_json_error('Error interno generando el PDF: ' . $e->getMessage());
    }
}

// Hook para eliminar PDFs temporales
add_action('delete_temp_pdf', 'delete_temporary_pdf_file');

function delete_temporary_pdf_file($pdf_path)
{
    if (file_exists($pdf_path)) {
        unlink($pdf_path);
        error_log('PDF temporal eliminado: ' . $pdf_path);
    }
}


// ✅ AÑADIR DESPUÉS DE LA CLASE SistemaReservas

// Hook para reiniciar contadores de localizadores cada año
add_action('reservas_reset_localizadores', 'reset_yearly_localizadores');

function reset_yearly_localizadores()
{
    global $wpdb;

    $table_config = $wpdb->prefix . 'reservas_configuration';
    $año_actual = date('Y');
    $config_key = "ultimo_localizador_$año_actual";

    // Insertar o actualizar contador para el nuevo año
    $wpdb->query($wpdb->prepare(
        "INSERT INTO $table_config (config_key, config_value, config_group, description) 
         VALUES (%s, '0', 'localizadores', %s)
         ON DUPLICATE KEY UPDATE config_value = '0', updated_at = NOW()",
        $config_key,
        "Último número de localizador usado en el año $año_actual (se reinicia cada año)"
    ));

    // Programar para el siguiente año
    $next_year = mktime(0, 0, 0, 1, 1, date('Y') + 1);
    wp_schedule_event($next_year, 'yearly', 'reservas_reset_localizadores');

    error_log("✅ Contador de localizadores reiniciado para el año $año_actual");
}

// ✅ SHORTCODES SIN CAMBIOS

// Shortcode para uso en páginas de WordPress (alternativa)
add_shortcode('reservas_login', 'reservas_login_shortcode');

function reservas_login_shortcode()
{
    // Procesar login si se envía el formulario
    if ($_POST && isset($_POST['shortcode_login'])) {
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_users';

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE username = %s AND status = 'active'",
            $username
        ));

        if ($user && password_verify($password, $user->password)) {
            if (!session_id()) {
                session_start();
            }

            $_SESSION['reservas_user'] = array(
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role
            );

            return '<div style="padding: 20px; background: #edfaed; border-left: 4px solid #00a32a; color: #00a32a;">
                        <strong>✅ Login exitoso!</strong> 
                        <br>Ahora puedes ir al <a href="' . home_url('/reservas-admin/') . '">dashboard</a>
                    </div>';
        } else {
            return '<div style="padding: 20px; background: #fbeaea; border-left: 4px solid #d63638; color: #d63638;">
                        <strong>❌ Error:</strong> Usuario o contraseña incorrectos
                    </div>';
        }
    }

    ob_start();
?>
    <div style="max-width: 400px; margin: 0 auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h2 style="text-align: center; color: #23282d;">Sistema de Reservas - Login</h2>
        <form method="post">
            <input type="hidden" name="shortcode_login" value="1">
            <p>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Usuario:</label>
                <input type="text" name="username" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" required>
            </p>
            <p>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Contraseña:</label>
                <input type="password" name="password" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" required>
            </p>
            <p>
                <input type="submit" value="Iniciar Sesión" style="width: 100%; padding: 12px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
            </p>
        </form>
        <div style="background: #f0f0f1; padding: 15px; margin-top: 15px; border-radius: 4px;">
            <p style="margin: 5px 0; font-size: 14px;"><strong>Usuario:</strong> superadmin</p>
            <p style="margin: 5px 0; font-size: 14px;"><strong>Contraseña:</strong> admin123</p>
        </div>
    </div>
<?php
    return ob_get_clean();
}

add_action('admin_init', function () {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservas_agencies';

    // Verificar si el campo email_notificaciones existe
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'email_notificaciones'");

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN email_notificaciones varchar(100) AFTER email");
        error_log('✅ Columna email_notificaciones añadida a tabla de agencias');
    }
});


function ajax_generar_formulario_pago_redsys()
{
    error_log('=== FUNCIÓN REDSYS EJECUTADA ===');
    
    try {
        // Verificación básica
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            error_log('❌ Error de nonce');
            wp_send_json_error('Error de seguridad');
            return;
        }
        
        if (!isset($_POST['reservation_data'])) {
            error_log('❌ No hay reservation_data');
            wp_send_json_error('No hay datos de reserva');
            return;
        }
        
        $reserva_raw = stripslashes($_POST['reservation_data']);
        error_log('Datos raw recibidos: ' . $reserva_raw);
        
        $reserva = json_decode($reserva_raw, true);
        
        if (!$reserva) {
            error_log('❌ JSON inválido: ' . json_last_error_msg());
            wp_send_json_error('JSON inválido: ' . json_last_error_msg());
            return;
        }
        
        error_log('✅ JSON parseado correctamente');
        error_log('Total price en datos: ' . ($reserva['total_price'] ?? 'NO_DEFINIDO'));
        
        // Verificar total_price
        if (!isset($reserva['total_price']) || empty($reserva['total_price'])) {
            error_log('❌ No hay total_price o está vacío');
            wp_send_json_error('Falta total_price');
            return;
        }
        
        // ✅ CAMBIO: Usar directamente la función desde class-redsys-handler.php
        error_log('✅ Generando formulario Redsys...');
        $formulario = generar_formulario_redsys($reserva);
        
        error_log('✅ Formulario generado, longitud: ' . strlen($formulario));
        wp_send_json_success($formulario);
        
    } catch (Exception $e) {
        error_log('❌ Excepción: ' . $e->getMessage());
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

// ✅ REGISTRAR LA FUNCIÓN AJAX
add_action('wp_ajax_generar_formulario_pago_redsys', 'ajax_generar_formulario_pago_redsys');
add_action('wp_ajax_nopriv_generar_formulario_pago_redsys', 'ajax_generar_formulario_pago_redsys');



add_action('wp_ajax_redsys_notification', 'handle_redsys_notification');
add_action('wp_ajax_nopriv_redsys_notification', 'handle_redsys_notification');

function handle_redsys_notification() {
    error_log('🔁 Recibida notificación de Redsys (MerchantURL)');

    $params = $_POST['Ds_MerchantParameters'] ?? '';
    $signature = $_POST['Ds_Signature'] ?? '';
    $version = $_POST['Ds_SignatureVersion'] ?? '';

    if (!$params || !$signature) {
        error_log('❌ Faltan parámetros en notificación');
        status_header(400);
        exit;
    }

    $redsys = new RedsysAPI();
    $decoded = $redsys->getParametersFromResponse($params);

    $order_id = $decoded['Ds_Order'] ?? null;

    if (!$order_id) {
        error_log('❌ No se pudo obtener el ID del pedido');
        status_header(400);
        exit;
    }

    // Selecciona la clave según el entorno
    $clave = is_production_environment()
        ? 'TU_CLAVE_PRODUCCION'
        : 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    if (!$redsys->verifySignature($signature, $params, $clave)) {
        error_log('❌ Firma inválida en notificación');
        status_header(403);
        exit;
    }

    error_log('✅ Firma verificada, procesando reserva...');
    $ok = process_successful_payment($order_id, $decoded);

    if ($ok) {
        error_log("✅ Reserva procesada correctamente desde notificación");
        status_header(200);
        echo 'OK';
    } else {
        error_log("❌ Fallo procesando reserva desde notificación");
        status_header(500);
        echo 'ERROR';
    }

    exit;
}




// Inicializar el plugin
new SistemaReservas();
