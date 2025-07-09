<?php
/**
 * Clase para gestionar envío de emails del sistema de reservas
 * Archivo: wp-content/plugins/sistema-reservas/includes/class-email-service.php
 */
class ReservasEmailService {
    
    public function __construct() {
        // No se necesitan hooks aquí, será llamado desde otras clases
    }

    /**
     * Enviar email de confirmación al cliente
     */
    public static function send_customer_confirmation($reserva_data) {
        $config = self::get_email_config();
        
        if (!$config['active']) {
            return array('success' => false, 'message' => 'Envío de emails desactivado');
        }

        $to = $reserva_data['email'];
        $subject = "Confirmación de Reserva - Localizador: " . $reserva_data['localizador'];
        
        $message = self::build_customer_email_template($reserva_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['nombre_remitente'] . ' <' . $config['email_remitente'] . '>'
        );

        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            error_log("✅ Email enviado al cliente: " . $to);
            return array('success' => true, 'message' => 'Email enviado al cliente correctamente');
        } else {
            error_log("❌ Error enviando email al cliente: " . $to);
            return array('success' => false, 'message' => 'Error enviando email al cliente');
        }
    }

    /**
     * Enviar email de notificación al administrador
     */
    public static function send_admin_notification($reserva_data) {
        $config = self::get_email_config();
        
        if (!$config['active'] || empty($config['email_admin'])) {
            return array('success' => false, 'message' => 'Envío de emails o email admin no configurado');
        }

        $to = $config['email_admin'];
        $subject = "Nueva Reserva Recibida - " . $reserva_data['localizador'];
        
        $message = self::build_admin_email_template($reserva_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['nombre_remitente'] . ' <' . $config['email_remitente'] . '>'
        );

        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            error_log("✅ Email enviado al administrador: " . $to);
            return array('success' => true, 'message' => 'Email enviado al administrador correctamente');
        } else {
            error_log("❌ Error enviando email al administrador: " . $to);
            return array('success' => false, 'message' => 'Error enviando email al administrador');
        }
    }

    /**
     * Obtener configuración de email
     */
    private static function get_email_config() {
        if (!class_exists('ReservasConfigurationAdmin')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-configuration-admin.php';
        }

        return array(
            'active' => true, // Siempre activo
            'email_remitente' => ReservasConfigurationAdmin::get_config('email_remitente', get_option('admin_email')),
            'nombre_remitente' => ReservasConfigurationAdmin::get_config('nombre_remitente', get_bloginfo('name')),
            'email_admin' => ReservasConfigurationAdmin::get_config('email_admin_reservas', ''),
        );
    }

    /**
     * Template de email para el cliente
     */
    private static function build_customer_email_template($reserva) {
        $fecha_formateada = date('d/m/Y', strtotime($reserva['fecha']));
        $fecha_creacion = date('d/m/Y H:i', strtotime($reserva['created_at'] ?? 'now'));
        
        $personas_detalle = "";
        if ($reserva['adultos'] > 0) $personas_detalle .= "Adultos: " . $reserva['adultos'] . "<br>";
        if ($reserva['residentes'] > 0) $personas_detalle .= "Residentes: " . $reserva['residentes'] . "<br>";
        if ($reserva['ninos_5_12'] > 0) $personas_detalle .= "Niños (5-12 años): " . $reserva['ninos_5_12'] . "<br>";
        if ($reserva['ninos_menores'] > 0) $personas_detalle .= "Niños menores (gratis): " . $reserva['ninos_menores'] . "<br>";

        $descuento_info = "";
        if ($reserva['descuento_total'] > 0) {
            $descuento_info = "<tr>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Descuentos aplicados:</strong></td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right; color: #e74c3c;'>-" . number_format($reserva['descuento_total'], 2) . "€</td>
            </tr>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmación de Reserva</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            
            <div style='background: #8B4513; color: white; text-align: center; padding: 20px; margin-bottom: 20px;'>
                <h1 style='margin: 0; font-size: 24px;'>¡RESERVA CONFIRMADA!</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px;'>Gracias por confiar en nosotros</p>
            </div>

            <div style='background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px;'>
                <h2 style='color: #8B4513; margin-top: 0;'>📋 Datos de tu Reserva</h2>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Localizador:</strong></td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right; font-size: 18px; color: #8B4513; font-weight: bold;'>" . $reserva['localizador'] . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Fecha del servicio:</strong></td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>" . $fecha_formateada . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Hora:</strong></td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>" . substr($reserva['hora'], 0, 5) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Fecha de reserva:</strong></td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>" . $fecha_creacion . "</td>
                    </tr>
                </table>
            </div>

            <div style='background: #e3f2fd; padding: 20px; margin-bottom: 20px; border-radius: 8px;'>
                <h3 style='color: #1565c0; margin-top: 0;'>👤 Datos del Cliente</h3>
                <p><strong>Nombre:</strong> " . $reserva['nombre'] . " " . $reserva['apellidos'] . "</p>
                <p><strong>Email:</strong> " . $reserva['email'] . "</p>
                <p><strong>Teléfono:</strong> " . $reserva['telefono'] . "</p>
            </div>

            <div style='background: #fff3e0; padding: 20px; margin-bottom: 20px; border-radius: 8px;'>
                <h3 style='color: #ef6c00; margin-top: 0;'>👥 Distribución de Personas</h3>
                " . $personas_detalle . "
                <p style='margin-top: 15px;'><strong>Total personas con plaza:</strong> " . $reserva['total_personas'] . "</p>
            </div>

            <div style='background: #e8f5e8; padding: 20px; margin-bottom: 20px; border-radius: 8px;'>
                <h3 style='color: #2e7d32; margin-top: 0;'>💰 Resumen de Precios</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Precio base:</strong></td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>" . number_format($reserva['precio_base'], 2) . "€</td>
                    </tr>
                    " . $descuento_info . "
                    <tr style='background: #c8e6c9;'>
                        <td style='padding: 12px; border-bottom: 2px solid #2e7d32; font-size: 18px;'><strong>TOTAL PAGADO:</strong></td>
                        <td style='padding: 12px; border-bottom: 2px solid #2e7d32; text-align: right; font-size: 18px; font-weight: bold; color: #2e7d32;'>" . number_format($reserva['precio_final'], 2) . "€</td>
                    </tr>
                </table>
            </div>

            <div style='background: #fff8e1; padding: 20px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #ffc107;'>
                <h3 style='color: #f57f17; margin-top: 0;'>📋 Información Importante</h3>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li>Guarda este localizador: <strong>" . $reserva['localizador'] . "</strong></li>
                    <li>Preséntate 15 minutos antes de la hora de salida</li>
                    <li>Los residentes deben presentar documento acreditativo</li>
                    <li>Los niños menores de 5 años viajan gratis sin ocupar plaza</li>
                    <li>Para cualquier consulta, contacta con nosotros</li>
                </ul>
            </div>

            <div style='text-align: center; padding: 20px; background: #f5f5f5; border-radius: 8px; margin-top: 30px;'>
                <p style='margin: 0; color: #666; font-size: 14px;'>
                    Este es un email automático de confirmación.<br>
                    Si tienes alguna duda, ponte en contacto con nosotros.
                </p>
                <p style='margin: 10px 0 0 0; color: #8B4513; font-weight: bold;'>
                    ¡Gracias por elegir nuestros servicios!
                </p>
            </div>

        </body>
        </html>";
    }

    /**
     * Template de email para el administrador
     */
    private static function build_admin_email_template($reserva) {
        $fecha_formateada = date('d/m/Y', strtotime($reserva['fecha']));
        $fecha_creacion = date('d/m/Y H:i', strtotime($reserva['created_at'] ?? 'now'));
        
        $personas_detalle = "Adultos: " . $reserva['adultos'] . " | Residentes: " . $reserva['residentes'] . " | Niños 5-12: " . $reserva['ninos_5_12'] . " | Menores: " . $reserva['ninos_menores'];

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Nueva Reserva Recibida</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            
            <div style='background: #dc3545; color: white; text-align: center; padding: 20px; margin-bottom: 20px;'>
                <h1 style='margin: 0; font-size: 24px;'>🎫 NUEVA RESERVA RECIBIDA</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px;'>Se ha procesado una nueva reserva en el sistema</p>
            </div>

            <div style='background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px;'>
                <h2 style='color: #dc3545; margin-top: 0;'>📋 Información de la Reserva</h2>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Localizador:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; font-size: 18px; color: #dc3545; font-weight: bold;'>" . $reserva['localizador'] . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Fecha servicio:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . $fecha_formateada . " a las " . substr($reserva['hora'], 0, 5) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Fecha reserva:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . $fecha_creacion . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Total personas:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . $reserva['total_personas'] . " plazas ocupadas</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Importe:</td>
                        <td style='padding: 8px; border-bottom: 1px solid #ddd; font-size: 16px; color: #28a745; font-weight: bold;'>" . number_format($reserva['precio_final'], 2) . "€</td>
                    </tr>
                </table>
            </div>

            <div style='background: #e3f2fd; padding: 20px; margin-bottom: 20px; border-radius: 8px;'>
                <h3 style='color: #1565c0; margin-top: 0;'>👤 Datos del Cliente</h3>
                <table style='width: 100%;'>
                    <tr>
                        <td style='padding: 4px 0; font-weight: bold;'>Nombre completo:</td>
                        <td style='padding: 4px 0;'>" . $reserva['nombre'] . " " . $reserva['apellidos'] . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 4px 0; font-weight: bold;'>Email:</td>
                        <td style='padding: 4px 0;'><a href='mailto:" . $reserva['email'] . "'>" . $reserva['email'] . "</a></td>
                    </tr>
                    <tr>
                        <td style='padding: 4px 0; font-weight: bold;'>Teléfono:</td>
                        <td style='padding: 4px 0;'><a href='tel:" . $reserva['telefono'] . "'>" . $reserva['telefono'] . "</a></td>
                    </tr>
                </table>
            </div>

            <div style='background: #fff3e0; padding: 20px; margin-bottom: 20px; border-radius: 8px;'>
                <h3 style='color: #ef6c00; margin-top: 0;'>👥 Distribución</h3>
                <p style='margin: 0; font-size: 16px;'>" . $personas_detalle . "</p>
            </div>

            <div style='text-align: center; padding: 20px; background: #f5f5f5; border-radius: 8px;'>
                <p style='margin: 0; color: #666;'>
                    Puedes gestionar esta reserva desde el panel de administración del sistema.
                </p>
            </div>

        </body>
        </html>";
    }

    /**
     * Reenviar email de confirmación
     */
    public static function resend_confirmation($reserva_id) {
        global $wpdb;
        
        $table_reservas = $wpdb->prefix . 'reservas_reservas';
        
        $reserva = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_reservas WHERE id = %d",
            $reserva_id
        ));
        
        if (!$reserva) {
            return array('success' => false, 'message' => 'Reserva no encontrada');
        }
        
        // Convertir objeto a array para el template
        $reserva_array = (array) $reserva;
        
        return self::send_customer_confirmation($reserva_array);
    }
}