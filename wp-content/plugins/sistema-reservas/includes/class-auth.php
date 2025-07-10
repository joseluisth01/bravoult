<?php
class ReservasAuth {
    
    public function __construct() {
        add_action('wp_ajax_reservas_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_reservas_login', array($this, 'handle_login'));
        add_action('wp_ajax_reservas_logout', array($this, 'handle_logout'));
        add_action('init', array($this, 'start_session'));
        
        // ✅ AGREGAR HOOK PARA CONFIGURAR HEADERS DE SESIÓN
        add_action('init', array($this, 'configure_session_security'));
    }
    
public function start_session() {
    // ✅ MEJORAR GESTIÓN DE SESIONES
    if (!session_id() && !headers_sent()) {
        // Configurar parámetros de sesión más seguros
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', is_ssl() ? 1 : 0);
        ini_set('session.cookie_samesite', 'Lax');
        
        session_start();
        
        // ✅ LOG PARA DEBUG
        error_log('SESIÓN INICIADA: ' . session_id() . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
}
    
    // ✅ NUEVA FUNCIÓN PARA CONFIGURAR SEGURIDAD DE SESIÓN
    public function configure_session_security() {
        // Solo configurar en las páginas del sistema de reservas
        if (strpos($_SERVER['REQUEST_URI'], 'reservas-') !== false) {
            // Permitir CORS para AJAX
            header('Access-Control-Allow-Credentials: true');
            
            // Configurar headers de seguridad
            if (!headers_sent()) {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
            }
        }
    }
    
    public function handle_login() {
        // ✅ VERIFICAR NONCE DE FORMA MÁS ROBUSTA
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            error_log('RESERVAS AUTH: Nonce verification failed');
            wp_send_json_error('Error de seguridad - nonce inválido');
            return;
        }
        
        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            wp_send_json_error('Usuario y contraseña son obligatorios');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE username = %s AND status = 'active'",
            $username
        ));
        
        if ($user && password_verify($password, $user->password)) {
            // ✅ INICIAR SESIÓN DE FORMA MÁS ROBUSTA
            if (!session_id()) {
                $this->start_session();
            }
            
            // ✅ REGENERAR ID DE SESIÓN POR SEGURIDAD
            session_regenerate_id(true);
            
            $_SESSION['reservas_user'] = array(
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'login_time' => time(),
                'ip_address' => $this->get_client_ip() // Para validación adicional
            );
            
            error_log('RESERVAS AUTH: Login successful for user: ' . $username);
            
            wp_send_json_success(array(
                'redirect' => home_url('/reservas-admin/'),
                'message' => 'Login exitoso'
            ));
        } else {
            error_log('RESERVAS AUTH: Login failed for user: ' . $username);
            wp_send_json_error('Usuario o contraseña incorrectos');
        }
    }
    
    public function handle_logout() {
        if (!session_id()) {
            session_start();
        }
        
        // ✅ LIMPIAR DATOS DE SESIÓN ESPECÍFICOS
        if (isset($_SESSION['reservas_user'])) {
            unset($_SESSION['reservas_user']);
        }
        
        // ✅ DESTRUIR SESIÓN COMPLETAMENTE
        session_destroy();
        
        wp_send_json_success(array(
            'redirect' => home_url('/reservas-login/')
        ));
    }
    
    public static function is_logged_in() {
        if (!session_id()) {
            session_start();
        }
        
        // ✅ VERIFICACIÓN MÁS ROBUSTA DE SESIÓN
        if (!isset($_SESSION['reservas_user'])) {
            return false;
        }
        
        // ✅ VERIFICAR QUE LA SESIÓN NO HAYA EXPIRADO
        $login_time = $_SESSION['reservas_user']['login_time'] ?? 0;
        $session_duration = 86400; // 24 horas
        
        if ((time() - $login_time) > $session_duration) {
            // Sesión expirada
            unset($_SESSION['reservas_user']);
            return false;
        }
        
        return true;
    }
    
    public static function get_current_user() {
        if (!self::is_logged_in()) {
            return null;
        }
        
        return $_SESSION['reservas_user'];
    }
    
    public static function has_permission($required_role) {
        if (!self::is_logged_in()) {
            return false;
        }
        
        $user = self::get_current_user();
        $roles_hierarchy = array(
            'super_admin' => 4,
            'admin' => 3,
            'agencia' => 2,
            'conductor' => 1
        );
        
        $user_level = $roles_hierarchy[$user['role']] ?? 0;
        $required_level = $roles_hierarchy[$required_role] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    public static function require_login() {
        if (!self::is_logged_in()) {
            // ✅ MANEJAR PETICIONES AJAX DE FORMA DIFERENTE
            if (wp_doing_ajax()) {
                wp_send_json_error('Sesión expirada. Recarga la página e inicia sesión nuevamente.');
                return;
            }
            
            wp_redirect(home_url('/reservas-login/'));
            exit;
        }
    }
    
    public static function require_permission($required_role) {
        self::require_login();
        
        if (!self::has_permission($required_role)) {
            // ✅ MANEJAR PETICIONES AJAX DE FORMA DIFERENTE
            if (wp_doing_ajax()) {
                wp_send_json_error('No tienes permisos para realizar esta acción.');
                return;
            }
            
            wp_die('No tienes permisos para acceder a esta página');
        }
    }
    
    // ✅ NUEVA FUNCIÓN PARA OBTENER IP DEL CLIENTE
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    // ✅ NUEVA FUNCIÓN PARA VALIDAR NONCE DE FORMA MÁS ROBUSTA
    public static function verify_ajax_nonce() {
        if (!isset($_POST['nonce'])) {
            error_log('RESERVAS AUTH: No nonce provided in AJAX request');
            wp_send_json_error('Error de seguridad: nonce faltante');
            return false;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            error_log('RESERVAS AUTH: Invalid nonce in AJAX request');
            wp_send_json_error('Error de seguridad: nonce inválido');
            return false;
        }
        
        return true;
    }
}