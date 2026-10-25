@extends('layouts.app')

@section('title', 'Mi Ruta de Cobro - Muebles Espinosa')

@section('content')
<div class="container-fluid px-1 px-md-2">
    
    <!-- BARRA DE ESTADO DE CONEXIÓN Y SINCRONIZACIÓN DINÁMICA -->
    <div class="card border-0 shadow-sm rounded-4 mb-3" id="card_conexion_status">
        <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <span id="dot_conexion" class="spinner-grow spinner-grow-sm text-success me-2" role="status"></span>
                <div>
                    <strong class="d-block lh-1 text-dark" id="txt_conexion">En línea (Conectado)</strong>
                    <small class="text-muted" id="txt_pendientes_sync">0 cobros pendientes por sincronizar</small>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-primary rounded-3 fw-bold shadow-sm" onclick="sincronizarAbonosManual()">
                <i class="bi bi-arrow-repeat me-1"></i> Sincronizar
            </button>
        </div>
    </div>

    <!-- ENCABEZADO DE LA RUTA -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold m-0 text-dark"><i class="bi bi-map text-primary me-2"></i><span id="lbl_nombre_ruta">{{ $ruta->nombre ?? 'Ruta Guardada' }}</span></h5>
        <span class="badge bg-primary rounded-pill px-3 py-2" id="lbl_total_clientes">0 Clientes</span>
    </div>

    <!-- PESTAÑAS / NAVEGACIÓN: PENDIENTES VS CUBIERTOS -->
    <ul class="nav nav-pills nav-fill bg-white p-1 rounded-4 shadow-sm mb-3 border" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-3 fw-bold py-2" id="tab-pendientes-tab" data-bs-toggle="pill" data-bs-target="#tab-pendientes" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i> Pendientes (<span id="cnt_pendientes">0</span>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 fw-bold py-2" id="tab-cubiertos-tab" data-bs-toggle="pill" data-bs-target="#tab-cubiertos" type="button" role="tab">
                <i class="bi bi-check-circle me-1"></i> Visitados (<span id="cnt_cubiertos">0</span>)
            </button>
        </li>
    </ul>

    <!-- CONTENIDO DE LAS PESTAÑAS -->
    <div class="tab-content" id="pills-tabContent">
        <!-- PESTAÑA: CLIENTES PENDIENTES -->
        <div class="tab-pane fade show active" id="tab-pendientes" role="tabpanel">
            <div class="row g-2" id="contenedor_pendientes">
                <!-- Carga dinámica JS -->
            </div>
        </div>

        <!-- PESTAÑA: CLIENTES VISITADOS / COBRADOS -->
        <div class="tab-pane fade" id="tab-cubiertos" role="tabpanel">
            <div class="row g-2" id="contenedor_cubiertos">
                <!-- Carga dinámica JS -->
            </div>
        </div>
    </div>
</div>

