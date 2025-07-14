<?php

/**
 * Clase para gestionar envío de emails del sistema de reservas - CON RECORDATORIOS AUTOMÁTICOS
 * Archivo: wp-content/plugins/sistema-reservas/includes/class-email-service.php
 */
class ReservasEmailService
{
    public function __construct()
    {
        // No se necesitan hooks aquí, será llamado desde otras clases
    }

    /**
     * Enviar email de confirmación al cliente CON PDF ADJUNTO
     */
    public static function send_customer_confirmation($reserva_data)
    {
        $config = self::get_email_config();

        $to = $reserva_data['email'];
        $subject = "Confirmación de Reserva - Localizador: " . $reserva_data['localizador'];

        $message = self::build_customer_email_template($reserva_data);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['nombre_remitente'] . ' <' . $config['email_remitente'] . '>'
        );

        // ✅ ENCONTRAR ESTAS LÍNEAS (alrededor de la línea 50-70):
        $attachments = array();
        try {
            error_log('=== INICIANDO GENERACIÓN DE PDF ===');
            $pdf_path = self::generate_ticket_pdf($reserva_data);
            error_log('PDF generado en: ' . $pdf_path);

            if ($pdf_path && file_exists($pdf_path)) {
                $file_size = filesize($pdf_path);
                error_log("✅ PDF existe - Tamaño: $file_size bytes");

                if ($file_size > 0) {
                    $attachments[] = $pdf_path;
                    error_log("✅ PDF añadido a attachments: " . $pdf_path);
                } else {
                    error_log("❌ PDF está vacío");
                }
            } else {
                error_log("❌ PDF no existe en: $pdf_path");
            }
        } catch (Exception $e) {
            error_log("❌ Error generando PDF: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            // Continuar enviando email sin PDF si hay error
        }

        // ✅ AÑADIR DEBUG ANTES DE ENVIAR
        error_log("=== ENVIANDO EMAIL ===");
        error_log("To: " . $to);
        error_log("Subject: " . $subject);
        error_log("Attachments: " . print_r($attachments, true));

        $sent = wp_mail($to, $subject, $message, $headers, $attachments);

        error_log("Email enviado: " . ($sent ? 'SÍ' : 'NO'));

        // ✅ NO ELIMINAR EL PDF HASTA DESPUÉS DEL EMAIL
        if ($sent) {
            error_log("✅ Email enviado al cliente: " . $to . " (con PDF: " . (!empty($attachments) ? 'SÍ' : 'NO') . ")");

            // ✅ AHORA SÍ ELIMINAR ARCHIVOS TEMPORALES
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment)) {
                        unlink($attachment);
                        error_log("🗑️ Archivo temporal eliminado: " . $attachment);
                    }
                }
            }

            return array('success' => true, 'message' => 'Email enviado al cliente correctamente');
        } else {
            error_log("❌ Error enviando email al cliente: " . $to);

            // Limpiar archivos aunque falle el email
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment)) {
                        unlink($attachment);
                    }
                }
            }

            return array('success' => false, 'message' => 'Error enviando email al cliente');
        }
    }

    /**
     * ✅ NUEVA FUNCIÓN: Generar PDF del billete
     */
    private static function generate_ticket_pdf($reserva_data)
    {
        // Cargar la clase del generador de PDF
        if (!class_exists('ReservasPDFGenerator')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-pdf-generator.php';
        }

        // Verificar si TCPDF está disponible
        if (!class_exists('TCPDF')) {
            // Intentar cargar TCPDF desde diferentes ubicaciones posibles
            $tcpdf_paths = array(
                ABSPATH . 'wp-content/plugins/sistema-reservas/vendor/tcpdf/tcpdf.php',
                ABSPATH . 'wp-includes/tcpdf/tcpdf.php',
                '/usr/share/php/tcpdf/tcpdf.php'
            );

            $tcpdf_loaded = false;
            foreach ($tcpdf_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $tcpdf_loaded = true;
                    break;
                }
            }

            if (!$tcpdf_loaded) {
                throw new Exception('TCPDF no está disponible. Instala la librería TCPDF.');
            }
        }

        $pdf_generator = new ReservasPDFGenerator();
        return $pdf_generator->generate_ticket_pdf($reserva_data);
    }

    /**
     * Enviar email de notificación al administrador (SIN PDF)
     */
    public static function send_admin_notification($reserva_data)
    {
        $config = self::get_email_config();

        if (empty($config['email_reservas'])) {
            error_log("❌ No hay email de reservas configurado");
            return array('success' => false, 'message' => 'Email de reservas no configurado');
        }

        $to = $config['email_reservas'];
        $subject = "Nueva Reserva Recibida - " . $reserva_data['localizador'];

        $message = self::build_admin_email_template($reserva_data);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['nombre_remitente'] . ' <' . $config['email_remitente'] . '>'
        );

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            error_log("✅ Email enviado al email de reservas: " . $to);
            return array('success' => true, 'message' => 'Email enviado al administrador correctamente');
        } else {
            error_log("❌ Error enviando email al email de reservas: " . $to);
            return array('success' => false, 'message' => 'Error enviando email al administrador');
        }
    }

    /**
     * Enviar email de recordatorio al cliente CON PDF ADJUNTO
     */
    public static function send_reminder_email($reserva_data)
    {
        $config = self::get_email_config();

        $to = $reserva_data['email'];
        $fecha_servicio = date('d/m/Y', strtotime($reserva_data['fecha']));
        $subject = "Recordatorio - Tu viaje es mañana - Localizador: " . $reserva_data['localizador'];

        $message = self::build_reminder_email_template($reserva_data);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['nombre_remitente'] . ' <' . $config['email_remitente'] . '>'
        );

        // ✅ ADJUNTAR PDF TAMBIÉN EN RECORDATORIOS
        $attachments = array();
        try {
            $pdf_path = self::generate_ticket_pdf($reserva_data);
            if ($pdf_path && file_exists($pdf_path)) {
                $attachments[] = $pdf_path;
                error_log("✅ PDF generado para recordatorio: " . $pdf_path);
            }
        } catch (Exception $e) {
            error_log("❌ Error generando PDF para recordatorio: " . $e->getMessage());
        }

        $sent = wp_mail($to, $subject, $message, $headers, $attachments);

        // Limpiar archivo temporal
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    unlink($attachment);
                }
            }
        }

        if ($sent) {
            error_log("✅ Email de recordatorio enviado al cliente: " . $to);
            return array('success' => true, 'message' => 'Recordatorio enviado correctamente');
        } else {
            error_log("❌ Error enviando recordatorio al cliente: " . $to);
            return array('success' => false, 'message' => 'Error enviando recordatorio');
        }
    }

    /**
     * Obtener configuración de email desde la base de datos
     */
    private static function get_email_config()
    {
        if (!class_exists('ReservasConfigurationAdmin')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-configuration-admin.php';
        }

        return array(
            'email_remitente' => ReservasConfigurationAdmin::get_config('email_remitente', get_option('admin_email')),
            'nombre_remitente' => ReservasConfigurationAdmin::get_config('nombre_remitente', get_bloginfo('name')),
            'email_reservas' => ReservasConfigurationAdmin::get_config('email_reservas', get_option('admin_email')),
        );
    }

    private static function build_customer_email_template($reserva)
    {
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
            <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; background: #FFF8DC; font-weight: 600; color: #871727;'>Descuentos aplicados:</td>
            <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; background: #FFF8DC; text-align: right; color: #871727; font-weight: bold; font-size: 16px;'>-" . number_format($reserva['descuento_total'], 2) . "€</td>
        </tr>";
        }

        return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Confirmación de Reserva - Medina Azahara</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        </style>
    </head>
    <body style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, sans-serif; line-height: 1.6; color: #2D2D2D; max-width: 600px; margin: 0 auto; padding: 0; background: #FAFAFA;'>
        
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #871727 0%, #A91D33 100%); color: #FFFFFF; text-align: center; padding: 50px 30px;'>
            <h1 style='margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.5px;'>RESERVA CONFIRMADA</h1>
            <div style='width: 60px; height: 3px; background: #EFCF4B; margin: 20px auto; border-radius: 2px;'></div>
            <p style='margin: 0; font-size: 18px; font-weight: 500; opacity: 0.95;'>Tu viaje a Medina Azahara está asegurado</p>
        </div>

        <!-- Contenido principal -->
        <div style='background: #FFFFFF; padding: 0;'>
            
            <!-- Localizador destacado -->
            <div style='background: #EFCF4B; padding: 30px; text-align: center; border-bottom: 1px solid #E0E0E0;'>
                <h2 style='margin: 0 0 10px 0; font-size: 16px; font-weight: 600; color: #2D2D2D; text-transform: uppercase; letter-spacing: 1px;'>LOCALIZADOR DE RESERVA</h2>
                <div style='font-size: 28px; font-weight: 700; color: #871727; letter-spacing: 3px; font-family: monospace; margin: 10px 0;'>" . $reserva['localizador'] . "</div>
                <p style='margin: 0; font-size: 14px; color: #2D2D2D; font-weight: 500;'>Presenta este código al subir al autobús</p>
            </div>

            <!-- Información de la reserva -->
            <div style='padding: 40px 30px; border-bottom: 1px solid #E0E0E0;'>
                <h3 style='margin: 0 0 25px 0; font-size: 20px; font-weight: 700; color: #871727; text-align: center;'>Detalles de tu Reserva</h3>
                
                <table style='width: 100%; border-collapse: collapse; background: #FFFFFF; border: 2px solid #EFCF4B; border-radius: 8px; overflow: hidden;'>
                    <tr>
                        <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; font-weight: 600; color: #2D2D2D;'>Fecha del viaje:</td>
                        <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; text-align: right; font-weight: 700; color: #871727;'>" . $fecha_formateada . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; font-weight: 600; color: #2D2D2D;'>Hora de salida:</td>
                        <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; text-align: right; font-weight: 700; color: #871727; font-size: 18px;'>" . substr($reserva['hora'], 0, 5) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; font-weight: 600; color: #2D2D2D;'>Fecha de reserva:</td>
                        <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; text-align: right; color: #666666;'>" . $fecha_creacion . "</td>
                    </tr>
                </table>
            </div>

            <!-- Datos del cliente -->
            <div style='padding: 40px 30px; background: #F8F9FA; border-bottom: 1px solid #E0E0E0;'>
                <h3 style='margin: 0 0 25px 0; font-size: 20px; font-weight: 700; color: #871727; text-align: center;'>Datos del Viajero</h3>
                
                <div style='background: #FFFFFF; padding: 25px; border-radius: 8px; border: 1px solid #E0E0E0;'>
                    <p style='margin: 8px 0; color: #2D2D2D; font-size: 16px;'><strong style='color: #871727;'>Nombre completo:</strong> " . $reserva['nombre'] . " " . $reserva['apellidos'] . "</p>
                    <p style='margin: 8px 0; color: #2D2D2D; font-size: 16px;'><strong style='color: #871727;'>Email:</strong> " . $reserva['email'] . "</p>
                    <p style='margin: 8px 0; color: #2D2D2D; font-size: 16px;'><strong style='color: #871727;'>Teléfono:</strong> " . $reserva['telefono'] . "</p>
                </div>
            </div>

            <!-- Distribución de personas -->
            <div style='padding: 40px 30px; border-bottom: 1px solid #E0E0E0;'>
                <h3 style='margin: 0 0 25px 0; font-size: 20px; font-weight: 700; color: #871727; text-align: center;'>Distribución de Viajeros</h3>
                
                <div style='background: #F8F9FA; padding: 25px; border-radius: 8px; border: 1px solid #E0E0E0;'>
                    <div style='font-size: 16px; color: #2D2D2D; line-height: 1.8;'>
                        " . $personas_detalle . "
                    </div>
                    <div style='margin-top: 20px; padding-top: 20px; border-top: 2px solid #EFCF4B; text-align: center;'>
                        <p style='margin: 0; font-weight: 700; color: #871727; font-size: 18px;'>Total personas con plaza: " . $reserva['total_personas'] . "</p>
                    </div>
                </div>
            </div>

            <!-- Resumen de precios -->
            <div style='padding: 40px 30px; background: #F8F9FA;'>
                <h3 style='margin: 0 0 25px 0; font-size: 20px; font-weight: 700; color: #871727; text-align: center;'>Resumen de Precios</h3>
                
                <table style='width: 100%; border-collapse: collapse; background: #FFFFFF; border: 2px solid #EFCF4B; border-radius: 8px; overflow: hidden;'>
                    <tr>
                        <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; font-weight: 600; color: #2D2D2D;'>Precio base:</td>
                        <td style='padding: 15px 25px; border-bottom: 1px solid #E0E0E0; text-align: right; font-weight: 600; color: #2D2D2D;'>" . number_format($reserva['precio_base'], 2) . "€</td>
                    </tr>
                    " . $descuento_info . "
                    <tr style='background: #871727;'>
                        <td style='padding: 20px 25px; font-size: 20px; font-weight: 700; color: #FFFFFF;'>TOTAL PAGADO:</td>
                        <td style='padding: 20px 25px; text-align: right; font-size: 24px; font-weight: 700; color: #FFFFFF;'>" . number_format($reserva['precio_final'], 2) . "€</td>
                    </tr>
                </table>
            </div>

            <!-- Información importante -->
            <div style='padding: 40px 30px; background: #FFFFFF;'>
                <h3 style='margin: 0 0 25px 0; font-size: 20px; font-weight: 700; color: #871727; text-align: center;'>Información Importante</h3>
                
                <div style='background: #F8F9FA; padding: 30px; border-radius: 8px; border-left: 4px solid #EFCF4B;'>
                    <ul style='margin: 0; padding-left: 25px; color: #2D2D2D; line-height: 1.8; font-size: 16px;'>
                        <li style='margin: 12px 0;'><strong style='color: #871727;'>Presenta tu localizador:</strong> <span style='background: #EFCF4B; color: #2D2D2D; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-family: monospace;'>" . $reserva['localizador'] . "</span> al subir al autobús</li>
                        <li style='margin: 12px 0;'><strong style='color: #871727;'>Puntualidad:</strong> Preséntate 15 minutos antes de la hora de salida</li>
                        <li style='margin: 12px 0;'><strong style='color: #871727;'>Residentes:</strong> Deben presentar documento acreditativo de residencia en Córdoba</li>
                        <li style='margin: 12px 0;'><strong style='color: #871727;'>Niños menores:</strong> Los menores de 5 años viajan gratis sin ocupar plaza</li>
                        <li style='margin: 12px 0;'><strong style='color: #871727;'>Contacto:</strong> Para cualquier consulta, contacta con nosotros</li>
                    </ul>
                </div>
                
                <!-- Mensaje final -->
                <div style='text-align: center; margin-top: 40px; padding: 30px; background: #871727; border-radius: 8px;'>
                    <p style='margin: 0; color: #FFFFFF; font-size: 20px; font-weight: 700;'>
                        ¡Disfruta de tu visita a Medina Azahara!
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style='text-align: center; padding: 40px 30px; background: #2D2D2D; color: #FFFFFF;'>
            <div style='width: 40px; height: 2px; background: #EFCF4B; margin: 0 auto 20px;'></div>
            <p style='margin: 0 0 15px 0; font-size: 14px; opacity: 0.8; line-height: 1.6;'>
                Este es un email automático de confirmación de tu reserva.<br>
                Si tienes alguna duda, ponte en contacto con nosotros.
            </p>
            <p style='margin: 0; color: #EFCF4B; font-weight: 600; font-size: 16px;'>
                Gracias por elegir nuestros servicios
            </p>
        </div>

    </body>
    </html>";
    }

    private static function build_reminder_email_template($reserva)
    {
        $fecha_formateada = date('d/m/Y', strtotime($reserva['fecha']));
        $dia_semana = date('l', strtotime($reserva['fecha']));
        $dias_semana_es = array(
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        );
        $dia_semana_es = $dias_semana_es[$dia_semana] ?? $dia_semana;

        $personas_detalle = "";
        if ($reserva['adultos'] > 0) $personas_detalle .= "Adultos: " . $reserva['adultos'] . "<br>";
        if ($reserva['residentes'] > 0) $personas_detalle .= "Residentes: " . $reserva['residentes'] . "<br>";
        if ($reserva['ninos_5_12'] > 0) $personas_detalle .= "Niños (5-12 años): " . $reserva['ninos_5_12'] . "<br>";
        if ($reserva['ninos_menores'] > 0) $personas_detalle .= "Niños menores (gratis): " . $reserva['ninos_menores'] . "<br>";

        return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Recordatorio de Viaje - Medina Azahara</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Open+Sans:wght@400;600&display=swap');
        </style>
    </head>
    <body style='font-family: \"Open Sans\", Arial, sans-serif; line-height: 1.6; color: #2C1810; max-width: 650px; margin: 0 auto; padding: 0; background: #F5F2E8;'>
        
        <!-- Header con estilo urgente pero elegante -->
        <div style='background: linear-gradient(135deg, #FF8C00 0%, #FF7F50 50%, #FF6347 100%); color: #FFF; text-align: center; padding: 40px 20px; position: relative; background-image: url(\"data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23D4AF37' fill-opacity='0.1'%3E%3Cpath d='m0 40l40-40h-40v40zm40 0v-40h-40l40 40z'/%3E%3C/g%3E%3C/svg%3E\"); border-bottom: 5px solid #D4AF37;'>
            
            <!-- Icono de alerta elegante -->
            <div style='margin-bottom: 20px; font-size: 40px; color: #D4AF37;'>🚌</div>
            
            <h1 style='margin: 0; font-family: \"Playfair Display\", serif; font-size: 32px; font-weight: 700; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); letter-spacing: 1px;'>RECORDATORIO DE VIAJE</h1>
            <div style='width: 100px; height: 3px; background: #D4AF37; margin: 15px auto; border-radius: 2px;'></div>
            <p style='margin: 15px 0 0 0; font-size: 20px; font-weight: 600;'>¡Tu visita a Medina Azahara es muy pronto!</p>
            
            <!-- Countdown visual -->
            <div style='margin-top: 25px; background: rgba(255,255,255,0.2); padding: 15px; border-radius: 15px; border: 2px solid #D4AF37;'>
                <p style='margin: 0; font-size: 18px; font-weight: bold;'>🕐 Tu viaje es mañana</p>
            </div>
        </div>

        <!-- Contenido principal -->
        <div style='background: #FEFCF7; padding: 0;'>
            
            <!-- Sección de datos urgentes del viaje -->
            <div style='background: #FFF; margin: 0; padding: 30px; border-left: 8px solid #FF8C00; border-right: 8px solid #FF8C00;'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <h2 style='color: #FF6347; margin: 0; font-family: \"Playfair Display\", serif; font-size: 28px; font-weight: 700;'>📅 Detalles de tu Viaje</h2>
                    <div style='width: 60px; height: 2px; background: #FF8C00; margin: 10px auto;'></div>
                </div>
                
                <table style='width: 100%; border-collapse: collapse; background: #FFF8DC; border: 3px solid #FF8C00; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(255, 140, 0, 0.3);'>
                    <tr>
                        <td style='padding: 15px 20px; border-bottom: 2px solid #FF8C00; background: linear-gradient(135deg, #FFE4B5 0%, #FFDAB9 100%); font-weight: bold; color: #8B4513;'>LOCALIZADOR</td>
                        <td style='padding: 15px 20px; border-bottom: 2px solid #FF8C00; text-align: right; font-size: 24px; color: #FF6347; font-weight: bold; background: linear-gradient(135deg, #FFE4B5 0%, #FFDAB9 100%); letter-spacing: 2px; font-family: monospace;'>" . $reserva['localizador'] . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 20px; border-bottom: 1px solid #FFE4B5; font-weight: 600; color: #8B4513;'>Fecha:</td>
                        <td style='padding: 12px 20px; border-bottom: 1px solid #FFE4B5; text-align: right; font-weight: bold; color: #2C1810; font-size: 18px;'>" . $dia_semana_es . ", " . $fecha_formateada . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 20px; border-bottom: 1px solid #FFE4B5; font-weight: 600; color: #8B4513;'>Hora de salida:</td>
                        <td style='padding: 12px 20px; border-bottom: 1px solid #FFE4B5; text-align: right; font-weight: bold; color: #FF6347; font-size: 22px;'>" . substr($reserva['hora'], 0, 5) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 20px; font-weight: 600; color: #8B4513;'>Cliente:</td>
                        <td style='padding: 12px 20px; text-align: right; font-weight: bold; color: #2C1810;'>" . $reserva['nombre'] . " " . $reserva['apellidos'] . "</td>
                    </tr>
                </table>
            </div>

            <!-- Sección de personas en la reserva -->
            <div style='background: linear-gradient(135deg, #FFF8DC 0%, #FFE4B5 100%); padding: 30px; border-left: 8px solid #FF8C00; border-right: 8px solid #FF8C00;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h3 style='color: #8B4513; margin: 0; font-family: \"Playfair Display\", serif; font-size: 24px; font-weight: 700;'>👥 Personas en tu Reserva</h3>
                    <div style='width: 50px; height: 2px; background: #FF8C00; margin: 8px auto;'></div>
                </div>
                
                <div style='background: rgba(255, 255, 255, 0.9); padding: 20px; border-radius: 10px; border: 2px solid #FF8C00;'>
                    " . $personas_detalle . "
                    <div style='margin-top: 15px; padding-top: 15px; border-top: 2px solid #FF8C00;'>
                        <p style='margin: 0; font-weight: bold; color: #8B4513; font-size: 16px;'>Total personas con plaza: " . $reserva['total_personas'] . "</p>
                    </div>
                </div>
            </div>

            <!-- Recordatorios importantes -->
            <div style='background: #FFF; padding: 30px; border-left: 8px solid #FF8C00; border-right: 8px solid #FF8C00;'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <h3 style='color: #FF6347; margin: 0; font-family: \"Playfair Display\", serif; font-size: 26px; font-weight: 700;'>⚠️ RECORDATORIOS IMPORTANTES</h3>
                    <div style='width: 50px; height: 2px; background: #FF8C00; margin: 8px auto;'></div>
                </div>
                
                <div style='background: linear-gradient(135deg, #FFE4E1 0%, #FFF0F5 100%); padding: 25px; border-radius: 10px; border: 3px solid #FF6347; border-left: 8px solid #FF6347;'>
                    <ul style='margin: 0; padding-left: 25px; color: #8B0000; line-height: 1.8; font-weight: 600;'>
                        <li style='margin: 12px 0;'><strong style='color: #FF6347;'>📱 LLEVA TU LOCALIZADOR:</strong> <span style='background: #FF6347; color: #FFF; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-family: monospace;'>" . $reserva['localizador'] . "</span></li>
                        <li style='margin: 12px 0;'><strong style='color: #FF6347;'>⏰ PUNTUALIDAD:</strong> Llega 15 minutos antes de las " . substr($reserva['hora'], 0, 5) . "</li>
                        <li style='margin: 12px 0;'><strong style='color: #FF6347;'>🆔 RESIDENTES:</strong> Documento acreditativo obligatorio</li>
                        <li style='margin: 12px 0;'><strong style='color: #FF6347;'>📞 CONTACTO:</strong> " . $reserva['telefono'] . "</li>
                    </ul>
                </div>
            </div>

            <!-- Total de la reserva destacado -->
            <div style='background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%); padding: 30px; border-left: 8px solid #FF8C00; border-right: 8px solid #FF8C00;'>
                <div style='text-align: center;'>
                    <h3 style='color: #F5DEB3; margin: 0 0 20px 0; font-family: \"Playfair Display\", serif; font-size: 24px; font-weight: 700;'>💰 Total de tu Reserva</h3>
                    <div style='background: rgba(255, 255, 255, 0.95); padding: 25px; border-radius: 15px; border: 3px solid #D4AF37;'>
                        <div style='font-size: 32px; font-weight: bold; color: #8B4513; margin-bottom: 10px;'>" . number_format($reserva['precio_final'], 2) . "€</div>
                        <p style='margin: 0; color: #228B22; font-weight: bold; font-size: 16px;'>✅ Reserva confirmada y pagada</p>
                    </div>
                </div>
            </div>

            <!-- Mensaje final de preparación -->
            <div style='background: #FFF8DC; padding: 30px; border-left: 8px solid #FF8C00; border-right: 8px solid #FF8C00; border-bottom: 8px solid #FF8C00;'>
                <div style='text-align: center;'>
                    <div style='background: linear-gradient(135deg, #32CD32 0%, #228B22 100%); color: #FFF; padding: 25px; border-radius: 15px; border: 3px solid #D4AF37;'>
                        <h3 style='color: #FFF; margin: 0 0 15px 0; font-family: \"Playfair Display\", serif; font-size: 24px; font-weight: 700;'>🎯 ¿Todo preparado?</h3>
                        <p style='margin: 0 0 15px 0; font-size: 18px; font-weight: 600;'>
                            ¡Te esperamos mañana para descubrir juntos las maravillas de Medina Azahara!
                        </p>
                        <p style='margin: 0; font-size: 16px;'>
                            Si tienes alguna duda de última hora, no dudes en contactarnos.
                        </p>
                    </div>
                    
                    <!-- Decoración final -->
                    <div style='margin-top: 30px;'>
                        <div style='font-size: 24px; color: #D4AF37; margin-bottom: 15px;'>◇ ◈ ◇</div>
                        <p style='margin: 0; color: #8B4513; font-size: 20px; font-weight: 600; font-family: \"Playfair Display\", serif;'>
                            ¡Que tengas un viaje excelente! 🚌✨
                        </p>
                        <div style='font-size: 20px; color: #D4AF37; margin-top: 10px;'>✦ ◆ ✦</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer elegante -->
        <div style='text-align: center; padding: 30px 20px; background: linear-gradient(135deg, #2C1810 0%, #8B4513 100%); color: #F5DEB3; border-top: 5px solid #D4AF37;'>
            <div style='font-size: 20px; color: #D4AF37; margin-bottom: 15px;'>◆</div>
            <p style='margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;'>
                Este es un recordatorio automático de tu reserva para mañana.<br>
                ¡Te deseamos un viaje fantástico!
            </p>
            <p style='margin: 0; color: #D4AF37; font-weight: bold; font-size: 16px; font-family: \"Playfair Display\", serif;'>
                Medina Azahara te espera
            </p>
            <div style='font-size: 16px; color: #D4AF37; margin-top: 10px;'>◆</div>
        </div>

    </body>
    </html>";
    }

    /**
     * Template de email para el administrador
     */
    private static function build_admin_email_template($reserva)
    {
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
    public static function resend_confirmation($reserva_id)
    {
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

    public static function send_cancellation_email($reserva_data)
    {
        $config = self::get_email_config();

        $to = $reserva_data['email'];
        $subject = "Reserva Cancelada - Localizador: " . $reserva_data['localizador'];

        $message = self::build_cancellation_email_template($reserva_data);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['nombre_remitente'] . ' <' . $config['email_remitente'] . '>'
        );

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            error_log("✅ Email de cancelación enviado al cliente: " . $to);
            return array('success' => true, 'message' => 'Email de cancelación enviado correctamente');
        } else {
            error_log("❌ Error enviando email de cancelación al cliente: " . $to);
            return array('success' => false, 'message' => 'Error enviando email de cancelación');
        }
    }

    private static function build_cancellation_email_template($reserva)
    {
        $fecha_formateada = date('d/m/Y', strtotime($reserva['fecha']));
        $motivo = $reserva['motivo_cancelacion'] ?? 'Cancelación administrativa';

        return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Reserva Cancelada</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 500px; margin: 0 auto; padding: 20px;'>
        
        <div style='background: #dc3545; color: white; text-align: center; padding: 20px; margin-bottom: 20px; border-radius: 8px;'>
            <h1 style='margin: 0; font-size: 24px;'>❌ RESERVA CANCELADA</h1>
        </div>

        <div style='background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #dc3545;'>
            <h2 style='color: #dc3545; margin-top: 0; font-size: 18px;'>Información de la Reserva Cancelada</h2>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 8px 0; font-weight: bold;'>Localizador:</td>
                    <td style='padding: 8px 0; text-align: right; color: #dc3545; font-weight: bold;'>" . $reserva['localizador'] . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: bold;'>Fecha del viaje:</td>
                    <td style='padding: 8px 0; text-align: right;'>" . $fecha_formateada . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: bold;'>Hora:</td>
                    <td style='padding: 8px 0; text-align: right;'>" . substr($reserva['hora'], 0, 5) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: bold;'>Cliente:</td>
                    <td style='padding: 8px 0; text-align: right;'>" . $reserva['nombre'] . " " . $reserva['apellidos'] . "</td>
                </tr>
            </table>
        </div>

        <div style='background: #fff3cd; padding: 15px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #ffc107;'>
            <h3 style='color: #856404; margin-top: 0; font-size: 16px;'>Motivo de la Cancelación</h3>
            <p style='margin: 0; color: #856404; font-weight: 600;'>" . $motivo . "</p>
        </div>

        <div style='background: #e2e3e5; padding: 15px; border-radius: 8px; text-align: center;'>
            <p style='margin: 0; color: #495057; font-size: 14px;'>
                <strong>¿Necesitas hacer una nueva reserva?</strong><br>
                Puedes realizar una nueva reserva cuando lo desees.<br>
                Lamentamos las molestias ocasionadas.
            </p>
        </div>

    </body>
    </html>";
    }
}
