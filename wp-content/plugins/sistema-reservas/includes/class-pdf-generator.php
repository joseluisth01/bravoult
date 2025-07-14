<?php

/**
 * Clase para generar PDFs de billetes idénticos al diseño original
 * Archivo: wp-content/plugins/sistema-reservas/includes/class-pdf-generator.php
 */

require_once(ABSPATH . 'wp-admin/includes/file.php');

class ReservasPDFGenerator {
    
    private $reserva_data;
    private $font_path;
    
    public function __construct() {
        // Verificar si TCPDF está disponible
        if (!class_exists('TCPDF')) {
            require_once(RESERVAS_PLUGIN_PATH . 'vendor/tcpdf/tcpdf.php');
        }
    }
    
    /**
     * Generar PDF del billete
     */
    public function generate_ticket_pdf($reserva_data) {
        $this->reserva_data = $reserva_data;
        
        // Crear instancia de TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configuración del documento
        $pdf->SetCreator('Sistema de Reservas');
        $pdf->SetAuthor('Autocares Bravo Palacios');
        $pdf->SetTitle('Billete - ' . $reserva_data['localizador']);
        $pdf->SetSubject('Billete de reserva Medina Azahara');
        
        // Configurar márgenes
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false, 10);
        
        // Añadir página
        $pdf->AddPage();
        
        // Generar contenido del billete
        $this->generate_ticket_content($pdf);
        
        // Nombre del archivo
        $filename = 'billete_' . $reserva_data['localizador'] . '.pdf';
        
        // Guardar PDF temporalmente
        $upload_dir = wp_upload_dir();
        $temp_path = $upload_dir['path'] . '/' . $filename;
        
        $pdf->Output($temp_path, 'F');
        