<!-- MODAL MÓVIL PARA CAPTURAR EL ABONO Y GPS -->
<div class="modal fade" id="modalCapturaCobro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Registrar Visita / Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <small class="text-muted fw-bold d-block">CLIENTE:</small>
                    <strong class="text-dark d-block text-truncate" id="lbl_modal_cliente">---</strong>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Estatus de la Visita:</label>
                    <select id="modal_select_status" class="form-select form-select-lg border-2" onchange="toggleMontoInput(this.value)">
                        <option value="Cobrado">🟢 Cobrado (Abono Recibido)</option>
                        <option value="No_Encontrado">🟠 No Se Encontró el Cliente</option>
                        <option value="Promesa_Pago">🔵 Promesa de Pago</option>
                    </select>
                </div>

                <div class="mb-3" id="div_monto_input">
                    <label class="form-label fw-bold text-muted small">Monto Recibido ($):</label>
                    <input type="number" step="0.01" id="modal_input_monto" class="form-control form-control-lg border-2 text-success fw-bold" placeholder="0.00">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Observaciones / Notas:</label>
                    <textarea id="modal_input_obs" class="form-control border-2" rows="2" placeholder="Ej. Prometió abonar el próximo viernes..."></textarea>
                </div>

                <div class="alert alert-light border rounded-3 small text-muted d-flex align-items-center mb-0">
                    <i class="bi bi-geo-alt-fill text-danger fs-5 me-2"></i>
                    <span>Se registrará la ubicación GPS exacta al confirmar el pago.</span>
                </div>
            </div>

            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm" id="btn_guardar_cobro" onclick="guardarCobroLocal()">
                    <i class="bi bi-check-circle me-1"></i> Guardar Cobro
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let db;
    let detalleActivo = null;
    let ventaActiva = null;
    let clienteActivo = null;

    const datosRutaServidor = @json($ruta ?? null);

    // Detección automática del estado de red en tiempo real
    window.addEventListener('online', actualizarIndicadorRed);
    window.addEventListener('offline', actualizarIndicadorRed);

    function actualizarIndicadorRed() {
        const txtConn = document.getElementById('txt_conexion');
        const dotConn = document.getElementById('dot_conexion');

        if (navigator.onLine) {
            txtConn.innerText = 'En línea (Conectado)';
            txtConn.className = 'd-block lh-1 text-dark';
            dotConn.className = 'spinner-grow spinner-grow-sm text-success me-2';
        } else {
            txtConn.innerText = 'Sin Conexión (Modo Offline)';
            txtConn.className = 'd-block lh-1 text-danger fw-bold';
            dotConn.className = 'spinner-grow spinner-grow-sm text-danger me-2';
        }
    }

    // Inicializar IndexedDB con soporte para ruta local y cobros pendientes
    const request = indexedDB.open("MueblesEspinosaCobrosDB", 2);

    request.onupgradeneeded = function(e) {
        db = e.target.result;
        if (!db.objectStoreNames.contains("cobros_pendientes")) {
            db.createObjectStore("cobros_pendientes", { keyPath: "detalle_id" });
        }
        if (!db.objectStoreNames.contains("ruta_cache")) {
            db.createObjectStore("ruta_cache", { keyPath: "id" });
        }
    };

    request.onsuccess = function(e) {
        db = e.target.result;
        actualizarIndicadorRed();
        inicializarModuloRuta();
        actualizarConteoPendientesOffline();
    };

    function inicializarModuloRuta() {
        if (navigator.onLine && datosRutaServidor) {
            // Guardar en la caché local
            const tx = db.transaction(["ruta_cache"], "readwrite");
            tx.objectStore("ruta_cache").put({ id: "ruta_actual", datos: datosRutaServidor });
            
            tx.oncomplete = function() {
                renderizarListaClientes(datosRutaServidor);
            };
        } else {
            // Cargar de la caché local si no hay internet o se recargó la página
            const tx = db.transaction(["ruta_cache"], "readonly");
            const req = tx.objectStore("ruta_cache").get("ruta_actual");
            
            req.onsuccess = function() {
                if (req.result && req.result.datos) {
                    renderizarListaClientes(req.result.datos);
                } else {
                    document.getElementById('contenedor_pendientes').innerHTML = `
                        <div class="col-12 text-center py-4 text-muted">
                            <i class="bi bi-wifi-off fs-1 d-block mb-2 text-secondary"></i>
                            Sin datos en caché local. Conéctese a internet para descargar la ruta.
                        </div>`;
                }
            };
        }
    }

    function renderizarListaClientes(rutaData) {
        const contenedorPendientes = document.getElementById('contenedor_pendientes');
        const contenedorCubiertos = document.getElementById('contenedor_cubiertos');

        contenedorPendientes.innerHTML = '';
        contenedorCubiertos.innerHTML = '';

        if (!rutaData || !rutaData.detalles || rutaData.detalles.length === 0) {
            contenedorPendientes.innerHTML = `<div class="col-12 text-center py-4 text-muted">No hay clientes asignados en esta ruta.</div>`;
            return;
        }

        document.getElementById('lbl_nombre_ruta').innerText = rutaData.nombre || 'Ruta del Día';
        document.getElementById('lbl_total_clientes').innerText = `${rutaData.detalles.length} Clientes`;

        const tx = db.transaction(["cobros_pendientes"], "readonly");
        const store = tx.objectStore("cobros_pendientes");
        const getAllReq = store.getAll();

        getAllReq.onsuccess = function() {
            const cobrosLocales = {};
            getAllReq.result.forEach(item => {
                cobrosLocales[item.detalle_id] = item;
            });

            let countPendientes = 0;
            let countCubiertos = 0;

            rutaData.detalles.forEach(d => {
                const v = d.venta || {};
                const c = v.cliente || {};
                const local = cobrosLocales[d.id];

                // Determinar si la tarjeta ya fue procesada/cubierta
                const estaCubierto = local || (d.status && d.status !== 'Pendiente');
                const statusMostrar = local ? `${local.status} (Offline)` : d.status;
                const badgeClass = local 
                    ? 'bg-warning text-dark border-warning' 
                    : (d.status === 'Cobrado' ? 'bg-success text-white' : 'bg-info bg-opacity-10 text-info border-info');

                const htmlCard = `
                    <div class="col-12" id="card_detalle_${d.id}">
                        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-light text-dark border mb-1">Folio #${v.folio || 'N/A'}</span>
                                    <h6 class="fw-bold text-dark m-0">${c.nombre || 'Cliente No Registrado'}</h6>
                                </div>
                                <span class="badge ${badgeClass} border rounded-pill px-3 py-1">
                                    ${statusMostrar}
                                </span>
                            </div>

                            <div class="small text-muted mb-2">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> ${c.calle || ''}, ${c.colonia || ''}
                            </div>

                            <div class="p-2 bg-light rounded-3 d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <small class="text-muted d-block fw-bold" style="font-size: 0.75rem;">SALDO PENDIENTE</small>
                                    <strong class="text-danger fs-6">$${parseFloat(v.saldo_restante || 0).toFixed(2)}</strong>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block fw-bold" style="font-size: 0.75rem;">SUGERIDO</small>
                                    <strong class="text-primary fs-6">$${parseFloat(d.monto_sugerido || 0).toFixed(2)}</strong>
                                </div>
                            </div>

                            <button type="button" class="btn ${estaCubierto ? 'btn-outline-secondary' : 'btn-success'} w-100 rounded-3 fw-bold py-2 shadow-sm" onclick="abrirModalRegistrarCobro('${d.id}', '${v.id}', '${c.id || ''}', '${(c.nombre || '').replace(/'/g, "'")}', '${v.saldo_restante}', '${d.monto_sugerido}')">
                                <i class="bi bi-cash-coin me-1"></i> ${estaCubierto ? 'Modificar Registro' : 'Registrar Cobro'}
                            </button>
                        </div>
                    </div>
                `;

                if (estaCubierto) {
                    contenedorCubiertos.innerHTML += htmlCard;
                    countCubiertos++;
                } else {
                    contenedorPendientes.innerHTML += htmlCard;
                    countPendientes++;
                }
            });

            document.getElementById('cnt_pendientes').innerText = countPendientes;
            document.getElementById('cnt_cubiertos').innerText = countCubiertos;

            if (countPendientes === 0) {
                contenedorPendientes.innerHTML = `<div class="col-12 text-center py-4 text-success fw-bold"><i class="bi bi-check-circle-fill fs-2 d-block mb-2"></i>¡Todas las visitas pendientes de hoy han sido cubiertas!</div>`;
            }
        };
    }

    // Capturar GPS y Guardar Cobro Localmente
    function guardarCobroLocal() {
        const btn = document.getElementById('btn_guardar_cobro');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Obteniendo GPS...';

        const status = document.getElementById('modal_select_status').value;
        const monto = (status === 'Cobrado') ? parseFloat(document.getElementById('modal_input_monto').value || 0) : 0;
        const obs = document.getElementById('modal_input_obs').value;
        
        const ahora = new Date();
        const fechaFormateada = ahora.getFullYear() + '-' +
            String(ahora.getMonth() + 1).padStart(2, '0') + '-' +
            String(ahora.getDate()).padStart(2, '0') + ' ' +
            String(ahora.getHours()).padStart(2, '0') + ':' +
            String(ahora.getMinutes()).padStart(2, '0') + ':' +
            String(ahora.getSeconds()).padStart(2, '0');

        // Intentar obtener la ubicación GPS antes de guardar
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const coords = position.coords.latitude + ',' + position.coords.longitude;
                    finalizarGuardadoLocal(status, monto, obs, fechaFormateada, coords);
                },
                function(error) {
                    console.warn('No se pudo obtener GPS:', error.message);
                    finalizarGuardadoLocal(status, monto, obs, fechaFormateada, null);
                },
                { enableHighAccuracy: true, timeout: 5000 }
            );
        } else {
            finalizarGuardadoLocal(status, monto, obs, fechaFormateada, null);
        }
    }

    function finalizarGuardadoLocal(status, monto, obs, fechaFormateada, gpsCoords) {
        const registro = {
            detalle_id: detalleActivo,
            venta_id: ventaActiva,
            cliente_id: clienteActivo,
            monto: monto,
            status: status,
            observaciones: obs,
            fecha_local: fechaFormateada,
            coordenadas_gps: gpsCoords
        };

        // 1. Guardar en la cola de cobros pendientes
        const tx = db.transaction(["cobros_pendientes", "ruta_cache"], "readwrite");
        tx.objectStore("cobros_pendientes").put(registro);

        // 2. ACTUALIZAR TAMBIÉN LA CACHÉ LOCAL DE LA RUTA PARA REFLEJAR EL CAMBIO VISUAL INMEDIATO
        const storeCache = tx.objectStore("ruta_cache");
        const reqCache = storeCache.get("ruta_actual");

        reqCache.onsuccess = function() {
            if (reqCache.result && reqCache.result.datos) {
                let datosActuales = reqCache.result.datos;
                let detalleEncontrado = datosActuales.detalles.find(d => d.id === detalleActivo);
                
                if (detalleEncontrado) {
                    detalleEncontrado.status = status;
                    detalleEncontrado.monto_cobrado = monto;
                    storeCache.put({ id: "ruta_actual", datos: datosActuales });
                }
            }
        };

        tx.oncomplete = function() {
            const btn = document.getElementById('btn_guardar_cobro');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Guardar Cobro';

            const modalEl = document.getElementById('modalCapturaCobro');
            const modalInst = bootstrap.Modal.getInstance(modalEl);
            if (modalInst) modalInst.hide();

            actualizarConteoPendientesOffline();

            // Refrescar la vista por pestañas inmediatamente con los datos en memoria actualizados
            const txRead = db.transaction(["ruta_cache"], "readonly");
            const reqRead = txRead.objectStore("ruta_cache").get("ruta_actual");
            reqRead.onsuccess = function() {
                if (reqRead.result && reqRead.result.datos) {
                    renderizarListaClientes(reqRead.result.datos);
                }
            };

            // Si hay internet, sincroniza automáticamente con el servidor
            if (navigator.onLine) {
                sincronizarAbonosManual();
            }
        };
    }

    function abrirModalRegistrarCobro(detalleId, ventaId, clienteId, nombreCliente, saldoRestante, montoSugerido) {
        detalleActivo = detalleId;
        ventaActiva = ventaId;
        clienteActivo = clienteId;

        document.getElementById('lbl_modal_cliente').innerText = nombreCliente;
        document.getElementById('modal_select_status').value = 'Cobrado';
        document.getElementById('modal_input_monto').value = montoSugerido || '0.00';
        document.getElementById('modal_input_obs').value = '';

        toggleMontoInput('Cobrado');

        const modalElement = document.getElementById('modalCapturaCobro');
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        modalInstance.show();
    }

    function toggleMontoInput(status) {
        const divMonto = document.getElementById('div_monto_input');
        if (status === 'Cobrado') {
            divMonto.style.display = 'block';
        } else {
            divMonto.style.display = 'none';
            document.getElementById('modal_input_monto').value = '0.00';
        }
    }

    function actualizarConteoPendientesOffline() {
        if (!db) return;

        const transaction = db.transaction(["cobros_pendientes"], "readonly");
        const objectStore = transaction.objectStore("cobros_pendientes");
        const countRequest = objectStore.count();

        countRequest.onsuccess = function() {
            const total = countRequest.result || 0;
            const txtPendientes = document.getElementById('txt_pendientes_sync');
            if (txtPendientes) {
                txtPendientes.innerText = `${total} cobros pendientes por sincronizar`;
            }
        };
    }

    function sincronizarAbonosManual() {
        if (!navigator.onLine) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Sin Conexión', 'Debes estar conectado a internet/Wi-Fi para sincronizar.', 'warning');
            } else {
                alert('Sin conexión a internet.');
            }
            return;
        }

        if (!db) return;

        const tx = db.transaction(["cobros_pendientes"], "readonly");
        const store = tx.objectStore("cobros_pendientes");
        const getAllReq = store.getAll();

        getAllReq.onsuccess = function() {
            const abonosPendientes = getAllReq.result;

            if (!abonosPendientes || abonosPendientes.length === 0) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Sincronización', 'No hay cobros pendientes por enviar.', 'info');
                } else {
                    alert('No hay cobros pendientes por sincronizar.');
                }
                return;
            }

            if (typeof mostrarCargando === 'function') {
                mostrarCargando('Sincronizando cobros...', 'Enviando ' + abonosPendientes.length + ' registros al servidor.');
            }

            fetch(`{{ route('cobrador.sincronizar') }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(abonosPendientes)
            })
            .then(async response => {
                const isJson = response.headers.get('content-type')?.includes('application/json');
                const data = isJson ? await response.json() : null;

                if (!response.ok) {
                    const error = (data && data.message) || response.statusText;
                    throw new Error(error);
                }
                return data;
            })
            .then(data => {
                if (typeof ocultarCargando === 'function') ocultarCargando();

                if (data && (data.status === 'success' || data.success)) {
                    // 1. Vaciar almacenes en IndexedDB
                    const transaction = db.transaction(["cobros_pendientes", "ruta_cache"], "readwrite");
                    transaction.objectStore("cobros_pendientes").clear();
                    transaction.objectStore("ruta_cache").delete("ruta_actual");

                    transaction.oncomplete = function() {
                        if (typeof actualizarConteoPendientesOffline === 'function') {
                            actualizarConteoPendientesOffline();
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire('¡Sincronizado!', 'Los cobros se han guardado con éxito en el servidor.', 'success').then(() => {
                                window.location.reload();
                            });
                        } else {
                            alert('¡Cobros sincronizados con éxito!');
                            window.location.reload();
                        }
                    };
                } else {
                    alert('Atención: ' + (data.message || 'No se pudo completar la sincronización.'));
                }
            })
            .catch(err => {
                if (typeof ocultarCargando === 'function') ocultarCargando();
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error de Sincronización', err.message || 'Ocurrió un problema al enviar los datos.', 'error');
                } else {
                    alert('Error al sincronizar: ' + err.message);
                }
            });
        };
    }
</script>
@endsection