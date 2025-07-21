// Variables globales
let currentDate = new Date();
let servicesData = {};
let bulkHorarios = [];
let defaultConfig = null; // ✅ NUEVA VARIABLE PARA CONFIGURACIÓN

function loadCalendarSection() {
    document.body.innerHTML = `
        <div class="calendar-management">
            <div class="calendar-header">
                <h1>Gestión de Calendario</h1>
                <div class="calendar-actions">
                    <button class="btn-primary" onclick="showBulkAddModal()">➕ Añadir Múltiples Servicios</button>
                    <button class="btn-secondary" onclick="goBackToDashboard()">← Volver al Dashboard</button>
                </div>
            </div>
            
            <div class="calendar-controls">
                <button onclick="changeMonth(-1)">← Mes Anterior</button>
                <span id="currentMonth"></span>
                <button onclick="changeMonth(1)">Siguiente Mes →</button>
            </div>
            
            <div id="calendar-container">
                <div class="loading">Cargando calendario...</div>
            </div>
        </div>
    `;

    // ✅ CARGAR CONFIGURACIÓN PRIMERO, LUEGO INICIALIZAR CALENDARIO
    loadDefaultConfiguration().then(() => {
        initCalendar();
    });
}

// ✅ FUNCIÓN PARA MANEJAR ERRORES AJAX
function handleAjaxError(xhr, status, error) {
    console.error('AJAX Error:', {
        status: xhr.status,
        statusText: xhr.statusText,
        responseText: xhr.responseText,
        error: error
    });

    if (xhr.status === 403 || xhr.status === 401) {
        alert('Sesión expirada. Recarga la página e inicia sesión nuevamente.');
        window.location.reload();
    } else if (xhr.status === 400) {
        alert('Error de solicitud. Verifica los datos e inténtalo de nuevo.');
    } else {
        alert('Error de conexión. Inténtalo de nuevo.');
    }
}

function loadDefaultConfiguration() {
    return new Promise((resolve, reject) => {
        console.log('=== CARGANDO CONFIGURACIÓN ===');

        // ✅ VERIFICAR QUE TENEMOS LAS VARIABLES NECESARIAS
        if (typeof reservasAjax === 'undefined') {
            console.error('reservasAjax no está definido');
            resolve(); // Continuar sin configuración
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_configuration');
        formData.append('nonce', reservasAjax.nonce);

        // ✅ MEJORAR EL FETCH CON MÁS DEBUGGING
        fetch(reservasAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin' // ✅ IMPORTANTE PARA SESIONES
        })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                return response.text(); // ✅ OBTENER COMO TEXTO PRIMERO
            })
            .then(text => {
                console.log('Response text:', text);

                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        defaultConfig = data.data;
                        console.log('✅ Configuración cargada:', defaultConfig);
                        resolve();
                    } else {
                        console.error('❌ Error del servidor:', data.data);
                        // Usar valores por defecto
                        defaultConfig = getDefaultConfigValues();
                        resolve();
                    }
                } catch (e) {
                    console.error('❌ Error parsing JSON:', e);
                    console.error('Raw response:', text);
                    defaultConfig = getDefaultConfigValues();
                    resolve();
                }
            })
            .catch(error => {
                console.error('❌ Fetch error:', error);
                defaultConfig = getDefaultConfigValues();
                resolve();
            });
    });
}

function getDefaultConfigValues() {
    return {
        precios: {
            precio_adulto_defecto: { value: '10.00' },
            precio_nino_defecto: { value: '5.00' },
            precio_residente_defecto: { value: '5.00' }
        },
        servicios: {
            plazas_defecto: { value: '50' },
            dias_anticipacion_minima: { value: '1' }
        }
    };
}

function initCalendar() {
    updateCalendarDisplay();
    loadCalendarData();
}

function updateCalendarDisplay() {
    const monthNames = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    document.getElementById('currentMonth').textContent =
        monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
}

function changeMonth(direction) {
    currentDate.setMonth(currentDate.getMonth() + direction);
    updateCalendarDisplay();
    loadCalendarData();
}

function loadCalendarData() {
    console.log('=== INICIANDO CARGA DE CALENDARIO ===');

    if (typeof reservasAjax === 'undefined') {
        console.error('❌ reservasAjax no está definido');
        alert('Error: Variables AJAX no disponibles. Recarga la página.');
        return;
    }

    console.log('AJAX URL:', reservasAjax.ajax_url);
    console.log('Nonce:', reservasAjax.nonce);

    const formData = new FormData();
    formData.append('action', 'get_calendar_data');
    formData.append('month', currentDate.getMonth() + 1);
    formData.append('year', currentDate.getFullYear());
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                servicesData = data.data;
                renderCalendar();
                console.log('✅ Calendario renderizado correctamente');
            } else {
                console.error('❌ Error del servidor:', data.data);
                alert('Error del servidor: ' + (data.data || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('❌ Fetch error:', error);
            handleAjaxError({ status: 500, statusText: error.message }, 'error', error);
        });
}

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    let firstDayOfWeek = firstDay.getDay();
    firstDayOfWeek = (firstDayOfWeek + 6) % 7;

    const daysInMonth = lastDay.getDate();
    const dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    let calendarHTML = '<div class="calendar-grid">';

    // Encabezados de días
    dayNames.forEach(day => {
        calendarHTML += `<div class="calendar-header-day">${day}</div>`;
    });

    for (let i = 0; i < firstDayOfWeek; i++) {
        const dayNum = new Date(year, month, -firstDayOfWeek + i + 1).getDate();
        calendarHTML += `<div class="calendar-day other-month">
            <div class="day-number">${dayNum}</div>
        </div>`;
    }

    // ✅ OBTENER DÍAS DE ANTICIPACIÓN MÍNIMA DE LA CONFIGURACIÓN
    const diasAnticiapcion = defaultConfig?.servicios?.dias_anticipacion_minima?.value || '1';
    const fechaMinima = new Date();
    fechaMinima.setDate(fechaMinima.getDate() + parseInt(diasAnticiapcion));

    // Días del mes actual
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayDate = new Date(year, month, day);
        const isToday = dateStr === new Date().toISOString().split('T')[0];
        const todayClass = isToday ? ' today' : '';

        // ✅ VERIFICAR SI EL DÍA ESTÁ BLOQUEADO POR DÍAS DE ANTICIPACIÓN
        const isBlocked = dayDate < fechaMinima;

        // Verificar si algún servicio tiene descuento
        let hasDiscount = false;
        if (servicesData[dateStr]) {
            hasDiscount = servicesData[dateStr].some(service =>
                service.tiene_descuento && parseFloat(service.porcentaje_descuento) > 0
            );
        }

        let servicesHTML = '';
        if (servicesData[dateStr]) {
            servicesData[dateStr].forEach(service => {
                let serviceClass = 'service-item';
                let discountText = '';

                if (service.tiene_descuento && parseFloat(service.porcentaje_descuento) > 0) {
                    serviceClass += ' service-discount';
                    discountText = ` (${service.porcentaje_descuento}% OFF)`;
                }

                servicesHTML += `<div class="${serviceClass}" onclick="editService(${service.id})">${service.hora}${discountText}</div>`;
            });
        }

        let dayClass = `calendar-day${todayClass}`;
        if (hasDiscount) {
            dayClass += ' day-with-discount';
        }

        // ✅ AGREGAR CLASE Y COMPORTAMIENTO PARA DÍAS BLOQUEADOS
        let clickHandler = `onclick="addService('${dateStr}')"`;
        if (isBlocked) {
            dayClass += ' blocked-day';
            clickHandler = `onclick="showBlockedDayMessage()"`;
        }

        calendarHTML += `<div class="${dayClass}" ${clickHandler}>
            <div class="day-number">${day}</div>
            ${servicesHTML}
        </div>`;
    }

    calendarHTML += '</div>';

    // Modales
    calendarHTML += getModalHTML();

    document.getElementById('calendar-container').innerHTML = calendarHTML;

    // Inicializar eventos de los modales
    initModalEvents();
}

// ✅ NUEVA FUNCIÓN PARA MOSTRAR MENSAJE DE DÍA BLOQUEADO
function showBlockedDayMessage() {
    const diasAnticiapcion = defaultConfig?.servicios?.dias_anticipacion_minima?.value || '1';
    alert(`No se pueden crear servicios para esta fecha. Se requiere un mínimo de ${diasAnticiapcion} días de anticipación.`);
}

function getModalHTML() {
    return `
        <!-- Modal Añadir/Editar Servicio -->
        <div id="serviceModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeServiceModal()">&times;</span>
                <h3 id="serviceModalTitle">Añadir Servicio</h3>
                <form id="serviceForm">
                    <input type="hidden" id="serviceId" name="service_id">
                    <div class="form-group">
                        <label for="serviceFecha">Fecha:</label>
                        <input type="date" id="serviceFecha" name="fecha" required>
                    </div>
                    <div class="form-group">
                        <label for="serviceHora">Hora:</label>
                        <input type="time" id="serviceHora" name="hora" required>
                    </div>
                    <div class="form-group">
                        <label for="servicePlazas">Plazas Totales:</label>
                        <input type="number" id="servicePlazas" name="plazas_totales" min="1" max="100" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precioAdulto">Precio Adulto (€):</label>
                            <input type="number" id="precioAdulto" name="precio_adulto" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="precioNino">Precio Niño (€):</label>
                            <input type="number" id="precioNino" name="precio_nino" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="precioResidente">Precio Residente (€):</label>
                        <input type="number" id="precioResidente" name="precio_residente" step="0.01" min="0" required>
                    </div>
                    
                    <!-- Sección de descuento -->
                    <div class="form-group discount-section">
                        <label>
                            <input type="checkbox" id="tieneDescuento" name="tiene_descuento"> 
                            Activar descuento especial para este servicio
                        </label>
                        <div id="discountFields" style="display: none; margin-top: 10px;">
                            <label for="porcentajeDescuento">Porcentaje de descuento (%):</label>
                            <input type="number" id="porcentajeDescuento" name="porcentaje_descuento" 
                                   min="0" max="100" step="0.1" placeholder="Ej: 15">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Guardar Servicio</button>
                        <button type="button" class="btn-secondary" onclick="closeServiceModal()">Cancelar</button>
                        <button type="button" id="deleteServiceBtn" class="btn-danger" onclick="deleteService()" style="display: none;">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Añadir Múltiples Servicios -->
        <div id="bulkAddModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeBulkAddModal()">&times;</span>
                <h3>Añadir Múltiples Servicios</h3>
                <form id="bulkAddForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bulkFechaInicio">Fecha Inicio:</label>
                            <input type="date" id="bulkFechaInicio" name="fecha_inicio" required>
                        </div>
                        <div class="form-group">
                            <label for="bulkFechaFin">Fecha Fin:</label>
                            <input type="date" id="bulkFechaFin" name="fecha_fin" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Días de la semana:</label>
                        <div class="dias-semana">
                            <div class="dia-checkbox">
                                <input type="checkbox" id="dom" name="dias_semana[]" value="0">
                                <label for="dom">Dom</label>
                            </div>
                            <div class="dia-checkbox">
                                <input type="checkbox" id="lun" name="dias_semana[]" value="1">
                                <label for="lun">Lun</label>
                            </div>
                            <div class="dia-checkbox">
                                <input type="checkbox" id="mar" name="dias_semana[]" value="2">
                                <label for="mar">Mar</label>
                            </div>
                            <div class="dia-checkbox">
                                <input type="checkbox" id="mie" name="dias_semana[]" value="3">
                                <label for="mie">Mié</label>
                            </div>
                            <div class="dia-checkbox">
                                <input type="checkbox" id="jue" name="dias_semana[]" value="4">
                                <label for="jue">Jue</label>
                            </div>
                            <div class="dia-checkbox">
                                <input type="checkbox" id="vie" name="dias_semana[]" value="5">
                                <label for="vie">Vie</label>
                            </div>
                            <div class="dia-checkbox">
                                <input type="checkbox" id="sab" name="dias_semana[]" value="6">
                                <label for="sab">Sáb</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Horarios:</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="time" id="nuevoHorario" placeholder="Hora">
                            <button type="button" class="btn-primary" onclick="addHorario()">Añadir</button>
                        </div>
                        <div id="horariosList" class="horarios-list">
                            <!-- Los horarios se añadirán aquí -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulkPlazas">Plazas por Servicio:</label>
                        <input type="number" id="bulkPlazas" name="plazas_totales" min="1" max="100" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bulkPrecioAdulto">Precio Adulto (€):</label>
                            <input type="number" id="bulkPrecioAdulto" name="precio_adulto" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="bulkPrecioNino">Precio Niño (€):</label>
                            <input type="number" id="bulkPrecioNino" name="precio_nino" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulkPrecioResidente">Precio Residente (€):</label>
                        <input type="number" id="bulkPrecioResidente" name="precio_residente" step="0.01" min="0" required>
                    </div>
                    
                    <!-- Sección de descuento para bulk -->
                    <div class="form-group discount-section">
                        <label>
                            <input type="checkbox" id="bulkTieneDescuento" name="bulk_tiene_descuento"> 
                            Aplicar descuento especial a todos los servicios
                        </label>
                        <div id="bulkDiscountFields" style="display: none; margin-top: 10px;">
                            <label for="bulkPorcentajeDescuento">Porcentaje de descuento (%):</label>
                            <input type="number" id="bulkPorcentajeDescuento" name="bulk_porcentaje_descuento" 
                                   min="0" max="100" step="0.1" placeholder="Ej: 15">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Crear Servicios</button>
                        <button type="button" class="btn-secondary" onclick="closeBulkAddModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        /* ✅ ESTILOS PARA DÍAS BLOQUEADOS */
        .calendar-day.blocked-day {
            background-color: #f8f8f8 !important;
            color: #999 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }
        
        .calendar-day.blocked-day:hover {
            background-color: #f8f8f8 !important;
            transform: none !important;
        }
        
        .calendar-day.blocked-day .day-number {
            text-decoration: line-through;
        }
        </style>
    `;
}

function initModalEvents() {
    // Formulario de servicio individual
    document.getElementById('serviceForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveService();
    });

    // Formulario de servicios masivos
    document.getElementById('bulkAddForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveBulkServices();
    });

    // Eventos para los checkboxes de descuento
    document.getElementById('tieneDescuento').addEventListener('change', function () {
        const discountFields = document.getElementById('discountFields');
        if (this.checked) {
            discountFields.style.display = 'block';
        } else {
            discountFields.style.display = 'none';
            document.getElementById('porcentajeDescuento').value = '';
        }
    });

    document.getElementById('bulkTieneDescuento').addEventListener('change', function () {
        const bulkDiscountFields = document.getElementById('bulkDiscountFields');
        if (this.checked) {
            bulkDiscountFields.style.display = 'block';
        } else {
            bulkDiscountFields.style.display = 'none';
            document.getElementById('bulkPorcentajeDescuento').value = '';
        }
    });
}

function addService(fecha) {
    // ✅ VERIFICAR DÍAS DE ANTICIPACIÓN ANTES DE ABRIR MODAL
    const diasAnticiapcion = defaultConfig?.servicios?.dias_anticipacion_minima?.value || '1';
    const fechaMinima = new Date();
    fechaMinima.setDate(fechaMinima.getDate() + parseInt(diasAnticiapcion));
    const fechaSeleccionada = new Date(fecha);

    if (fechaSeleccionada < fechaMinima) {
        showBlockedDayMessage();
        return;
    }

    document.getElementById('serviceModalTitle').textContent = 'Añadir Servicio';
    document.getElementById('serviceForm').reset();
    document.getElementById('serviceId').value = '';
    document.getElementById('serviceFecha').value = fecha;
    document.getElementById('deleteServiceBtn').style.display = 'none';

    // ✅ USAR VALORES DE CONFIGURACIÓN POR DEFECTO
    const defaultPrices = defaultConfig?.precios || {};
    const defaultPlazas = defaultConfig?.servicios?.plazas_defecto?.value || '50';

    document.getElementById('servicePlazas').value = defaultPlazas;
    document.getElementById('precioAdulto').value = defaultPrices.precio_adulto_defecto?.value || '10.00';
    document.getElementById('precioNino').value = defaultPrices.precio_nino_defecto?.value || '5.00';
    document.getElementById('precioResidente').value = defaultPrices.precio_residente_defecto?.value || '5.00';

    // Ocultar campos de descuento por defecto
    document.getElementById('discountFields').style.display = 'none';
    document.getElementById('tieneDescuento').checked = false;
    document.getElementById('porcentajeDescuento').value = '';

    document.getElementById('serviceModal').style.display = 'block';
}

