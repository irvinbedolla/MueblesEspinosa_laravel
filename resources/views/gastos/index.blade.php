@extends('layouts.app')

@section('title', 'Módulo de Gastos y Préstamos - Muebles Espinosa')

@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="container-fluid px-4 mt-4 mb-5">
    
    <!-- NAVEGACIÓN SUPERIOR EN 4 SECCIONES -->
    <div class="d-flex justify-content-center flex-wrap gap-2 mb-4">
        <button type="button" class="btn btn-dark fw-bold px-4 py-2 shadow-sm nav-gasto-btn" onclick="mostrarSeccion('agregar', this)">AGREGAR</button>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2 shadow-sm nav-gasto-btn" onclick="mostrarSeccion('consultar', this)">CONSULTAR</button>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2 shadow-sm nav-gasto-btn" onclick="mostrarSeccion('prestamos', this)">PRÉSTAMOS</button>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2 shadow-sm nav-gasto-btn" onclick="mostrarSeccion('mis_prestamos', this)">MIS PRÉSTAMOS</button>
    </div>

    <!-- ==========================================
        SECCIÓN 1: AGREGAR GASTO OPERATIVO
    ========================================== -->
    <div id="seccionAgregar" class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-plus-circle text-primary me-2"></i>Registrar Nuevo Gasto Operativo</h5>
        
        <form id="formAgregarGasto" onsubmit="return false;">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small text-muted text-uppercase fw-bold">Descripción / Motivo</label>
                    <input type="text" id="gastoDescripcion" class="form-control form-control-lg" placeholder="Ej. Combustible, Mantenimiento..." required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted text-uppercase fw-bold">Fecha</label>
                    <input type="date" id="gastoFecha" class="form-control form-control-lg" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted text-uppercase fw-bold">Costo ($)</label>
                    <input type="number" step="any" id="gastoCosto" class="form-control form-control-lg" placeholder="0.00" required>
                </div>
            </div>
            
            <button type="button" class="btn btn-primary btn-lg rounded-3 px-5 fw-bold shadow-sm" onclick="guardarGastoDirecto()">
                <i class="bi bi-send-fill me-2"></i> GUARDAR GASTO
            </button>
        </form>
    </div>

    <!-- ==========================================
         SECCIÓN 2: CONSULTAR GASTOS POR FECHA
    ========================================== -->
    <div id="seccionConsultar" class="card border-0 shadow-sm rounded-4 p-4" style="display: none;">
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-search text-primary me-2"></i>Consulta de Gastos Operativos</h5>
        <div class="row g-3 align-items-end mb-4">
            <div class="col-md-4">
                <label class="form-label small text-muted text-uppercase fw-bold">Fecha Inicial</label>
                <!-- Ajustado a fecha amplia por defecto para traer registros migrados -->
                <input type="date" id="fecha1Gasto" class="form-control" value="2020-01-01">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted text-uppercase fw-bold">Fecha Final</label>
                <input type="date" id="fecha2Gasto" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" onclick="consultarGastos()">
                    <i class="bi bi-search me-1"></i> BUSCAR GASTOS
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle w-100" id="tablaConsultarGastos">
                <thead>
                    <tr class="text-uppercase small text-muted border-bottom">
                        <th>N° Gasto</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Costo</th>
                        <th class="text-center">Eliminar</th>
                    </tr>
                </thead>
                <tbody id="tbodyConsultarGastos"></tbody>
            </table>
        </div>
        <div class="text-center mt-3">
            <h3 class="fw-bold text-dark">Total: $<span id="lblTotalGastos">0.00</span></h3>
        </div>
    </div>

    <!-- ==========================================
         SECCIÓN 3: PRÉSTAMOS DE USUARIOS
    ========================================== -->
    <div id="seccionPrestamos" style="display: none;">
        <!-- SUB-NAVEGACIÓN DE PRÉSTAMOS -->
        <div class="d-flex justify-content-center gap-2 mb-4">
            <button type="button" class="btn btn-dark btn-sm fw-bold px-3 sub-prestamo-btn" onclick="cargarPrestamosSub('activos', this)">ACTIVOS</button>
            <button type="button" class="btn btn-outline-primary btn-sm fw-bold px-3 sub-prestamo-btn" onclick="cargarPrestamosSub('generar', this)">GENERAR</button>
            <button type="button" class="btn btn-outline-primary btn-sm fw-bold px-3 sub-prestamo-btn" onclick="cargarPrestamosSub('historial', this)">HISTORIAL PAGADOS</button>
        </div>

        <!-- VISTA SUB 1: PRÉSTAMOS ACTIVOS -->
        <div id="subPrestamosActivos" class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-warning me-2"></i>Préstamos Activos Pendientes</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="tablaPrestamosActivos">
                    <thead>
                        <tr class="text-uppercase small text-muted border-bottom">
                            <th>N° Gasto</th><th>Fecha</th><th>Usuario</th><th>Descripción</th><th>Costo</th><th>Restante</th><th>Historial</th><th>Abonar</th><th class="text-center">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPrestamosActivos"></tbody>
                </table>
            </div>
        </div>

        <!-- VISTA SUB 2: GENERAR NUEVO PRÉSTAMO -->
        <div id="subPrestamosGenerar" class="card border-0 shadow-sm rounded-4 p-4" style="display: none;">
            <h5 class="fw-bold text-primary mb-3"><i class="bi bi-cash-coin me-2"></i>Registrar Nuevo Préstamo</h5>
            
            <form id="formRegistrarPrestamo" onsubmit="return false;">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase fw-bold">Descripción / Motivo</label>
                        <input type="text" id="presDescripcion" class="form-control" placeholder="Ej. Préstamo personal, viáticos..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase fw-bold">Fecha del Préstamo</label>
                        <input type="date" id="presFecha" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase fw-bold">Monto del Préstamo ($)</label>
                        <input type="number" step="any" id="presMonto" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase fw-bold">Usuario Destino</label>
                        <select id="presUserId" class="form-select" required>
                            <option value="" selected>Selecciona un cobrador/usuario...</option>
                            @foreach($cobradores as $cob)
                                <option value="{{ $cob->id }}">{{ $cob->username }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <button type="button" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm" onclick="guardarPrestamoDirecto()">
                    <i class="bi bi-send-fill me-2"></i> Guardar Préstamo
                </button>
            </form>
        </div>

        <!-- VISTA SUB 3: HISTORIAL PRÉSTAMOS PAGADOS -->
        <div id="subPrestamosHistorial" class="card border-0 shadow-sm rounded-4 p-4" style="display: none;">
            <h5 class="fw-bold text-success mb-3"><i class="bi bi-check-circle-fill me-2"></i>Historial de Préstamos Totalmente Pagados</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="tablaPrestamosPagados">
                    <thead>
                        <tr class="text-uppercase small text-muted border-bottom">
                            <th>N° Gasto</th><th>Fecha</th><th>Usuario</th><th>Descripción</th><th>Costo</th><th>Pagos</th><th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPrestamosPagados"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==========================================
         SECCIÓN 4: MIS PRÉSTAMOS
    ========================================== -->
    <div id="seccionMisPrestamos" class="card border-0 shadow-sm rounded-4 p-4" style="display: none;">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge text-info me-2"></i>Mis Préstamos Personales</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100" id="tablaMisPrestamos">
                <thead>
                    <tr class="text-uppercase small text-muted border-bottom">
                        <th>N° Gasto</th><th>Fecha</th><th>Descripción</th><th>Costo</th><th>Restante</th><th>Historial</th>
                    </tr>
                </thead>
                <tbody id="tbodyMisPrestamos"></tbody>
            </table>
        </div>
    </div>

</div>

<!-- MODAL: REGISTRAR ABONO A PRÉSTAMO -->
<div class="modal fade" id="modalAbonarPrestamo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-wallet2 text-success me-2"></i>Registrar Abono a Préstamo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAbonarPrestamo" onsubmit="return false;">
                <div class="modal-body p-4">
                    <input type="hidden" id="modalPrestamoId">
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Saldo Restante Actual</label>
                        <input type="text" id="modalPrestamoRestante" class="form-control fw-bold text-danger" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Monto del Abono ($)</label>
                        <input type="number" step="any" id="modalMontoAbono" class="form-control form-control-lg" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Fecha del Abono</label>
                        <input type="date" id="modalFechaAbono" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success rounded-3 px-4 fw-bold" onclick="guardarAbonoPrestamoDirecto()">Guardar Abono</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: HISTORIAL DE ABONOS DE PRÉSTAMO -->
<div class="modal fade" id="modalHistorialAbonos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Historial de Abonos del Préstamo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-uppercase small text-muted border-bottom">
                                <th>Fecha</th>
                                <th>Registrado Por</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyHistorialAbonos"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS JQUERY & DATATABLES -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    // Cancelar envíos de formulario nativos
    document.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    }, true);

    // NAVEGACIÓN PRINCIPAL
    function mostrarSeccion(seccion, btn) {
        document.querySelectorAll('.nav-gasto-btn').forEach(b => {
            b.classList.remove('btn-dark');
            b.classList.add('btn-primary');
        });
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-dark');

        document.getElementById('seccionAgregar').style.display = (seccion === 'agregar') ? 'block' : 'none';
        document.getElementById('seccionConsultar').style.display = (seccion === 'consultar') ? 'block' : 'none';
        document.getElementById('seccionPrestamos').style.display = (seccion === 'prestamos') ? 'block' : 'none';
        document.getElementById('seccionMisPrestamos').style.display = (seccion === 'mis_prestamos') ? 'block' : 'none';

        if (seccion === 'consultar') consultarGastos();
        if (seccion === 'prestamos') cargarPrestamosSub('activos', document.querySelector('.sub-prestamo-btn'));
        if (seccion === 'mis_prestamos') cargarMisPrestamos();
    }

    // 1. GUARDAR GASTO OPERATIVO
    function guardarGastoDirecto() {
        const descElem  = document.getElementById('gastoDescripcion');
        const fechaElem = document.getElementById('gastoFecha');
        const costoElem = document.getElementById('gastoCosto');

        const desc  = descElem ? descElem.value.trim() : '';
        const fecha = fechaElem ? fechaElem.value : '';
        const costo = costoElem ? costoElem.value : '';

        if (!desc || !fecha || !costo) {
            alert('Por favor complete todos los campos requeridos.');
            return;
        }

        const btnGuardar = document.querySelector('#seccionAgregar button');
        const textoOriginal = btnGuardar ? btnGuardar.innerHTML : '';

        if (btnGuardar) {
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Guardando...`;
        }

        fetch(`{{ route('gastos.store') }}`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}' 
            },
            body: JSON.stringify({ descripcion: desc, fecha: fecha, costo: costo })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw data;
            return data;
        })
        .then(res => {
            alert('¡Éxito! ' + (res.message || 'Gasto guardado correctamente.'));
            document.getElementById('formAgregarGasto').reset();
            document.getElementById('gastoFecha').value = new Date().toISOString().split('T')[0];
        })
        .catch(err => {
            console.error('Error al guardar gasto:', err);
            alert('Error: ' + (err.message || 'No se pudo guardar el gasto.'));
        })
        .finally(() => {
            if (btnGuardar) {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            }
        });
    }

    // 2. CONSULTAR GASTOS CON DATATABLES
    function consultarGastos() {
        const f1 = document.getElementById('fecha1Gasto').value || '2020-01-01';
        const f2 = document.getElementById('fecha2Gasto').value || '2026-12-31';

        if ($.fn.DataTable.isDataTable('#tablaConsultarGastos')) {
            $('#tablaConsultarGastos').DataTable().clear().destroy();
        }

        const tbody = document.getElementById('tbodyConsultarGastos');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr>';

        fetch(`{{ route('gastos.consultar.ajax') }}?fecha1=${f1}&fecha2=${f2}`)
            .then(res => res.json())
            .then(res => {
                tbody.innerHTML = '';
                document.getElementById('lblTotalGastos').innerText = res.total || '0.00';

                const data = res.data || [];
                if (data.length > 0) {
                    data.forEach(item => {
                        const folio = item.folio_mostrar || item.gasto_id_legacy || item.id;
                        const costo = parseFloat(item.costo_real || item.costo || item.monto || 0).toFixed(2);

                        tbody.innerHTML += `
                            <tr>
                                <td class="fw-bold">#${folio}</td>
                                <td>${item.fecha || ''}</td>
                                <td>${item.descripcion || ''}</td>
                                <td class="fw-bold text-danger">$${costo}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="eliminarRegistro('${item.id}', 'gasto')">
                                        <i class="bi bi-trash-fill fs-5"></i>
                                    </button>
                                </td>
                            </tr>`;
                    });
                }

                $('#tablaConsultarGastos').DataTable({
                    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    pageLength: 10,
                    order: [[1, 'desc']],
                    responsive: true
                });
            })
            .catch(err => {
                console.error('Error al consultar gastos:', err);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Error al consultar gastos.</td></tr>';
            });
    }

    // 3. SUB-NAVEGACIÓN DE PRÉSTAMOS
    function cargarPrestamosSub(sub, btn) {
        document.querySelectorAll('.sub-prestamo-btn').forEach(b => {
            b.classList.remove('btn-dark');
            b.classList.add('btn-outline-primary');
        });
        if (btn) { 
            btn.classList.remove('btn-outline-primary'); 
            btn.classList.add('btn-dark'); 
        }

        document.getElementById('subPrestamosActivos').style.display = (sub === 'activos') ? 'block' : 'none';
        document.getElementById('subPrestamosGenerar').style.display = (sub === 'generar') ? 'block' : 'none';
        document.getElementById('subPrestamosHistorial').style.display = (sub === 'historial') ? 'block' : 'none';

        if (sub === 'activos' || sub === 'historial') {
            const seccionFiltro = (sub === 'activos') ? 'activos' : 'pagados';
            const tableId = (sub === 'activos') ? '#tablaPrestamosActivos' : '#tablaPrestamosPagados';
            const tbodyId = (sub === 'activos') ? 'tbodyPrestamosActivos' : 'tbodyPrestamosPagados';

            if ($.fn.DataTable.isDataTable(tableId)) {
                $(tableId).DataTable().clear().destroy();
            }

            const tbody = document.getElementById(tbodyId);
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Cargando préstamos...</td></tr>';

            fetch(`{{ route('gastos.prestamos.ajax') }}?seccion=${seccionFiltro}`)
                .then(res => res.json())
                .then(res => {
                    tbody.innerHTML = '';
                    const listado = res.data || [];

                    if (listado.length > 0) {
                        listado.forEach(item => {
                            const folio = item.folio_mostrar || item.prestamo_id_legacy || item.id;
                            const costo = parseFloat(item.costo_real || item.costo || item.monto_total || 0).toFixed(2);
                            const restante = parseFloat(item.saldo_restante || 0).toFixed(2);

                            if (sub === 'activos') {
                                tbody.innerHTML += `
                                    <tr>
                                        <td class="fw-bold">#${folio}</td>
                                        <td>${item.fecha || ''}</td>
                                        <td class="fw-bold text-dark">${item.nombre_usuario || 'N/A'}</td>
                                        <td>${item.descripcion || ''}</td>
                                        <td>$${costo}</td>
                                        <td class="text-danger fw-bold">$${restante}</td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm text-white rounded-circle" onclick="verHistorialAbonos('${item.id}')">
                                                <i class="bi bi-box-arrow-down fs-6"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm text-white rounded-circle" onclick="abrirModalAbonar('${item.id}', '${restante}')">
                                                <i class="bi bi-wallet2 fs-6"></i>
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="eliminarRegistro('${item.id}', 'prestamo')">
                                                <i class="bi bi-trash-fill fs-5"></i>
                                            </button>
                                        </td>
                                    </tr>`;
                            } else {
                                tbody.innerHTML += `
                                    <tr>
                                        <td class="fw-bold">#${folio}</td>
                                        <td>${item.fecha || ''}</td>
                                        <td class="fw-bold text-dark">${item.nombre_usuario || 'N/A'}</td>
                                        <td>${item.descripcion || ''}</td>
                                        <td>$${costo}</td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm text-white rounded-circle" onclick="verHistorialAbonos('${item.id}')">
                                                <i class="bi bi-box-arrow-down fs-6"></i>
                                            </button>
                                        </td>
                                        <td><span class="badge bg-success px-3 py-2 rounded-pill">Pagado</span></td>
                                    </tr>`;
                            }
                        });
                    }

                    $(tableId).DataTable({
                        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                        pageLength: 10,
                        order: [[1, 'desc']],
                        responsive: true
                    });
                })
                .catch(err => {
                    console.error('Error al cargar préstamos:', err);
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-3">Error al obtener préstamos.</td></tr>';
                });
        }
    }

    // 4. GUARDAR NUEVO PRÉSTAMO
    function guardarPrestamoDirecto() {
        const descElem  = document.getElementById('presDescripcion');
        const fechaElem = document.getElementById('presFecha');
        const montoElem = document.getElementById('presMonto');
        const userElem  = document.getElementById('presUserId');

        const desc   = descElem ? descElem.value.trim() : '';
        const fecha  = fechaElem ? fechaElem.value : '';
        const monto  = montoElem ? montoElem.value : '';
        const userId = userElem ? userElem.value : '';

        if (!desc || !fecha || !monto || !userId) {
            alert('Por favor complete todos los campos del préstamo.');
            return;
        }

        const btnGuardar = document.querySelector('#subPrestamosGenerar button');
        const textoOriginal = btnGuardar ? btnGuardar.innerHTML : '';

        if (btnGuardar) {
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Guardando...`;
        }

        fetch(`{{ route('gastos.prestamos.store') }}`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}' 
            },
            body: JSON.stringify({ descripcion: desc, fecha: fecha, monto: monto, user_id: userId })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw data;
            return data;
        })
        .then(res => {
            alert('¡Éxito! ' + (res.message || 'Préstamo guardado correctamente.'));
            document.getElementById('formRegistrarPrestamo').reset();
            document.getElementById('presFecha').value = new Date().toISOString().split('T')[0];
            cargarPrestamosSub('activos', document.querySelectorAll('.sub-prestamo-btn')[0]);
        })
        .catch(err => {
            console.error('Error al guardar préstamo:', err);
            alert('Error: ' + (err.message || 'No se pudo registrar el préstamo.'));
        })
        .finally(() => {
            if (btnGuardar) {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            }
        });
    }

    // 5. CARGAR MIS PRÉSTAMOS
    function cargarMisPrestamos() {
        if ($.fn.DataTable.isDataTable('#tablaMisPrestamos')) {
            $('#tablaMisPrestamos').DataTable().clear().destroy();
        }

        const tbody = document.getElementById('tbodyMisPrestamos');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Cargando mis préstamos...</td></tr>';

        fetch(`{{ route('gastos.prestamos.ajax') }}?seccion=mis_prestamos`)
            .then(res => res.json())
            .then(res => {
                tbody.innerHTML = '';
                const data = res.data || [];

                if (data.length > 0) {
                    data.forEach(item => {
                        const folio = item.folio_mostrar || item.prestamo_id_legacy || item.id;
                        const costo = parseFloat(item.costo_real || item.costo || item.monto_total || 0).toFixed(2);
                        const restante = parseFloat(item.saldo_restante || 0).toFixed(2);

                        tbody.innerHTML += `
                            <tr>
                                <td class="fw-bold">#${folio}</td>
                                <td>${item.fecha || ''}</td>
                                <td>${item.descripcion || ''}</td>
                                <td>$${costo}</td>
                                <td class="text-danger fw-bold">$${restante}</td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm text-white rounded-circle" onclick="verHistorialAbonos('${item.id}')">
                                        <i class="bi bi-box-arrow-down fs-6"></i>
                                    </button>
                                </td>
                            </tr>`;
                    });
                }

                $('#tablaMisPrestamos').DataTable({
                    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    pageLength: 10,
                    order: [[1, 'desc']],
                    responsive: true
                });
            })
            .catch(err => {
                console.error('Error al obtener mis préstamos:', err);
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error al consultar datos.</td></tr>';
            });
    }

    // 6. MODAL ABONAR A PRÉSTAMO
    function abrirModalAbonar(id, saldoRestante) {
        document.getElementById('modalPrestamoId').value = id;
        document.getElementById('modalPrestamoRestante').value = '$' + parseFloat(saldoRestante || 0).toFixed(2);
        document.getElementById('modalMontoAbono').value = '';
        
        const modalElem = document.getElementById('modalAbonarPrestamo');
        const modal = bootstrap.Modal.getInstance(modalElem) || new bootstrap.Modal(modalElem);
        modal.show();
    }

    function guardarAbonoPrestamoDirecto() {
        const id    = document.getElementById('modalPrestamoId').value;
        const monto = document.getElementById('modalMontoAbono').value;
        const fecha = document.getElementById('modalFechaAbono').value;

        if (!id || !monto || !fecha) {
            alert('Por favor ingrese el monto y la fecha del abono.');
            return;
        }

        const btnGuardar = document.querySelector('#modalAbonarPrestamo button.btn-success');
        const textoOriginal = btnGuardar ? btnGuardar.innerHTML : '';

        if (btnGuardar) {
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Guardando...`;
        }

        fetch(`{{ route('gastos.prestamos.abonar') }}`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}' 
            },
            body: JSON.stringify({ prestamo_id: id, monto_abono: monto, fecha_abono: fecha })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw data;
            return data;
        })
        .then(res => {
            alert('¡Éxito! ' + (res.message || 'Abono registrado correctamente.'));
            
            // Cerrar el modal correctamente
            const modalElem = document.getElementById('modalAbonarPrestamo');
            const modal = bootstrap.Modal.getInstance(modalElem);
            if (modal) modal.hide();

            // Limpieza manual de overlays Bootstrap para que la pantalla no se congele
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style = '';

            // Recargar sub-pestaña de préstamos activos
            cargarPrestamosSub('activos');
        })
        .catch(err => {
            console.error('Error al registrar abono:', err);
            alert('Error: ' + (err.message || 'No se pudo guardar el abono.'));
        })
        .finally(() => {
            if (btnGuardar) {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            }
        });
    }

    // 7. HISTORIAL DE ABONOS MODAL
    function verHistorialAbonos(id) {
        const tbody = document.getElementById('tbodyHistorialAbonos');
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Cargando...</td></tr>';

        const modalElem = document.getElementById('modalHistorialAbonos');
        const modal = bootstrap.Modal.getInstance(modalElem) || new bootstrap.Modal(modalElem);
        modal.show();

        fetch(`{{ url('/gastos/prestamos/historial') }}/${id}`)
            .then(res => res.json())
            .then(res => {
                tbody.innerHTML = '';
                const listado = res.data || (Array.isArray(res) ? res : []);

                if (listado.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No hay abonos registrados.</td></tr>';
                } else {
                    listado.forEach(ab => {
                        tbody.innerHTML += `
                            <tr>
                                <td>${ab.fecha || ''}</td>
                                <td>${ab.cobrador || 'Sistema'}</td>
                                <td class="text-end fw-bold text-success">$${parseFloat(ab.monto || 0).toFixed(2)}</td>
                            </tr>`;
                    });
                }
            })
            .catch(err => {
                console.error('Error al obtener historial:', err);
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">No se pudo cargar el historial.</td></tr>';
            });
    }

    // 8. ELIMINAR REGISTRO
    function eliminarRegistro(id, tipo) {
        if (confirm('¿Está seguro de eliminar este registro? Esta acción no se puede deshacer.')) {
            fetch(`{{ route('gastos.eliminar') }}`, {
                method: 'DELETE',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                },
                body: JSON.stringify({ id: id, tipo: tipo })
            })
            .then(res => res.json())
            .then(res => {
                alert(res.message || 'Registro eliminado correctamente.');
                if (tipo === 'gasto') consultarGastos();
                else cargarPrestamosSub('activos');
            })
            .catch(err => {
                console.error('Error al eliminar:', err);
                alert('No se pudo eliminar el registro.');
            });
        }
    }
</script>
@endsection