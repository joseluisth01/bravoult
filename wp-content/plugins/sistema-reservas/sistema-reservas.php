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
        'includes/class-email-service.php',  // ✅ NUEVA CLASE DE EMAILS
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

    // Inicializar clase de informes
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
        add_rewrite_rule('^reservas-admin/?, 'index.php?reservas_page=dashboard', 'top');
        add_rewrite_rule('^reservas-admin/([^/]+)/?, 'index.php?reservas_page=dashboard&reservas_section=$matches[1]', 'top');
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
            metodo_pago varchar(50) DEFAULT 'tpv',
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

        // ✅ TABLA DE CONFIGURACIÓN - ACTUALIZADA
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

        // ✅ CREAR CONFIGURACIÓN POR DEFECTO ACTUALIZADA
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

    // ✅ FUNCIÓN ACTUALIZADA PARA CREAR CONFIGURACIÓN CON EMAIL ADMIN
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
            
            // Notificaciones - SIN CHECKBOX DE CONFIRMACIÓN
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
            // ✅ NUEVO CAMPO PARA EMAIL DEL ADMINISTRADOR
            array(
                'config_key' => 'email_admin_reservas',
                'config_value' => get_option('admin_email'),
                'config_group' => 'notificaciones',
                'description' => 'Email del administrador donde se enviarán las notificaciones de nuevas reservas'
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

// ✅ SHORTCODES ACTUALIZADOS

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

// ✅ SHORTCODE PARA TPV SIMULADO
add_shortcode('tpv_simulado', 'tpv_simulado_shortcode');

function tpv_simulado_shortcode() {
    // Obtener parámetros de la URL
    $amount = isset($_GET['amount']) ? sanitize_text_field($_GET['amount']) : '0.00';
    $customer_name = isset($_GET['customer_name']) ? sanitize_text_field($_GET['customer_name']) : '';
    $customer_email = isset($_GET['customer_email']) ? sanitize_email($_GET['customer_email']) : '';
    $return_url = isset($_GET['return_url']) ? esc_url($_GET['return_url']) : home_url('/');
    $cancel_url = isset($_GET['cancel_url']) ? esc_url($_GET['cancel_url']) : home_url('/');

    ob_start();
    ?>
    <style>
        .tpv-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .tpv-header {
            background: #2c5282;
            color: white;
            padding: 20px;
            margin: -30px -30px 30px -30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .tpv-header h2 {
            margin: 0;
            font-size: 24px;
        }
        .tpv-amount {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            border-left: 4px solid #38a169;
        }
        .tpv-amount .amount {
            font-size: 32px;
            font-weight: bold;
            color: #38a169;
        }
        .tpv-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #4a5568;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }
        .tpv-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #38a169;
            color: white;
        }
        .btn-primary:hover {
            background: #2f855a;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        .customer-info {
            background: #edf2f7;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .customer-info h4 {
            margin: 0 0 10px 0;
            color: #2d3748;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3182ce;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <div class="tpv-container">
        <div class="tpv-header">
            <h2>💳 Terminal de Pago Virtual (TPV)</h2>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Pasarela de Pago Segura</p>
        </div>

        <div class="customer-info">
            <h4>📋 Datos del Cliente</h4>
            <p><strong>Nombre:</strong> <?php echo esc_html($customer_name); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($customer_email); ?></p>
        </div>

        <div class="tpv-amount">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-weight: 600;">Importe a Pagar:</p>
            <div class="amount"><?php echo esc_html($amount); ?>€</div>
        </div>

        <div class="tpv-form">
            <h4 style="margin-top: 0; color: #2d3748;">💳 Datos de la Tarjeta</h4>
            <form id="tpv-form">
                <div class="form-group">
                    <label for="card_number">Número de Tarjeta:</label>
                    <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
                </div>
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="expiry">Fecha Caducidad:</label>
                        <input type="text" id="expiry" name="expiry" placeholder="MM/AA" maxlength="5" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="cvv">CVV:</label>
                        <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cardholder">Titular de la Tarjeta:</label>
                    <input type="text" id="cardholder" name="cardholder" placeholder="Nombre del titular" required>
                </div>
            </form>
        </div>

        <div class="tpv-buttons">
            <button type="button" class="btn btn-secondary" onclick="cancelPayment()">
                ❌ Cancelar Pago
            </button>
            <button type="button" class="btn btn-primary" onclick="processPayment()">
                ✅ Pagar <?php echo esc_html($amount); ?>€
            </button>
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Procesando pago seguro...</p>
        </div>

        <div style="text-align: center; margin-top: 30px; padding: 15px; background: #f0fff4; border-radius: 6px; border-left: 4px solid #38a169;">
            <p style="margin: 0; color: #2f855a; font-size: 14px;">
                🔒 <strong>TPV SIMULADO</strong> - Este es un entorno de pruebas.<br>
                Puedes usar cualquier número de tarjeta para simular el pago.
            </p>
        </div>
    </div>

    <script>
        // Variables globales
        const returnUrl = '<?php echo $return_url; ?>';
        const cancelUrl = '<?php echo $cancel_url; ?>';

        // Formatear número de tarjeta
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            if (formattedValue.length > 19) formattedValue = formattedValue.substr(0, 19);
            e.target.value = formattedValue;
        });

        // Formatear fecha de caducidad
        document.getElementById('expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0,2) + '/' + value.substring(2,4);
            }
            e.target.value = value;
        });

        // Solo números en CVV
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });

        function cancelPayment() {
            if (confirm('¿Estás seguro de que quieres cancelar el pago?')) {
                window.location.href = cancelUrl;
            }
        }

        function processPayment() {
            // Validar formulario
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const expiry = document.getElementById('expiry').value;
            const cvv = document.getElementById('cvv').value;
            const cardholder = document.getElementById('cardholder').value;

            if (!cardNumber || cardNumber.length < 13) {
                alert('Por favor, introduce un número de tarjeta válido');
                return;
            }

            if (!expiry || expiry.length !== 5) {
                alert('Por favor, introduce una fecha de caducidad válida (MM/AA)');
                return;
            }

            if (!cvv || cvv.length < 3) {
                alert('Por favor, introduce un CVV válido');
                return;
            }

            if (!cardholder.trim()) {
                alert('Por favor, introduce el nombre del titular');
                return;
            }

            // Mostrar loading
            document.querySelector('.tpv-form').style.display = 'none';
            document.querySelector('.tpv-buttons').style.display = 'none';
            document.getElementById('loading').style.display = 'block';

            // Simular procesamiento (3 segundos)
            setTimeout(function() {
                // Simular éxito del pago (95% de probabilidad)
                const success = Math.random() > 0.05;
                
                if (success) {
                    alert('✅ ¡Pago procesado correctamente!\\n\\nRedirigiendo a la confirmación de tu reserva...');
                    
                    // Redirigir a procesar pago después del TPV
                    window.location.href = returnUrl + (returnUrl.includes('?') ? '&' : '?') + 'payment_success=1';
                    
                } else {
                    alert('❌ Error en el procesamiento del pago.\\n\\nPor favor, verifica los datos de tu tarjeta e inténtalo de nuevo.');
                    
                    // Mostrar formulario de nuevo
                    document.querySelector('.tpv-form').style.display = 'block';
                    document.querySelector('.tpv-buttons').style.display = 'flex';
                    document.getElementById('loading').style.display = 'none';
                }
            }, 3000);
        }

        // Auto-focus en el primer campo
        document.getElementById('card_number').focus();
    </script>
    <?php
    return ob_get_clean();
}