function editService(serviceId) {
    console.log('=== EDITANDO SERVICIO ===');
    console.log('Service ID:', serviceId);

    const formData = new FormData();
    formData.append('action', 'get_service_details');
    formData.append('service_id', serviceId);
    formData.append('nonce', reservasAjax.nonce);

    console.log('=== DEBUG FETCH REQUEST ===');
    console.log('URL:', reservasAjax.ajax_url);
    console.log('FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(response => {
            console.log('=== RESPONSE DEBUG ===');
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            console.log('Response OK:', response.ok);
            console.log('Response statusText:', response.statusText);

            // ✅ LEER LA RESPUESTA COMO TEXTO PRIMERO
            return response.text().then(text => {
                console.log('Response text:', text);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText} - ${text}`);
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            console.log('Service details response:', data);
            if (data.success) {
                const service = data.data;
                document.getElementById('serviceModalTitle').textContent = 'Editar Servicio';
                document.getElementById('serviceId').value = service.id;
                document.getElementById('serviceFecha').value = service.fecha;
                document.getElementById('serviceHora').value = service.hora;
                document.getElementById('servicePlazas').value = service.plazas_totales;
                document.getElementById('precioAdulto').value = service.precio_adulto;
                document.getElementById('precioNino').value = service.precio_nino;
                document.getElementById('precioResidente').value = service.precio_residente;

                // Cargar datos de descuento
                const tieneDescuento = service.tiene_descuento == '1';
                document.getElementById('tieneDescuento').checked = tieneDescuento;

                if (tieneDescuento) {
                    document.getElementById('discountFields').style.display = 'block';
                    document.getElementById('porcentajeDescuento').value = service.porcentaje_descuento || '';
                } else {
                    document.getElementById('discountFields').style.display = 'none';
                    document.getElementById('porcentajeDescuento').value = '';
                }

                document.getElementById('deleteServiceBtn').style.display = 'block';
                document.getElementById('serviceModal').style.display = 'block';
            } else {
                alert('Error al cargar el servicio: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error loading service details:', error);
            alert('Error de conexión: ' + error.message);
        });
}

function saveService() {
    const formData = new FormData(document.getElementById('serviceForm'));
    formData.append('action', 'save_service');
    formData.append('nonce', reservasAjax.nonce);

    // ✅ DEBUGGING MEJORADO
    console.log('=== GUARDANDO SERVICIO ===');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                alert('Servicio guardado correctamente');
                closeServiceModal();
                loadCalendarData();
            } else {
                alert('Error: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error guardando servicio:', error);
            alert('Error de conexión: ' + error.message);
        });
}

function deleteService() {
    if (!confirm('¿Estás seguro de que quieres eliminar este servicio?')) {
        return;
    }

    const serviceId = document.getElementById('serviceId').value;
    const formData = new FormData();
    formData.append('action', 'delete_service');
    formData.append('service_id', serviceId);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Servicio eliminado correctamente');
                closeServiceModal();
                loadCalendarData();
            } else {
                alert('Error: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}

function closeServiceModal() {
    document.getElementById('serviceModal').style.display = 'none';
}

function showBulkAddModal() {
    document.getElementById('bulkAddForm').reset();
    bulkHorarios = [];
    updateHorariosList();

    // ✅ USAR VALORES DE CONFIGURACIÓN POR DEFECTO PARA BULK
    const defaultPrices = defaultConfig?.precios || {};
    const defaultPlazas = defaultConfig?.servicios?.plazas_defecto?.value || '50';

    document.getElementById('bulkPlazas').value = defaultPlazas;
    document.getElementById('bulkPrecioAdulto').value = defaultPrices.precio_adulto_defecto?.value || '10.00';
    document.getElementById('bulkPrecioNino').value = defaultPrices.precio_nino_defecto?.value || '5.00';
    document.getElementById('bulkPrecioResidente').value = defaultPrices.precio_residente_defecto?.value || '5.00';

    // ✅ ESTABLECER FECHA MÍNIMA BASADA EN CONFIGURACIÓN
    const diasAnticiapcion = defaultConfig?.servicios?.dias_anticipacion_minima?.value || '1';
    const fechaMinima = new Date();
    fechaMinima.setDate(fechaMinima.getDate() + parseInt(diasAnticiapcion));
    const fechaMinimaStr = fechaMinima.toISOString().split('T')[0];

    document.getElementById('bulkFechaInicio').setAttribute('min', fechaMinimaStr);
    document.getElementById('bulkFechaFin').setAttribute('min', fechaMinimaStr);

    // Ocultar campos de descuento por defecto
    document.getElementById('bulkDiscountFields').style.display = 'none';
    document.getElementById('bulkTieneDescuento').checked = false;
    document.getElementById('bulkPorcentajeDescuento').value = '';

    document.getElementById('bulkAddModal').style.display = 'block';
}

function closeBulkAddModal() {
    document.getElementById('bulkAddModal').style.display = 'none';
}

function addHorario() {
    const horarioInput = document.getElementById('nuevoHorario');
    const horario = horarioInput.value;

    if (horario && !bulkHorarios.find(h => h.hora === horario)) {
        bulkHorarios.push({
            hora: horario
        });
        horarioInput.value = '';
        updateHorariosList();
    }
}

function removeHorario(index) {
    bulkHorarios.splice(index, 1);
    updateHorariosList();
}

function updateHorariosList() {
    const container = document.getElementById('horariosList');

    if (bulkHorarios.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #666;">No hay horarios añadidos</p>';
        return;
    }

    let html = '';
    bulkHorarios.forEach((horario, index) => {
        html += `
            <div class="horario-item">
                <span>${horario.hora}</span>
                <button type="button" class="btn-small btn-danger" onclick="removeHorario(${index})">Eliminar</button>
            </div>
        `;
    });

    container.innerHTML = html;
}

function saveBulkServices() {
    if (bulkHorarios.length === 0) {
        alert('Debes añadir al menos un horario');
        return;
    }

    const formData = new FormData(document.getElementById('bulkAddForm'));
    formData.append('action', 'bulk_add_services');
    formData.append('horarios', JSON.stringify(bulkHorarios));
    formData.append('nonce', reservasAjax.nonce);

    // Obtener días de la semana seleccionados
    const diasSeleccionados = [];
    document.querySelectorAll('input[name="dias_semana[]"]:checked').forEach(checkbox => {
        diasSeleccionados.push(checkbox.value);
    });

    diasSeleccionados.forEach(dia => {
        formData.append('dias_semana[]', dia);
    });

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.data.mensaje);
                closeBulkAddModal();
                loadCalendarData();
            } else {
                alert('Error: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}

function goBackToDashboard() {
    location.reload();
}

// ✅ FUNCIONES PARA GESTIÓN DE DESCUENTOS (mantenidas igual)
function loadDiscountsConfigSection() {
    document.body.innerHTML = `
        <div class="discounts-management">
            <div class="discounts-header">
                <h1>Configuración de Descuentos</h1>
                <div class="discounts-actions">
                    <button class="btn-primary" onclick="showAddDiscountModal()">➕ Añadir Nueva Regla</button>
                    <button class="btn-secondary" onclick="goBackToDashboard()">← Volver al Dashboard</button>
                </div>
            </div>
            
            <div class="current-rules-section">
                <h3>Reglas de Descuento Actuales</h3>
                <div id="discounts-list">
                    <div class="loading">Cargando reglas de descuento...</div>
                </div>
            </div>
        </div>
        
        <!-- Modal Añadir/Editar Regla de Descuento -->
        <div id="discountModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeDiscountModal()">&times;</span>
                <h3 id="discountModalTitle">Añadir Regla de Descuento</h3>
                <form id="discountForm">
                    <input type="hidden" id="discountId" name="discount_id">
                    
                    <div class="form-group">
                        <label for="ruleName">Nombre de la Regla:</label>
                        <input type="text" id="ruleName" name="rule_name" placeholder="Ej: Descuento Grupo Grande" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="minimumPersons">Mínimo de Personas:</label>
                            <input type="number" id="minimumPersons" name="minimum_persons" min="1" max="100" placeholder="10" required>
                        </div>
                        <div class="form-group">
                            <label for="discountPercentage">Porcentaje de Descuento (%):</label>
                            <input type="number" id="discountPercentage" name="discount_percentage" min="1" max="100" step="0.1" placeholder="15" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="applyTo">Aplicar a:</label>
                        <select id="applyTo" name="apply_to" required>
                            <option value="total">Total de la reserva</option>
                            <option value="adults_only">Solo adultos</option>
                            <option value="all_paid">Todas las personas que pagan</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ruleDescription">Descripción:</label>
                        <textarea id="ruleDescription" name="rule_description" rows="3" placeholder="Describe cuándo se aplica este descuento"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="isActive" name="is_active" checked>
                            Regla activa
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Guardar Regla</button>
                        <button type="button" class="btn-secondary" onclick="closeDiscountModal()">Cancelar</button>
                        <button type="button" id="deleteDiscountBtn" class="btn-danger" onclick="deleteDiscountRule()" style="display: none;">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    // Inicializar eventos
    initDiscountEvents();

    // Cargar reglas existentes
    loadDiscountRules();
}

function initDiscountEvents() {
    // Formulario de regla de descuento
    document.getElementById('discountForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveDiscountRule();
    });
}

function loadDiscountRules() {
    const formData = new FormData();
    formData.append('action', 'get_discount_rules');
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderDiscountRules(data.data);
            } else {
                document.getElementById('discounts-list').innerHTML =
                    '<p class="error">Error cargando las reglas: ' + data.data + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('discounts-list').innerHTML =
                '<p class="error">Error de conexión</p>';
        });
}

function renderDiscountRules(rules) {
    let html = '';

    if (rules.length === 0) {
        html = `
            <div class="no-rules">
                <p>No hay reglas de descuento configuradas.</p>
                <button class="btn-primary" onclick="showAddDiscountModal()">Crear Primera Regla</button>
            </div>
        `;
    } else {
        html = `
            <div class="rules-table">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Mínimo Personas</th>
                            <th>Descuento</th>
                            <th>Aplicar a</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        rules.forEach(rule => {
            const statusClass = rule.is_active == 1 ? 'status-active' : 'status-inactive';
            const statusText = rule.is_active == 1 ? 'Activa' : 'Inactiva';
            const applyToText = getApplyToText(rule.apply_to);

            html += `
                <tr>
                    <td>${rule.rule_name}</td>
                    <td>${rule.minimum_persons} personas</td>
                    <td>${rule.discount_percentage}%</td>
                    <td>${applyToText}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <button class="btn-edit" onclick="editDiscountRule(${rule.id})">Editar</button>
                        <button class="btn-delete" onclick="confirmDeleteRule(${rule.id})">Eliminar</button>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;
    }

    document.getElementById('discounts-list').innerHTML = html;
}

function getApplyToText(applyTo) {
    const texts = {
        'total': 'Total de la reserva',
        'adults_only': 'Solo adultos',
        'all_paid': 'Personas que pagan'
    };
    return texts[applyTo] || applyTo;
}

function showAddDiscountModal() {
    document.getElementById('discountModalTitle').textContent = 'Añadir Regla de Descuento';
    document.getElementById('discountForm').reset();
    document.getElementById('discountId').value = '';
    document.getElementById('deleteDiscountBtn').style.display = 'none';
    document.getElementById('isActive').checked = true;

    // Valores por defecto
    document.getElementById('minimumPersons').value = 10;
    document.getElementById('discountPercentage').value = 15;
    document.getElementById('applyTo').value = 'total';

    document.getElementById('discountModal').style.display = 'block';
}

