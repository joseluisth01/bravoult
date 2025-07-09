<?php

/**
 * Plugin Name: Sistema de Reservas
 * Description: Sistema completo de reservas para servicios de transporte
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

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init'));
    }

    public function init()
    {
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
        'includes/class-reservas-processor.php',
        'includes/class-frontend.php',
    );

    foreach ($files as $file) {
        $path = RESERVAS_PLUGIN_PATH . $file;
        if (file_exists($path)) {
            require_once $path;
        } else {
            error_log("RESERVAS ERROR: No se pudo cargar $file");
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

    // Inicializar clase de configuración
    if (class_exists('ReservasConfigurationAdmin')) {
        $this->configuration_admin = new ReservasConfigurationAdmin();
    }

    // ✅ INICIALIZAR CLASE DE INFORMES
    if (class_exists('ReservasReportsAdmin')) {
        $this->reports_admin = new ReservasReportsAdmin();
    }

    // Inicializar procesador de reservas
    if (class_exists('ReservasProcessor')) {
        new ReservasProcessor();
    }

    if (class_exists('ReservasFrontend')) {
        new ReservasFrontend();
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

        // Flush rewrite rules para activar las nuevas URLs
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        // Limpiar rewrite rules
        flush_rewrite_rules();
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

        // Tabla de servicios
        $table_servicios = $wpdb->prefix . 'reservas_servicios';
        $sql_servicios = "CREATE TABLE $table_servicios (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            fecha date NOT NULL,
            hora time NOT NULL,
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

        // Tabla de reservas
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
            estado enum('pendiente', 'confirmada', 'cancelada') DEFAULT 'confirmada',
            metodo_pago varchar(50) DEFAULT 'simulado',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY servicio_id (servicio_id),
            KEY fecha (fecha),
            KEY estado (estado),
            KEY localizador (localizador)
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

        // ✅ TABLA DE CONFIGURACIÓN - NUEVA Y MEJORADA
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

        // ✅ CREAR CONFIGURACIÓN POR DEFECTO MEJORADA
        $this->create_default_configuration();
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

    // ✅ FUNCIÓN MEJORADA PARA CREAR CONFIGURACIÓN POR DEFECTO
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
            
            // Notificaciones
            array(
                'config_key' => 'email_confirmacion_activo',
                'config_value' => '1',
                'config_group' => 'notificaciones',
                'description' => 'Activar email de confirmación automático al cliente y administrador'
            ),
            array(
                'config_key' => 'email_recordatorio_activo',
                'config_value' => '0',
                'config_group' => 'notificaciones',
                'description' => 'Activar recordatorios antes del viaje'
            ),
            array(
                'config_key' => 'horas_recordatorio',
                'config_value' => '24',
                'config_group' => 'notificaciones',
                'description' => 'Horas antes del viaje para enviar recordatorio'
            ),
            array(
                'config_key' => 'email_remitente',
                'config_value' => get_option('admin_email'),
                'config_group' => 'notificaciones',
                'description' => 'Email remitente para notificaciones del sistema'
            ),
            array(
                'config_key' => 'nombre_remitente',
                'config_value' => get_bloginfo('name'),
                'config_group' => 'notificaciones',
                'description' => 'Nombre del remitente para notificaciones'
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
}

// Shortcode para usar en páginas de WordPress (alternativa)
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

// Inicializar el plugin
new SistemaReservas();