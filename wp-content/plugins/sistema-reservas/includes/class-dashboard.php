<?php
class ReservasDashboard {
    
    public function show_dashboard() {
        // ✅ VERIFICACIÓN MEJORADA DE SESIÓN
        if (!session_id()) {
            session_start();
        }

        // ✅ AGREGAR HEADERS PARA EVITAR CACHE
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        if (!isset($_SESSION['reservas_user'])) {
            // ✅ REDIRECCIÓN MÁS ROBUSTA
            $login_url = home_url('/reservas-login/');
            if (!headers_sent()) {
                wp_redirect($login_url . '?error=access');
            } else {
                echo "<script>window.location.href = '{$login_url}?error=access';</script>";
            }
            exit;
        }
        
        $this->render_dashboard_page();
    }

    private function render_dashboard_page() {
        $user = $_SESSION['reservas_user'];
        
        // ✅ VERIFICAR QUE EL USUARIO EXISTE EN LA BASE DE DATOS
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservas_users';
        $user_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE id = %d AND status = 'active'",
            $user['id']
        ));
        
        if (!$user_exists) {
            // Usuario no válido, cerrar sesión
            session_destroy();
            wp_redirect(home_url('/reservas-login/?error=invalid_user'));
            exit;
        }
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Sistema de Reservas - Dashboard</title>
            
            <!-- ✅ MEJORAR CARGA DE ASSETS -->
            <link rel="stylesheet" href="<?php echo RESERVAS_PLUGIN_URL; ?>assets/css/admin-style.css?v=<?php echo time(); ?>">
            
            <!-- ✅ CARGAR JQUERY DESDE CDN COMO BACKUP -->
            <script>
                window.jQuery || document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
            </script>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            
            <!-- ✅ VARIABLES JAVASCRIPT MÁS ROBUSTAS -->
            <script>
                // Variables globales para JavaScript
                const reservasAjax = {
                    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    nonce: '<?php echo wp_create_nonce('reservas_nonce'); ?>',
                    home_url: '<?php echo home_url(); ?>',
                    plugin_url: '<?php echo RESERVAS_PLUGIN_URL; ?>',
                    user_role: '<?php echo esc_js($user['role']); ?>',
                    debug: <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>
                };
                
                // ✅ FUNCIÓN DE DEBUG MEJORADA
                function debugLog(message, data = null) {
                    if (reservasAjax.debug) {
                        console.log('[RESERVAS DEBUG]', message, data);
                    }
                }
                
                // ✅ VERIFICAR QUE TODO ESTÉ CARGADO
                document.addEventListener('DOMContentLoaded', function() {
                    debugLog('DOM cargado correctamente');
                    debugLog('jQuery disponible:', typeof jQuery !== 'undefined');
                    debugLog('Variables AJAX:', reservasAjax);
                });
            </script>
            
            <script src="<?php echo RESERVAS_PLUGIN_URL; ?>assets/js/dashboard-script.js?v=<?php echo time(); ?>"></script>
            
            <!-- ✅ INCLUIR ESTILOS ESPECÍFICOS PARA INFORMES -->
            <style>
                <?php include RESERVAS_PLUGIN_PATH . 'assets/css/reports-styles.css'; ?>
            </style>
        </head>
        <body>
            <!-- ✅ AGREGAR INDICADOR DE CARGA -->
            <div id="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); z-index: 9999; display: none;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                    <div style="font-size: 18px; margin-bottom: 10px;">Cargando...</div>
                    <div style="width: 50px; height: 50px; border: 3px solid #f3f3f3; border-top: 3px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                </div>
            </div>
            
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>

            <div class="dashboard-header">
                <h1>Sistema de Reservas</h1>
                <div class="user-info">
                    <span>Bienvenido, <?php echo esc_html($user['username']); ?></span>
                    <span class="user-role"><?php echo esc_html($user['role']); ?></span>
                    <a href="<?php echo home_url('/reservas-login/?logout=1'); ?>" class="btn-logout">Cerrar Sesión</a>
                </div>
            </div>

            <!-- Resto del contenido igual... -->
            <div class="dashboard-content">
                <div class="welcome-card">
                    <h2>Dashboard Principal</h2>
                    <p class="status-active">✅ El sistema está funcionando correctamente</p>
                    <p>Has iniciado sesión correctamente en el sistema de reservas.</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Estado del Sistema</h3>
                        <div class="stat-number">✓</div>
                        <p>Operativo</p>
                    </div>
                    <div class="stat-card">
                        <h3>Tu Rol</h3>
                        <div class="stat-number"><?php echo strtoupper($user['role']); ?></div>
                        <p>Nivel de acceso</p>
                    </div>
                    <div class="stat-card">
                        <h3>Versión</h3>
                        <div class="stat-number">1.0</div>
                        <p>Sistema base</p>
                    </div>
                    
                    <?php if (in_array($user['role'], ['super_admin', 'admin'])): ?>
                    <div class="stat-card">
                        <h3>Reservas Hoy</h3>
                        <div class="stat-number"><?php echo $this->get_reservas_today(); ?></div>
                        <p>Confirmadas</p>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($user['role'] === 'super_admin'): ?>
                    <div class="menu-actions">
                        <h3>Acciones Disponibles</h3>
                        <div class="action-buttons">
                            <button class="action-btn" onclick="loadCalendarSection()">📅 Gestionar Calendario</button>
                            <button class="action-btn" onclick="loadDiscountsConfigSection()">💰 Configurar Descuentos</button>
                            <button class="action-btn" onclick="loadConfigurationSection()">⚙️ Configuración</button>
                            <button class="action-btn" onclick="loadReportsSection()">📊 Informes y Reservas</button>
                            <button class="action-btn" onclick="alert('Función en desarrollo')">🏢 Gestionar Agencias</button>
                        </div>
                    </div>
                <?php elseif ($user['role'] === 'admin'): ?>
                    <div class="menu-actions">
                        <h3>Acciones Disponibles</h3>
                        <div class="action-buttons">
                            <button class="action-btn" onclick="loadCalendarSection()">📅 Gestionar Calendario</button>
                            <button class="action-btn" onclick="loadReportsSection()">📊 Informes y Reservas</button>
                            <button class="action-btn" onclick="alert('Función en desarrollo')">📈 Ver Estadísticas</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ✅ SCRIPT DE MANEJO DE ERRORES -->
            <script>
                // ✅ MANEJO GLOBAL DE ERRORES AJAX
                jQuery(document).ready(function($) {
                    // Configurar manejo global de errores AJAX
                    $(document).ajaxError(function(event, xhr, settings, thrownError) {
                        debugLog('Error AJAX detectado:', {
                            url: settings.url,
                            status: xhr.status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText,
                            thrownError: thrownError
                        });
                        
                        // Ocultar overlay de carga
                        $('#loading-overlay').hide();
                        
                        // Mostrar error específico según el código
                        let errorMessage = 'Error de conexión desconocido';
                        
                        if (xhr.status === 0) {
                            errorMessage = 'Sin conexión al servidor. Verifica tu conexión a internet.';
                        } else if (xhr.status === 403) {
                            errorMessage = 'Acceso denegado. Tu sesión puede haber expirado.';
                            // Redirigir al login después de 3 segundos
                            setTimeout(function() {
                                window.location.href = reservasAjax.home_url + '/reservas-login/?error=session_expired';
                            }, 3000);
                        } else if (xhr.status === 404) {
                            errorMessage = 'Servicio no encontrado (Error 404).';
                        } else if (xhr.status === 500) {
                            errorMessage = 'Error interno del servidor (Error 500).';
                        } else if (xhr.status >= 400) {
                            errorMessage = `Error del servidor: ${xhr.status} ${xhr.statusText}`;
                        }
                        
                        alert('⚠️ ' + errorMessage + '\n\nSi el problema persiste, contacta al administrador.');
                    });
                    
                    // ✅ CONFIGURAR TIMEOUT PARA PETICIONES AJAX
                    $.ajaxSetup({
                        timeout: 30000 // 30 segundos
                    });
                    
                    // ✅ MOSTRAR OVERLAY DE CARGA EN PETICIONES AJAX
                    $(document).ajaxStart(function() {
                        $('#loading-overlay').show();
                    });
                    
                    $(document).ajaxStop(function() {
                        $('#loading-overlay').hide();
                    });
                });
            </script>
        </body>
        </html>
        <?php
    }
}