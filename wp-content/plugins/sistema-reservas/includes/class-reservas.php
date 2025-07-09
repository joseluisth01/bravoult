<?php
class ReservasReservas {
    
    public function __construct() {
        // Hooks para reservas
        add_action('wp_ajax_create_reservation', array($this, 'create_reservation'));
        add_action('wp_ajax_nopriv_create_reservation', array($this, 'create_reservation'));
    }
    
    public function create_reservation() {
        // Funcionalidad de reservas se implementará después
        wp_send_json_success('Función de reservas en desarrollo');
    }
    
    public static function generate_localizador() {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }
    
    public static function calculate_total_price($adultos, $ninos, $bebes, $residentes, $servicio_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'reservas_servicios';
        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $servicio_id
        ));
        
        if (!$servicio) {
            return 0;
        }
        
        $total = 0;
        $total += $adultos * $servicio->precio_adulto;
        $total += $ninos * $servicio->precio_nino;
        $total += $residentes * $servicio->precio_residente;
        // Los bebés no pagan
        
        return $total;
    }
}