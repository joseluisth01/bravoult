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
    handleAjaxError({status: 500, statusText: error.message}, 'error', error);
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
    document.getElementById('serviceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveService();
    });

    // Formulario de servicios masivos
    document.getElementById('bulkAddForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveBulkServices();
    });

    // Eventos para los checkboxes de descuento
    document.getElementById('tieneDescuento').addEventListener('change', function() {
        const discountFields = document.getElementById('discountFields');
        if (this.checked) {
            discountFields.style.display = 'block';
        } else {
            discountFields.style.display = 'none';
            document.getElementById('porcentajeDescuento').value = '';
        }
    });

    document.getElementById('bulkTieneDescuento').addEventListener('change', function() {
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
    document.getElementById('discountForm').addEventListener('submit', function(e) {
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
    document.getElementById('configurationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveAllConfiguration();
    });

    // Eventos para los selectores de moneda (sincronizar símbolo)
    document.getElementById('moneda').addEventListener('change', function() {
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
    document.getElementById('editEmailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        updateReservationEmail();
    });

    // Evento para cambiar tipo de búsqueda
    document.getElementById('search-type').addEventListener('change', function() {
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
    document.getElementById('search-value').addEventListener('keypress', function(e) {
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
        document.getElementById('cancelReservationForm').addEventListener('submit', function(e) {
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