function editDiscountRule(ruleId) {
    const formData = new FormData();
    formData.append('action', 'get_discount_rule_details');
    formData.append('rule_id', ruleId);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const rule = data.data;
                document.getElementById('discountModalTitle').textContent = 'Editar Regla de Descuento';
                document.getElementById('discountId').value = rule.id;
                document.getElementById('ruleName').value = rule.rule_name;
                document.getElementById('minimumPersons').value = rule.minimum_persons;
                document.getElementById('discountPercentage').value = rule.discount_percentage;
                document.getElementById('applyTo').value = rule.apply_to;
                document.getElementById('ruleDescription').value = rule.rule_description || '';
                document.getElementById('isActive').checked = rule.is_active == 1;
                document.getElementById('deleteDiscountBtn').style.display = 'block';

                document.getElementById('discountModal').style.display = 'block';
            } else {
                alert('Error al cargar la regla: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}

function saveDiscountRule() {
    const formData = new FormData(document.getElementById('discountForm'));
    formData.append('action', 'save_discount_rule');
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Regla guardada correctamente');
                closeDiscountModal();
                loadDiscountRules();
            } else {
                alert('Error: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}

function confirmDeleteRule(ruleId) {
    if (confirm('¿Estás seguro de que quieres eliminar esta regla de descuento?')) {
        deleteDiscountRule(ruleId);
    }
}

function deleteDiscountRule(ruleId = null) {
    const id = ruleId || document.getElementById('discountId').value;

    const formData = new FormData();
    formData.append('action', 'delete_discount_rule');
    formData.append('rule_id', id);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Regla eliminada correctamente');
                closeDiscountModal();
                loadDiscountRules();
            } else {
                alert('Error: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}

function closeDiscountModal() {
    document.getElementById('discountModal').style.display = 'none';
}

// ✅ FUNCIONES PARA CONFIGURACIÓN DEL SISTEMA (actualizadas sin personalización e idioma)
function loadConfigurationSection() {
    document.body.innerHTML = `
        <div class="configuration-management">
            <div class="configuration-header">
                <h1>⚙️ Configuración del Sistema</h1>
                <div class="configuration-actions">
                    <button class="btn-primary" onclick="saveAllConfiguration()">💾 Guardar Toda la Configuración</button>
                    <button class="btn-secondary" onclick="goBackToDashboard()">← Volver al Dashboard</button>
                </div>
            </div>
            
            <div class="configuration-content">
                <div class="loading">Cargando configuración...</div>
            </div>
        </div>
    `;

    // Cargar configuración actual
    loadConfigurationData();
}

function loadConfigurationData() {
    const formData = new FormData();
    formData.append('action', 'get_configuration');
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderConfigurationForm(data.data);
            } else {
                document.querySelector('.configuration-content').innerHTML =
                    '<p class="error">Error cargando la configuración: ' + data.data + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.querySelector('.configuration-content').innerHTML =
                '<p class="error">Error de conexión</p>';
        });
}

// ✅ FUNCIÓN ACTUALIZADA SIN CHECKBOX DE CONFIRMACIÓN + NUEVO CAMPO
function renderConfigurationForm(configs) {
    let html = `
        <form id="configurationForm" class="configuration-form">
            
            <!-- Sección: Precios por Defecto -->
            <div class="config-section">
                <h3>💰 Precios por Defecto para Nuevos Servicios</h3>
                <div class="config-grid">
                    <div class="config-item">
                        <label for="precio_adulto_defecto">Precio Adulto (€)</label>
                        <input type="number" id="precio_adulto_defecto" name="precio_adulto_defecto" 
                               step="0.01" min="0" value="${configs.precios?.precio_adulto_defecto?.value || '10.00'}">
                        <small>${configs.precios?.precio_adulto_defecto?.description || ''}</small>
                    </div>
                    <div class="config-item">
                        <label for="precio_nino_defecto">Precio Niño (€)</label>
                        <input type="number" id="precio_nino_defecto" name="precio_nino_defecto" 
                               step="0.01" min="0" value="${configs.precios?.precio_nino_defecto?.value || '5.00'}">
                        <small>${configs.precios?.precio_nino_defecto?.description || ''}</small>
                    </div>
                    <div class="config-item">
                        <label for="precio_residente_defecto">Precio Residente (€)</label>
                        <input type="number" id="precio_residente_defecto" name="precio_residente_defecto" 
                               step="0.01" min="0" value="${configs.precios?.precio_residente_defecto?.value || '5.00'}">
                        <small>${configs.precios?.precio_residente_defecto?.description || ''}</small>
                    </div>
                </div>
            </div>

            <!-- Sección: Configuración de Servicios -->
            <div class="config-section">
                <h3>🚌 Configuración de Servicios</h3>
                <div class="config-grid">
                    <div class="config-item">
                        <label for="plazas_defecto">Plazas por Defecto</label>
                        <input type="number" id="plazas_defecto" name="plazas_defecto" 
                               min="1" max="200" value="${configs.servicios?.plazas_defecto?.value || '50'}">
                        <small>${configs.servicios?.plazas_defecto?.description || ''}</small>
                    </div>
                    <div class="config-item">
                        <label for="dias_anticipacion_minima">Días Anticipación Mínima</label>
                        <input type="number" id="dias_anticipacion_minima" name="dias_anticipacion_minima" 
                               min="0" max="30" value="${configs.servicios?.dias_anticipacion_minima?.value || '1'}">
                        <small>${configs.servicios?.dias_anticipacion_minima?.description || ''}</small>
                    </div>
                </div>
            </div>

            <!-- ✅ SECCIÓN ACTUALIZADA: Notificaciones - SIN CHECKBOX DE CONFIRMACIÓN -->
            <div class="config-section">
                <h3>📧 Notificaciones por Email</h3>
                <div class="config-grid">
                    <div class="config-item config-checkbox">
                        <label>
                            <input type="checkbox" id="email_recordatorio_activo" name="email_recordatorio_activo" 
                                   ${configs.notificaciones?.email_recordatorio_activo?.value == '1' ? 'checked' : ''}>
                            Recordatorios Automáticos antes del Viaje
                        </label>
                        <small>${configs.notificaciones?.email_recordatorio_activo?.description || ''}</small>
                    </div>
                    <div class="config-item">
                        <label for="horas_recordatorio">Horas antes para Recordatorio</label>
                        <input type="number" id="horas_recordatorio" name="horas_recordatorio" 
                               min="1" max="168" value="${configs.notificaciones?.horas_recordatorio?.value || '24'}">
                        <small>${configs.notificaciones?.horas_recordatorio?.description || ''}</small>
                    </div>
                    <div class="config-item">
                        <label for="email_remitente">Email Remitente (Técnico)</label>
                        <input type="email" id="email_remitente" name="email_remitente" 
                               value="${configs.notificaciones?.email_remitente?.value || ''}"
                               style="background-color: #fff3cd; border: 2px solid #ffc107;">
                        <small style="color: #856404; font-weight: bold;">⚠️ ${configs.notificaciones?.email_remitente?.description || 'Email técnico desde el que se envían todos los correos - NO MODIFICAR sin conocimientos técnicos'}</small>
                    </div>
                    <div class="config-item">
                        <label for="nombre_remitente">Nombre del Remitente</label>
                        <input type="text" id="nombre_remitente" name="nombre_remitente" 
                               value="${configs.notificaciones?.nombre_remitente?.value || ''}">
                        <small>${configs.notificaciones?.nombre_remitente?.description || ''}</small>
                    </div>
                    <!-- ✅ NUEVO CAMPO: Email de Reservas -->
                    <div class="config-item">
                        <label for="email_reservas">Email de Reservas</label>
                        <input type="email" id="email_reservas" name="email_reservas" 
                               value="${configs.notificaciones?.email_reservas?.value || ''}"
                               style="background-color: #e8f5e8; border: 2px solid #28a745;">
                        <small style="color: #155724; font-weight: bold;">📧 ${configs.notificaciones?.email_reservas?.description || 'Email donde llegarán las notificaciones de nuevas reservas de clientes'}</small>
                    </div>
                </div>
                
                <!-- ✅ INFORMACIÓN ADICIONAL SOBRE EMAILS -->
                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #2196f3;">
                    <h4 style="margin-top: 0; color: #1565c0;">ℹ️ Información sobre Emails</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #1565c0;">
                        <li><strong>Confirmaciones:</strong> Se envían automáticamente SIEMPRE al cliente tras cada reserva</li>
                        <li><strong>Recordatorios:</strong> Se envían automáticamente según las horas configuradas</li>
                        <li><strong>Notificaciones de reservas:</strong> Llegan al "Email de Reservas" cada vez que un cliente hace una reserva</li>
                        <li><strong>Email Remitente:</strong> Es el email técnico desde el que se envían todos los correos</li>
                    </ul>
                </div>
            </div>

            <!-- Sección: Configuración General -->
            <div class="config-section">
                <h3>🌍 Configuración General</h3>
                <div class="config-grid">
                    <div class="config-item">
                        <label for="zona_horaria">Zona Horaria</label>
                        <select id="zona_horaria" name="zona_horaria">
                            <option value="Europe/Madrid" ${configs.general?.zona_horaria?.value === 'Europe/Madrid' ? 'selected' : ''}>Europe/Madrid</option>
                            <option value="Europe/London" ${configs.general?.zona_horaria?.value === 'Europe/London' ? 'selected' : ''}>Europe/London</option>
                            <option value="America/New_York" ${configs.general?.zona_horaria?.value === 'America/New_York' ? 'selected' : ''}>America/New_York</option>
                        </select>
                        <small>${configs.general?.zona_horaria?.description || ''}</small>
                    </div>
                    <div class="config-item">
                        <label for="moneda">Moneda</label>
                        <select id="moneda" name="moneda">
                            <option value="EUR" ${configs.general?.moneda?.value === 'EUR' ? 'selected' : ''}>EUR - Euro</option>
                            <option value="USD" ${configs.general?.moneda?.value === 'USD' ? 'selected' : ''}>USD - Dólar</option>
                            <option value="GBP" ${configs.general?.moneda?.value === 'GBP' ? 'selected' : ''}>GBP - Libra</option>
                        </select>
                        <small>${configs.general?.moneda?.description || ''}</small>
                    </div>
                    <div class="config-item">
                        <label for="simbolo_moneda">Símbolo de Moneda</label>
                        <input type="text" id="simbolo_moneda" name="simbolo_moneda" maxlength="3"
                               value="${configs.general?.simbolo_moneda?.value || '€'}">
                        <small>${configs.general?.simbolo_moneda?.description || ''}</small>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="config-actions">
                <button type="submit" class="btn-primary btn-large">💾 Guardar Toda la Configuración</button>
                <button type="button" class="btn-secondary" onclick="resetConfigurationForm()">🔄 Resetear Formulario</button>
            </div>
        </form>
    `;

    document.querySelector('.configuration-content').innerHTML = html;

    // Inicializar eventos del formulario
    initConfigurationEvents();
}

function initConfigurationEvents() {
    // Formulario de configuración
    document.getElementById('configurationForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveAllConfiguration();
    });

    // Eventos para los selectores de moneda (sincronizar símbolo)
    document.getElementById('moneda').addEventListener('change', function () {
        const monedaSeleccionada = this.value;
        const simboloInput = document.getElementById('simbolo_moneda');

        const simbolos = {
            'EUR': '€',
            'USD': ',',
            'GBP': '£'
        };

        if (simbolos[monedaSeleccionada]) {
            simboloInput.value = simbolos[monedaSeleccionada];
        }
    });
}

function saveAllConfiguration() {
    const form = document.getElementById('configurationForm');
    const formData = new FormData(form);
    formData.append('action', 'save_configuration');
    formData.append('nonce', reservasAjax.nonce);

    // Mostrar estado de carga
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = '⏳ Guardando...';

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            // Restaurar botón
            submitButton.disabled = false;
            submitButton.textContent = originalText;

            if (data.success) {
                alert('✅ ' + data.data);

                // ✅ RECARGAR CONFIGURACIÓN POR DEFECTO DESPUÉS DE GUARDAR
                loadDefaultConfiguration().then(() => {
                    showConfigurationNotification('Configuración guardada y sincronizada exitosamente', 'success');
                });
            } else {
                alert('❌ Error: ' + data.data);
                showConfigurationNotification('Error guardando configuración: ' + data.data, 'error');
            }
        })
        .catch(error => {
            // Restaurar botón
            submitButton.disabled = false;
            submitButton.textContent = originalText;

            console.error('Error:', error);
            alert('❌ Error de conexión: ' + error.message);
            showConfigurationNotification('Error de conexión', 'error');
        });
}

function resetConfigurationForm() {
    if (confirm('¿Estás seguro de que quieres resetear el formulario? Se perderán los cambios no guardados.')) {
        loadConfigurationData(); // Recargar datos originales
    }
}

function showConfigurationNotification(message, type) {
    // Crear notificación temporal
    const notification = document.createElement('div');
    notification.className = `config-notification config-notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">✕</button>
    `;

    // Agregar al top de la página
    const header = document.querySelector('.configuration-header');
    header.insertAdjacentElement('afterend', notification);

    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}



function loadReportsSection() {
    document.body.innerHTML = `
        <div class="reports-management">
            <div class="reports-header">
                <h1>📊 Informes y Gestión de Reservas</h1>
                <div class="reports-actions">
                    <button class="btn-primary" onclick="showQuickStatsModal()">📈 Estadísticas Rápidas</button>
                    <button class="btn-secondary" onclick="goBackToDashboard()">← Volver al Dashboard</button>
                </div>
            </div>
            
            <!-- Pestañas de navegación -->
            <div class="reports-tabs">
                <button class="tab-btn active" onclick="switchTab('reservations')">🎫 Gestión de Reservas</button>
                <button class="tab-btn" onclick="switchTab('search')">🔍 Buscar Billetes</button>
                <button class="tab-btn" onclick="switchTab('analytics')">📊 Análisis por Fechas</button>
            </div>
            
            <!-- Contenido de las pestañas -->
            <div class="tab-content">
                <!-- Pestaña 1: Gestión de Reservas -->
                <div id="tab-reservations" class="tab-panel active">
                    <div class="reservations-section">
                        <h3>Reservas por Rango de Fechas</h3>
                        <div class="date-filters">
                            <div class="filter-group">
                                <label for="fecha-inicio">Fecha Inicio:</label>
                                <input type="date" id="fecha-inicio" value="${new Date().toISOString().split('T')[0]}">
                            </div>
                            <div class="filter-group">
                                <label for="fecha-fin">Fecha Fin:</label>
                                <input type="date" id="fecha-fin" value="${new Date().toISOString().split('T')[0]}">
                            </div>
                            <button class="btn-primary" onclick="loadReservationsByDate()">🔍 Buscar Reservas</button>
                        </div>
                        
                        <div id="reservations-stats" class="stats-summary" style="display: none;">
                            <!-- Estadísticas se cargarán aquí -->
                        </div>
                        
                        <div id="reservations-list" class="reservations-table">
                            <!-- Lista de reservas se cargará aquí -->
                        </div>
                        
                        <div id="reservations-pagination" class="pagination-controls">
                            <!-- Paginación se cargará aquí -->
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña 2: Buscar Billetes -->
                <div id="tab-search" class="tab-panel">
                    <div class="search-section">
                        <h3>Buscar Billetes</h3>
                        <div class="search-form">
                            <div class="search-row">
                                <select id="search-type">
                                    <option value="localizador">Localizador</option>
                                    <option value="email">Email</option>
                                    <option value="telefono">Teléfono</option>
                                    <option value="nombre">Nombre/Apellidos</option>
                                    <option value="fecha_emision">Fecha de Emisión</option>
                                    <option value="fecha_servicio">Fecha de Servicio</option>
                                </select>
                                <input type="text" id="search-value" placeholder="Introduce el valor a buscar...">
                                <button class="btn-primary" onclick="searchReservations()">🔍 Buscar</button>
                            </div>
                        </div>
                        
                        <div id="search-results" class="search-results">
                            <!-- Resultados de búsqueda se cargarán aquí -->
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña 3: Análisis por Fechas -->
                <div id="tab-analytics" class="tab-panel">
                    <div class="analytics-section">
                        <h3>Análisis Estadístico por Períodos</h3>
                        <div class="analytics-filters">
                            <div class="quick-ranges">
                                <h4>Períodos Rápidos:</h4>
                                <button class="range-btn" onclick="loadRangeStats('7_days')">Últimos 7 días</button>
                                <button class="range-btn" onclick="loadRangeStats('30_days')">Últimos 30 días</button>
                                <button class="range-btn" onclick="loadRangeStats('60_days')">Últimos 60 días</button>
                                <button class="range-btn" onclick="loadRangeStats('this_month')">Este mes</button>
                                <button class="range-btn" onclick="loadRangeStats('last_month')">Mes pasado</button>
                                <button class="range-btn" onclick="loadRangeStats('this_year')">Este año</button>
                            </div>
                            
                            <div class="custom-range">
                                <h4>Rango Personalizado:</h4>
                                <input type="date" id="custom-fecha-inicio" placeholder="Fecha inicio">
                                <input type="date" id="custom-fecha-fin" placeholder="Fecha fin">
                                <button class="btn-primary" onclick="loadCustomRangeStats()">Analizar Período</button>
                            </div>
                        </div>
                        
                        <div id="analytics-results" class="analytics-results">
                            <!-- Resultados de análisis se cargarán aquí -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal para estadísticas rápidas -->
        <div id="quickStatsModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close" onclick="closeQuickStatsModal()">&times;</span>
                <h3>📈 Estadísticas Rápidas</h3>
                <div id="quick-stats-content">
                    <div class="loading">Cargando estadísticas...</div>
                </div>
            </div>
        </div>
        
        <!-- Modal para detalles de reserva -->
        <div id="reservationDetailsModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close" onclick="closeReservationDetailsModal()">&times;</span>
                <h3 id="reservationModalTitle">Detalles de Reserva</h3>
                <div id="reservation-details-content">
                    <!-- Contenido se cargará aquí -->
                </div>
            </div>
        </div>
        
        <!-- Modal para editar email -->
        <div id="editEmailModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close" onclick="closeEditEmailModal()">&times;</span>
                <h3>✏️ Editar Email de Cliente</h3>
                <form id="editEmailForm">
                    <input type="hidden" id="edit-reserva-id">
                    <div class="form-group">
                        <label for="current-email">Email Actual:</label>
                        <input type="email" id="current-email" readonly>
                    </div>
                    <div class="form-group">
                        <label for="new-email">Nuevo Email:</label>
                        <input type="email" id="new-email" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">💾 Actualizar Email</button>
                        <button type="button" class="btn-secondary" onclick="closeEditEmailModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    // Inicializar eventos
    initReportsEvents();

    // Cargar datos iniciales
    loadReservationsByDate();
}

// ✅ FUNCIÓN PARA INICIALIZAR EVENTOS
function initReportsEvents() {
    // Evento para el formulario de editar email
    document.getElementById('editEmailForm').addEventListener('submit', function (e) {
        e.preventDefault();
        updateReservationEmail();
    });

    // Evento para cambiar tipo de búsqueda
    document.getElementById('search-type').addEventListener('change', function () {
        const searchValue = document.getElementById('search-value');
        const searchType = this.value;

        if (searchType === 'fecha_emision' || searchType === 'fecha_servicio') {
            searchValue.type = 'date';
            searchValue.placeholder = 'Selecciona una fecha';
        } else {
            searchValue.type = 'text';
            searchValue.placeholder = 'Introduce el valor a buscar...';
        }
    });

    // Permitir búsqueda con Enter
    document.getElementById('search-value').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            searchReservations();
        }
    });
}

// ✅ FUNCIÓN PARA CAMBIAR PESTAÑAS
function switchTab(tabName) {
    // Ocultar todas las pestañas
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });

    // Quitar clase active de todos los botones
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Mostrar pestaña seleccionada
    document.getElementById('tab-' + tabName).classList.add('active');

    // Activar botón correspondiente
    event.target.classList.add('active');
}

// ✅ FUNCIÓN PARA CARGAR RESERVAS POR FECHA
function loadReservationsByDate(page = 1) {
    const fechaInicio = document.getElementById('fecha-inicio').value;
    const fechaFin = document.getElementById('fecha-fin').value;

    if (!fechaInicio || !fechaFin) {
        alert('Por favor, selecciona ambas fechas');
        return;
    }

    document.getElementById('reservations-list').innerHTML = '<div class="loading">Cargando reservas...</div>';

    const formData = new FormData();
    formData.append('action', 'get_reservations_report');
    formData.append('fecha_inicio', fechaInicio);
    formData.append('fecha_fin', fechaFin);
    formData.append('page', page);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderReservationsReport(data.data);
            } else {
                document.getElementById('reservations-list').innerHTML =
                    '<div class="error">Error: ' + data.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('reservations-list').innerHTML =
                '<div class="error">Error de conexión</div>';
        });
}

function renderReservationsReport(data) {
    // Mostrar estadísticas
    const statsHtml = `
        <div class="stats-cards">
            <div class="stat-card">
                <h4>Total Reservas</h4>
                <div class="stat-number">${data.stats.total_reservas || 0}</div>
            </div>
            <div class="stat-card">
                <h4>Adultos</h4>
                <div class="stat-number">${data.stats.total_adultos || 0}</div>
            </div>
            <div class="stat-card">
                <h4>Residentes</h4>
                <div class="stat-number">${data.stats.total_residentes || 0}</div>
            </div>
            <div class="stat-card">
                <h4>Niños (5-12)</h4>
                <div class="stat-number">${data.stats.total_ninos_5_12 || 0}</div>
            </div>
            <div class="stat-card">
                <h4>Niños (-5)</h4>
                <div class="stat-number">${data.stats.total_ninos_menores || 0}</div>
            </div>
            <div class="stat-card">
                <h4>Ingresos Totales</h4>
                <div class="stat-number">${parseFloat(data.stats.ingresos_totales || 0).toFixed(2)}€</div>
            </div>
        </div>
    `;

    document.getElementById('reservations-stats').innerHTML = statsHtml;
    document.getElementById('reservations-stats').style.display = 'block';

    // Mostrar tabla de reservas
    let tableHtml = `
        <div class="table-header">
            <h4>Reservas del ${data.fecha_inicio} al ${data.fecha_fin}</h4>
        </div>
        <table class="reservations-table-data">
            <thead>
                <tr>
                    <th>Localizador</th>
                    <th>Fecha Servicio</th>
                    <th>Hora</th>
                    <th>Cliente</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Personas</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
    `;

    if (data.reservas && data.reservas.length > 0) {
        data.reservas.forEach(reserva => {
            const fechaFormateada = new Date(reserva.fecha).toLocaleDateString('es-ES');
            const personasDetalle = `A:${reserva.adultos} R:${reserva.residentes} N:${reserva.ninos_5_12} B:${reserva.ninos_menores}`;

            tableHtml += `
                <tr>
                    <td><strong>${reserva.localizador}</strong></td>
                    <td>${fechaFormateada}</td>
                    <td>${reserva.hora}</td>
                    <td>${reserva.nombre} ${reserva.apellidos}</td>
                    <td>${reserva.email}</td>
                    <td>${reserva.telefono}</td>
                    <td title="Adultos: ${reserva.adultos}, Residentes: ${reserva.residentes}, Niños 5-12: ${reserva.ninos_5_12}, Menores: ${reserva.ninos_menores}">${personasDetalle}</td>
                    <td><strong>${parseFloat(reserva.precio_final).toFixed(2)}€</strong></td>
                    <td><span class="status-badge status-${reserva.estado}">${reserva.estado}</span></td>
                    <td>
        <button class="btn-small btn-info" onclick="showReservationDetails(${reserva.id})" title="Ver detalles">👁️</button>
        <button class="btn-small btn-edit" onclick="showEditEmailModal(${reserva.id}, '${reserva.email}')" title="Editar email">✏️</button>
        <button class="btn-small btn-primary" onclick="resendConfirmationEmail(${reserva.id})" title="Reenviar confirmación">📧</button>
        ${reserva.estado !== 'cancelada' ?
                    `<button class="btn-small btn-danger" onclick="showCancelReservationModal(${reserva.id}, '${reserva.localizador}')" title="Cancelar reserva">❌</button>` :
                    `<span class="btn-small" style="background: #6c757d; color: white;">CANCELADA</span>`
                }
    </td>
                </tr>
            `;
        });
    } else {
        tableHtml += `
            <tr>
                <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
                    No se encontraron reservas en este período
                </td>
            </tr>
        `;
    }

    tableHtml += `
            </tbody>
        </table>
    `;

    document.getElementById('reservations-list').innerHTML = tableHtml;

    // Mostrar paginación
    if (data.pagination && data.pagination.total_pages > 1) {
        renderPagination(data.pagination);
    } else {
        document.getElementById('reservations-pagination').innerHTML = '';
    }
}

function renderPagination(pagination) {
    let paginationHtml = '<div class="pagination">';

    // Botón anterior
    if (pagination.current_page > 1) {
        paginationHtml += `<button class="btn-pagination" onclick="loadReservationsByDate(${pagination.current_page - 1})">« Anterior</button>`;
    }

    // Números de página
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            paginationHtml += `<button class="btn-pagination active">${i}</button>`;
        } else {
            paginationHtml += `<button class="btn-pagination" onclick="loadReservationsByDate(${i})">${i}</button>`;
        }
    }

    // Botón siguiente
    if (pagination.current_page < pagination.total_pages) {
        paginationHtml += `<button class="btn-pagination" onclick="loadReservationsByDate(${pagination.current_page + 1})">Siguiente »</button>`;
    }

    paginationHtml += `</div>
        <div class="pagination-info">
            Página ${pagination.current_page} de ${pagination.total_pages} 
            (${pagination.total_items} reservas total)
        </div>`;

    document.getElementById('reservations-pagination').innerHTML = paginationHtml;
}



function searchReservations() {
    const searchType = document.getElementById('search-type').value;
    const searchValue = document.getElementById('search-value').value.trim();

    if (!searchValue) {
        alert('Por favor, introduce un valor para buscar');
        return;
    }

    document.getElementById('search-results').innerHTML = '<div class="loading">Buscando reservas...</div>';

    const formData = new FormData();
    formData.append('action', 'search_reservations');
    formData.append('search_type', searchType);
    formData.append('search_value', searchValue);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderSearchResults(data.data);
            } else {
                document.getElementById('search-results').innerHTML =
                    '<div class="error">Error: ' + data.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('search-results').innerHTML =
                '<div class="error">Error de conexión</div>';
        });
}


function renderSearchResults(data) {
    let resultsHtml = `
        <div class="search-header">
            <h4>Resultados de búsqueda: ${data.total_found} reservas encontradas</h4>
            <p>Búsqueda por <strong>${data.search_type}</strong>: "${data.search_value}"</p>
        </div>
    `;

    if (data.reservas && data.reservas.length > 0) {
        resultsHtml += `
            <table class="search-results-table">
                <thead>
                    <tr>
                        <th>Localizador</th>
                        <th>Fecha Servicio</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Personas</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.reservas.forEach(reserva => {
            const fechaFormateada = new Date(reserva.fecha).toLocaleDateString('es-ES');
            const personasDetalle = `A:${reserva.adultos} R:${reserva.residentes} N:${reserva.ninos_5_12} B:${reserva.ninos_menores}`;

            resultsHtml += `
                <tr>
                    <td><strong>${reserva.localizador}</strong></td>
                    <td>${fechaFormateada}</td>
                    <td>${reserva.nombre} ${reserva.apellidos}</td>
                    <td>${reserva.email}</td>
                    <td>${reserva.telefono}</td>
                    <td title="Adultos: ${reserva.adultos}, Residentes: ${reserva.residentes}, Niños 5-12: ${reserva.ninos_5_12}, Menores: ${reserva.ninos_menores}">${personasDetalle}</td>
                    <td><strong>${parseFloat(reserva.precio_final).toFixed(2)}€</strong></td>
                    <td>
        <button class="btn-small btn-info" onclick="showReservationDetails(${reserva.id})" title="Ver detalles">👁️</button>
        <button class="btn-small btn-edit" onclick="showEditEmailModal(${reserva.id}, '${reserva.email}')" title="Editar email">✏️</button>
        <button class="btn-small btn-primary" onclick="resendConfirmationEmail(${reserva.id})" title="Reenviar confirmación">📧</button>
        ${reserva.estado !== 'cancelada' ?
                    `<button class="btn-small btn-danger" onclick="showCancelReservationModal(${reserva.id}, '${reserva.localizador}')" title="Cancelar reserva">❌</button>` :
                    `<span class="btn-small" style="background: #6c757d; color: white;">CANCELADA</span>`
                }
    </td>
                </tr>
            `;
        });

        resultsHtml += `
                </tbody>
            </table>
        `;
    } else {
        resultsHtml += `
            <div class="no-results">
                <p>No se encontraron reservas con los criterios especificados.</p>
            </div>
        `;
    }

    document.getElementById('search-results').innerHTML = resultsHtml;
}


