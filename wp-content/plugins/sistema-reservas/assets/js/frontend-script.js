// Variables globales
let currentStep = 1;
let currentDate = new Date();
let selectedDate = null;
let selectedServiceId = null;
let servicesData = {};
let diasAnticiapcionMinima = 1; // ✅ NUEVA VARIABLE GLOBAL

jQuery(document).ready(function ($) {

    // Inicializar formulario de reservas
    initBookingForm();

    function initBookingForm() {
        // Cargar configuración primero, luego calendario
        loadSystemConfiguration().then(() => {
            loadCalendar();
            setupEventListeners();

            initializePricing();
        });
    }

    function initializePricing() {
        $('#total-price').text('0€');
        $('#total-discount').text('');
        $('#discount-row').hide();
        $('#discount-message').removeClass('show');
        console.log('Precios inicializados con 0€');
    }

    function loadSystemConfiguration() {
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
                        diasAnticiapcionMinima = parseInt(config.servicios?.dias_anticipacion_minima?.value || '1');
                        console.log('Días de anticipación mínima cargados:', diasAnticiapcionMinima);
                        resolve();
                    } else {
                        console.warn('No se pudo cargar configuración, usando valores por defecto');
                        diasAnticiapcionMinima = 1;
                        resolve();
                    }
                })
                .catch(error => {
                    console.error('Error cargando configuración:', error);
                    diasAnticiapcionMinima = 1;
                    resolve();
                });
        });
    }

    function setupEventListeners() {
        // Navegación del calendario
        $('#prev-month').on('click', function () {
            currentDate.setMonth(currentDate.getMonth() - 1);
            loadCalendar();
        });

        $('#next-month').on('click', function () {
            currentDate.setMonth(currentDate.getMonth() + 1);
            loadCalendar();
        });

        // Selección de horario
        $('#horarios-select').on('change', function () {
            selectedServiceId = $(this).val();
            if (selectedServiceId) {
                $('#btn-siguiente').prop('disabled', false);
                loadPrices();
            } else {
                $('#btn-siguiente').prop('disabled', true);
                // ✅ Si no hay servicio seleccionado, mostrar 0€
                $('#total-price').text('0€');
            }
        });

        // ✅ CAMBIOS EN SELECTORES DE PERSONAS - MEJORADO
        $('#adultos, #residentes, #ninos-5-12, #ninos-menores').on('input change keyup', function () {
            // Delay pequeño para mejor UX
            setTimeout(() => {
                calculateTotalPrice();
                validatePersonSelection();
            }, 100);
        });

        // Navegación entre pasos
        $('#btn-siguiente').on('click', function () {
            nextStep();
        });

        $('#btn-anterior').on('click', function () {
            previousStep();
        });
    }

    function loadCalendar() {
        updateCalendarHeader();

        const formData = new FormData();
        formData.append('action', 'get_available_services');
        formData.append('month', currentDate.getMonth() + 1);
        formData.append('year', currentDate.getFullYear());
        formData.append('nonce', reservasAjax.nonce);

        fetch(reservasAjax.ajax_url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    servicesData = data.data;
                    renderCalendar();
                } else {
                    console.error('Error cargando servicios:', data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function updateCalendarHeader() {
        const monthNames = [
            'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
            'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'
        ];

        const monthYear = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
        $('#current-month-year').text(monthYear);
    }

    function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

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

    // ✅ CORRECCIÓN: Usar solo la fecha, no la fecha mínima
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Resetear hora para comparación de solo fecha

    console.log(`Configuración frontend: ${diasAnticiapcionMinima} días de anticipación`);
    console.log(`Fecha actual: ${today.toDateString()}`);

    // Días del mes actual
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayDate = new Date(year, month, day);

        let dayClass = 'calendar-day';
        let clickHandler = '';

        // ✅ NUEVA LÓGICA: Verificar si el día está bloqueado por días de anticipación
        let isBlockedByAnticipacion = false;
        
        if (diasAnticiapcionMinima > 0) {
            // Si hay días de anticipación, bloquear días anteriores a hoy + días de anticipación
            const fechaMinima = new Date(today);
            fechaMinima.setDate(today.getDate() + diasAnticiapcionMinima);
            isBlockedByAnticipacion = dayDate < fechaMinima;
        } else {
            // Si días de anticipación = 0, solo bloquear días anteriores a hoy
            isBlockedByAnticipacion = dayDate < today;
        }

        if (isBlockedByAnticipacion) {
            dayClass += ' no-disponible';
            console.log(`Día ${day} bloqueado por anticipación mínima`);
        } else if (servicesData[dateStr] && servicesData[dateStr].length > 0) {
            // ✅ VERIFICAR SI HAY SERVICIOS DISPONIBLES EN ESTA FECHA
            const servicesAvailable = servicesData[dateStr];
            let hasAvailableServices = false;

            // ✅ LÓGICA MEJORADA: Si es hoy, verificar que haya servicios con hora posterior a la actual
            const isToday = dateStr === today.toISOString().split('T')[0];

            if (isToday) {
                const currentHour = new Date().getHours(); // Hora actual real
                const currentMinute = new Date().getMinutes();
                const currentTimeInMinutes = currentHour * 60 + currentMinute;

                hasAvailableServices = servicesAvailable.some(service => {
                    const serviceTime = service.hora.split(':');
                    const serviceTimeInMinutes = parseInt(serviceTime[0]) * 60 + parseInt(serviceTime[1]);
                    return serviceTimeInMinutes > currentTimeInMinutes;
                });

                console.log(`Día ${day} (hoy) - Hora actual: ${currentHour}:${String(currentMinute).padStart(2, '0')}`);
                console.log(`Servicios disponibles después de la hora actual:`, hasAvailableServices);
            } else {
                hasAvailableServices = servicesAvailable.length > 0;
            }

            if (hasAvailableServices) {
                dayClass += ' disponible';
                clickHandler = `onclick="selectDate('${dateStr}')"`;

                // Verificar si algún servicio tiene descuento
                const tieneDescuento = servicesAvailable.some(service =>
                    service.tiene_descuento && parseFloat(service.porcentaje_descuento) > 0
                );

                if (tieneDescuento) {
                    dayClass += ' oferta';
                }
            } else {
                dayClass += ' no-disponible';
                console.log(`Día ${day} no disponible (sin servicios válidos para la hora actual)`);
            }
        } else {
            dayClass += ' no-disponible';
        }

        if (selectedDate === dateStr) {
            dayClass += ' selected';
        }

        calendarHTML += `<div class="${dayClass}" ${clickHandler}>${day}</div>`;
    }

    $('#calendar-grid').html(calendarHTML);

    // Reasignar eventos de clic después de regenerar el HTML
    setupCalendarClickEvents();
}

    function setupCalendarClickEvents() {
        $('.calendar-day.disponible').off('click').on('click', function () {
            const dayNumber = $(this).text();
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(dayNumber).padStart(2, '0')}`;

            selectDate(dateStr, $(this));
        });
    }

    function selectDate(dateStr, dayElement) {
        selectedDate = dateStr;
        selectedServiceId = null;

        // Actualizar visual del calendario
        $('.calendar-day').removeClass('selected');
        if (dayElement) {
            dayElement.addClass('selected');
        }

        // Cargar horarios disponibles
        loadAvailableSchedules(dateStr);
    }

    function loadAvailableSchedules(dateStr) {
        const services = servicesData[dateStr] || [];

        let optionsHTML = '<option value="">Selecciona un horario</option>';

        services.forEach(service => {
            let descuentoInfo = '';

            // ✅ LÓGICA MEJORADA PARA MOSTRAR INFORMACIÓN DEL DESCUENTO
            if (service.tiene_descuento && parseFloat(service.porcentaje_descuento) > 0) {
                const porcentaje = parseFloat(service.porcentaje_descuento);
                const tipo = service.descuento_tipo || 'fijo';
                const minimo = parseInt(service.descuento_minimo_personas) || 1;

                if (tipo === 'fijo') {
                    // Descuento fijo para todos
                    descuentoInfo = ` (${porcentaje}% descuento)`;
                } else if (tipo === 'por_grupo') {
                    // Descuento por grupo con mínimo de personas
                    descuentoInfo = ` (${porcentaje}% descuento desde ${minimo} personas)`;
                }
            }

            optionsHTML += `<option value="${service.id}" 
                               data-plazas="${service.plazas_disponibles}"
                               data-descuento-tipo="${service.descuento_tipo || 'fijo'}"
                               data-descuento-minimo="${service.descuento_minimo_personas || 1}">
                            ${service.hora} - ${service.plazas_disponibles} plazas disponibles${descuentoInfo}
                        </option>`;
        });

        $('#horarios-select').html(optionsHTML).prop('disabled', false);
        $('#btn-siguiente').prop('disabled', true);
    }

    function loadPrices() {
        if (!selectedServiceId) return;

        const service = findServiceById(selectedServiceId);
        if (service) {
            $('#price-adultos').text(service.precio_adulto + '€');
            $('#price-ninos').text(service.precio_nino + '€');
            calculateTotalPrice();
        }
    }

    function findServiceById(serviceId) {
        for (let date in servicesData) {
            for (let service of servicesData[date]) {
                if (service.id == serviceId) {
                    return service;
                }
            }
        }
        return null;
    }

    function calculateTotalPrice() {
        if (!selectedServiceId) {
            clearPricing();
            return;
        }

        const adultos = parseInt($('#adultos').val()) || 0;
        const residentes = parseInt($('#residentes').val()) || 0;
        const ninos512 = parseInt($('#ninos-5-12').val()) || 0;
        const ninosMenores = parseInt($('#ninos-menores').val()) || 0;

        const totalPersonas = adultos + residentes + ninos512 + ninosMenores;

        // ✅ CAMBIO: Si no hay personas, mostrar 0€ en lugar de limpiar
        if (totalPersonas === 0) {
            $('#total-discount').text('');
            $('#total-price').text('0€'); // ✅ Mostrar 0€ siempre
            $('#discount-row').hide();
            $('#discount-message').removeClass('show');
            console.log('No hay personas seleccionadas - mostrando 0€');
            return;
        }

        // Resto de la función igual...
        const formData = new FormData();
        formData.append('action', 'calculate_price');
        formData.append('service_id', selectedServiceId);
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
                    updatePricingDisplay(result);
                } else {
                    console.error('Error calculando precio:', data);
                    // ✅ CAMBIO: En caso de error, mostrar 0€
                    $('#total-price').text('0€');
                    $('#total-discount').text('');
                    $('#discount-row').hide();
                    $('#discount-message').removeClass('show');
                }
            })
            .catch(error => {
                console.error('Error calculando precio:', error);
                // ✅ CAMBIO: En caso de error, mostrar 0€
                $('#total-price').text('0€');
                $('#total-discount').text('');
                $('#discount-row').hide();
                $('#discount-message').removeClass('show');
            });
    }

    function clearPricing() {
        $('#total-discount').text('');
        $('#total-price').text('0€'); // ✅ CAMBIO: Siempre mostrar 0€
        $('#discount-row').hide();
        $('#discount-message').removeClass('show');
        console.log('Precios limpiados - mostrando 0€');
    }

    function updatePricingDisplay(result) {
        console.log('Datos recibidos del servidor:', result);

        // Calcular descuento total para mostrar
        const descuentoTotal = (result.descuento_grupo || 0) + (result.descuento_servicio || 0);

        // Manejar descuentos totales
        if (descuentoTotal > 0) {
            $('#total-discount').text('-' + descuentoTotal.toFixed(2) + '€');
            $('#discount-row').show();
        } else {
            $('#discount-row').hide();
        }

        // ✅ LÓGICA MEJORADA PARA MENSAJES DE DESCUENTO
        let mensajeDescuento = '';

        // Si hay descuento por grupo (reglas globales)
        if (result.regla_descuento_aplicada && result.regla_descuento_aplicada.rule_name && result.descuento_grupo > 0) {
            const regla = result.regla_descuento_aplicada;
            mensajeDescuento = `Descuento del ${regla.discount_percentage}% por ${regla.rule_name.toLowerCase()}`;
        }

        // Si hay descuento específico del servicio aplicado
        if (result.servicio_con_descuento && result.servicio_con_descuento.descuento_aplicado && result.descuento_servicio > 0) {
            const servicio = result.servicio_con_descuento;
            let mensajeServicio = '';

            if (servicio.descuento_tipo === 'fijo') {
                mensajeServicio = `Descuento del ${servicio.porcentaje_descuento}% aplicado a este servicio`;
            } else if (servicio.descuento_tipo === 'por_grupo') {
                mensajeServicio = `Descuento del ${servicio.porcentaje_descuento}% por alcanzar ${servicio.descuento_minimo_personas} personas`;
            }

            // ✅ COMBINAR MENSAJES SI HAY AMBOS DESCUENTOS
            if (mensajeDescuento && mensajeServicio) {
                if (servicio.descuento_acumulable == '1') {
                    mensajeDescuento += ` + ${mensajeServicio}`;
                } else {
                    // Mostrar solo el que tiene prioridad
                    const prioridad = servicio.descuento_prioridad || 'servicio';
                    if (prioridad === 'servicio') {
                        mensajeDescuento = mensajeServicio;
                    }
                    // Si prioridad es 'grupo', ya tenemos el mensaje del grupo
                }
            } else if (mensajeServicio) {
                mensajeDescuento = mensajeServicio;
            }
        }

        // Mostrar mensaje final
        if (mensajeDescuento) {
            $('#discount-text').text(mensajeDescuento);
            $('#discount-message').addClass('show');
            console.log('Mensaje de descuento mostrado:', mensajeDescuento);
        } else {
            $('#discount-message').removeClass('show');
        }

        window.lastDiscountRule = result.regla_descuento_aplicada;

        // Actualizar precio total
        const totalPrice = parseFloat(result.total) || 0;
        $('#total-price').text(totalPrice.toFixed(2) + '€');

        console.log('Precios actualizados:', {
            descuento_grupo: result.descuento_grupo,
            descuento_servicio: result.descuento_servicio,
            descuento_total: descuentoTotal,
            total: totalPrice,
            debug: result.debug
        });
    }

    function validatePersonSelection() {
        const adultos = parseInt($('#adultos').val()) || 0;
        const residentes = parseInt($('#residentes').val()) || 0;
        const ninos512 = parseInt($('#ninos-5-12').val()) || 0;
        const ninosMenores = parseInt($('#ninos-menores').val()) || 0;

        const totalAdults = adultos + residentes;
        const totalChildren = ninos512 + ninosMenores;

        if (totalChildren > 0 && totalAdults === 0) {
            alert('Debe haber al menos un adulto si hay niños en la reserva.');
            $('#ninos-5-12, #ninos-menores').val(0);
            calculateTotalPrice();
            return false;
        }

        return true;
    }

    function nextStep() {
        if (!selectedDate || !selectedServiceId) {
            alert('Por favor, selecciona una fecha y horario.');
            return;
        }

        const adultos = parseInt($('#adultos').val()) || 0;
        const residentes = parseInt($('#residentes').val()) || 0;
        const ninos512 = parseInt($('#ninos-5-12').val()) || 0;
        const ninosMenores = parseInt($('#ninos-menores').val()) || 0;

        const totalPersonas = adultos + residentes + ninos512 + ninosMenores;

        if (totalPersonas === 0) {
            alert('Debe seleccionar al menos una persona.');
            return;
        }

        if (!validatePersonSelection()) {
            return;
        }

        $('#step-2').show();
        $('#btn-siguiente').hide();
    }

    function previousStep() {
        if (currentStep === 2) {
            currentStep = 1;
            $('#step-2').hide();
            $('#step-1').show();
            $('#btn-anterior').hide();
            $('#btn-siguiente').text('Siguiente →').show();

        } else if (currentStep === 3) {
            currentStep = 2;
            $('#step-3').hide();
            $('#step-2').show();
            $('#btn-siguiente').text('Siguiente →').show();
        }
    }

    function resetForm() {
        currentStep = 1;
        selectedDate = null;
        selectedServiceId = null;

        $('#step-2, #step-3').hide();
        $('#step-1').show();
        $('#btn-anterior').hide();
        $('#btn-siguiente').text('Siguiente →').show().prop('disabled', true);

        $('#adultos, #residentes, #ninos-5-12, #ninos-menores').val(0).trigger('change');
        $('#horarios-select').html('<option value="">Selecciona primero una fecha</option>').prop('disabled', true);

        $('.calendar-day').removeClass('selected');

        // ✅ CAMBIO: Usar la función clearPricing que ahora muestra 0€
        clearPricing();
    }

    window.proceedToPayment = function () {
        const service = findServiceById(selectedServiceId);
        const adultos = parseInt($('#adultos').val()) || 0;
        const residentes = parseInt($('#residentes').val()) || 0;
        const ninos512 = parseInt($('#ninos-5-12').val()) || 0;
        const ninosMenores = parseInt($('#ninos-menores').val()) || 0;

        const resumen = `
            RESUMEN DE LA RESERVA:
            
            Fecha: ${selectedDate}
            Hora: ${service.hora}
            
            Adultos: ${adultos}
            Residentes: ${residentes}
            Niños (5-12 años): ${ninos512}
            Niños (-5 años): ${ninosMenores}
            
            Total: ${$('#total-price').text()}
            
            ¿Proceder con la reserva?
        `;

        if (confirm(resumen)) {
            alert('Función de pago en desarrollo. La reserva se procesaría aquí.');
            resetForm();
        }
    };

    // ✅ FUNCIÓN MEJORADA PARA PROCEDER A DETALLES
    window.proceedToDetails = function () {
        console.log('=== INICIANDO proceedToDetails ===');

        if (!selectedDate || !selectedServiceId) {
            alert('Error: No hay fecha o servicio seleccionado');
            return;
        }

        const service = findServiceById(selectedServiceId);
        if (!service) {
            alert('Error: No se encontraron datos del servicio');
            return;
        }

        const adultos = parseInt($('#adultos').val()) || 0;
        const residentes = parseInt($('#residentes').val()) || 0;
        const ninos_5_12 = parseInt($('#ninos-5-12').val()) || 0;
        const ninos_menores = parseInt($('#ninos-menores').val()) || 0;

        let totalPrice = '0';
        try {
            const totalPriceElement = $('#total-price');
            if (totalPriceElement.length > 0) {
                const totalPriceText = totalPriceElement.text();
                totalPrice = totalPriceText.replace('€', '').trim();
            }
        } catch (error) {
            console.error('Error obteniendo precio total:', error);
        }

        const reservationData = {
            fecha: selectedDate,
            service_id: selectedServiceId,
            hora_ida: service.hora,
            hora_vuelta: service.hora_vuelta || '',
            adultos: adultos,
            residentes: residentes,
            ninos_5_12: ninos_5_12,
            ninos_menores: ninos_menores,
            precio_adulto: service.precio_adulto,
            precio_nino: service.precio_nino,
            precio_residente: service.precio_residente,
            total_price: totalPrice,
            descuento_grupo: $('#total-discount').text().includes('€') ?
                parseFloat($('#total-discount').text().replace('€', '').replace('-', '')) : 0,
            regla_descuento_aplicada: window.lastDiscountRule || null
        };

        console.log('Datos de reserva preparados:', reservationData);

        try {
            const dataString = JSON.stringify(reservationData);
            sessionStorage.setItem('reservationData', dataString);
            console.log('Datos guardados en sessionStorage exitosamente');
        } catch (error) {
            console.error('Error guardando en sessionStorage:', error);
            alert('Error guardando los datos de la reserva: ' + error.message);
            return;
        }

        // ✅ CALCULAR URL DESTINO DE FORMA MEJORADA
        let targetUrl;
        const currentPath = window.location.pathname;

        if (currentPath.includes('/bravo/')) {
            targetUrl = window.location.origin + '/bravo/detalles-reserva/';
        } else if (currentPath.includes('/')) {
            const pathParts = currentPath.split('/').filter(part => part !== '');
            if (pathParts.length > 0 && pathParts[0] !== 'detalles-reserva') {
                targetUrl = window.location.origin + '/' + pathParts[0] + '/detalles-reserva/';
            } else {
                targetUrl = window.location.origin + '/detalles-reserva/';
            }
        } else {
            targetUrl = window.location.origin + '/detalles-reserva/';
        }

        console.log('Redirigiendo a:', targetUrl);
        window.location.href = targetUrl;
    };

    window.selectDate = selectDate;
    window.findServiceById = findServiceById;

});

// ✅ FUNCIÓN MEJORADA PARA PROCESAR RESERVA
function processReservation() {
    console.log("=== PROCESANDO RESERVA SIMPLIFICADA ===");

    const checkbox = document.getElementById("privacy-policy");
    if (!checkbox || !checkbox.checked) {
        alert("Debes aceptar la política de privacidad para continuar.");
        checkbox.focus();
        return;
    }
    // Verificar que reservasAjax está definido
    if (typeof reservasAjax === "undefined") {
        console.error("reservasAjax no está definido");
        alert("Error: Variables AJAX no disponibles. Recarga la página e inténtalo de nuevo.");
        return;
    }

    // Validar formularios
    const nombre = jQuery("[name='nombre']").val().trim();
    const apellidos = jQuery("[name='apellidos']").val().trim();
    const email = jQuery("[name='email']").val().trim();
    const telefono = jQuery("[name='telefono']").val().trim();

    if (!nombre || !apellidos || !email || !telefono) {
        alert("Por favor, completa todos los campos de datos personales.");
        return;
    }

    // Validar email básico
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert("Por favor, introduce un email válido.");
        return;
    }

    // Obtener datos de reserva desde sessionStorage
    let reservationData;
    try {
        const dataString = sessionStorage.getItem("reservationData");
        if (!dataString) {
            alert("Error: No hay datos de reserva. Por favor, vuelve a hacer la reserva.");
            window.history.back();
            return;
        }

        reservationData = JSON.parse(dataString);
        console.log("Datos de reserva recuperados:", reservationData);
    } catch (error) {
        console.error("Error parseando datos de reserva:", error);
        alert("Error en los datos de reserva. Por favor, vuelve a hacer la reserva.");
        window.history.back();
        return;
    }

    // Deshabilitar botón y mostrar estado de carga
    const processBtn = jQuery(".process-btn");
    const originalText = processBtn.text();
    processBtn.prop("disabled", true).text("Procesando reserva...");

    // Preparar datos
    const ajaxData = {
        action: "process_reservation",
        nonce: reservasAjax.nonce,
        nombre: nombre,
        apellidos: apellidos,
        email: email,
        telefono: telefono,
        reservation_data: JSON.stringify(reservationData)
    };

    console.log("Datos a enviar:", ajaxData);

    // Enviar solicitud AJAX usando jQuery
    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: "POST",
        data: ajaxData,
        timeout: 30000,
        dataType: 'json',
        success: function (response) {
            console.log("Respuesta recibida:", response);

            // Rehabilitar botón
            processBtn.prop("disabled", false).text(originalText);

            if (response && response.success) {
                console.log("Reserva procesada exitosamente:", response.data);

                // ✅ MOSTRAR ALERT DE CONFIRMACIÓN
                const detalles = response.data.detalles;
                const mensaje = "🎉 ¡RESERVA CONFIRMADA! 🎉\n\n" +
                    "📋 LOCALIZADOR: " + response.data.localizador + "\n\n" +
                    "📅 DETALLES:\n" +
                    "• Fecha: " + detalles.fecha + "\n" +
                    "• Hora: " + detalles.hora + "\n" +
                    "• Personas: " + detalles.personas + "\n" +
                    "• Precio: " + detalles.precio_final + "€\n\n" +
                    "✅ Tu reserva ha sido procesada correctamente.\n\n" +
                    "¡Guarda tu localizador para futuras consultas!";

                alert(mensaje);

                // ✅ GUARDAR DATOS EN SESSIONSTORAGE PARA LA PÁGINA DE CONFIRMACIÓN
                try {
                    sessionStorage.setItem('confirmedReservation', JSON.stringify(response.data));
                    console.log("Datos de confirmación guardados en sessionStorage");
                } catch (error) {
                    console.error("Error guardando datos de confirmación:", error);
                }

                // Limpiar datos de reserva pendiente
                try {
                    sessionStorage.removeItem("reservationData");
                    console.log("SessionStorage de reserva limpiado");
                } catch (error) {
                    console.error("Error limpiando sessionStorage:", error);
                }

                // ✅ REDIRIGIR A PÁGINA DE CONFIRMACIÓN
                setTimeout(function () {
                    // Calcular URL de confirmación
                    let confirmUrl;
                    const currentPath = window.location.pathname;

                    if (currentPath.includes('/bravo/')) {
                        confirmUrl = window.location.origin + '/bravo/confirmacion-reserva/';
                    } else if (currentPath.includes('/')) {
                        const pathParts = currentPath.split('/').filter(part => part !== '');
                        if (pathParts.length > 0 && pathParts[0] !== 'confirmacion-reserva') {
                            confirmUrl = window.location.origin + '/' + pathParts[0] + '/confirmacion-reserva/';
                        } else {
                            confirmUrl = window.location.origin + '/confirmacion-reserva/';
                        }
                    } else {
                        confirmUrl = window.location.origin + '/confirmacion-reserva/';
                    }

                    console.log('Redirigiendo a confirmación:', confirmUrl);
                    window.location.href = confirmUrl;
                }, 2000);

            } else {
                console.error("Error procesando reserva:", response);
                const errorMsg = response && response.data ? response.data : "Error desconocido";
                alert("Error procesando la reserva: " + errorMsg);
            }
        },
        error: function (xhr, status, error) {
            console.error("Error de conexión:", error);
            console.error("XHR completo:", xhr);

            // Rehabilitar botón
            processBtn.prop("disabled", false).text(originalText);

            let errorMessage = "Error de conexión al procesar la reserva.";

            if (xhr.status === 0) {
                errorMessage += " (Sin conexión al servidor)";
            } else if (xhr.status === 403) {
                errorMessage += " (Error 403: Acceso denegado)";
            } else if (xhr.status === 404) {
                errorMessage += " (Error 404: URL no encontrada)";
            } else if (xhr.status === 500) {
                errorMessage += " (Error 500: Error interno del servidor)";
            }

            errorMessage += "\n\nPor favor, inténtalo de nuevo. Si el problema persiste, contacta con soporte.";
            alert(errorMessage);
        }
    });
}

function goBackToBooking() {
    sessionStorage.removeItem("reservationData");
    window.history.back();
}