
@extends('layouts.app')

@section('title', 'Módulo de Reportes - Muebles Espinosa')

@section('content')
<!-- CSS de DataTables para Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4 mt-4 mb-5">
    
    <div class="text-center mb-4">
        <h2 class="fw-bold text-dark">Reportes</h2>
        <p class="text-muted small">Genera consultas contables, reportes de ventas, clientes, gastos y abonos con paginación y exportación.</p>
    </div>

    <!-- PESTAÑAS DE NAVEGACIÓN (Imagen 1) -->
    <div class="d-flex justify-content-center flex-wrap gap-2 mb-4">
        <button type="button" class="btn btn-dark fw-bold px-4 py-2 shadow-sm nav-report-btn" onclick="cambiarReporte('usuario', this)">R. USUARIO</button>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2 shadow-sm nav-report-btn" onclick="cambiarReporte('cliente', this)">R. CLIENTE</button>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2 shadow-sm nav-report-btn" onclick="cambiarReporte('ventas', this)">R. VENTAS</button>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2 shadow-sm nav-report-btn" onclick="cambiarReporte('gastos', this)">R. GASTOS</button>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2 shadow-sm nav-report-btn" onclick="cambiarReporte('abonos', this)">R. ABONOS</button>
    </div>

    <!-- PANEL DE FILTROS -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <!-- NOTA: Se evita el submit tradicional utilizando type="button" para no disparar el spinner global -->
            <form id="formFiltrosReporte" onsubmit="return false;">
                <input type="hidden" id="tipoReporteActual" value="usuario">

                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Fecha Inicial</label>
                        <input type="date" id="fecha1" class="form-control" value="{{ date('Y-m-01') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Fecha Final</label>
                        <input type="date" id="fecha2" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <!-- SELECTOR ESPECÍFICO -->
                    <div class="col-md-4" id="contenedorTarget" style="display: block;">
                        <label class="form-label small fw-bold text-muted text-uppercase" id="lblTarget">Seleccionar Usuario</label>
                        
                        <select id="selectUsuario" class="form-select">
                            <option value="">Todos los Usuarios...</option>
                            @foreach($cobradores as $cob)
                                <option value="{{ $cob->id }}">{{ $cob->username }}</option>
                            @endforeach
                        </select>

                        <div id="contenedorClienteSelect" style="display: none;">
                            <input type="text" id="inputBuscarClienteAjax" class="form-control" placeholder="Escriba el nombre del cliente..." autocomplete="off">
                            <input type="hidden" id="selectClienteId">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" onclick="ejecutarConsulta()">
                            <i class="bi bi-search me-1"></i> BUSCAR
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- GRÁFICA INTERACTIVA -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-graph-up me-1 text-primary"></i> Comportamiento del Periodo</h6>
            <div style="height: 220px;">
                <canvas id="chartReportes"></canvas>
            </div>
        </div>
    </div>

    <!-- TABLA CON DATATABLES Y PAGINACIÓN -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark mb-0" id="tituloReporteVista">Reporte USUARIO</h4>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="tablaDataTables">
                    <thead id="theadReporte">
                        <!-- Se llena dinámicamente -->
                    </thead>
                    <tbody id="tbodyReporte">
                        <!-- Se llena dinámicamente -->
                    </tbody>
                </table>
            </div>

            <!-- TOTAL TOTALIZADO EN GRANDE (Imagen 2, 4, 5, 7, 8) -->
            <div class="text-center mt-4">
                <h2 class="fw-bold text-dark mb-0">Total: $<span id="lblTotalSumado">0.00</span></h2>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS DE JQUERY, DATATABLES Y CHART.JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    let myChart = null;
    let dataTableInstance = null;

    // 1. DESACTIVAR ENVÍOS TRADICIONALES DE FORMULARIO
    document.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    }, true);

    // 2. CAMBIO DE PESTAÑAS
    function cambiarReporte(tipo, btn) {
        document.querySelectorAll('.nav-report-btn').forEach(b => {
            b.classList.remove('btn-dark');
            b.classList.add('btn-primary');
        });
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-dark');

        document.getElementById('tipoReporteActual').value = tipo;
        document.getElementById('tituloReporteVista').innerText = 'Reporte ' + tipo.toUpperCase();

        const contTarget = document.getElementById('contenedorTarget');
        const selectU = document.getElementById('selectUsuario');
        const contC = document.getElementById('contenedorClienteSelect');

        if (tipo === 'usuario') {
            contTarget.style.display = 'block';
            selectU.style.display = 'block';
            contC.style.display = 'none';
            document.getElementById('lblTarget').innerText = 'Seleccionar Usuario';
        } else if (tipo === 'cliente') {
            contTarget.style.display = 'block';
            selectU.style.display = 'none';
            contC.style.display = 'block';
            document.getElementById('lblTarget').innerText = 'Buscar Cliente';
        } else {
            contTarget.style.display = 'none';
        }

        // Destruir tabla previa limpia al cambiar de sección
        limpiarTabla();
    }

    function limpiarTabla() {
        if ($.fn.DataTable.isDataTable('#tablaDataTables')) {
            $('#tablaDataTables').DataTable().clear().destroy();
        }
        document.getElementById('theadReporte').innerHTML = '';
        document.getElementById('tbodyReporte').innerHTML = '';
        document.getElementById('lblTotalSumado').innerText = '0.00';
    }

    // 3. CONSULTA VÍA FETCH CON MANEJO DE ERRORES Y ESTADO DE CARGA
    function ejecutarConsulta() {
        const btnBuscar = document.querySelector('button[onclick="ejecutarConsulta()"]');
        const textoOriginal = btnBuscar ? btnBuscar.innerHTML : '';

        // Feedback visual en el botón durante la carga
        if (btnBuscar) {
            btnBuscar.disabled = true;
            btnBuscar.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Cargando...`;
        }

        const tipo = document.getElementById('tipoReporteActual').value;
        const f1 = document.getElementById('fecha1').value;
        const f2 = document.getElementById('fecha2').value;
        let targetId = '';

        if (tipo === 'usuario') targetId = document.getElementById('selectUsuario').value;
        if (tipo === 'cliente') targetId = document.getElementById('selectClienteId').value;

        const url = `{{ route('reportes.datos') }}?tipo=${tipo}&fecha1=${f1}&fecha2=${f2}&target_id=${targetId}`;

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error en el servidor (${response.status})`);
                }
                return response.json();
            })
            .then(res => {
                const data = res.data || [];
                document.getElementById('lblTotalSumado').innerText = res.total || '0.00';
                
                // Construcción de UI
                renderizarTablaConPaginacion(tipo, data);
                renderizarGrafica(data);
            })
            .catch(err => {
                console.error('Error al consultar reporte:', err);
                alert('Ocurrió un error al cargar la información del reporte. Verifique la consola (F12) o intente de nuevo.');
            })
            .finally(() => {
                // Restaurar botón al terminar
                if (btnBuscar) {
                    btnBuscar.disabled = false;
                    btnBuscar.innerHTML = textoOriginal;
                }
            });
    }

    // 4. RENDERIZADO SEGURO DE DATATABLES
    function renderizarTablaConPaginacion(tipo, data) {
        // Destruir instancia previa de forma segura
        if ($.fn.DataTable.isDataTable('#tablaDataTables')) {
            $('#tablaDataTables').DataTable().clear().destroy();
        }

        const thead = document.getElementById('theadReporte');
        const tbody = document.getElementById('tbodyReporte');
        
        thead.innerHTML = '';
        tbody.innerHTML = '';

        if (tipo === 'usuario' || tipo === 'cliente' || tipo === 'ventas') {
            thead.innerHTML = `
                <tr class="text-uppercase small text-muted border-bottom">
                    <th>N° Venta</th><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Vendedor</th><th>N° Pagos</th><th>Venta</th><th>T. Pago</th><th>Restante</th><th class="text-end">Total</th>
                </tr>`;
            
            data.forEach(item => {
                const f = item.created_at || '';
                tbody.innerHTML += `
                    <tr>
                        <td>#${item.folio || item.venta_id_legacy || item.id || ''}</td>
                        <td>${f ? f.substring(0,10) : ''}</td>
                        <td>${f ? f.substring(11,16) : ''}</td>
                        <td class="fw-bold text-uppercase">${item.cliente_nombre || 'N/A'}</td>
                        <td>${item.vendedor_nombre || 'N/A'}</td>
                        <td>-</td>
                        <td>$${parseFloat(item.total || 0).toFixed(2)}</td>
                        <td>Efectivo</td>
                        <td class="text-danger">$${parseFloat(item.saldo_restante || 0).toFixed(2)}</td>
                        <td class="text-end fw-bold text-success">$${parseFloat(item.total || 0).toFixed(2)}</td>
                    </tr>`;
            });
        } else if (tipo === 'gastos') {
            thead.innerHTML = `
                <tr class="text-uppercase small text-muted border-bottom">
                    <th>N° Gasto</th><th>Fecha</th><th>Descripción</th><th class="text-end">Total</th>
                </tr>`;
            
            data.forEach(item => {
                tbody.innerHTML += `
                    <tr>
                        <td>#${item.id || ''}</td>
                        <td>${item.fecha || ''}</td>
                        <td>${item.descripcion || ''}</td>
                        <td class="text-end fw-bold text-danger">$${parseFloat(item.monto || 0).toFixed(2)}</td>
                    </tr>`;
            });
        } else if (tipo === 'abonos') {
            thead.innerHTML = `
                <tr class="text-uppercase small text-muted border-bottom">
                    <th>N° Venta</th><th>Fecha</th><th>Cliente</th><th>N° Pago</th><th>Tipo Pago</th><th>Cobrador</th><th class="text-end">Total</th>
                </tr>`;
            
            data.forEach(item => {
                tbody.innerHTML += `
                    <tr>
                        <td>#${item.a_venta || ''}</td>
                        <td>${item.a_fecha || ''}</td>
                        <td class="fw-bold">${item.cliente_nombre || 'N/A'}</td>
                        <td>#${item.a_npago || ''}</td>
                        <td>${item.a_tipopago || ''}</td>
                        <td>${item.nombre_cobrador || ''}</td>
                        <td class="text-end fw-bold text-success">$${parseFloat(item.a_pago || 0).toFixed(2)}</td>
                    </tr>`;
            });
        }

        // Si existen datos, se inicializa DataTables
        if (data.length > 0) {
            $('#tablaDataTables').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                pageLength: 10,
                responsive: true,
                order: []
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">No se encontraron registros en el rango de fechas seleccionado.</td></tr>`;
        }
    }

    // 5. RENDERIZADO DE LA GRÁFICA SEGURO
    function renderizarGrafica(data) {
        const ctx = document.getElementById('chartReportes');
        if (!ctx) return;

        if (myChart) {
            myChart.destroy();
            myChart = null;
        }

        if (!data || data.length === 0) return;

        const sliced = data.slice(0, 15);
        const labels = sliced.map(i => i.created_at ? i.created_at.substring(0,10) : (i.fecha || i.a_fecha || 'Reg'));
        const valores = sliced.map(i => parseFloat(i.total || i.monto || i.a_pago || 0));

        myChart = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Monto ($)',
                    data: valores,
                    backgroundColor: 'rgba(13, 110, 253, 0.65)',
                    borderColor: '#0d6efd',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
</script>
@endsection