function showReservationDetails(reservaId) {
    const formData = new FormData();
    formData.append('action', 'get_reservation_details');
    formData.append('reserva_id', reservaId);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderReservationDetails(data.data);
                document.getElementById('reservationDetailsModal').style.display = 'block';
            } else {
                alert('Error cargando detalles: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}


function renderReservationDetails(reserva) {
    const fechaServicio = new Date(reserva.fecha).toLocaleDateString('es-ES');
    const fechaCreacion = new Date(reserva.created_at).toLocaleDateString('es-ES');

    let descuentoInfo = '';
    if (reserva.regla_descuento_aplicada) {
        descuentoInfo = `
            <div class="detail-section">
                <h4>💰 Información de Descuento</h4>
                <p><strong>Regla aplicada:</strong> ${reserva.regla_descuento_aplicada.rule_name}</p>
                <p><strong>Porcentaje:</strong> ${reserva.regla_descuento_aplicada.discount_percentage}%</p>
                <p><strong>Mínimo personas:</strong> ${reserva.regla_descuento_aplicada.minimum_persons}</p>
            </div>
        `;
    }

    const detailsHtml = `
        <div class="reservation-details">
            <div class="details-grid">
                <div class="detail-section">
                    <h4>📋 Información General</h4>
                    <p><strong>Localizador:</strong> ${reserva.localizador}</p>
                    <p><strong>Estado:</strong> <span class="status-badge status-${reserva.estado}">${reserva.estado}</span></p>
                    <p><strong>Fecha de servicio:</strong> ${fechaServicio}</p>
                    <p><strong>Hora:</strong> ${reserva.hora}</p>
                    <p><strong>Fecha de reserva:</strong> ${fechaCreacion}</p>
                </div>
                
                <div class="detail-section">
                    <h4>👤 Datos del Cliente</h4>
                    <p><strong>Nombre:</strong> ${reserva.nombre} ${reserva.apellidos}</p>
                    <p><strong>Email:</strong> ${reserva.email}</p>
                    <p><strong>Teléfono:</strong> ${reserva.telefono}</p>
                </div>
                
                <div class="detail-section">
                    <h4>👥 Distribución de Personas</h4>
                    <p><strong>Adultos:</strong> ${reserva.adultos}</p>
                    <p><strong>Residentes:</strong> ${reserva.residentes}</p>
                    <p><strong>Niños (5-12 años):</strong> ${reserva.ninos_5_12}</p>
                    <p><strong>Niños menores (gratis):</strong> ${reserva.ninos_menores}</p>
                    <p><strong>Total personas con plaza:</strong> ${reserva.total_personas}</p>
                </div>
                
                <div class="detail-section">
                    <h4>💰 Información de Precios</h4>
                    <p><strong>Precio base:</strong> ${parseFloat(reserva.precio_base).toFixed(2)}€</p>
                    <p><strong>Descuento total:</strong> ${parseFloat(reserva.descuento_total).toFixed(2)}€</p>
                    <p><strong>Precio final:</strong> <span class="price-final">${parseFloat(reserva.precio_final).toFixed(2)}€</span></p>
                    <p><strong>Método de pago:</strong> ${reserva.metodo_pago}</p>
                </div>
            </div>
            
            ${descuentoInfo}
            
            <div class="detail-actions">
                <button class="btn-primary" onclick="showEditEmailModal(${reserva.id}, '${reserva.email}')">✏️ Editar Email</button>
                <button class="btn-secondary" onclick="resendConfirmationEmail(${reserva.id})">📧 Reenviar Confirmación</button>
            </div>
        </div>
    `;

    document.getElementById('reservationModalTitle').textContent = `Detalles de Reserva - ${reserva.localizador}`;
    document.getElementById('reservation-details-content').innerHTML = detailsHtml;
}

function showEditEmailModal(reservaId, currentEmail) {
    document.getElementById('edit-reserva-id').value = reservaId;
    document.getElementById('current-email').value = currentEmail;
    document.getElementById('new-email').value = currentEmail;
    document.getElementById('editEmailModal').style.display = 'block';
}


function updateReservationEmail() {
    const reservaId = document.getElementById('edit-reserva-id').value;
    const newEmail = document.getElementById('new-email').value;

    if (!newEmail || !newEmail.includes('@')) {
        alert('Por favor, introduce un email válido');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_reservation_email');
    formData.append('reserva_id', reservaId);
    formData.append('new_email', newEmail);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Email actualizado correctamente');
                closeEditEmailModal();
                // Recargar la lista actual
                const activeTab = document.querySelector('.tab-btn.active').onclick.toString();
                if (activeTab.includes('reservations')) {
                    loadReservationsByDate();
                } else if (activeTab.includes('search')) {
                    searchReservations();
                }
            } else {
                alert('Error actualizando email: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}

// ✅ FUNCIÓN PARA REENVIAR EMAIL DE CONFIRMACIÓN
function resendConfirmationEmail(reservaId) {
    if (!confirm('¿Reenviar email de confirmación al cliente?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'resend_confirmation_email');
    formData.append('reserva_id', reservaId);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.data);
            } else {
                alert('Error reenviando email: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}

// ✅ FUNCIÓN PARA CARGAR ESTADÍSTICAS POR RANGO
function loadRangeStats(rangeType) {
    document.getElementById('analytics-results').innerHTML = '<div class="loading">Cargando análisis...</div>';

    const formData = new FormData();
    formData.append('action', 'get_date_range_stats');
    formData.append('range_type', rangeType);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAnalyticsResults(data.data);
            } else {
                document.getElementById('analytics-results').innerHTML =
                    '<div class="error">Error: ' + data.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('analytics-results').innerHTML =
                '<div class="error">Error de conexión</div>';
        });
}

// ✅ FUNCIÓN PARA CARGAR ESTADÍSTICAS PERSONALIZADAS
function loadCustomRangeStats() {
    const fechaInicio = document.getElementById('custom-fecha-inicio').value;
    const fechaFin = document.getElementById('custom-fecha-fin').value;

    if (!fechaInicio || !fechaFin) {
        alert('Por favor, selecciona ambas fechas');
        return;
    }

    document.getElementById('analytics-results').innerHTML = '<div class="loading">Cargando análisis...</div>';

    const formData = new FormData();
    formData.append('action', 'get_date_range_stats');
    formData.append('range_type', 'custom');
    formData.append('fecha_inicio', fechaInicio);
    formData.append('fecha_fin', fechaFin);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAnalyticsResults(data.data);
            } else {
                document.getElementById('analytics-results').innerHTML =
                    '<div class="error">Error: ' + data.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('analytics-results').innerHTML =
                '<div class="error">Error de conexión</div>';
        });
}

// ✅ FUNCIÓN PARA RENDERIZAR RESULTADOS DE ANÁLISIS
function renderAnalyticsResults(data) {
    const stats = data.stats;
    const promedioPersonasPorReserva = stats.total_reservas > 0 ?
        (parseFloat(stats.total_personas_con_plaza) / parseFloat(stats.total_reservas)).toFixed(1) : 0;

    let analyticsHtml = `
        <div class="analytics-summary">
            <h4>📊 Resumen del Período: ${data.fecha_inicio} al ${data.fecha_fin}</h4>
            
            <div class="analytics-stats-grid">
                <div class="analytics-stat-card">
                    <h5>Total Reservas</h5>
                    <div class="analytics-stat-number">${stats.total_reservas || 0}</div>
                </div>
                <div class="analytics-stat-card">
                    <h5>Ingresos Totales</h5>
                    <div class="analytics-stat-number">${parseFloat(stats.ingresos_totales || 0).toFixed(2)}€</div>
                </div>
                <div class="analytics-stat-card">
                    <h5>Descuentos Aplicados</h5>
                    <div class="analytics-stat-number">${parseFloat(stats.descuentos_totales || 0).toFixed(2)}€</div>
                </div>
                <div class="analytics-stat-card">
                    <h5>Precio Promedio</h5>
                    <div class="analytics-stat-number">${parseFloat(stats.precio_promedio || 0).toFixed(2)}€</div>
                </div>
            </div>
            
            <div class="people-breakdown">
                <h5>👥 Distribución de Personas</h5>
                <div class="people-stats">
                    <div class="people-stat">
                        <span class="people-label">Adultos:</span>
                        <span class="people-number">${stats.total_adultos || 0}</span>
                    </div>
                    <div class="people-stat">
                        <span class="people-label">Residentes:</span>
                        <span class="people-number">${stats.total_residentes || 0}</span>
                    </div>
                    <div class="people-stat">
                        <span class="people-label">Niños (5-12):</span>
                        <span class="people-number">${stats.total_ninos_5_12 || 0}</span>
                    </div>
                    <div class="people-stat">
                        <span class="people-label">Niños menores:</span>
                        <span class="people-number">${stats.total_ninos_menores || 0}</span>
                    </div>
                    <div class="people-stat total">
                        <span class="people-label">Total con plaza:</span>
                        <span class="people-number">${stats.total_personas_con_plaza || 0}</span>
                    </div>
                </div>
                <p><strong>Promedio personas por reserva:</strong> ${promedioPersonasPorReserva}</p>
            </div>
        </div>
    `;

    // Agregar gráfico simple de reservas por día si hay datos
    if (data.reservas_por_dia && data.reservas_por_dia.length > 0) {
        analyticsHtml += `
            <div class="daily-chart">
                <h5>📈 Reservas por Día</h5>
                <div class="chart-container">
        `;

        data.reservas_por_dia.forEach(dia => {
            const fecha = new Date(dia.fecha).toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit'
            });
            analyticsHtml += `
                <div class="chart-bar">
                    <div class="bar-value">${dia.reservas_dia}</div>
                    <div class="bar" style="height: ${Math.max(dia.reservas_dia * 20, 10)}px;"></div>
                    <div class="bar-label">${fecha}</div>
                </div>
            `;
        });

        analyticsHtml += `
                </div>
            </div>
        `;
    }

    document.getElementById('analytics-results').innerHTML = analyticsHtml;
}

function showQuickStatsModal() {
    document.getElementById('quick-stats-content').innerHTML = '<div class="loading">📊 Cargando estadísticas...</div>';
    document.getElementById('quickStatsModal').style.display = 'block';

    // Cargar estadísticas
    loadQuickStats();
}


function loadQuickStats() {
    const formData = new FormData();
    formData.append('action', 'get_quick_stats');
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderQuickStats(data.data);
            } else {
                document.getElementById('quick-stats-content').innerHTML =
                    '<div class="error">❌ Error cargando estadísticas: ' + data.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('quick-stats-content').innerHTML =
                '<div class="error">❌ Error de conexión</div>';
        });
}