        return $temp_path;
    }
    
    /**
     * Generar el contenido del billete
     */
    private function generate_ticket_content($pdf) {
        // Configurar fuente
        $pdf->SetFont('helvetica', '', 10);
        
        // ========== SECCIÓN SUPERIOR DEL BILLETE ==========
        $this->generate_main_ticket_section($pdf);
        
        // ========== SECCIÓN DEL TALÓN ==========
        $this->generate_stub_section($pdf);
        
        // ========== CONDICIONES DE COMPRA ==========
        $this->generate_conditions_section($pdf);
        
        // ========== ILUSTRACIÓN DE CÓRDOBA ==========
        $this->generate_cordoba_illustration($pdf);
        
        // ========== CÓDIGO DE BARRAS Y PIE ==========
        $this->generate_barcode_footer($pdf);
    }
    
    /**
     * Sección principal del billete
     */
    private function generate_main_ticket_section($pdf) {
        $y_start = 20;
        
        // Tabla de productos y precios
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y_start);
        $pdf->Cell(20, 6, 'Unidades', 1, 0, 'C');
        $pdf->Cell(80, 6, 'Plazas:', 1, 0, 'L');
        $pdf->Cell(25, 6, 'Precio:', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Total:', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 9);
        
        // Línea de adultos
        if ($this->reserva_data['adultos'] > 0) {
            $pdf->SetX(15);
            $pdf->Cell(20, 6, $this->reserva_data['adultos'], 1, 0, 'C');
            $pdf->Cell(80, 6, 'Adultos', 1, 0, 'L');
            $pdf->Cell(25, 6, number_format($this->get_precio_adulto(), 2) . ' €', 1, 0, 'C');
            $pdf->Cell(25, 6, number_format($this->reserva_data['adultos'] * $this->get_precio_adulto(), 2) . ' €', 1, 1, 'C');
        }
        
        // Línea de residentes
        if ($this->reserva_data['residentes'] > 0) {
            $pdf->SetX(15);
            $pdf->Cell(20, 6, $this->reserva_data['residentes'], 1, 0, 'C');
            $pdf->Cell(80, 6, 'Residentes', 1, 0, 'L');
            $pdf->Cell(25, 6, number_format($this->get_precio_residente(), 2) . ' €', 1, 0, 'C');
            $pdf->Cell(25, 6, number_format($this->reserva_data['residentes'] * $this->get_precio_residente(), 2) . ' €', 1, 1, 'C');
        }
        
        // Línea de niños (5-12)
        if ($this->reserva_data['ninos_5_12'] > 0) {
            $pdf->SetX(15);
            $pdf->Cell(20, 6, $this->reserva_data['ninos_5_12'], 1, 0, 'C');
            $pdf->Cell(80, 6, 'Niños (5 a 12 años) (5-12 a.)', 1, 0, 'L');
            $pdf->Cell(25, 6, number_format($this->get_precio_nino(), 2) . ' €', 1, 0, 'C');
            $pdf->Cell(25, 6, number_format($this->reserva_data['ninos_5_12'] * $this->get_precio_nino(), 2) . ' €', 1, 1, 'C');
        }
        
        // Línea de niños menores (gratis)
        if ($this->reserva_data['ninos_menores'] > 0) {
            $pdf->SetX(15);
            $pdf->Cell(20, 6, $this->reserva_data['ninos_menores'], 1, 0, 'C');
            $pdf->Cell(80, 6, 'Niños (menores 5 años)', 1, 0, 'L');
            $pdf->Cell(25, 6, '0,00 €', 1, 0, 'C');
            $pdf->Cell(25, 6, '0,00 €', 1, 1, 'C');
        }
        
        // Total
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetX(120);
        $pdf->Cell(30, 8, number_format($this->reserva_data['precio_final'], 2) . ' €', 1, 1, 'C');
        
        // Información del producto
        $y_current = $pdf->GetY() + 10;
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(15, $y_current);
        $pdf->Cell(0, 8, 'TAQ BUS Madinat Al-Zahra + Lanzadera (' . substr($this->reserva_data['hora'], 0, 5) . ' hrs)', 0, 1, 'L');
        
        $y_current += 15;
        
        // Información de fechas y horarios
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y_current);
        $pdf->Cell(35, 6, 'Fecha Visita:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, $this->format_date($this->reserva_data['fecha']), 0, 0, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetX(15);
        $pdf->Cell(35, 6, 'Hora de Salida:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, substr($this->reserva_data['hora'], 0, 5) . ' hrs', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetX(15);
        $pdf->Cell(35, 6, 'Idioma:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, 'Español', 0, 0, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetX(15);
        $pdf->Cell(35, 6, 'Producto:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, 'TAQ BUS Madinat Al-Zahra + Lanzadera (' . substr($this->reserva_data['hora'], 0, 5) . ' hrs)', 0, 1, 'L');
        
        $y_current = $pdf->GetY() + 5;
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y_current);
        $pdf->Cell(35, 6, 'Fecha Compra:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, $this->format_date($this->reserva_data['created_at']), 0, 0, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetX(120);
        $pdf->Cell(35, 6, 'Localizador/Localizer:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(30, 6, $this->reserva_data['localizador'], 0, 1, 'L');
        
        // Punto de encuentro
        $y_current = $pdf->GetY() + 5;
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y_current);
        $pdf->Cell(35, 6, 'Punto de Encuentro:', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, '1-Paseo de la Victoria (glorieta Hospital Cruz Roja)', 0, 1, 'L');
        $pdf->SetX(15);
        $pdf->Cell(0, 5, '2-Paseo de la Victoria (frente Mercado Victoria)', 0, 1, 'L');
        
        // Cliente/Agente
        $y_current = $pdf->GetY() + 5;
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y_current);
        $pdf->Cell(35, 6, 'Cliente/Agente:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'TAQUILLA BRAVO BUS - FRANCISCO BRAVO', 0, 1, 'L');
        
        // Organiza
        $y_current = $pdf->GetY() + 5;
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y_current);
        $pdf->Cell(20, 6, 'Organiza:', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, 'AUTOCARES BRAVO PALACIOS,S.L.', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, 'INGENIERO BARBUDO, S/N - CORDOBA - CIF: B14485817 - Teléfono: 957429034', 0, 1, 'L');
    }
    
    /**
     * Sección del talón (parte desprendible)
     */
    private function generate_stub_section($pdf) {
        $y_start = 120;
        
        // Marco del talón
        $pdf->Rect(125, $y_start, 70, 50);
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(150, $y_start + 5);
        $pdf->Cell(0, 6, 'Localizador/Localizer:', 0, 0, 'C');
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(150, $y_start + 12);
        $pdf->Cell(0, 8, $this->reserva_data['localizador'], 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(127, $y_start + 25);
        $pdf->Cell(25, 4, 'Fecha Compra:', 0, 0, 'L');
        $pdf->Cell(40, 4, $this->format_date($this->reserva_data['created_at']), 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetX(127);
        $pdf->Cell(25, 4, 'Producto:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell(40, 4, 'TAQ BUS Madinat Al-Zahra + Lanzadera (' . substr($this->reserva_data['hora'], 0, 5) . ' hrs)', 0, 'L');
        
        // Total en el talón
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(150, $y_start + 40);
        $pdf->Cell(0, 6, 'Total: ' . number_format($this->reserva_data['precio_final'], 2) . ' €', 0, 0, 'C');
        
        // Información de la empresa en el talón
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY(127, $y_start + 47);
        $pdf->Cell(0, 3, 'AUTOCARES BRAVO PALACIOS,S.L.', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetX(127);
        $pdf->Cell(0, 3, 'INGENIERO BARBUDO, S/N - CORDOBA - CIF: B14485817 - Teléfono: 957429034', 0, 1, 'L');
    }
    
    /**
     * Sección de condiciones de compra
     */
    private function generate_conditions_section($pdf) {
        $y_start = 180;
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y_start);
        $pdf->Cell(0, 6, 'CONDICIONES DE COMPRA', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 7);
        $conditions_text = "La adquisición de la entrada supone la aceptación de las siguientes condiciones- No se admiten devoluciones ni cambios de entradas.- La Organización no garantiza la autenticidad de la entrada si ésta no ha sido adquirida en los puntos oficiales de venta.- En caso de suspensión del servicio, la devolución se efectuará por la Organización dentro del plazo de 15 días de la fecha de celebración.- En caso de suspensión del servicio, una vez iniciado, no habrá derecho a devolución del importe de la entrada.- La Organización no se responsabiliza de posibles demoras ajenas a su voluntad.- Es potestad de la Organización permitir la entrada al servicio una vez haya empezado.- La admisión se supedita a la disposición de la entrada en buenas condiciones.- Debe de estar en el punto de salida 10 minutos antes de la hora prevista de partida.";
        
        $pdf->SetXY(15, $y_start + 8);
        $pdf->MultiCell(180, 4, $conditions_text, 1, 'J');
        
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(15, $y_start + 35);
        $pdf->Cell(0, 4, 'Mantenga la integridad de toda la hoja, sin cortar ninguna de las zonas impresas.', 0, 1, 'C');
    }
    
    /**
     * Ilustración de Córdoba (simplificada)
     */
    private function generate_cordoba_illustration($pdf) {
        // Esta sería una representación simplificada
        // En una implementación real, podrías incluir una imagen SVG o PNG
        $y_start = 220;
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(15, $y_start);
        $pdf->Cell(0, 4, 'www.turismodecordoba.org', 0, 1, 'C');
    }
    
    /**
     * Código de barras y pie de página
     */
    private function generate_barcode_footer($pdf) {
        $y_start = 260;
        
        // Código de barras simulado (líneas verticales)
        $pdf->SetFont('helvetica', '', 6);
        for ($i = 0; $i < 50; $i++) {
            $x = 15 + ($i * 2);
            $height = rand(8, 12);
            $pdf->Line($x, $y_start, $x, $y_start + $height);
        }
        
        // Información del pie
        $pdf->SetXY(140, $y_start + 5);
        $pdf->Cell(30, 4, '00000545944', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 4, 'Total: ' . number_format($this->reserva_data['precio_final'], 2) . ' €', 0, 1, 'R');
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetX(140);
        $pdf->Cell(20, 4, 'Localizador', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(20, 4, $this->reserva_data['localizador'], 0, 1, 'L');
        
        // Información de la empresa en el pie
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(15, $y_start + 15);
        $pdf->Cell(0, 4, 'Organiza: AUTOCARES BRAVO PALACIOS,S.L.', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetX(15);
        $pdf->Cell(0, 4, 'INGENIERO BARBUDO, S/N - CORDOBA - CIF: B14485817 - Teléfono: 957429034', 0, 1, 'L');
    }
    
    /**
     * Métodos auxiliares
     */
    private function format_date($date_string) {
        if (strpos($date_string, '-') !== false) {
            // Formato Y-m-d
            $date = DateTime::createFromFormat('Y-m-d', $date_string);
        } else {
            // Formato datetime
            $date = new DateTime($date_string);
        }
        
        return $date ? $date->format('d/m/Y') : $date_string;
    }
    
    private function get_precio_adulto() {
        // Obtener del servicio o usar valor por defecto
        return $this->reserva_data['precio_adulto'] ?? 10.00;
    }
    
    private function get_precio_nino() {
        return $this->reserva_data['precio_nino'] ?? 5.00;
    }
    
    private function get_precio_residente() {
        return $this->reserva_data['precio_residente'] ?? 5.00;
    }
}