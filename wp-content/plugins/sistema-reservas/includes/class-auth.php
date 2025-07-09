<?php
class ReservasAuth {
    
    public function __construct() {
        add_action('wp_ajax_reservas_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_reservas_login', array($this, 'handle_login'));
        add_action('wp_ajax_reservas_logout', array($this, 'handle_logout'));
        add_action('init', array($this, 'start_session'));
    }
    
    public function start_session() {
        if (!session_id()) {
            session_start();
        }
    }
    
    public function handle_login() {
        // Funcionalidad básica
        wp_send_json_success('Login funcionando');
    }
    
    public function handle_logout() {
        session_destroy();
        wp_send_json_success(array(
            'redirect' => home_url('/reservas-login/')
        ));
    }
    
    public static function is_logged_in() {
        return isset($_SESSION['reservas_user']);
    }
    
    public static function get_current_user() {
        return isset($_SESSION['reservas_user']) ? $_SESSION['reservas_user'] : null;
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
        
        return $roles_hierarchy[$user['role']] >= $roles_hierarchy[$required_role];
    }
    
    public static function require_login() {
        if (!self::is_logged_in()) {
            wp_redirect(home_url('/reservas-login/'));
            exit;
        }
    }
    
    public static function require_permission($required_role) {
        self::require_login();
        
        if (!self::has_permission($required_role)) {
            wp_die('No tienes permisos para acceder a esta página');
        }
    }
}