function renderQuickStats(stats) {
    const hoy = new Date().toLocaleDateString('es-ES', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    // Determinar color y emoji para el crecimiento
    let crecimientoColor = '#28a745';
    let crecimientoEmoji = '📈';
    let crecimientoTexto = 'Crecimiento';

    if (stats.ingresos.crecimiento < 0) {
        crecimientoColor = '#dc3545';
        crecimientoEmoji = '📉';
        crecimientoTexto = 'Decrecimiento';
    } else if (stats.ingresos.crecimiento === 0) {
        crecimientoColor = '#ffc107';
        crecimientoEmoji = '➡️';
        crecimientoTexto = 'Sin cambios';
    }

    let html = `
        <div class="quick-stats-container">
            <!-- Resumen Ejecutivo -->
            <div class="stats-summary-header">
                <h4>📊 Resumen Ejecutivo - ${hoy}</h4>
            </div>
            
            <!-- Métricas Principales -->
            <div class="main-metrics">
                <div class="metric-card today">
                    <div class="metric-icon">🎫</div>
                    <div class="metric-content">
                        <div class="metric-number">${stats.hoy.reservas}</div>
                        <div class="metric-label">Reservas Hoy</div>
                    </div>
                </div>
                
                <div class="metric-card revenue">
                    <div class="metric-icon">💰</div>
                    <div class="metric-content">
                        <div class="metric-number">${parseFloat(stats.ingresos.mes_actual).toFixed(2)}€</div>
                        <div class="metric-label">Ingresos Este Mes</div>
                    </div>
                </div>
                
                <div class="metric-card growth" style="border-left-color: ${crecimientoColor}">
                    <div class="metric-icon">${crecimientoEmoji}</div>
                    <div class="metric-content">
                        <div class="metric-number" style="color: ${crecimientoColor}">
                            ${stats.ingresos.crecimiento > 0 ? '+' : ''}${stats.ingresos.crecimiento.toFixed(1)}%
                        </div>
                        <div class="metric-label">${crecimientoTexto} vs Mes Pasado</div>
                    </div>
                </div>
                
                <div class="metric-card occupancy">
                    <div class="metric-icon">🚌</div>
                    <div class="metric-content">
                        <div class="metric-number">${stats.ocupacion.porcentaje.toFixed(1)}%</div>
                        <div class="metric-label">Ocupación Media</div>
                    </div>
                </div>
            </div>
            
            <!-- Información Detallada -->
            <div class="detailed-stats">
                <!-- Top Días -->
                <div class="stat-section">
                    <h5>🏆 Top Días con Más Reservas</h5>
                    <div class="top-days">
    `;

    if (stats.top_dias && stats.top_dias.length > 0) {
        stats.top_dias.forEach((dia, index) => {
            const fecha = new Date(dia.fecha).toLocaleDateString('es-ES', {
                weekday: 'short',
                day: '2-digit',
                month: '2-digit'
            });
            const medalla = ['🥇', '🥈', '🥉'][index] || '🏅';

            html += `
                <div class="top-day-item">
                    <span class="medal">${medalla}</span>
                    <span class="date">${fecha}</span>
                    <span class="count">${dia.total_reservas} reservas</span>
                    <span class="people">${dia.total_personas} personas</span>
                </div>
            `;
        });
    } else {
        html += '<p class="no-data">📊 No hay datos suficientes este mes</p>';
    }

    html += `
                    </div>
                </div>
                
                <!-- Cliente Frecuente -->
                <div class="stat-section">
                    <h5>⭐ Cliente Más Frecuente (último mes)</h5>
    `;

    if (stats.cliente_frecuente && stats.cliente_frecuente.total_reservas > 1) {
        html += `
            <div class="frequent-customer">
                <div class="customer-info">
                    <strong>${stats.cliente_frecuente.nombre_completo}</strong>
                    <span class="email">${stats.cliente_frecuente.email}</span>
                </div>
                <div class="customer-stats">
                    <span class="reservas-count">${stats.cliente_frecuente.total_reservas} reservas</span>
                </div>
            </div>
        `;
    } else {
        html += '<p class="no-data">👥 No hay clientes frecuentes aún</p>';
    }

    html += `
                </div>
                
                <!-- Distribución de Clientes -->
                <div class="stat-section">
                    <h5>👥 Distribución de Clientes (Este Mes)</h5>
                    <div class="client-distribution">
    `;

    if (stats.tipos_cliente) {
        const total = parseInt(stats.tipos_cliente.total_adultos || 0) +
            parseInt(stats.tipos_cliente.total_residentes || 0) +
            parseInt(stats.tipos_cliente.total_ninos || 0) +
            parseInt(stats.tipos_cliente.total_bebes || 0);

        if (total > 0) {
            html += `
                <div class="client-type">
                    <span class="type-icon">👨‍💼</span>
                    <span class="type-label">Adultos:</span>
                    <span class="type-count">${stats.tipos_cliente.total_adultos || 0}</span>
                </div>
                <div class="client-type">
                    <span class="type-icon">🏠</span>
                    <span class="type-label">Residentes:</span>
                    <span class="type-count">${stats.tipos_cliente.total_residentes || 0}</span>
                </div>
                <div class="client-type">
                    <span class="type-icon">👶</span>
                    <span class="type-label">Niños (5-12):</span>
                    <span class="type-count">${stats.tipos_cliente.total_ninos || 0}</span>
                </div>
                <div class="client-type">
                    <span class="type-icon">🍼</span>
                    <span class="type-label">Bebés (gratis):</span>
                    <span class="type-count">${stats.tipos_cliente.total_bebes || 0}</span>
                </div>
            `;
        } else {
            html += '<p class="no-data">📊 No hay reservas este mes</p>';
        }
    }

    html += `
                    </div>
                </div>
                
                <!-- Servicios con Alta Ocupación -->
                <div class="stat-section">
                    <h5>⚠️ Próximos Servicios con Alta Ocupación (>80%)</h5>
                    <div class="high-occupancy">
    `;

    if (stats.servicios_alta_ocupacion && stats.servicios_alta_ocupacion.length > 0) {
        stats.servicios_alta_ocupacion.forEach(servicio => {
            const fecha = new Date(servicio.fecha).toLocaleDateString('es-ES', {
                weekday: 'short',
                day: '2-digit',
                month: '2-digit'
            });
            const ocupacion = parseFloat(servicio.ocupacion).toFixed(1);
            const ocupadas = servicio.plazas_totales - servicio.plazas_disponibles;

            html += `
                <div class="service-alert">
                    <span class="service-date">${fecha} ${servicio.hora}</span>
                    <span class="service-occupancy">${ocupacion}% ocupado</span>
                    <span class="service-seats">${ocupadas}/${servicio.plazas_totales} plazas</span>
                </div>
            `;
        });
    } else {
        html += '<p class="no-data">✅ No hay servicios con alta ocupación</p>';
    }

    html += `
                    </div>
                </div>
            </div>
            
            <!-- Botón de Actualizar -->
            <div class="stats-actions">
                <button class="btn-primary" onclick="loadQuickStats()">🔄 Actualizar Estadísticas</button>
            </div>
        </div>
    `;

    document.getElementById('quick-stats-content').innerHTML = html;
}


function closeQuickStatsModal() {
    document.getElementById('quickStatsModal').style.display = 'none';
}

function closeReservationDetailsModal() {
    document.getElementById('reservationDetailsModal').style.display = 'none';
}

function closeEditEmailModal() {
    document.getElementById('editEmailModal').style.display = 'none';
}


function showCancelReservationModal(reservaId, localizador) {
    // Crear modal si no existe
    if (!document.getElementById('cancelReservationModal')) {
        const modalHtml = `
            <div id="cancelReservationModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <span class="close" onclick="closeCancelReservationModal()">&times;</span>
                    <h3 style="color: #dc3545;">⚠️ Cancelar Reserva</h3>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107;">
                        <p style="margin: 0; color: #856404; font-weight: bold;">
                            ¿Estás seguro de que quieres cancelar la reserva <strong id="cancel-localizador"></strong>?
                        </p>
                        <p style="margin: 5px 0 0 0; color: #856404; font-size: 14px;">
                            Esta acción NO se puede deshacer y se enviarán notificaciones automáticas.
                        </p>
                    </div>
                    <form id="cancelReservationForm">
                        <input type="hidden" id="cancel-reserva-id">
                        <div class="form-group">
                            <label for="motivo-cancelacion" style="font-weight: bold; color: #495057;">
                                Motivo de cancelación (opcional):
                            </label>
                            <textarea id="motivo-cancelacion" name="motivo_cancelacion" 
                                      rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;" 
                                      placeholder="Ej: Problema técnico, Cancelación por parte del cliente, etc."></textarea>
                        </div>
                        <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                            <button type="button" class="btn-secondary" onclick="closeCancelReservationModal()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn-danger" style="background: #dc3545; color: white;">
                                ❌ Confirmar Cancelación
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Añadir evento al formulario
        document.getElementById('cancelReservationForm').addEventListener('submit', function (e) {
            e.preventDefault();
            processCancelReservation();
        });
    }

    // Configurar modal
    document.getElementById('cancel-reserva-id').value = reservaId;
    document.getElementById('cancel-localizador').textContent = localizador;
    document.getElementById('motivo-cancelacion').value = '';
    document.getElementById('cancelReservationModal').style.display = 'block';
}

/**
 * Cerrar modal de cancelación
 */
function closeCancelReservationModal() {
    document.getElementById('cancelReservationModal').style.display = 'none';
}

/**
 * Procesar cancelación de reserva
 */
function processCancelReservation() {
    const reservaId = document.getElementById('cancel-reserva-id').value;
    const motivo = document.getElementById('motivo-cancelacion').value || 'Cancelación administrativa';

    if (!confirm('¿Estás COMPLETAMENTE SEGURO de cancelar esta reserva?\n\n⚠️ ESTA ACCIÓN NO SE PUEDE DESHACER ⚠️')) {
        return;
    }

    // Deshabilitar botón
    const submitBtn = document.querySelector('#cancelReservationForm button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Cancelando...';

    const formData = new FormData();
    formData.append('action', 'cancel_reservation');
    formData.append('reserva_id', reservaId);
    formData.append('motivo_cancelacion', motivo);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            // Rehabilitar botón
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;

            if (data.success) {
                alert('✅ ' + data.data);
                closeCancelReservationModal();

                // Recargar la lista actual
                const activeTab = document.querySelector('.tab-btn.active');
                if (activeTab && activeTab.textContent.includes('Reservas')) {
                    loadReservationsByDate();
                } else if (activeTab && activeTab.textContent.includes('Buscar')) {
                    searchReservations();
                }
            } else {
                alert('❌ Error: ' + data.data);
            }
        })
        .catch(error => {
            // Rehabilitar botón
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;

            console.error('Error:', error);
            alert('❌ Error de conexión al cancelar la reserva');
        });
}

function showLoadingInContent() {
    const targetElement = document.querySelector('.dashboard-content') || document.getElementById('dashboard-content');
    
    if (targetElement) {
        targetElement.innerHTML = '<div class="loading">Cargando reserva rápida...</div>';
    } else {
        console.log('Loading reserva rápida...');
    }
}

function showErrorInContent(message) {
    const targetElement = document.querySelector('.dashboard-content') || document.getElementById('dashboard-content');
    
    if (targetElement) {
        targetElement.innerHTML = `<div class="error">${message}</div>`;
    } else {
        alert('Error: ' + message);
    }
}

function loadAdminReservaRapida() {
    console.log('=== CARGANDO RESERVA RÁPIDA ADMIN ===');
    
    showLoadingInContent();
    
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_reserva_rapida_form',
            nonce: reservasAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                if (response.data.action === 'initialize_admin_reserva_rapida') {
                    // Inicializar reserva rápida con flujo de calendario
                    initAdminReservaRapida();
                } else {
                    // Fallback al método anterior si es necesario
                    document.body.innerHTML = response.data;
                }
            } else {
                showErrorInContent('Error cargando reserva rápida: ' + response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            showErrorInContent('Error de conexión cargando reserva rápida');
        }
    });
}



// Variables globales para reserva rápida admin
let adminCurrentDate = new Date();
let adminSelectedDate = null;
let adminSelectedServiceId = null;
let adminServicesData = {};
let adminCurrentStep = 1;
let adminDiasAnticiapcionMinima = 1;

function initAdminQuickReservation() {
    console.log('=== INICIALIZANDO RESERVA RÁPIDA ADMIN ===');
    
    // Cargar configuración y luego calendario
    loadAdminSystemConfiguration().then(() => {
        loadAdminCalendar();
        setupAdminEventListeners();
    });
}

function loadAdminSystemConfiguration() {
    return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('action', 'get_configuration');
        formData.append('nonce', reservasAjax.nonce);

        fetch(reservasAjax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const config = data.data;
                adminDiasAnticiapcionMinima = parseInt(config.servicios?.dias_anticipacion_minima?.value || '1');
                console.log('Admin: Días de anticipación mínima cargados:', adminDiasAnticiapcionMinima);
                resolve();
            } else {
                console.warn('Admin: No se pudo cargar configuración, usando valores por defecto');
                adminDiasAnticiapcionMinima = 1;
                resolve();
            }
        })
        .catch(error => {
            console.error('Error cargando configuración:', error);
            adminDiasAnticiapcionMinima = 1;
            resolve();
        });
    });
}

function setupAdminEventListeners() {
    // Navegación del calendario
    document.getElementById('admin-prev-month').addEventListener('click', function() {
        adminCurrentDate.setMonth(adminCurrentDate.getMonth() - 1);
        loadAdminCalendar();
    });

    document.getElementById('admin-next-month').addEventListener('click', function() {
        adminCurrentDate.setMonth(adminCurrentDate.getMonth() + 1);
        loadAdminCalendar();
    });

    // Selección de horario
    document.getElementById('admin-horarios-select').addEventListener('change', function() {
        adminSelectedServiceId = this.value;
        if (adminSelectedServiceId) {
            document.getElementById('admin-btn-siguiente').disabled = false;
            loadAdminPrices();
        } else {
            document.getElementById('admin-btn-siguiente').disabled = true;
            document.getElementById('admin-total-price').textContent = '0€';
        }
    });

    ['admin-adultos', 'admin-residentes', 'admin-ninos-5-12', 'admin-ninos-menores'].forEach(id => {
        const input = document.getElementById(id);
        
        // Múltiples eventos para asegurar detección
        ['input', 'change', 'keyup', 'blur'].forEach(eventType => {
            input.addEventListener(eventType, function() {
                setTimeout(() => {
                    calculateAdminTotalPrice();
                    validateAdminPersonSelectionForNext();
                }, 100);
            });
        });
    });
}

function validateAdminPersonSelectionForNext() {
    const adultos = parseInt(document.getElementById('admin-adultos').value) || 0;
    const residentes = parseInt(document.getElementById('admin-residentes').value) || 0;
    const ninos512 = parseInt(document.getElementById('admin-ninos-5-12').value) || 0;
    const ninosMenores = parseInt(document.getElementById('admin-ninos-menores').value) || 0;

    const totalAdults = adultos + residentes;
    const totalChildren = ninos512 + ninosMenores;
    const totalPersonas = totalAdults + totalChildren;

    console.log('=== VALIDACIÓN PARA SIGUIENTE ===');
    console.log('Adultos:', adultos, 'Residentes:', residentes, 'Niños 5-12:', ninos512, 'Menores:', ninosMenores);
    console.log('Total personas:', totalPersonas, 'Total adultos:', totalAdults);

    // Validar que hay al menos una persona
    if (totalPersonas === 0) {
        console.log('❌ No hay personas seleccionadas');
        document.getElementById('admin-btn-siguiente').disabled = true;
        return false;
    }

    // Validar que si hay niños, debe haber al menos un adulto
    if (totalChildren > 0 && totalAdults === 0) {
        console.log('❌ Hay niños pero no adultos');
        alert('Debe haber al menos un adulto si hay niños en la reserva.');
        document.getElementById('admin-ninos-5-12').value = 0;
        document.getElementById('admin-ninos-menores').value = 0;
        calculateAdminTotalPrice();
        document.getElementById('admin-btn-siguiente').disabled = true;
        return false;
    }

    // Si llegamos aquí, todo está bien
    console.log('✅ Validación correcta - habilitando botón siguiente');
    document.getElementById('admin-btn-siguiente').disabled = false;
    return true;
}

function validateAdminPersonSelection() {
    const adultos = parseInt(document.getElementById('admin-adultos').value) || 0;
    const residentes = parseInt(document.getElementById('admin-residentes').value) || 0;
    const ninos512 = parseInt(document.getElementById('admin-ninos-5-12').value) || 0;
    const ninosMenores = parseInt(document.getElementById('admin-ninos-menores').value) || 0;

    const totalAdults = adultos + residentes;
    const totalChildren = ninos512 + ninosMenores;

    if (totalChildren > 0 && totalAdults === 0) {
        alert('Debe haber al menos un adulto si hay niños en la reserva.');
        document.getElementById('admin-ninos-5-12').value = 0;
        document.getElementById('admin-ninos-menores').value = 0;
        calculateAdminTotalPrice();
        return false;
    }

    return true;
}

function loadAdminCalendar() {
    updateAdminCalendarHeader();

    const formData = new FormData();
    formData.append('action', 'get_available_services'); // ✅ MISMO ENDPOINT QUE FRONTEND
    formData.append('month', adminCurrentDate.getMonth() + 1);
    formData.append('year', adminCurrentDate.getFullYear());
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            adminServicesData = data.data;
            renderAdminCalendar();
        } else {
            console.error('Error cargando servicios admin:', data.data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function updateAdminCalendarHeader() {
    const monthNames = [
        'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
        'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'
    ];

    const monthYear = monthNames[adminCurrentDate.getMonth()] + ' ' + adminCurrentDate.getFullYear();
    document.getElementById('admin-current-month-year').textContent = monthYear;
}

function renderAdminCalendar() {
    const year = adminCurrentDate.getFullYear();
    const month = adminCurrentDate.getMonth();

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    let firstDayOfWeek = firstDay.getDay();
    firstDayOfWeek = (firstDayOfWeek + 6) % 7; // Lunes = 0

    const daysInMonth = lastDay.getDate();
    const dayNames = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

    let calendarHTML = '';

    // Encabezados de días
    dayNames.forEach(day => {
        calendarHTML += `<div class="calendar-day-header">${day}</div>`;
    });

    // Días del mes anterior
    for (let i = 0; i < firstDayOfWeek; i++) {
        const dayNum = new Date(year, month, -firstDayOfWeek + i + 1).getDate();
        calendarHTML += `<div class="calendar-day other-month">${dayNum}</div>`;
    }

    // Calcular fecha mínima basada en configuración
    const today = new Date();
    const fechaMinima = new Date();
    fechaMinima.setDate(today.getDate() + adminDiasAnticiapcionMinima);

    // Días del mes actual
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayDate = new Date(year, month, day);

        let dayClass = 'calendar-day';
        let clickHandler = '';

        // Verificar si el día está bloqueado por días de anticipación
        const isBlockedByAnticipacion = dayDate < fechaMinima;

        if (isBlockedByAnticipacion) {
            dayClass += ' no-disponible';
        } else if (adminServicesData[dateStr] && adminServicesData[dateStr].length > 0) {
            dayClass += ' disponible';
            clickHandler = `onclick="selectAdminDate('${dateStr}')"`;

            // Verificar si algún servicio tiene descuento
            const tieneDescuento = adminServicesData[dateStr].some(service =>
                service.tiene_descuento && parseFloat(service.porcentaje_descuento) > 0
            );

            if (tieneDescuento) {
                dayClass += ' oferta';
            }
        } else {
            dayClass += ' no-disponible';
        }

        if (adminSelectedDate === dateStr) {
            dayClass += ' selected';
        }

        calendarHTML += `<div class="${dayClass}" ${clickHandler}>${day}</div>`;
    }

    document.getElementById('admin-calendar-grid').innerHTML = calendarHTML;
}

function selectAdminDate(dateStr) {
    adminSelectedDate = dateStr;
    adminSelectedServiceId = null;

    // Actualizar visual del calendario
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.classList.remove('selected');
    });
    event.target.classList.add('selected');

    // Cargar horarios disponibles
    loadAdminAvailableSchedules(dateStr);
}

function loadAdminAvailableSchedules(dateStr) {
    const services = adminServicesData[dateStr] || [];

    let optionsHTML = '<option value="">Selecciona un horario</option>';

    services.forEach(service => {
        let descuentoInfo = '';
        if (service.tiene_descuento && parseFloat(service.porcentaje_descuento) > 0) {
            descuentoInfo = ` (${service.porcentaje_descuento}% descuento)`;
        }

        optionsHTML += `<option value="${service.id}">${service.hora} - ${service.plazas_disponibles} plazas disponibles${descuentoInfo}</option>`;
    });

    document.getElementById('admin-horarios-select').innerHTML = optionsHTML;
    document.getElementById('admin-horarios-select').disabled = false;
    document.getElementById('admin-btn-siguiente').disabled = true;
}

function loadAdminPrices() {
    if (!adminSelectedServiceId) return;

    const service = findAdminServiceById(adminSelectedServiceId);
    if (service) {
        document.getElementById('admin-price-adultos').textContent = service.precio_adulto + '€';
        document.getElementById('admin-price-ninos').textContent = service.precio_nino + '€';
        calculateAdminTotalPrice();
    }
}

function findAdminServiceById(serviceId) {
    for (let date in adminServicesData) {
        for (let service of adminServicesData[date]) {
            if (service.id == serviceId) {
                return service;
            }
        }
    }
    return null;
}

function calculateAdminTotalPrice() {
    if (!adminSelectedServiceId) {
        clearAdminPricing();
        return;
    }

    const adultos = parseInt(document.getElementById('admin-adultos').value) || 0;
    const residentes = parseInt(document.getElementById('admin-residentes').value) || 0;
    const ninos512 = parseInt(document.getElementById('admin-ninos-5-12').value) || 0;
    const ninosMenores = parseInt(document.getElementById('admin-ninos-menores').value) || 0;

    const totalPersonas = adultos + residentes + ninos512 + ninosMenores;

    if (totalPersonas === 0) {
        document.getElementById('admin-total-discount').textContent = '';
        document.getElementById('admin-total-price').textContent = '0€';
        document.getElementById('admin-discount-row').style.display = 'none';
        document.getElementById('admin-discount-message').classList.remove('show');
        return;
    }

    // ✅ USAR MISMO ENDPOINT QUE FRONTEND
    const formData = new FormData();
    formData.append('action', 'calculate_price'); // ✅ MISMO ENDPOINT
    formData.append('service_id', adminSelectedServiceId);
    formData.append('adultos', adultos);
    formData.append('residentes', residentes);
    formData.append('ninos_5_12', ninos512);
    formData.append('ninos_menores', ninosMenores);
    formData.append('nonce', reservasAjax.nonce);

    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const result = data.data;
            updateAdminPricingDisplay(result);
        } else {
            console.error('Error calculando precio admin:', data);
            document.getElementById('admin-total-price').textContent = '0€';
            document.getElementById('admin-total-discount').textContent = '';
            document.getElementById('admin-discount-row').style.display = 'none';
            document.getElementById('admin-discount-message').classList.remove('show');
        }
    })
    .catch(error => {
        console.error('Error calculando precio admin:', error);
        document.getElementById('admin-total-price').textContent = '0€';
        document.getElementById('admin-total-discount').textContent = '';
        document.getElementById('admin-discount-row').style.display = 'none';
        document.getElementById('admin-discount-message').classList.remove('show');
    });
}

function updateAdminPricingDisplay(result) {
    // Manejar descuentos (misma lógica que frontend)
    if (result.descuento > 0) {
        document.getElementById('admin-total-discount').textContent = '-' + result.descuento.toFixed(2) + '€';
        document.getElementById('admin-discount-row').style.display = 'block';
    } else {
        document.getElementById('admin-discount-row').style.display = 'none';
    }

    // Manejar mensaje de descuento por grupo
    if (result.regla_descuento_aplicada && result.regla_descuento_aplicada.rule_name) {
        const regla = result.regla_descuento_aplicada;
        const mensaje = `Descuento del ${regla.discount_percentage}% por ${regla.rule_name.toLowerCase()}`;

        document.getElementById('admin-discount-text').textContent = mensaje;
        document.getElementById('admin-discount-message').classList.add('show');
    } else {
        document.getElementById('admin-discount-message').classList.remove('show');
    }

    window.adminLastDiscountRule = result.regla_descuento_aplicada;

    // Actualizar precio total
    const totalPrice = parseFloat(result.total) || 0;
    document.getElementById('admin-total-price').textContent = totalPrice.toFixed(2) + '€';
}

function clearAdminPricing() {
    document.getElementById('admin-total-discount').textContent = '';
    document.getElementById('admin-total-price').textContent = '0€';
    document.getElementById('admin-discount-row').style.display = 'none';
    document.getElementById('admin-discount-message').classList.remove('show');
}

function validateAdminPersonSelection() {
    const adultos = parseInt(document.getElementById('admin-adultos').value) || 0;
    const residentes = parseInt(document.getElementById('admin-residentes').value) || 0;
    const ninos512 = parseInt(document.getElementById('admin-ninos-5-12').value) || 0;
    const ninosMenores = parseInt(document.getElementById('admin-ninos-menores').value) || 0;

    const totalAdults = adultos + residentes;
    const totalChildren = ninos512 + ninosMenores;

    if (totalChildren > 0 && totalAdults === 0) {
        alert('Debe haber al menos un adulto si hay niños en la reserva.');
        document.getElementById('admin-ninos-5-12').value = 0;
        document.getElementById('admin-ninos-menores').value = 0;
        calculateAdminTotalPrice();
        return false;
    }

    return true;
}



function adminPreviousStep() {
    console.log('Admin: Retrocediendo desde paso', adminCurrentStep);
    
    if (adminCurrentStep === 2) {
        // Volver al paso 1
        document.getElementById('admin-step-2').style.display = 'none';
        document.getElementById('admin-step-1').style.display = 'block';
        
        // Actualizar indicadores
        document.getElementById('admin-step-2-indicator').classList.remove('active');
        document.getElementById('admin-step-1-indicator').classList.add('active');
        
        // Actualizar navegación
        document.getElementById('admin-btn-anterior').style.display = 'none';
        document.getElementById('admin-btn-siguiente').disabled = adminSelectedServiceId ? false : true;
        document.getElementById('admin-step-text').textContent = 'Paso 1 de 4: Seleccionar fecha y horario';
        
        adminCurrentStep = 1;
        
    } else if (adminCurrentStep === 3) {
        // Volver al paso 2
        document.getElementById('admin-step-3').style.display = 'none';
        document.getElementById('admin-step-2').style.display = 'block';
        
        // Actualizar indicadores
        document.getElementById('admin-step-3-indicator').classList.remove('active');
        document.getElementById('admin-step-2-indicator').classList.add('active');
        
        // Actualizar navegación
        document.getElementById('admin-btn-siguiente').disabled = false;
        document.getElementById('admin-step-text').textContent = 'Paso 2 de 4: Seleccionar personas';
        
        adminCurrentStep = 2;
        
    } else if (adminCurrentStep === 4) {
        // Volver al paso 3
        document.getElementById('admin-step-4').style.display = 'none';
        document.getElementById('admin-step-3').style.display = 'block';
        
        // Actualizar indicadores
        document.getElementById('admin-step-4-indicator').classList.remove('active');
        document.getElementById('admin-step-3-indicator').classList.add('active');
        
        // Actualizar navegación
        document.getElementById('admin-btn-siguiente').style.display = 'block';
        document.getElementById('admin-btn-confirmar').style.display = 'none';
        document.getElementById('admin-btn-siguiente').disabled = false;
        document.getElementById('admin-step-text').textContent = 'Paso 3 de 4: Datos del cliente';
        
        adminCurrentStep = 3;
    }
}

function setupAdminFormValidation() {
    const inputs = document.querySelectorAll('#admin-client-form input');
    
    function validateForm() {
        let allValid = true;
        inputs.forEach(input => {
            if (!input.value.trim()) {
                allValid = false;
            }
        });
        
        // Validar email específicamente
        const emailInput = document.querySelector('#admin-client-form input[name="email"]');
        if (emailInput.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailInput.value.trim())) {
                allValid = false;
            }
        }
        
        document.getElementById('admin-btn-siguiente').disabled = !allValid;
    }
    
    inputs.forEach(input => {
        input.addEventListener('input', validateForm);
        input.addEventListener('blur', validateForm);
    });
    
    // Validar inicialmente
    validateForm();
}

function fillAdminConfirmationData() {
    console.log('=== LLENANDO DATOS DE CONFIRMACIÓN ===');
    
    // Verificar que tenemos todos los datos necesarios
    if (!adminSelectedServiceId || !adminSelectedDate) {
        console.error('❌ Faltan datos básicos:', {
            serviceId: adminSelectedServiceId,
            selectedDate: adminSelectedDate
        });
        return;
    }
    
    const service = findAdminServiceById(adminSelectedServiceId);
    if (!service) {
        console.error('❌ No se encontró el servicio');
        return;
    }
    
    console.log('✅ Servicio encontrado:', service);
    
    // Obtener datos del formulario
    const nombreInput = document.getElementById('admin-nombre');
    const apellidosInput = document.getElementById('admin-apellidos');
    const emailInput = document.getElementById('admin-email');
    const telefonoInput = document.getElementById('admin-telefono');
    
    if (!nombreInput || !apellidosInput || !emailInput || !telefonoInput) {
        console.error('❌ No se encontraron los campos del formulario');
        return;
    }
    
    const nombre = nombreInput.value.trim();
    const apellidos = apellidosInput.value.trim();
    const email = emailInput.value.trim();
    const telefono = telefonoInput.value.trim();
    
    console.log('✅ Datos del cliente:', { nombre, apellidos, email, telefono });
    
    // Obtener datos de personas
    const adultos = parseInt(document.getElementById('admin-adultos').value) || 0;
    const residentes = parseInt(document.getElementById('admin-residentes').value) || 0;
    const ninos512 = parseInt(document.getElementById('admin-ninos-5-12').value) || 0;
    const ninosMenores = parseInt(document.getElementById('admin-ninos-menores').value) || 0;
    const totalPersonas = adultos + residentes + ninos512 + ninosMenores;
    
    console.log('✅ Datos de personas:', { adultos, residentes, ninos512, ninosMenores, totalPersonas });
    
    // Formatear fecha
    let fechaFormateada = adminSelectedDate;
    try {
        const fechaObj = new Date(adminSelectedDate + 'T00:00:00');
        fechaFormateada = fechaObj.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        // Capitalizar primera letra
        fechaFormateada = fechaFormateada.charAt(0).toUpperCase() + fechaFormateada.slice(1);
    } catch (e) {
        console.warn('No se pudo formatear la fecha, usando formato original');
    }
    
    // Crear detalle de personas
    let personasDetalle = [];
    if (adultos > 0) personasDetalle.push(`${adultos} adulto${adultos > 1 ? 's' : ''}`);
    if (residentes > 0) personasDetalle.push(`${residentes} residente${residentes > 1 ? 's' : ''}`);
    if (ninos512 > 0) personasDetalle.push(`${ninos512} niño${ninos512 > 1 ? 's' : ''} (5-12)`);
    if (ninosMenores > 0) personasDetalle.push(`${ninosMenores} bebé${ninosMenores > 1 ? 's' : ''} (gratis)`);
    
    const personasTexto = personasDetalle.length > 0 ? 
        `${totalPersonas} personas (${personasDetalle.join(', ')})` : 
        `${totalPersonas} personas`;
    
    // Obtener precio total
    const totalPriceElement = document.getElementById('admin-total-price');
    const precioTotal = totalPriceElement ? totalPriceElement.textContent : '0€';
    
    console.log('✅ Datos finales a mostrar:', {
        fecha: fechaFormateada,
        hora: service.hora,
        personas: personasTexto,
        cliente: `${nombre} ${apellidos}`,
        email: email,
        total: precioTotal
    });
    
    // Actualizar elementos de confirmación
    const confirmElements = {
        'admin-confirm-fecha': fechaFormateada,
        'admin-confirm-hora': service.hora,
        'admin-confirm-personas': personasTexto,
        'admin-confirm-cliente': `${nombre} ${apellidos}`,
        'admin-confirm-email': email,
        'admin-confirm-total': precioTotal
    };
    
    // Aplicar datos a los elementos
    let errorsFound = 0;
    Object.keys(confirmElements).forEach(elementId => {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = confirmElements[elementId];
            console.log(`✅ ${elementId}: ${confirmElements[elementId]}`);
        } else {
            console.error(`❌ No se encontró elemento: ${elementId}`);
            errorsFound++;
        }
    });
    
    if (errorsFound === 0) {
        console.log('✅ Todos los datos de confirmación se llenaron correctamente');
    } else {
        console.error(`❌ Se encontraron ${errorsFound} errores al llenar los datos`);
    }
}

function adminConfirmReservation() {
    console.log('=== CONFIRMANDO RESERVA RÁPIDA ADMIN ===');
    
    if (!confirm('¿Estás seguro de que quieres procesar esta reserva?\n\nSe enviará automáticamente la confirmación por email al cliente.')) {
        return;
    }
    
    // Deshabilitar botón
    const confirmBtn = document.getElementById('admin-btn-confirmar');
    const originalText = confirmBtn.textContent;
    confirmBtn.disabled = true;
    confirmBtn.textContent = '⏳ Procesando...';
    
    // Preparar datos de la reserva
    const service = findAdminServiceById(adminSelectedServiceId);
    const form = document.getElementById('admin-client-form');
    const formData = new FormData(form);
    
    const adultos = parseInt(document.getElementById('admin-adultos').value) || 0;
    const residentes = parseInt(document.getElementById('admin-residentes').value) || 0;
    const ninos_5_12 = parseInt(document.getElementById('admin-ninos-5-12').value) || 0;
    const ninos_menores = parseInt(document.getElementById('admin-ninos-menores').value) || 0;
    
    const totalPrice = document.getElementById('admin-total-price').textContent.replace('€', '').trim();
    const descuentoTotal = document.getElementById('admin-total-discount').textContent.replace('€', '').replace('-', '').trim();
    
    const reservationData = {
        fecha: adminSelectedDate,
        service_id: adminSelectedServiceId,
        hora_ida: service.hora,
        adultos: adultos,
        residentes: residentes,
        ninos_5_12: ninos_5_12,
        ninos_menores: ninos_menores,
        precio_adulto: service.precio_adulto,
        precio_nino: service.precio_nino,
        precio_residente: service.precio_residente,
        total_price: totalPrice,
        descuento_grupo: descuentoTotal ? parseFloat(descuentoTotal) : 0,
        regla_descuento_aplicada: window.adminLastDiscountRule || null
    };
    
    // Enviar solicitud AJAX
    const ajaxData = {
        action: 'process_reservation',
        nonce: reservasAjax.nonce,
        nombre: formData.get('nombre'),
        apellidos: formData.get('apellidos'),
        email: formData.get('email'),
        telefono: formData.get('telefono'),
        reservation_data: JSON.stringify(reservationData)
    };
    
    console.log('Datos a enviar:', ajaxData);
    
    fetch(reservasAjax.ajax_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(ajaxData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Respuesta recibida:', data);
        
        // Rehabilitar botón
        confirmBtn.disabled = false;
        confirmBtn.textContent = originalText;
        
        if (data && data.success) {
            console.log('Reserva procesada exitosamente:', data.data);
            
            // Mostrar mensaje de éxito
            const detalles = data.data.detalles;
            const mensaje = "🎉 ¡RESERVA CREADA EXITOSAMENTE! 🎉\n\n" +
                           "📋 LOCALIZADOR: " + data.data.localizador + "\n\n" +
                           "📅 DETALLES:\n" +
                           "• Fecha: " + detalles.fecha + "\n" +
                           "• Hora: " + detalles.hora + "\n" +
                           "• Personas: " + detalles.personas + "\n" +
                           "• Precio: " + detalles.precio_final + "€\n\n" +
                           "✅ La reserva ha sido procesada correctamente.\n" +
                           "📧 El cliente recibirá la confirmación por email.\n\n" +
                           "¡Reserva administrativa completada!";

            alert(mensaje);
            
            // Volver al dashboard
            setTimeout(() => {
                goBackToDashboard();
            }, 2000);
            
        } else {
            console.error('Error procesando reserva:', data);
            const errorMsg = data && data.data ? data.data : 'Error desconocido';
            alert('❌ Error procesando la reserva: ' + errorMsg);
        }
    })
    .catch(error => {
        console.error('Error de conexión:', error);
        
        // Rehabilitar botón
        confirmBtn.disabled = false;
        confirmBtn.textContent = originalText;
        
        alert('❌ Error de conexión al procesar la reserva.\n\nPor favor, inténtalo de nuevo. Si el problema persiste, contacta con soporte técnico.');
    });
}

// Exponer funciones globalmente para onclick
window.selectAdminDate = selectAdminDate;
window.adminNextStep = adminNextStep;
window.adminPreviousStep = adminPreviousStep;
window.adminConfirmReservation = adminConfirmReservation;

function adminNextStep() {
    console.log('Admin: Avanzando al siguiente paso desde', adminCurrentStep);
    
    if (adminCurrentStep === 1) {
        // Validar paso 1
        if (!adminSelectedDate || !adminSelectedServiceId) {
            alert('Por favor, selecciona una fecha y horario.');
            return;
        }
        
        // Ocultar paso 1 y mostrar paso 2
        document.getElementById('admin-step-1').style.display = 'none';
        document.getElementById('admin-step-2').style.display = 'block';
        
        // Actualizar indicadores de pasos
        document.getElementById('admin-step-1-indicator').classList.remove('active');
        document.getElementById('admin-step-2-indicator').classList.add('active');
        
        // Actualizar navegación
        document.getElementById('admin-btn-anterior').style.display = 'block';
        document.getElementById('admin-btn-siguiente').disabled = true;
        document.getElementById('admin-step-text').textContent = 'Paso 2 de 4: Seleccionar personas';
        
        adminCurrentStep = 2;
        
        // Cargar precios en el paso 2
        loadAdminPrices();
        
    } else if (adminCurrentStep === 2) {
        // Validar paso 2
        const adultos = parseInt(document.getElementById('admin-adultos').value) || 0;
        const residentes = parseInt(document.getElementById('admin-residentes').value) || 0;
        const ninos512 = parseInt(document.getElementById('admin-ninos-5-12').value) || 0;
        const ninosMenores = parseInt(document.getElementById('admin-ninos-menores').value) || 0;

        const totalPersonas = adultos + residentes + ninos512 + ninosMenores;

        if (totalPersonas === 0) {
            alert('Debe seleccionar al menos una persona.');
            return;
        }

        if (!validateAdminPersonSelection()) {
            return;
        }
        
        // Ocultar paso 2 y mostrar paso 3
        document.getElementById('admin-step-2').style.display = 'none';
        document.getElementById('admin-step-3').style.display = 'block';
        
        // Actualizar indicadores de pasos
        document.getElementById('admin-step-2-indicator').classList.remove('active');
        document.getElementById('admin-step-3-indicator').classList.add('active');
        
        // Actualizar navegación
        document.getElementById('admin-btn-siguiente').disabled = true;
        document.getElementById('admin-step-text').textContent = 'Paso 3 de 4: Datos del cliente';
        
        adminCurrentStep = 3;
        
        // Configurar validación del formulario
        setupAdminFormValidation();
        
    } else if (adminCurrentStep === 3) {
    // Validar paso 3
    const form = document.getElementById('admin-client-form');
    
    // Verificar que el formulario existe
    if (!form) {
        console.error('❌ No se encontró el formulario de cliente');
        alert('Error: No se encontró el formulario. Recarga la página e inténtalo de nuevo.');
        return;
    }
    
    const formData = new FormData(form);
    
    const nombre = formData.get('nombre') ? formData.get('nombre').trim() : '';
    const apellidos = formData.get('apellidos') ? formData.get('apellidos').trim() : '';
    const email = formData.get('email') ? formData.get('email').trim() : '';
    const telefono = formData.get('telefono') ? formData.get('telefono').trim() : '';

    console.log('=== VALIDANDO PASO 3 ===');
    console.log('Datos del formulario:', { nombre, apellidos, email, telefono });

    if (!nombre || !apellidos || !email || !telefono) {
        console.error('❌ Campos faltantes:', { nombre, apellidos, email, telefono });
        alert('Por favor, completa todos los campos del cliente.');
        return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        console.error('❌ Email no válido:', email);
        alert('Por favor, introduce un email válido.');
        return;
    }
    
    console.log('✅ Validación del paso 3 completada');
    
    // Ocultar paso 3 y mostrar paso 4
    document.getElementById('admin-step-3').style.display = 'none';
    document.getElementById('admin-step-4').style.display = 'block';
    
    // Actualizar indicadores de pasos
    document.getElementById('admin-step-3-indicator').classList.remove('active');
    document.getElementById('admin-step-4-indicator').classList.add('active');
    
    // Actualizar navegación
    document.getElementById('admin-btn-siguiente').style.display = 'none';
    document.getElementById('admin-btn-confirmar').style.display = 'block';
    document.getElementById('admin-step-text').textContent = 'Paso 4 de 4: Confirmar reserva';
    
    adminCurrentStep = 4;
    
    // ✅ AÑADIR UN PEQUEÑO DELAY PARA ASEGURAR QUE EL DOM SE ACTUALICE
    setTimeout(() => {
        fillAdminConfirmationData();
    }, 100);
}
}


function loadAgenciesSection() {
    console.log('=== CARGANDO SECCIÓN DE AGENCIAS ===');
    
    // Mostrar indicador de carga
    showLoadingInMainContent();
    
    // Cargar la lista de agencias
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_agencies_list',
            nonce: reservasAjax.nonce
        },
        success: function(response) {
            console.log('Respuesta del servidor:', response);
            
            if (response.success) {
                renderAgenciesSection(response.data);
            } else {
                showErrorInMainContent('Error cargando agencias: ' + response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            showErrorInMainContent('Error de conexión al cargar agencias');
        }
    });
}

function showErrorInMainContent(message) {
    document.body.innerHTML = `
        <div class="error-container" style="text-align: center; padding: 50px;">
            <h2 style="color: #d63638;">Error</h2>
            <p style="color: #d63638;">${message}</p>
            <button class="btn-secondary" onclick="goBackToDashboard()">← Volver al Dashboard</button>
        </div>
    `;
}

/**
 * Renderizar la sección de gestión de agencias
 */
function renderAgenciesSection(agencies) {
    const content = `
        <div class="agencies-management">
            <div class="section-header">
                <h2>🏢 Gestión de Agencias</h2>
                <p>Administra las agencias asociadas al sistema de reservas</p>
            </div>
            
            <div class="actions-bar">
                <button class="btn-primary" onclick="showCreateAgencyModal()">
                    ➕ Crear Nueva Agencia
                </button>
                <button class="btn-secondary" onclick="refreshAgenciesList()">
                    🔄 Actualizar Lista
                </button>
                <button class="btn-secondary" onclick="goBackToDashboard()">
                    ← Volver al Dashboard
                </button>
            </div>
            
            <div class="agencies-stats">
                <div class="stat-card">
                    <h3>Total Agencias</h3>
                    <div class="stat-number">${agencies.length}</div>
                </div>
                <div class="stat-card">
                    <h3>Agencias Activas</h3>
                    <div class="stat-number">${agencies.filter(a => a.status === 'active').length}</div>
                </div>
                <div class="stat-card">
                    <h3>Agencias Inactivas</h3>
                    <div class="stat-number">${agencies.filter(a => a.status !== 'active').length}</div>
                </div>
            </div>
            
            <div class="agencies-table-container">
                <table class="agencies-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre Agencia</th>
                            <th>Contacto</th>
                            <th>Email</th>
                            <th>Usuario</th>
                            <th>Comisión</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${renderAgenciesTableRows(agencies)}
                    </tbody>
                </table>
            </div>
        </div>
        
        ${renderCreateAgencyModal()}
        ${renderEditAgencyModal()}
        
        <style>
        .agencies-management {
            padding: 20px;
        }
        
        .section-header h2 {
            margin: 0 0 10px 0;
            color: #23282d;
        }
        
        .section-header p {
            margin: 0 0 30px 0;
            color: #666;
        }
        
        .actions-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            align-items: center;
        }
        
        .agencies-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #0073aa;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .agencies-table-container {
            background: white;
            border-radius: 8px;
            overflow-x: auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .agencies-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .agencies-table th,
        .agencies-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .agencies-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #23282d;
        }
        
        .agencies-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #edfaed;
            color: #00a32a;
        }
        
        .status-inactive {
            background: #fef7f7;
            color: #d63638;
        }
        
        .status-suspended {
            background: #fff8e1;
            color: #f57c00;
        }
        
        .actions-cell {
            white-space: nowrap;
        }
        
        .btn-edit, .btn-toggle, .btn-delete {
            padding: 6px 12px;
            margin: 0 2px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit {
            background: #0073aa;
            color: white;
        }
        
        .btn-toggle {
            background: #f57c00;
            color: white;
        }
        
        .btn-delete {
            background: #d63638;
            color: white;
        }
        
        .btn-edit:hover {
            background: #005a87;
        }
        
        .btn-toggle:hover {
            background: #e65100;
        }
        
        .btn-delete:hover {
            background: #b32d36;
        }
        </style>
    `;
    
    // Insertar contenido en el dashboard principal
    jQuery('.dashboard-content').html(content);
}

/**
 * Renderizar filas de la tabla de agencias
 */
function renderAgenciesTableRows(agencies) {
    if (agencies.length === 0) {
        return `
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                    No hay agencias registradas. Crea la primera agencia usando el botón "Crear Nueva Agencia".
                </td>
            </tr>
        `;
    }
    
    return agencies.map(agency => `
        <tr>
            <td>${agency.id}</td>
            <td><strong>${escapeHtml(agency.agency_name)}</strong></td>
            <td>${escapeHtml(agency.contact_person)}</td>
            <td><a href="mailto:${agency.email}">${escapeHtml(agency.email)}</a></td>
            <td><code>${escapeHtml(agency.username)}</code></td>
            <td>${parseFloat(agency.commission_percentage).toFixed(1)}%</td>
            <td>
                <span class="status-badge status-${agency.status}">
                    ${getStatusText(agency.status)}
                </span>
            </td>
            <td>${formatDate(agency.created_at)}</td>
            <td class="actions-cell">
                <button class="btn-edit" onclick="editAgency(${agency.id})" title="Editar">
                    ✏️
                </button>
                <button class="btn-toggle" onclick="toggleAgencyStatus(${agency.id}, '${agency.status}')" title="Cambiar Estado">
                    ${agency.status === 'active' ? '⏸️' : '▶️'}
                </button>
                <button class="btn-delete" onclick="deleteAgency(${agency.id})" title="Eliminar">
                    🗑️
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Renderizar modal de crear agencia
 */
function renderCreateAgencyModal() {
    return `
        <div id="createAgencyModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Crear Nueva Agencia</h3>
                    <span class="close" onclick="closeCreateAgencyModal()">&times;</span>
                </div>
                <form id="createAgencyForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="agency_name">Nombre de la Agencia *</label>
                            <input type="text" name="agency_name" required placeholder="Ej: Viajes El Sol">
                        </div>
                        <div class="form-group">
                            <label for="contact_person">Persona de Contacto *</label>
                            <input type="text" name="contact_person" required placeholder="Ej: Juan Pérez">
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" name="email" required placeholder="contacto@agencia.com">
                        </div>
                        <div class="form-group">
                            <label for="phone">Teléfono</label>
                            <input type="tel" name="phone" placeholder="957 123 456">
                        </div>
                        <div class="form-group">
                            <label for="username">Usuario de Acceso *</label>
                            <input type="text" name="username" required placeholder="agencia_sol">
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña *</label>
                            <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
                        </div>
                        <div class="form-group">
                            <label for="commission_percentage">Comisión (%)</label>
                            <input type="number" name="commission_percentage" min="0" max="100" step="0.1" value="0" placeholder="5.0">
                        </div>
                        <div class="form-group">
                            <label for="max_credit_limit">Límite de Crédito (€)</label>
                            <input type="number" name="max_credit_limit" min="0" step="0.01" value="0" placeholder="1000.00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Dirección</label>
                        <textarea name="address" rows="2" placeholder="Dirección completa de la agencia"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notas</label>
                        <textarea name="notes" rows="3" placeholder="Información adicional sobre la agencia"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Estado</label>
                        <select name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                            <option value="suspended">Suspendida</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Crear Agencia</button>
                        <button type="button" class="btn-secondary" onclick="closeCreateAgencyModal()">Cancelar</button>
                    </div>
                </form>
            </div>
            <style>
                .form-grid{
                    display: grid
;
    grid-template-columns: repeat(3, 1fr);
    gap: 50px;
                }
            </style>
        </div>
    `;
}


/**
 * Renderizar modal de editar agencia
 */
function renderEditAgencyModal() {
    return `
        <div id="editAgencyModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Editar Agencia</h3>
                    <span class="close" onclick="closeEditAgencyModal()">&times;</span>
                </div>
                <form id="editAgencyForm">
                    <input type="hidden" name="agency_id" id="edit_agency_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_agency_name">Nombre de la Agencia *</label>
                            <input type="text" name="agency_name" id="edit_agency_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_contact_person">Persona de Contacto *</label>
                            <input type="text" name="contact_person" id="edit_contact_person" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email">Email *</label>
                            <input type="email" name="email" id="edit_email" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone">Teléfono</label>
                            <input type="tel" name="phone" id="edit_phone">
                        </div>
                        <div class="form-group">
                            <label for="edit_username">Usuario de Acceso *</label>
                            <input type="text" name="username" id="edit_username" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_password">Nueva Contraseña</label>
                            <input type="password" name="password" id="edit_password" placeholder="Dejar vacío para no cambiar">
                        </div>
                        <div class="form-group">
                            <label for="edit_commission_percentage">Comisión (%)</label>
                            <input type="number" name="commission_percentage" id="edit_commission_percentage" min="0" max="100" step="0.1">
                        </div>
                        <div class="form-group">
                            <label for="edit_max_credit_limit">Límite de Crédito (€)</label>
                            <input type="number" name="max_credit_limit" id="edit_max_credit_limit" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Dirección</label>
                        <textarea name="address" id="edit_address" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_notes">Notas</label>
                        <textarea name="notes" id="edit_notes" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Estado</label>
                        <select name="status" id="edit_status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                            <option value="suspended">Suspendida</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Actualizar Agencia</button>
                        <button type="button" class="btn-secondary" onclick="closeEditAgencyModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    `;
}

function showCreateAgencyModal() {
    jQuery('#createAgencyModal').show();
    jQuery('#createAgencyForm')[0].reset();
}

/**
 * Cerrar modal de crear agencia
 */
function closeCreateAgencyModal() {
    jQuery('#createAgencyModal').hide();
}

/**
 * Cerrar modal de editar agencia
 */
function closeEditAgencyModal() {
    jQuery('#editAgencyModal').hide();
}

/**
 * Editar agencia
 */
function editAgency(agencyId) {
    console.log('Editando agencia ID:', agencyId);
    
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_agency_details',
            agency_id: agencyId,
            nonce: reservasAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                const agency = response.data;
                
                // Rellenar formulario de edición
                jQuery('#edit_agency_id').val(agency.id);
                jQuery('#edit_agency_name').val(agency.agency_name);
                jQuery('#edit_contact_person').val(agency.contact_person);
                jQuery('#edit_email').val(agency.email);
                jQuery('#edit_phone').val(agency.phone || '');
                jQuery('#edit_username').val(agency.username);
                jQuery('#edit_password').val('');
                jQuery('#edit_commission_percentage').val(agency.commission_percentage);
                jQuery('#edit_max_credit_limit').val(agency.max_credit_limit);
                jQuery('#edit_address').val(agency.address || '');
                jQuery('#edit_notes').val(agency.notes || '');
                jQuery('#edit_status').val(agency.status);
                
                // Mostrar modal
                jQuery('#editAgencyModal').show();
            } else {
                alert('Error cargando datos de la agencia: ' + response.data);
            }
        },
        error: function() {
            alert('Error de conexión al cargar datos de la agencia');
        }
    });
}

