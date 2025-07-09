<?php
class ReservasFrontend
{

    public function __construct()
    {
        add_shortcode('reservas_formulario', array($this, 'render_booking_form'));
        add_shortcode('reservas_detalles', array($this, 'render_details_form'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // AJAX hooks para el frontend
        add_action('wp_ajax_get_available_services', array($this, 'get_available_services'));
        add_action('wp_ajax_nopriv_get_available_services', array($this, 'get_available_services'));
        add_action('wp_ajax_calculate_price', array($this, 'calculate_price'));
        add_action('wp_ajax_nopriv_calculate_price', array($this, 'calculate_price'));
    }

    public function enqueue_frontend_assets()
    {
        global $post;

        // Cargar assets para formulario de reserva
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'reservas_formulario')) {
            wp_enqueue_style(
                'reservas-frontend-style',
                RESERVAS_PLUGIN_URL . 'assets/css/frontend-style.css',
                array(),
                '1.0.0'
            );

            wp_enqueue_script(
                'reservas-frontend-script',
                RESERVAS_PLUGIN_URL . 'assets/js/frontend-script.js',
                array('jquery'),
                '1.0.0',
                true
            );

            wp_localize_script('reservas-frontend-script', 'reservasAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('reservas_nonce')
            ));
        }

        // ❌ PROBLEMA ARREGLADO: Cargar assets para página de detalles también
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'reservas_detalles')) {
            // Cargar CSS
            wp_enqueue_style(
                'reservas-frontend-style',
                RESERVAS_PLUGIN_URL . 'assets/css/frontend-style.css',
                array(),
                '1.0.0'
            );

            // ✅ CARGAR EL SCRIPT JAVASCRIPT - ESTO FALTABA
            wp_enqueue_script(
                'reservas-frontend-script',
                RESERVAS_PLUGIN_URL . 'assets/js/frontend-script.js',
                array('jquery'),
                '1.0.0',
                true
            );

            // ✅ LOCALIZAR VARIABLES AJAX - ESTO TAMBIÉN FALTABA
            wp_localize_script('reservas-frontend-script', 'reservasAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('reservas_nonce')
            ));
        }
    }

    public function render_booking_form()
    {
        ob_start();
?>
        <div id="reservas-formulario procesocompra" class="reservas-booking-container container">
            <!-- Paso 1: Seleccionar fecha/hora Y personas juntos -->
            <div class="booking-step" id="step-1">
                <div class="booking-steps-grid">
                    <!-- Columna izquierda: Calendario -->
                    <div class="step-card">
                        <h3>1. ELIGE EL DÍA Y LA HORA</h3>
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <button type="button" id="prev-month">‹</button>
                                <span id="current-month-year"></span>
                                <button type="button" id="next-month">›</button>
                            </div>
                            <div class="calendar-grid" id="calendar-grid">
                                <!-- El calendario se generará aquí -->
                            </div>
                            <div class="calendar-legend">
                                <span class="legend-item">
                                    <span class="legend-color no-disponible"></span>
                                    Día No Disponible
                                </span>
                                <span class="legend-item">
                                    <span class="legend-color seleccion"></span>
                                    Selección
                                </span>
                                <span class="legend-item">
                                    <span class="legend-color oferta"></span>
                                    Día con Oferta
                                </span>
                            </div>
                            <div class="horarios-section">
                                <label>HORARIOS</label>
                                <select id="horarios-select" disabled>
                                    <option value="">Selecciona primero una fecha</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Columna derecha: Selección de personas -->
                    <div class="step-card">
                        <h3>2. SELECCIONA LAS PERSONAS</h3>
                        <div class="calendar-container">
                            <div class="persons-grid">
                                <div class="person-selector">
                                    <label>ADULTOS</label>
                                    <input type="number" id="adultos" min="0" max="999" value="0" class="person-input">
                                </div>

                                <div class="person-selector">
                                    <label>ADULTOS RESIDENTES</label>
                                    <input type="number" id="residentes" min="0" max="999" value="0" class="person-input">
                                </div>

                                <div class="person-selector">
                                    <label>NIÑOS (5/12 AÑOS)</label>
                                    <input type="number" id="ninos-5-12" min="0" max="999" value="0" class="person-input">
                                </div>

                                <div class="person-selector">
                                    <label>NIÑOS (-5 AÑOS)</label>
                                    <input type="number" id="ninos-menores" min="0" max="999" value="0" class="person-input">
                                </div>
                            </div>

                            <div class="price-summary">
                                <div class="price-row">
                                    <span>ADULTOS: <span id="price-adultos">10€</span></span>
                                    <span>NIÑOS (DE 5 A 12 AÑOS): <span id="price-ninos">5€</span></span>
                                </div>
                                <div class="price-notes">
                                    <img src="https://dev.tictac-comunicacion.es/bravo/wp-content/uploads/2025/07/Vector-14.svg" alt="">
                                    <div class="notas">
                                        <p>*NIÑOS (Menores de 5 años): 0€ (viajan gratis).</p>
                                        <p>*RESIDENTES en Córdoba: 50% de descuento.</p>
                                        <p>*Los RESIDENTES deben llevar un documento que lo acredite y presentarlo en persona.</p>
                                        <p>*En reservas de más de 10 personas se aplica DESCUENTO POR GRUPO.</p>
                                    </div>

                                </div>


                            </div>

                            <!-- Mensaje de descuento por grupo -->
                            <div id="discount-message" class="discount-message">
                                <span id="discount-text">Descuento del 15% por grupo numeroso</span>
                            </div>

                            <div class="total-price">
    <div class="discount-row" id="discount-row" style="display: none;">
        <span class="discount">DESCUENTOS: <span id="total-discount"></span></span>
    </div>
    <div class="total-row">
        <span class="total">TOTAL: <span id="total-price">0€</span></span>
    </div>
</div>
                        </div>

                    </div>
                </div>
            </div>
            <div style="text-align: center; width: 100%; margin-top: 50px;">
                <button type="button" class="complete-btn" onclick="proceedToDetails()">
                    3. COMPLETAR RESERVA
                </button>
            </div>



        </div>
    <?php
        return ob_get_clean();
    }

    public function get_available_services()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_die('Error de seguridad');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        $month = intval($_POST['month']);
        $year = intval($_POST['year']);

        // Calcular primer y último día del mes
        $first_day = sprintf('%04d-%02d-01', $year, $month);
        $last_day = date('Y-m-t', strtotime($first_day));

        // ACTUALIZADO: Incluir los nuevos campos de descuento en la consulta
        $servicios = $wpdb->get_results($wpdb->prepare(
            "SELECT id, fecha, hora, plazas_disponibles, precio_adulto, precio_nino, precio_residente, 
                    tiene_descuento, porcentaje_descuento
            FROM $table_name 
            WHERE fecha BETWEEN %s AND %s 
            AND status = 'active'
            AND plazas_disponibles > 0
            ORDER BY fecha, hora",
            $first_day,
            $last_day
        ));

        // Organizar por fecha
        $calendar_data = array();
        foreach ($servicios as $servicio) {
            if (!isset($calendar_data[$servicio->fecha])) {
                $calendar_data[$servicio->fecha] = array();
            }

            $calendar_data[$servicio->fecha][] = array(
                'id' => $servicio->id,
                'hora' => substr($servicio->hora, 0, 5),
                'plazas_disponibles' => $servicio->plazas_disponibles,
                'precio_adulto' => $servicio->precio_adulto,
                'precio_nino' => $servicio->precio_nino,
                'precio_residente' => $servicio->precio_residente,
                'tiene_descuento' => $servicio->tiene_descuento,
                'porcentaje_descuento' => $servicio->porcentaje_descuento
            );
        }

        wp_send_json_success($calendar_data);
    }

    public function calculate_price()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'reservas_nonce')) {
            wp_die('Error de seguridad');
        }

        $service_id = intval($_POST['service_id']);
        $adultos = intval($_POST['adultos']);
        $residentes = intval($_POST['residentes']);
        $ninos_5_12 = intval($_POST['ninos_5_12']);
        $ninos_menores = intval($_POST['ninos_menores']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_servicios';

        // Obtener datos del servicio
        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT *, tiene_descuento, porcentaje_descuento FROM $table_name WHERE id = %d",
            $service_id
        ));

        if (!$servicio) {
            wp_send_json_error('Servicio no encontrado');
        }

        // ✅ CALCULAR TOTAL DE PERSONAS QUE OCUPAN PLAZA
        $total_personas_con_plaza = $adultos + $residentes + $ninos_5_12;
        // Los niños menores de 5 años NO ocupan plaza y van gratis

        // ✅ CALCULAR PRECIO BASE (todos pagan precio de adulto inicialmente)
        $precio_base = 0;
        $precio_base += $adultos * $servicio->precio_adulto;        // Adultos normales
        $precio_base += $residentes * $servicio->precio_adulto;     // Residentes (primero precio normal)
        $precio_base += $ninos_5_12 * $servicio->precio_adulto;     // Niños 5-12 (primero precio normal)
        // Los niños menores NO se añaden al precio base

        // ✅ CALCULAR DESCUENTOS INDIVIDUALES
        $descuento_total = 0;

        // Descuento por ser residente (diferencia entre precio adulto y precio residente)
        $descuento_residentes = $residentes * ($servicio->precio_adulto - $servicio->precio_residente);
        $descuento_total += $descuento_residentes;

        // Descuento por ser niño (diferencia entre precio adulto y precio niño)
        $descuento_ninos = $ninos_5_12 * ($servicio->precio_adulto - $servicio->precio_nino);
        $descuento_total += $descuento_ninos;

        // ✅ CALCULAR DESCUENTO POR GRUPO (solo si hay suficientes personas)
        $descuento_grupo = 0;
        $regla_aplicada = null;

        if ($total_personas_con_plaza > 0) {
            if (!class_exists('ReservasDiscountsAdmin')) {
                require_once RESERVAS_PLUGIN_PATH . 'includes/class-discounts-admin.php';
            }

            // Calcular el subtotal después de descuentos individuales
            $subtotal_para_grupo = $precio_base - $descuento_total;

            $discount_info = ReservasDiscountsAdmin::calculate_discount(
                $total_personas_con_plaza,
                $subtotal_para_grupo,
                'total'
            );

            if ($discount_info['discount_applied']) {
                $descuento_grupo = $discount_info['discount_amount'];
                $descuento_total += $descuento_grupo;
                $regla_aplicada = array(
                    'rule_name' => $discount_info['rule_name'],
                    'discount_percentage' => $discount_info['discount_percentage'],
                    'minimum_persons' => $discount_info['minimum_persons']
                );
            }
        }

        // ✅ APLICAR DESCUENTO ESPECÍFICO DEL SERVICIO (si existe)
        $descuento_servicio = 0;
        if ($servicio->tiene_descuento && floatval($servicio->porcentaje_descuento) > 0) {
            $subtotal_actual = $precio_base - $descuento_total;
            $descuento_servicio = ($subtotal_actual * floatval($servicio->porcentaje_descuento)) / 100;
            $descuento_total += $descuento_servicio;
        }

        // ✅ CALCULAR TOTAL FINAL
        $total = $precio_base - $descuento_total;
        if ($total < 0) $total = 0;

        // ✅ PREPARAR RESPUESTA CON INFORMACIÓN DETALLADA
        $response_data = array(
            'precio_base' => round($precio_base, 2),
            'descuento' => round($descuento_total, 2),
            'descuento_residentes' => round($descuento_residentes, 2),
            'descuento_ninos' => round($descuento_ninos, 2),
            'descuento_grupo' => round($descuento_grupo, 2),
            'descuento_servicio' => round($descuento_servicio, 2),
            'total' => round($total, 2),
            'precio_adulto' => $servicio->precio_adulto,
            'precio_nino' => $servicio->precio_nino,
            'precio_residente' => $servicio->precio_residente,
            'total_personas_con_plaza' => $total_personas_con_plaza,
            'regla_descuento_aplicada' => $regla_aplicada,
            'servicio_con_descuento' => array(
                'tiene_descuento' => $servicio->tiene_descuento,
                'porcentaje_descuento' => $servicio->porcentaje_descuento
            ),
            // ✅ INFORMACIÓN DE DEBUG
            'debug' => array(
                'adultos' => $adultos,
                'residentes' => $residentes,
                'ninos_5_12' => $ninos_5_12,
                'ninos_menores' => $ninos_menores,
                'total_personas_con_plaza' => $total_personas_con_plaza,
                'precio_base_calculado' => $precio_base,
                'descuento_grupo_aplicado' => $descuento_grupo > 0
            )
        );

        wp_send_json_success($response_data);
    }

    public function render_details_form()
    {
        ob_start();
    ?>
        <div id="reservas-detalles" class="reservas-details-container">
            <!-- Detalles de la reserva -->
            <div class="details-summary">
                <h2>DETALLES DE LA RESERVA</h2>
                <div class="details-grid">
                    <div class="details-section">
                        <h3>FECHAS Y HORAS</h3>
                        <div class="detail-row">
                            <span class="label">FECHA AUTOBÚS IDA:</span>
                            <span class="value" id="fecha-ida">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">HORA AUTOBÚS IDA:</span>
                            <span class="value" id="hora-ida">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">FECHA AUTOBÚS VUELTA:</span>
                            <span class="value" id="fecha-vuelta">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">HORA AUTOBÚS VUELTA:</span>
                            <span class="value" id="hora-vuelta">-</span>
                        </div>
                    </div>

                    <div class="details-section">
                        <h3>BILLETES Y/O PERSONAS</h3>
                        <div class="detail-row">
                            <span class="label">NÚMERO DE ADULTOS:</span>
                            <span class="value" id="num-adultos">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">NÚMERO DE RESIDENTES:</span>
                            <span class="value" id="num-residentes">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">NÚMERO DE NIÑOS (5/12 AÑOS):</span>
                            <span class="value" id="num-ninos-5-12">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">NÚMERO DE NIÑOS (-5 AÑOS):</span>
                            <span class="value" id="num-ninos-menores">-</span>
                        </div>
                    </div>

                    <div class="details-section">
                        <h3>PRECIOS</h3>
                        <div class="detail-row">
                            <span class="label">IMPORTE BASE:</span>
                            <span class="value" id="importe-base">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">DESCUENTO RESIDENTES:</span>
                            <span class="value" id="descuento-residentes">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">DESCUENTO MENORES:</span>
                            <span class="value" id="descuento-menores">-0€</span>
                        </div>
                        <!-- AÑADIR ESTA NUEVA FILA -->
                        <div class="detail-row" id="descuento-grupo-row" style="display: none;">
                            <span class="label">DESCUENTO GRUPO:</span>
                            <span class="value" id="descuento-grupo-detalle">-0€</span>
                        </div>
                        <div class="detail-row total-row">
                            <span class="label">TOTAL RESERVA:</span>
                            <span class="value total-price" id="total-reserva">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de datos personales directamente debajo -->
            <div class="personal-data-section">
                <div class="form-card-single">
                    <h3>DATOS PERSONALES</h3>
                    <form id="personal-data-form">
                        <div class="form-row">
                            <div class="form-group">
                                <input type="text" name="nombre" placeholder="NOMBRE" required>
                            </div>
                            <div class="form-group">
                                <input type="text" name="apellidos" placeholder="APELLIDOS" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="email" name="email" placeholder="EMAIL" required>
                            </div>
                            <div class="form-group">
                                <input type="tel" name="telefono" placeholder="MÓVIL O TELÉFONO" required>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Botones finales -->
            <div class="final-buttons">
                <button type="button" class="back-btn" onclick="goBackToBooking()">
                    ← VOLVER A MODIFICAR RESERVA
                </button>
                <button type="button" class="process-btn" onclick="processReservation()">
                    PROCESAR RESERVA
                </button>
            </div>
        </div>

        <!-- ✅ SCRIPT MEJORADO QUE LLAMA A LAS FUNCIONES CORRECTAS -->
        <script>
            // ✅ EJECUTAR DESPUÉS DE QUE SE CARGUE EL DOCUMENT Y LOS SCRIPTS
            jQuery(document).ready(function($) {
                console.log("=== PÁGINA DE DETALLES CARGADA ===");

                // ✅ CARGAR DATOS DIRECTAMENTE DESDE ESTA PÁGINA
                loadReservationDataFromStorage();
            });

            // ✅ FUNCIÓN QUE CARGA LOS DATOS DESDE sessionStorage
            function loadReservationDataFromStorage() {
                console.log("=== INICIANDO CARGA DE DATOS ===");

                try {
                    const dataString = sessionStorage.getItem("reservationData");
                    console.log("Datos en sessionStorage:", dataString);

                    if (!dataString) {
                        alert("No hay datos de reserva. Redirigiendo...");
                        window.history.back();
                        return;
                    }

                    const data = JSON.parse(dataString);
                    console.log("Datos parseados:", data);
                    fillReservationDetailsDirectly(data);

                } catch (error) {
                    console.error("Error cargando datos:", error);
                    alert("Error cargando los datos de la reserva");
                }
            }

            // ✅ FUNCIÓN QUE RELLENA LOS DATOS EN LA PÁGINA DE DETALLES - ARREGLADA
            function fillReservationDetailsDirectly(data) {
                console.log("=== RELLENANDO DETALLES ===");
                console.log("Datos recibidos:", data);

                // Formatear fecha
                let fechaFormateada = "-";
                if (data.fecha) {
                    const fechaObj = new Date(data.fecha + "T00:00:00");
                    fechaFormateada = fechaObj.toLocaleDateString("es-ES", {
                        weekday: "long",
                        year: "numeric",
                        month: "long",
                        day: "numeric"
                    });
                }

                // Rellenar datos básicos
                jQuery("#fecha-ida").text(fechaFormateada);
                jQuery("#hora-ida").text(data.hora_ida || "-");
                jQuery("#fecha-vuelta").text(fechaFormateada);
                jQuery("#hora-vuelta").text("13:30");

                jQuery("#num-adultos").text(data.adultos || 0);
                jQuery("#num-residentes").text(data.residentes || 0);
                jQuery("#num-ninos-5-12").text(data.ninos_5_12 || 0);
                jQuery("#num-ninos-menores").text(data.ninos_menores || 0);

                // ✅ OBTENER PRECIOS DEL SERVICIO
                const precioAdulto = parseFloat(data.precio_adulto) || 0;
                const precioNino = parseFloat(data.precio_nino) || 0;
                const precioResidente = parseFloat(data.precio_residente) || 0;

                const adultos = parseInt(data.adultos) || 0;
                const residentes = parseInt(data.residentes) || 0;
                const ninos_5_12 = parseInt(data.ninos_5_12) || 0;
                const ninos_menores = parseInt(data.ninos_menores) || 0;

                // ✅ CALCULAR PERSONAS QUE OCUPAN PLAZA
                const totalPersonasConPlaza = adultos + residentes + ninos_5_12;

                // ✅ CALCULAR PRECIO BASE (todos empiezan pagando precio de adulto)
                const importeBase = totalPersonasConPlaza * precioAdulto;

                // ✅ CALCULAR DESCUENTOS INDIVIDUALES
                const descuentoResidentes = residentes * (precioAdulto - precioResidente);
                const descuentoNinos = ninos_5_12 * (precioAdulto - precioNino);

                // ✅ MOSTRAR PRECIOS CALCULADOS
                jQuery("#importe-base").text(formatPrice(importeBase));
                jQuery("#descuento-residentes").text(formatPrice(-descuentoResidentes));
                jQuery("#descuento-menores").text(formatPrice(-descuentoNinos));

                // ✅ MOSTRAR DESCUENTO POR GRUPO SOLO SI REALMENTE SE APLICÓ
                const descuentoGrupo = parseFloat(data.descuento_grupo) || 0;

                console.log("Datos de descuento:");
                console.log("- Total personas con plaza:", totalPersonasConPlaza);
                console.log("- Descuento grupo en datos:", descuentoGrupo);
                console.log("- Regla aplicada:", data.regla_descuento_aplicada);

                if (descuentoGrupo > 0 && data.regla_descuento_aplicada) {
                    // Solo mostrar si realmente hay descuento por grupo
                    jQuery("#descuento-grupo-detalle").text(formatPrice(-descuentoGrupo));
                    jQuery("#descuento-grupo-row").show();
                    console.log("✅ Mostrando descuento por grupo:", descuentoGrupo);
                } else {
                    // Ocultar la fila de descuento por grupo
                    jQuery("#descuento-grupo-row").hide();
                    console.log("❌ Ocultando descuento por grupo (no aplica)");
                }

                // ✅ MOSTRAR TOTAL FINAL
                jQuery("#total-reserva").text(formatPrice(data.total_price || "0"));

                console.log("✅ Detalles rellenados correctamente");
                console.log("Resumen:");
                console.log("- Importe base:", formatPrice(importeBase));
                console.log("- Descuento residentes:", formatPrice(-descuentoResidentes));
                console.log("- Descuento niños:", formatPrice(-descuentoNinos));
                console.log("- Descuento grupo:", descuentoGrupo > 0 ? formatPrice(-descuentoGrupo) : "No aplica");
                console.log("- Total final:", formatPrice(data.total_price || "0"));
            }

            function formatPrice(price) {
                const numPrice = parseFloat(price) || 0;
                return numPrice.toFixed(2) + "€";
            }

            function goBackToBooking() {
                sessionStorage.removeItem("reservationData");
                window.history.back();
            }
        </script>
<?php
        return ob_get_clean();
    }
}