// ✅ SHORTCODE PARA PÁGINA DE CONFIRMACIÓN
add_shortcode('confirmacion_reserva', 'confirmacion_reserva_shortcode');

function confirmacion_reserva_shortcode() {
    // ✅ VERIFICAR SI VIENE DEL TPV Y PROCESAR PAGO
    if (isset($_GET['payment_success']) && $_GET['payment_success'] == '1') {
        // Llamar a la función de procesamiento de pago
        add_action('wp_footer', 'process_payment_after_tpv_redirect');
        
        function process_payment_after_tpv_redirect() {
            ?>
            <script>
                // Llamar a la función JavaScript para procesar el pago
                if (typeof processPaymentAfterTPV === 'function') {
                    processPaymentAfterTPV();
                } else {
                    console.error('Función processPaymentAfterTPV no encontrada');
                }
            </script>
            <?php
        }
    }

    ob_start();
    ?>
    <style>
        .confirmacion-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            text-align: center;
        }
        .success-header {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
        }
        .success-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            font-weight: bold;
        }
        .success-header p {
            margin: 0;
            font-size: 18px;
            opacity: 0.9;
        }
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            color: #38a169;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .detail-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #38a169;
        }
        .detail-card h3 {
            margin: 0 0 15px 0;
            color: #2d3748;
            font-size: 18px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #4a5568;
            font-weight: 600;
        }
        .detail-value {
            color: #2d3748;
            font-weight: bold;
        }
        .localizador-box {
            background: #fef5e7;
            border: 2px solid #ed8936;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }
        .localizador-box h3 {
            margin: 0 0 10px 0;
            color: #c05621;
        }
        .localizador {
            font-size: 24px;
            font-weight: bold;
            color: #c05621;
            letter-spacing: 2px;
        }
        .actions {
            margin: 40px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #3182ce;
            color: white;
        }
        .btn-primary:hover {
            background: #2c5282;
            color: white;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        .info-box {
            background: #ebf8ff;
            border: 1px solid #90cdf4;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #2c5282;
        }
        .info-box ul {
            margin: 0;
            padding-left: 20px;
            text-align: left;
        }
        .info-box li {
            margin: 5px 0;
            color: #2d3748;
        }
    </style>

    <div class="confirmacion-container">
        <div class="success-header">
            <div class="checkmark">✓</div>
            <h1>¡RESERVA CONFIRMADA!</h1>
            <p>Tu reserva ha sido procesada correctamente</p>
        </div>

        <div class="localizador-box">
            <h3>📋 Tu Localizador de Reserva</h3>
            <div class="localizador" id="reservation-localizador">Cargando...</div>
            <p style="margin: 10px 0 0 0; color: #c05621; font-weight: 600;">
                Guarda este código para futuras consultas
            </p>
        </div>

        <div class="details-grid">
            <div class="detail-card">
                <h3>📅 Información del Viaje</h3>
                <div class="detail-row">
                    <span class="detail-label">Fecha:</span>
                    <span class="detail-value" id="reservation-fecha">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Hora:</span>
                    <span class="detail-value" id="reservation-hora">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Personas:</span>
                    <span class="detail-value" id="reservation-personas">-</span>
                </div>
            </div>

            <div class="detail-card">
                <h3>💰 Información del Pago</h3>
                <div class="detail-row">
                    <span class="detail-label">Total Pagado:</span>
                    <span class="detail-value" id="reservation-total" style="color: #38a169;">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Método:</span>
                    <span class="detail-value">Tarjeta de Crédito</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value" style="color: #38a169;">✅ Confirmado</span>
                </div>
            </div>
        </div>

        <div class="info-box">
            <h4>📋 Información Importante</h4>
            <ul>
                <li><strong>Presenta tu localizador</strong> al subir al autobús</li>
                <li><strong>Llega 15 minutos antes</strong> de la hora de salida</li>
                <li><strong>Los residentes</strong> deben presentar documento acreditativo</li>
                <li><strong>Los niños menores de 5 años</strong> viajan gratis sin ocupar plaza</li>
                <li><strong>Guarda este localizador</strong> para futuras consultas</li>
            </ul>
        </div>

        <div class="actions">
            <a href="/" class="btn btn-primary">🏠 Volver al Inicio</a>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ Imprimir Confirmación</button>
        </div>

        <div style="margin-top: 40px; padding: 20px; background: #f7fafc; border-radius: 8px;">
            <p style="margin: 0; color: #4a5568;">
                <strong>¿Necesitas ayuda?</strong><br>
                Si tienes alguna duda sobre tu reserva, ponte en contacto con nosotros.<br>
                <strong>¡Gracias por elegir nuestros servicios!</strong>
            </p>
        </div>
    </div>

    <script>
        // Cargar datos de la reserva confirmada
        window.addEventListener('DOMContentLoaded', function() {
            try {
                const confirmedData = sessionStorage.getItem('confirmedReservation');
                if (confirmedData) {
                    const data = JSON.parse(confirmedData);
                    console.log('Datos de confirmación:', data);
                    
                    // Rellenar información
                    document.getElementById('reservation-localizador').textContent = data.localizador || 'N/A';
                    document.getElementById('reservation-fecha').textContent = data.detalles?.fecha || 'N/A';
                    document.getElementById('reservation-hora').textContent = data.detalles?.hora || 'N/A';
                    document.getElementById('reservation-personas').textContent = data.detalles?.personas || 'N/A';
                    document.getElementById('reservation-total').textContent = (data.detalles?.precio_final || '0') + '€';
                    
                    // Limpiar sessionStorage después de mostrar
                    sessionStorage.removeItem('confirmedReservation');
                } else {
                    // Si no hay datos, mostrar información genérica
                    document.getElementById('reservation-localizador').textContent = 'CONFIRMADO';
                    console.log('No hay datos de confirmación en sessionStorage');
                }
            } catch (error) {
                console.error('Error cargando datos de confirmación:', error);
                document.getElementById('reservation-localizador').textContent = 'ERROR';
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

// Inicializar el plugin
new SistemaReservas();