/**
 * Cambiar estado de agencia
 */
function toggleAgencyStatus(agencyId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const statusText = newStatus === 'active' ? 'activar' : 'desactivar';
    
    if (confirm(`¿Estás seguro de que quieres ${statusText} esta agencia?`)) {
        jQuery.ajax({
            url: reservasAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_agency_status',
                agency_id: agencyId,
                new_status: newStatus,
                nonce: reservasAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    loadAgenciesSection(); // Recargar lista
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error de conexión al cambiar estado');
            }
        });
    }
}

/**
 * Eliminar agencia
 */
function deleteAgency(agencyId) {
    if (confirm('¿Estás seguro de que quieres eliminar esta agencia? Esta acción no se puede deshacer.')) {
        jQuery.ajax({
            url: reservasAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_agency',
                agency_id: agencyId,
                nonce: reservasAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    loadAgenciesSection(); // Recargar lista
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error de conexión al eliminar agencia');
            }
        });
    }
}

/**
 * Actualizar lista de agencias
 */
function refreshAgenciesList() {
    loadAgenciesSection();
}

/**
 * Manejar envío del formulario de crear agencia
 */
jQuery(document).on('submit', '#createAgencyForm', function(e) {
    e.preventDefault();
    
    const formData = jQuery(this).serialize();
    
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'save_agency',
            ...Object.fromEntries(new URLSearchParams(formData)),
            nonce: reservasAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                alert(response.data);
                closeCreateAgencyModal();
                loadAgenciesSection(); // Recargar lista
            } else {
                alert('Error: ' + response.data);
            }
        },
        error: function() {
            alert('Error de conexión al crear agencia');
        }
    });
});

/**
 * Manejar envío del formulario de editar agencia
 */
jQuery(document).on('submit', '#editAgencyForm', function(e) {
    e.preventDefault();
    
    const formData = jQuery(this).serialize();
    
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'save_agency',
            ...Object.fromEntries(new URLSearchParams(formData)),
            nonce: reservasAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                alert(response.data);
                closeEditAgencyModal();
                loadAgenciesSection(); // Recargar lista
            } else {
                alert('Error: ' + response.data);
            }
        },
        error: function() {
            alert('Error de conexión al actualizar agencia');
        }
    });
});

// Funciones auxiliares
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getStatusText(status) {
    const statusMap = {
        'active': 'Activa',
        'inactive': 'Inactiva', 
        'suspended': 'Suspendida'
    };
    return statusMap[status] || status;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        return dateString;
    }
}

function showLoadingInMainContent() {
    jQuery('.dashboard-content').html('<div class="loading">Cargando gestión de agencias...</div>');
}

function showErrorInMainContent(message) {
    jQuery('.dashboard-content').html(`<div class="error">${message}</div>`);
}







function initAdminReservaRapida() {
    console.log('=== INICIALIZANDO RESERVA RÁPIDA ADMIN (NUEVO FLUJO) ===');
    
    // Mostrar interfaz de reserva rápida
    document.body.innerHTML = `
        <div class="admin-reserva-rapida">
            <div class="admin-header">
                <h1>⚡ Reserva Rápida - Administrador</h1>
                <div class="admin-actions">
                    <button class="btn-secondary" onclick="goBackToDashboard()">← Volver al Dashboard</button>
                </div>
            </div>
            
            <div class="admin-steps-container">
                <div class="admin-step-indicator">
                    <div class="admin-step active" id="admin-step-1-indicator">
                        <div class="admin-step-number">1</div>
                        <div class="admin-step-title">Fecha y Hora</div>
                    </div>
                    <div class="admin-step" id="admin-step-2-indicator">
                        <div class="admin-step-number">2</div>
                        <div class="admin-step-title">Personas</div>
                    </div>
                    <div class="admin-step" id="admin-step-3-indicator">
                        <div class="admin-step-number">3</div>
                        <div class="admin-step-title">Datos Cliente</div>
                    </div>
                    <div class="admin-step" id="admin-step-4-indicator">
                        <div class="admin-step-number">4</div>
                        <div class="admin-step-title">Confirmar</div>
                    </div>
                </div>
                
                <!-- Paso 1: Seleccionar fecha y horario -->
                <div class="admin-step-content" id="admin-step-1">
                    <h2>1. Selecciona fecha y horario</h2>
                    
                    <div class="admin-calendar-section">
                        <div class="admin-calendar-controls">
                            <button id="admin-prev-month">← Mes Anterior</button>
                            <h3 id="admin-current-month-year"></h3>
                            <button id="admin-next-month">Siguiente Mes →</button>
                        </div>
                        
                        <div class="admin-calendar-container">
                            <div id="admin-calendar-grid">
                                <!-- Calendario se cargará aquí -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="admin-schedule-section">
                        <label for="admin-horarios-select">Horarios disponibles:</label>
                        <select id="admin-horarios-select" disabled>
                            <option value="">Selecciona primero una fecha</option>
                        </select>
                    </div>
                </div>
                
                <!-- Paso 2: Seleccionar personas -->
                <div class="admin-step-content" id="admin-step-2" style="display: none;">
                    <h2>2. Selecciona el número de personas</h2>
                    
                    <div class="admin-persons-grid">
                        <div class="admin-person-selector">
                            <label for="admin-adultos">Adultos:</label>
                            <input type="number" id="admin-adultos" min="0" max="50" value="0">
                            <span id="admin-price-adultos" class="admin-price">10€</span>
                        </div>
                        
                        <div class="admin-person-selector">
                            <label for="admin-residentes">Residentes:</label>
                            <input type="number" id="admin-residentes" min="0" max="50" value="0">
                            <span class="admin-price">5€</span>
                        </div>
                        
                        <div class="admin-person-selector">
                            <label for="admin-ninos-5-12">Niños (5-12 años):</label>
                            <input type="number" id="admin-ninos-5-12" min="0" max="50" value="0">
                            <span id="admin-price-ninos" class="admin-price">5€</span>
                        </div>
                        
                        <div class="admin-person-selector">
                            <label for="admin-ninos-menores">Niños (-5 años):</label>
                            <input type="number" id="admin-ninos-menores" min="0" max="50" value="0">
                            <span class="admin-price">GRATIS</span>
                        </div>
                    </div>
                    
                    <div class="admin-pricing-summary">
                        <div class="admin-discount-row" id="admin-discount-row" style="display: none;">
                            <span>Descuento:</span>
                            <span id="admin-total-discount">-0€</span>
                        </div>
                        <div class="admin-total-row">
                            <span>Total:</span>
                            <span id="admin-total-price">0€</span>
                        </div>
                    </div>
                    
                    <div class="admin-discount-message" id="admin-discount-message">
                        <span id="admin-discount-text"></span>
                    </div>
                </div>
                
                <!-- Paso 3: Datos del cliente -->
                <div class="admin-step-content" id="admin-step-3" style="display: none;">
                    <h2>3. Datos del cliente</h2>
                    
                    <form id="admin-client-form" class="admin-client-form">
                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label for="admin-nombre">Nombre *</label>
                                <input type="text" id="admin-nombre" name="nombre" required>
                            </div>
                            <div class="admin-form-group">
                                <label for="admin-apellidos">Apellidos *</label>
                                <input type="text" id="admin-apellidos" name="apellidos" required>
                            </div>
                        </div>
                        
                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label for="admin-email">Email *</label>
                                <input type="email" id="admin-email" name="email" required>
                            </div>
                            <div class="admin-form-group">
                                <label for="admin-telefono">Teléfono *</label>
                                <input type="tel" id="admin-telefono" name="telefono" required>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Paso 4: Confirmación -->
                <div class="admin-step-content" id="admin-step-4" style="display: none;">
                    <h2>4. Confirmar reserva</h2>
                    
                    <div class="admin-confirmation-details">
                        <div class="admin-confirm-row">
                            <strong>Fecha:</strong> <span id="admin-confirm-fecha"></span>
                        </div>
                        <div class="admin-confirm-row">
                            <strong>Hora:</strong> <span id="admin-confirm-hora"></span>
                        </div>
                        <div class="admin-confirm-row">
                            <strong>Personas:</strong> <span id="admin-confirm-personas"></span>
                        </div>
                        <div class="admin-confirm-row">
                            <strong>Cliente:</strong> <span id="admin-confirm-cliente"></span>
                        </div>
                        <div class="admin-confirm-row">
                            <strong>Email:</strong> <span id="admin-confirm-email"></span>
                        </div>
                        <div class="admin-confirm-row">
                            <strong>Total:</strong> <span id="admin-confirm-total"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Navegación -->
                <div class="admin-navigation">
                    <button id="admin-btn-anterior" class="btn-secondary" onclick="adminPreviousStep()" style="display: none;">← Anterior</button>
                    <div class="admin-step-info">
                        <span id="admin-step-text">Paso 1 de 4: Seleccionar fecha y horario</span>
                    </div>
                    <button id="admin-btn-siguiente" class="btn-primary" onclick="adminNextStep()" disabled>Siguiente →</button>
                    <button id="admin-btn-confirmar" class="btn-success" onclick="adminConfirmReservation()" style="display: none;">Confirmar Reserva</button>
                </div>
            </div>
        </div>
        
        <style>
        .admin-reserva-rapida {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0073aa;
        }
        
        .admin-header h1 {
            color: #23282d;
            margin: 0;
        }
        
        .admin-steps-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .admin-step-indicator {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        
        .admin-step {
            flex: 1;
            padding: 20px;
            text-align: center;
            border-right: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .admin-step:last-child {
            border-right: none;
        }
        
        .admin-step.active {
            background: #0073aa;
            color: white;
        }
        
        .admin-step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        
        .admin-step.active .admin-step-number {
            background: white;
            color: #0073aa;
        }
        
        .admin-step-title {
            font-size: 14px;
            font-weight: 600;
        }
        
        .admin-step-content {
            padding: 30px;
        }
        
        .admin-step-content h2 {
            color: #23282d;
            margin-bottom: 20px;
        }
        
        .admin-calendar-section {
            margin-bottom: 30px;
        }
        
        .admin-calendar-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .admin-calendar-controls button {
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .admin-calendar-controls h3 {
            margin: 0;
            color: #23282d;
        }
        
        .admin-calendar-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        #admin-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }
        
        .calendar-day-header {
            background: #0073aa;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }
        
        .calendar-day {
            background: white;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            min-height: 40px;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .calendar-day:hover {
            background: #f0f0f0;
        }
        
        .calendar-day.disponible {
            background: #e8f5e8;
            color: #155724;
        }
        
        .calendar-day.disponible:hover {
            background: #d4edda;
        }
        
        .calendar-day.selected {
            background: #0073aa !important;
            color: white !important;
            border-color: #005177;
        }
        
        .calendar-day.no-disponible {
            background: #f8f8f8;
            color: #999;
            cursor: not-allowed;
        }
        
        .calendar-day.blocked-day {
            background: #ffeaa7;
            color: #856404;
            cursor: not-allowed;
        }
        
        .calendar-day.oferta {
            background: #fff3cd;
            color: #856404;
        }
        
        .calendar-day.other-month {
            background: #f8f9fa;
            color: #999;
        }
        
        .admin-schedule-section {
            margin-bottom: 30px;
        }
        
        .admin-schedule-section label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .admin-schedule-section select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .admin-persons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .admin-person-selector {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .admin-person-selector label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .admin-person-selector input {
            width: 80px;
            padding: 8px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .admin-price {
            display: block;
            font-weight: bold;
            color: #0073aa;
        }
        
        .admin-pricing-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .admin-discount-row, .admin-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .admin-total-row {
            font-size: 20px;
            font-weight: bold;
            color: #0073aa;
            border-top: 2px solid #ddd;
            padding-top: 10px;
        }
        
        .admin-discount-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }
        
        .admin-discount-message.show {
            display: block;
        }
        
        .admin-client-form {
            max-width: 600px;
        }
        
        .admin-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .admin-form-group {
            display: flex;
            flex-direction: column;
        }
        
        .admin-form-group label {
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .admin-form-group input {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .admin-form-group input:focus {
            outline: none;
            border-color: #0073aa;
        }
        
        .admin-confirmation-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .admin-confirm-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .admin-confirm-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .admin-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .admin-step-info {
            font-weight: 600;
            color: #23282d;
        }
        
        .btn-primary, .btn-secondary, .btn-success {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #0073aa;
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #005177;
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        @media (max-width: 768px) {
            .admin-form-row {
                grid-template-columns: 1fr;
            }
            
            .admin-persons-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-navigation {
                flex-direction: column;
                gap: 10px;
            }
        }
        </style>
    `;
    
    // Inicializar calendario y eventos
    loadAdminSystemConfiguration().then(() => {
        loadAdminCalendar();
        setupAdminEventListeners();
    });
}



/**
 * Función principal para procesar reserva rápida
 */
function processReservaRapida(callbackOnError) {
    console.log('=== INICIANDO PROCESS RESERVA RÁPIDA ===');
    
    try {
        // Recopilar datos del formulario
        const formData = {
            action: 'process_reserva_rapida',
            nonce: reservasAjax.nonce,
            // Datos del cliente
            nombre: document.getElementById('nombre').value.trim(),
            apellidos: document.getElementById('apellidos').value.trim(),
            email: document.getElementById('email').value.trim(),
            telefono: document.getElementById('telefono').value.trim(),
            // Datos del servicio
            service_id: document.getElementById('service_id').value,
            // Datos de personas
            adultos: parseInt(document.getElementById('adultos').value) || 0,
            residentes: parseInt(document.getElementById('residentes').value) || 0,
            ninos_5_12: parseInt(document.getElementById('ninos_5_12').value) || 0,
            ninos_menores: parseInt(document.getElementById('ninos_menores').value) || 0
        };
        
        console.log('Datos a enviar:', formData);
        
        // Validaciones del lado cliente
        const validation = validateReservaRapidaData(formData);
        if (!validation.valid) {
            showError(validation.error);
            if (callbackOnError) callbackOnError();
            return;
        }
        
        // Enviar solicitud AJAX
        jQuery.ajax({
            url: reservasAjax.ajax_url,
            type: 'POST',
            data: formData,
            timeout: 30000, // 30 segundos timeout
            success: function(response) {
                console.log('Respuesta del servidor:', response);
                
                if (response.success) {
                    handleReservaRapidaSuccess(response.data);
                } else {
                    showError('Error procesando reserva: ' + response.data);
                    if (callbackOnError) callbackOnError();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', status, error);
                
                let errorMessage = 'Error de conexión';
                if (status === 'timeout') {
                    errorMessage = 'La solicitud tardó demasiado tiempo. Por favor, inténtalo de nuevo.';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                
                showError(errorMessage);
                if (callbackOnError) callbackOnError();
            }
        });
        
    } catch (error) {
        console.error('Error en processReservaRapida:', error);
        showError('Error interno: ' + error.message);
        if (callbackOnError) callbackOnError();
    }
}

/**
 * Validar datos del formulario del lado cliente
 */
function validateReservaRapidaData(data) {
    // Validar datos del cliente
    if (!data.nombre || data.nombre.length < 2) {
        return { valid: false, error: 'El nombre debe tener al menos 2 caracteres' };
    }
    
    if (!data.apellidos || data.apellidos.length < 2) {
        return { valid: false, error: 'Los apellidos deben tener al menos 2 caracteres' };
    }
    
    if (!data.email || !isValidEmail(data.email)) {
        return { valid: false, error: 'Email no válido' };
    }
    
    if (!data.telefono || data.telefono.length < 9) {
        return { valid: false, error: 'Teléfono debe tener al menos 9 dígitos' };
    }
    
    // Validar servicio
    if (!data.service_id) {
        return { valid: false, error: 'Debe seleccionar un servicio' };
    }
    
    // Validar personas
    const totalPersonas = data.adultos + data.residentes + data.ninos_5_12;
    
    if (totalPersonas === 0) {
        return { valid: false, error: 'Debe haber al menos una persona que ocupe plaza' };
    }
    
    if (data.ninos_5_12 > 0 && (data.adultos + data.residentes) === 0) {
        return { valid: false, error: 'Debe haber al menos un adulto si hay niños' };
    }
    
    // Validar disponibilidad de plazas
    const serviceSelect = document.getElementById('service_id');
    const selectedOption = serviceSelect.selectedOptions[0];
    if (selectedOption) {
        const plazasDisponibles = parseInt(selectedOption.dataset.plazas);
        if (totalPersonas > plazasDisponibles) {
            return { 
                valid: false, 
                error: `Solo quedan ${plazasDisponibles} plazas disponibles, necesitas ${totalPersonas}` 
            };
        }
    }
    
    return { valid: true };
}

/**
 * Manejar respuesta exitosa de reserva rápida
 */
function handleReservaRapidaSuccess(data) {
    console.log('=== RESERVA RÁPIDA EXITOSA ===');
    console.log('Datos de respuesta:', data);
    
    // Mostrar mensaje de éxito con detalles
    const successMessage = `
        <div style="text-align: center; padding: 30px; background: #d4edda; border: 2px solid #28a745; border-radius: 12px; margin: 20px 0;">
            <h3 style="color: #155724; margin: 0 0 15px 0; font-size: 24px;">
                ✅ ¡RESERVA RÁPIDA PROCESADA EXITOSAMENTE!
            </h3>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
                <h4 style="color: #28a745; margin: 0 0 15px 0;">Detalles de la Reserva:</h4>
                <div style="font-size: 16px; line-height: 1.6; color: #2d2d2d;">
                    <strong>Localizador:</strong> <span style="font-family: monospace; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 18px; color: #28a745; font-weight: bold;">${data.localizador}</span><br>
                    <strong>Cliente:</strong> ${document.getElementById('nombre').value} ${document.getElementById('apellidos').value}<br>
                    <strong>Email:</strong> ${document.getElementById('email').value}<br>
                    <strong>Fecha:</strong> ${formatDateForDisplay(data.detalles.fecha)}<br>
                    <strong>Hora:</strong> ${data.detalles.hora}<br>
                    <strong>Personas:</strong> ${data.detalles.personas}<br>
                    <strong>Total:</strong> <span style="color: #28a745; font-weight: bold; font-size: 18px;">${data.detalles.precio_final}€</span><br>
                    <strong>Procesado por:</strong> ${data.admin_user}
                </div>
            </div>
            
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196f3;">
                <p style="margin: 0; color: #1976d2; font-weight: 600;">
                    📧 Emails enviados automáticamente:
                </p>
                <ul style="margin: 10px 0 0 0; color: #1976d2; text-align: left; display: inline-block;">
                    <li>Confirmación al cliente (con PDF adjunto)</li>
                    <li>Notificación al super administrador</li>
                </ul>
            </div>
            
            <div style="margin-top: 25px;">
                <button onclick="loadReportsSection()" style="background: #28a745; color: white; border: none; padding: 12px 25px; border-radius: 6px; margin-right: 10px; cursor: pointer; font-weight: 600;">
                    📊 Ver en Informes
                </button>
                <button onclick="createNewReservaRapida()" style="background: #007bff; color: white; border: none; padding: 12px 25px; border-radius: 6px; margin-right: 10px; cursor: pointer; font-weight: 600;">
                    ➕ Nueva Reserva Rápida
                </button>
                <button onclick="loadDashboardSection('dashboard')" style="background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                    🏠 Volver al Dashboard
                </button>
            </div>
        </div>
    `;
    
    // Mostrar el mensaje de éxito
    document.getElementById('dashboard-content').innerHTML = successMessage;
    
    // Hacer scroll hacia arriba para ver el mensaje
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Log para debugging
    console.log('✅ Reserva rápida completada exitosamente');
    console.log('Localizador:', data.localizador);
    console.log('Admin:', data.admin_user);
}

/**
 * Crear nueva reserva rápida
 */
function createNewReservaRapida() {
    loadReservaRapidaSection();
}

/**
 * Función auxiliar para validar email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Función auxiliar para formatear fecha
 */
function formatDateForDisplay(dateString) {
    try {
        const date = new Date(dateString + 'T00:00:00');
        return date.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch (error) {
        return dateString;
    }
}

/**
 * Funciones auxiliares para mostrar mensajes (si no existen ya)
 */
if (typeof showError === 'undefined') {
    function showError(message) {
        const messagesDiv = document.getElementById('form-messages');
        if (messagesDiv) {
            messagesDiv.innerHTML = `<div class="error-message" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #dc3545;">${message}</div>`;
        } else {
            console.error('Error:', message);
            alert('Error: ' + message);
        }
    }
}

if (typeof showSuccess === 'undefined') {
    function showSuccess(message) {
        const messagesDiv = document.getElementById('form-messages');
        if (messagesDiv) {
            messagesDiv.innerHTML = `<div class="success-message" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #28a745;">${message}</div>`;
        } else {
            console.log('Success:', message);
        }
    }
}

if (typeof clearMessages === 'undefined') {
    function clearMessages() {
        const messagesDiv = document.getElementById('form-messages');
        if (messagesDiv) {
            messagesDiv.innerHTML = '';
        }
    }
}

/**
 * Función específica para cargar Reserva Rápida en dashboard de agencias
 */
function loadAgencyReservaRapida() {
    console.log('=== CARGANDO RESERVA RÁPIDA PARA AGENCIA ===');
    
    // Mostrar indicador de carga
    document.body.innerHTML = `
        <div class="loading-container" style="text-align: center; padding: 50px;">
            <h2>⚡ Cargando Reserva Rápida...</h2>
            <p>Preparando el formulario de reserva para agencias...</p>
        </div>
    `;
    
    // Cargar la sección de reserva rápida usando AJAX
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_agency_reserva_rapida_form',
            nonce: reservasAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                document.body.innerHTML = response.data;
                
                // Inicializar la reserva rápida si la función existe
                if (typeof initializeAgencyReservaRapida === 'function') {
                    initializeAgencyReservaRapida();
                }
            } else {
                showErrorInContent('Error cargando reserva rápida: ' + response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            showErrorInContent('Error de conexión cargando reserva rápida');
        }
    });
}

function showErrorInContent(message) {
   document.body.innerHTML = `
       <div class="error-container" style="text-align: center; padding: 50px;">
           <h2 style="color: #d63638;">Error</h2>
           <p style="color: #d63638;">${message}</p>
           <button class="btn-secondary" onclick="location.reload()">← Recargar Página</button>
       </div>
   `;
}


// Agregar al archivo: wp-content/plugins/sistema-reservas/assets/js/dashboard-script.js

/**
 * Función para cargar el perfil de la agencia
 */
function loadAgencyProfile() {
    console.log('=== CARGANDO PERFIL DE AGENCIA ===');
    
    // Mostrar indicador de carga
    showLoadingInMainContent();
    
    // Cargar datos del perfil
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_agency_profile',
            nonce: reservasAjax.nonce
        },
        success: function(response) {
            console.log('Respuesta del servidor:', response);
            
            if (response.success) {
                renderAgencyProfile(response.data);
            } else {
                showErrorInMainContent('Error cargando perfil: ' + response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            showErrorInMainContent('Error de conexión al cargar perfil');
        }
    });
}

/**
 * Renderizar la sección de perfil de agencia
 */
function renderAgencyProfile(agencyData) {
    const content = `
        <div class="agency-profile-management">
            <div class="section-header">
                <h2>👤 Mi Perfil</h2>
                <p>Gestiona la información de tu agencia</p>
            </div>
            
            <div class="profile-actions">
                <button class="btn-primary" onclick="saveAgencyProfile()">
                    💾 Guardar Cambios
                </button>
                <button class="btn-secondary" onclick="resetAgencyProfile()">
                    🔄 Resetear Cambios
                </button>
                <button class="btn-secondary" onclick="goBackToDashboard()">
                    ← Volver al Dashboard
                </button>
            </div>
            
            <div class="profile-form-container">
                <form id="agency-profile-form" class="profile-form">
                    
                    <!-- Información Básica -->
                    <div class="form-section">
                        <h3>🏢 Información Básica</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="agency_name">Nombre de la Agencia *</label>
                                <input type="text" id="agency_name" name="agency_name" 
                                       value="${escapeHtml(agencyData.agency_name)}" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_person">Persona de Contacto *</label>
                                <input type="text" id="contact_person" name="contact_person" 
                                       value="${escapeHtml(agencyData.contact_person)}" required>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="form-section">
                        <h3>📧 Información de Contacto</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="email">Email de Contacto *</label>
                                <input type="email" id="email" name="email" 
                                       value="${escapeHtml(agencyData.email)}" required>
                                <small class="form-help">Email principal de la agencia</small>
                            </div>
                            <div class="form-group">
                                <label for="phone">Teléfono</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="${escapeHtml(agencyData.phone || '')}" placeholder="957 123 456">
                            </div>
                        </div>
                    </div>

                    <!-- Notificaciones -->
                    <div class="form-section">
                        <h3>🔔 Configuración de Notificaciones</h3>
                        <div class="form-group">
                            <label for="email_notificaciones">Email para Notificaciones de Compras</label>
                            <input type="email" id="email_notificaciones" name="email_notificaciones" 
                                   value="${escapeHtml(agencyData.email_notificaciones || '')}" 
                                   placeholder="notificaciones@agencia.com">
                            <small class="form-help">A este email llegarán las notificaciones de nuevas reservas realizadas por tu agencia. Si se deja vacío, se usará el email de contacto principal.</small>
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div class="form-section">
                        <h3>📍 Dirección</h3>
                        <div class="form-group">
                            <label for="address">Dirección Completa</label>
                            <textarea id="address" name="address" rows="3" 
                                      placeholder="Calle, número, código postal, ciudad...">${escapeHtml(agencyData.address || '')}</textarea>
                        </div>
                    </div>

                    <!-- Notas -->
                    <div class="form-section">
                        <h3>📝 Notas Adicionales</h3>
                        <div class="form-group">
                            <label for="notes">Notas Internas</label>
                            <textarea id="notes" name="notes" rows="4" 
                                      placeholder="Información adicional sobre la agencia...">${escapeHtml(agencyData.notes || '')}</textarea>
                            <small class="form-help">Estas notas son visibles solo para los administradores</small>
                        </div>
                    </div>

                    <!-- Información de Solo Lectura -->
                    <div class="form-section readonly-section">
                        <h3>ℹ️ Información de la Cuenta</h3>
                        <div class="readonly-grid">
                            <div class="readonly-item">
                                <label>Usuario de Acceso:</label>
                                <span class="readonly-value">${escapeHtml(agencyData.username)}</span>
                            </div>
                            <div class="readonly-item">
                                <label>Comisión:</label>
                                <span class="readonly-value">${parseFloat(agencyData.commission_percentage).toFixed(1)}%</span>
                            </div>
                            <div class="readonly-item">
                                <label>Límite de Crédito:</label>
                                <span class="readonly-value">${parseFloat(agencyData.max_credit_limit).toFixed(2)}€</span>
                            </div>
                            <div class="readonly-item">
                                <label>Balance Actual:</label>
                                <span class="readonly-value">${parseFloat(agencyData.current_balance).toFixed(2)}€</span>
                            </div>
                            <div class="readonly-item">
                                <label>Estado:</label>
                                <span class="readonly-value status-${agencyData.status}">${getStatusText(agencyData.status)}</span>
                            </div>
                            <div class="readonly-item">
                                <label>Fecha de Creación:</label>
                                <span class="readonly-value">${formatDate(agencyData.created_at)}</span>
                            </div>
                        </div>
                        <div class="readonly-note">
                            <p><strong>Nota:</strong> Para cambios en usuario de acceso, comisión o límite de crédito, contacta con el administrador.</p>
                        </div>
                    </div>

                </form>
            </div>

            <!-- Mensaje de estado -->
            <div id="profile-messages" class="profile-messages"></div>
        </div>
        
        <style>
        .agency-profile-management {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-header h2 {
            margin: 0 0 10px 0;
            color: #23282d;
        }
        
        .section-header p {
            margin: 0 0 30px 0;
            color: #666;
        }
        
        .profile-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            align-items: center;
        }
        
        .profile-form-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .profile-form {
            padding: 0;
        }
        
        .form-section {
            padding: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h3 {
            margin: 0 0 20px 0;
            color: #0073aa;
            font-size: 18px;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid #0073aa;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #23282d;
        }
        
        .form-group input,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
        }
        
        .form-group input:required:invalid {
            border-color: #dc3545;
        }
        
        .form-group input:required:valid {
            border-color: #28a745;
        }
        
        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        
        .readonly-section {
            background: #f8f9fa;
        }
        
        .readonly-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .readonly-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #0073aa;
        }
        
        .readonly-item label {
            font-weight: 600;
            color: #23282d;
        }
        
        .readonly-value {
            font-weight: 500;
            color: #666;
        }
        
        .readonly-value.status-active {
            color: #28a745;
            font-weight: 600;
        }
        
        .readonly-value.status-inactive {
            color: #dc3545;
            font-weight: 600;
        }
        
        .readonly-value.status-suspended {
            color: #ffc107;
            font-weight: 600;
        }
        
        .readonly-note {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            color: #856404;
        }
        
        .profile-messages {
            margin-top: 20px;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        @media (max-width: 768px) {
            .agency-profile-management {
                padding: 10px;
            }
            
            .profile-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .profile-actions button {
                width: 100%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .readonly-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Animaciones */
        .form-group input,
        .form-group textarea {
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 115, 170, 0.2);
        }
        
        .readonly-item {
            transition: background-color 0.3s ease;
        }
        
        .readonly-item:hover {
            background-color: #f8f9fa;
        }
        </style>
    `;
    
    // Insertar contenido en el dashboard principal
    jQuery('.dashboard-content').html(content);
    
    // Almacenar datos originales para reset
    window.originalAgencyData = { ...agencyData };
    
    // Inicializar eventos
    initializeProfileEvents();
}

function initializeProfileEvents() {
    // Validación en tiempo real
    jQuery('#agency_name, #contact_person, #email').on('input', function() {
        validateRequiredField(this);
    });
    
    // Validación de email
    jQuery('#email, #email_notificaciones').on('blur', function() {
        validateEmailField(this);
    });
    
    // Validación de teléfono
    jQuery('#phone').on('input', function() {
        validatePhoneField(this);
    });
    
    // Detectar cambios para mostrar indicador
    jQuery('#agency-profile-form input, #agency-profile-form textarea').on('input', function() {
        showUnsavedChangesIndicator();
    });
}

/**
 * Validar campo requerido
 */
function validateRequiredField(field) {
    const value = field.value.trim();
    
    if (value.length === 0) {
        field.style.borderColor = '#dc3545';
        return false;
    } else if (value.length < 2) {
        field.style.borderColor = '#ffc107';
        return false;
    } else {
        field.style.borderColor = '#28a745';
        return true;
    }
}

/**
 * Validar campo de email
 */
function validateEmailField(field) {
    const value = field.value.trim();
    
    if (value === '') {
        field.style.borderColor = field.required ? '#dc3545' : '#ddd';
        return !field.required;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailRegex.test(value)) {
        field.style.borderColor = '#28a745';
        return true;
    } else {
        field.style.borderColor = '#dc3545';
        return false;
    }
}

/**
 * Validar campo de teléfono
 */
function validatePhoneField(field) {
    const value = field.value.trim();
    
    if (value === '') {
        field.style.borderColor = '#ddd';
        return true;
    }
    
    if (value.length >= 9) {
        field.style.borderColor = '#28a745';
        return true;
    } else {
        field.style.borderColor = '#ffc107';
        return false;
    }
}

/**
 * Mostrar indicador de cambios no guardados
 */
function showUnsavedChangesIndicator() {
    const messagesDiv = jQuery('#profile-messages');
    messagesDiv.html(`
        <div class="message info">
            <strong>💡 Cambios detectados:</strong> Hay cambios sin guardar en el formulario.
        </div>
    `);
}

/**
 * Guardar perfil de agencia
 */
function saveAgencyProfile() {
    console.log('=== GUARDANDO PERFIL DE AGENCIA ===');
    
    // Validar formulario
    if (!validateProfileForm()) {
        return;
    }
    
    // Mostrar indicador de carga
    showProfileMessage('info', '⏳ Guardando cambios...');
    
    // Deshabilitar botón
    const saveBtn = jQuery('button[onclick="saveAgencyProfile()"]');
    const originalText = saveBtn.text();
    saveBtn.prop('disabled', true).text('💾 Guardando...');
    
    // Recopilar datos del formulario
    const formData = {
        action: 'save_agency_profile',
        agency_name: jQuery('#agency_name').val().trim(),
        contact_person: jQuery('#contact_person').val().trim(),
        email: jQuery('#email').val().trim(),
        phone: jQuery('#phone').val().trim(),
        email_notificaciones: jQuery('#email_notificaciones').val().trim(),
        address: jQuery('#address').val().trim(),
        notes: jQuery('#notes').val().trim(),
        nonce: reservasAjax.nonce
    };
    
    console.log('Datos a enviar:', formData);
    
    // Enviar datos
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: formData,
        success: function(response) {
            console.log('Respuesta:', response);
            
            // Rehabilitar botón
            saveBtn.prop('disabled', false).text(originalText);
            
            if (response.success) {
                showProfileMessage('success', '✅ ' + response.data);
                
                // Actualizar datos originales
                window.originalAgencyData = { ...formData };
                
                // Actualizar datos de sesión si es necesario
                updateSessionData();
                
            } else {
                showProfileMessage('error', '❌ Error: ' + response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            
            // Rehabilitar botón
            saveBtn.prop('disabled', false).text(originalText);
            
            showProfileMessage('error', '❌ Error de conexión al guardar los cambios');
        }
    });
}

/**
 * Validar formulario completo
 */
function validateProfileForm() {
    let isValid = true;
    const errors = [];
    
    // Validar nombre de agencia
    const agencyName = jQuery('#agency_name').val().trim();
    if (agencyName.length < 2) {
        errors.push('El nombre de la agencia debe tener al menos 2 caracteres');
        isValid = false;
    }
    
    // Validar persona de contacto
    const contactPerson = jQuery('#contact_person').val().trim();
    if (contactPerson.length < 2) {
        errors.push('La persona de contacto debe tener al menos 2 caracteres');
        isValid = false;
    }
    
    // Validar email principal
    const email = jQuery('#email').val().trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        errors.push('El email de contacto no es válido');
        isValid = false;
    }
    
    // Validar email de notificaciones si está presente
    const emailNotifications = jQuery('#email_notificaciones').val().trim();
    if (emailNotifications && !emailRegex.test(emailNotifications)) {
        errors.push('El email de notificaciones no es válido');
        isValid = false;
    }
    
    // Validar teléfono si está presente
    const phone = jQuery('#phone').val().trim();
    if (phone && phone.length < 9) {
        errors.push('El teléfono debe tener al menos 9 dígitos');
        isValid = false;
    }
    
    // Mostrar errores si los hay
    if (!isValid) {
        showProfileMessage('error', '❌ Errores de validación:<br>• ' + errors.join('<br>• '));
    }
    
    return isValid;
}

/**
 * Resetear cambios del perfil
 */
function resetAgencyProfile() {
    if (confirm('¿Estás seguro de que quieres descartar todos los cambios?')) {
        // Restaurar valores originales
        if (window.originalAgencyData) {
            jQuery('#agency_name').val(window.originalAgencyData.agency_name || '');
            jQuery('#contact_person').val(window.originalAgencyData.contact_person || '');
            jQuery('#email').val(window.originalAgencyData.email || '');
            jQuery('#phone').val(window.originalAgencyData.phone || '');
            jQuery('#email_notificaciones').val(window.originalAgencyData.email_notificaciones || '');
            jQuery('#address').val(window.originalAgencyData.address || '');
            jQuery('#notes').val(window.originalAgencyData.notes || '');
            
            // Limpiar mensajes
            jQuery('#profile-messages').html('');
            
            // Resetear estilos de validación
            jQuery('#agency-profile-form input, #agency-profile-form textarea').css('border-color', '#ddd');
            
            showProfileMessage('info', '🔄 Formulario reseteado a los valores originales');
        }
    }
}

/**
 * Mostrar mensaje de perfil
 */
function showProfileMessage(type, message) {
    const messagesDiv = jQuery('#profile-messages');
    messagesDiv.html(`<div class="message ${type}">${message}</div>`);
    
    // Scroll suave hacia el mensaje
    messagesDiv[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Actualizar datos de sesión
 */
function updateSessionData() {
    // Actualizar datos de sesión para reflejar cambios en el header
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'refresh_session_data',
            nonce: reservasAjax.nonce
        },
        success: function(response) {
            if (response.success) {
                console.log('✅ Datos de sesión actualizados');
            }
        }
    });
}

/**
 * Funciones auxiliares reutilizadas
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getStatusText(status) {
    const statusMap = {
        'active': 'Activa',
        'inactive': 'Inactiva', 
        'suspended': 'Suspendida'
    };
    return statusMap[status] || status;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        return dateString;
    }
}

function showLoadingInMainContent() {
    jQuery('.dashboard-content').html('<div class="loading">Cargando Mi Perfil...</div>');
}

function showErrorInMainContent(message) {
    jQuery('.dashboard-content').html(`<div class="error">${message}</div>`);
}

// Exponer función globalmente
window.loadAgencyProfile = loadAgencyProfile;



/**
 * FUNCIÓN HELPER PARA VALIDACIÓN DE EMAILS
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * FUNCIÓN HELPER PARA FORMATO DE FECHAS
 */
function formatDateForProfile(dateString) {
    if (!dateString) return '-';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch (e) {
        return dateString;
    }
}

/**
 * FUNCIÓN PARA MOSTRAR NOTIFICACIONES TEMPORALES
 */
function showTemporaryNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `temporary-notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
        color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
        padding: 15px 20px;
        border-radius: 6px;
        border-left: 4px solid ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        z-index: 10000;
        max-width: 300px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        animation: slideIn 0.3s ease-out;
    `;
    
    notification.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 18px; margin-left: 10px;">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-eliminar después del tiempo especificado
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }
    }, duration);
}

// Agregar animaciones CSS para las notificaciones
const animationCSS = `
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
`;

// Agregar estilos al documento
if (!document.getElementById('agency-profile-styles')) {
    const style = document.createElement('style');
    style.id = 'agency-profile-styles';
    style.textContent = animationCSS;
    document.head.appendChild(style);
}