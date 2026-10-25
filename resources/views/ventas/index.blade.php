@extends('layouts.app')

@section('title', 'Consulta de Ventas - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0 px-md-2">
    
    <!-- ENCABEZADO Y BARRA DE BOTONES SUPERIORES RESPONSIVA -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 p-md-4 mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
            <div>
                <h4 class="fw-bold text-dark m-0"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Consulta General de Ventas</h4>
                <p class="text-muted small m-0">Filtra y revisa el estado financiero de ventas de contado y crédito</p>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 pt-2 border-top">
            <a href="{{ route('ventas.index', ['filtro' => 'Abierta']) }}" class="btn {{ ($filtro ?? 'Abierta') === 'Abierta' ? 'btn-primary' : 'btn-outline-primary' }} fw-bold px-3 py-2 rounded-3 text-uppercase small flex-grow-1 flex-md-grow-0 text-center">
                V. Abiertas
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'Pendiente']) }}" class="btn {{ ($filtro ?? '') === 'Pendiente' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' }} fw-bold px-3 py-2 rounded-3 text-uppercase small flex-grow-1 flex-md-grow-0 text-center">
                V. Pendientes
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'TODAS']) }}" class="btn {{ ($filtro ?? '') === 'TODAS' ? 'btn-secondary' : 'btn-outline-secondary' }} fw-bold px-3 py-2 rounded-3 text-uppercase small flex-grow-1 flex-md-grow-0 text-center">
                Todas
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'Pagado']) }}" class="btn {{ ($filtro ?? '') === 'Pagado' ? 'btn-success' : 'btn-outline-success' }} fw-bold px-3 py-2 rounded-3 text-uppercase small flex-grow-1 flex-md-grow-0 text-center">
                V. Pagadas
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'V_CREDITO']) }}" class="btn {{ ($filtro ?? '') === 'V_CREDITO' ? 'btn-info text-white' : 'btn-outline-info' }} fw-bold px-3 py-2 rounded-3 text-uppercase small flex-grow-1 flex-md-grow-0 text-center">
                V. Crédito
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'Eliminar']) }}" class="btn {{ ($filtro ?? '') === 'Eliminar' ? 'btn-danger' : 'btn-outline-danger' }} fw-bold px-3 py-2 rounded-3 text-uppercase small flex-grow-1 flex-md-grow-0 text-center">
                V. Eliminadas
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'LISTA_NEGRA']) }}" class="btn {{ ($filtro ?? '') === 'LISTA_NEGRA' ? 'btn-dark' : 'btn-outline-dark' }} fw-bold px-3 py-2 rounded-3 text-uppercase small flex-grow-1 flex-md-grow-0 text-center">
                Lista Negra
            </a>
        </div>
    </div>

    <!-- BUSCADOR -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
        <form action="{{ route('ventas.index') }}" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="filtro" value="{{ $filtro }}">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar por Folio o Nombre del Cliente..." value="{{ $busqueda }}">
                </div>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary rounded-3 fw-semibold">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- TABLA DE VENTAS -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th class="text-center">Tipo Venta</th>
                        <th class="text-end">Total Venta</th>
                        <th class="text-center">Estatus</th>

                        @if(($filtro ?? 'Abierta') === 'Abierta')
                            @if(auth()->user()->hasRole('administrador'))
                                <th class="text-center">Continuar</th>
                                <th class="text-center">Pasar a Pendiente</th>
                                <th class="text-center pe-4">Descartar</th>
                            @endif
                        @else
                            <!-- COLUMNAS IMAGEN 1 -->
                            <th class="text-center">Detalles</th>
                            <th class="text-center pe-4">Historial</th>
                            @if(auth()->user()->hasRole('administrador'))
                                <th class="text-center pe-4">Cambiar</th>
                            @endif
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($ventas as $v)
                        <tr>
                            <td class="ps-4">
                                <span class="d-block text-dark fw-semibold">{{ \Carbon\Carbon::parse($v->fecha_venta)->format('d / m / Y') }}</span>
                                <small class="text-muted">Folio #{{ $v->folio }}</small>
                            </td>
                            <td>
                                <strong class="text-dark d-block">{{ $v->cliente->nombre ?? 'Sin Cliente Asignado' }}</strong>
                            </td>
                            <td class="text-muted small">
                                {{ strtoupper($v->vendedor->username ?? 'SIN VENDEDOR') }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $v->tipo_venta === 'Credito' ? 'bg-info' : 'bg-cyan' }} text-white px-3 py-1 rounded-pill fw-bold" style="{{ $v->tipo_venta === 'Contado' ? 'background-color: #00d3f8;' : '' }}">
                                    {{ $v->tipo_venta }}
                                </span>
                            </td>
                            <td class="text-end fw-bold text-success fs-6">
                                ${{ number_format($v->total, 2) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-secondary border px-3 py-1 rounded-pill">
                                    {{ $v->status }}
                                </span>
                            </td>

                            @if(($filtro ?? 'Abierta') === 'Abierta')
                                @if(auth()->user()->hasRole('administrador'))
                                    <td class="text-center">
                                        <a href="{{ route('ventas.continuar', $v->id) }}" class="btn btn-sm btn-outline-primary rounded-3 px-3 fw-semibold shadow-sm">
                                            <i class="bi bi-pencil-square me-1"></i> Abrir
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <form action="{{ route('ventas.pendiente', $v->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-success rounded-3 px-3 fw-semibold shadow-sm">
                                                <i class="bi bi-check-circle me-1"></i> Pasar a Pendiente
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-center pe-4">
                                        <form action="{{ route('ventas.descartar', $v->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Descartar esta venta?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-2 shadow-sm">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            @else
                                <!-- ACCIONES IMAGEN 1: DETALLES, ABONAR, HISTORIAL -->
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-3 fw-semibold" onclick="verDetallesVenta('{{ $v->id }}')">
                                        <i class="bi bi-eye me-1"></i> Ver
                                    </button>
                                </td>
                                <td class="text-center pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-3 px-3 fw-semibold text-dark" onclick="verHistorialPagos('{{ $v->id }}')">
                                        <i class="bi bi-clock-history me-1"></i> Historial
                                    </button>
                                </td>
                                @if(auth()->user()->hasRole('administrador'))
                                    <td class="text-center pe-4">
                                        <button type="button" 
                                                class="btn btn-sm btn-primary rounded-3 px-3 fw-semibold shadow-sm" 
                                                onclick="abrirModalCambioEstatus('{{ $v->id }}', '{{$v->folio }}', '{{ $v->status }}', '{{ addslashes($v->cliente->nombre ?? 'Sin Cliente') }}')">
                                            <i class="bi bi-gear-fill me-1"></i> Cambiar
                                        </button>
                                    </td>
                                @endif
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                No hay ventas que coincidan con este estatus.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table> <!-- CIERRE CORRECTO DE LA TABLA -->
        </div> <!-- CIERRE CORRECTO DEL TABLE-RESPONSIVE -->

        <!-- FOOTER DE LA TARJETA CON LA PAGINACIÓN ABAJO -->
        <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <small class="text-muted fw-semibold">
                    Mostrando <strong>{{ $ventas->firstItem() ?? 0 }}</strong> al <strong>{{ $ventas->lastItem() ?? 0 }}</strong> de <strong>{{ $ventas->total() }}</strong> ventas
                </small>
            </div>

            <div>
                <!-- Renderizado explícito de los enlaces con Bootstrap 5 -->
                {{ $ventas->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <!-- MODAL DESGLOSE TÉCNICO (IMAGEN 2 y 3) -->
    <div class="modal fade" id="modalDesgloseTecnico" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-box-seam text-primary me-2 fs-4"></i> Desglose Técnico de Venta #<span id="lbl_modal_folio"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>N° Venta</th>
                                    <th>Cantidad</th>
                                    <th>Artículo</th>
                                    <th class="text-center">N° Serie / Acción</th>
                                    <th class="text-end">Precio U.</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Boletas / PDF</th>
                                </tr>
                            </thead>
                            <tbody id="tbl_desglose_body">
                                <!-- Inyección dinámica de filas vía JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4 fw-bold" data-bs-dismiss="modal">Cerrar Ventana</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL HISTORIAL DE PAGOS / ABONOS -->
    <div class="modal fade" id="modalHistorialPagos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-clock-history text-info me-2 fs-4"></i> Historial de Abonos - Venta #<span id="lbl_historial_folio"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <!-- TARJETA RESUMEN DEL ESTADO DE CUENTA -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block">CLIENTE</small>
                                <strong class="text-dark d-block text-truncate" id="lbl_historial_cliente">---</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block">TOTAL VENTA</small>
                                <strong class="text-primary fs-5" id="lbl_historial_total">$0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-danger bg-opacity-10 rounded-3 border border-danger">
                                <small class="text-danger fw-bold d-block">SALDO RESTANTE</small>
                                <strong class="text-danger fs-5" id="lbl_historial_saldo">$0.00</strong>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA DE ABONOS -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-3">N° Pago</th>
                                    <th>Fecha / Hora</th>
                                    <th>Concepto / Referencia</th>
                                    <th class="text-end pe-3">Monto Abonado</th>
                                </tr>
                            </thead>
                            <tbody id="tbl_historial_body">
                                <!-- Filas dinámicas generadas vía JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4 fw-bold" data-bs-dismiss="modal">Cerrar Ventana</button>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS POR FECHA Y BÚSQUEDA INTEGRADOS (Remplaza la tarjeta de búsqueda anterior) -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
        <form action="{{ route('ventas.index') }}" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="filtro" value="{{ $filtro }}">
            
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar por Folio o Cliente..." value="{{ $busqueda }}">
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted small">Desde</span>
                    <input type="date" name="fecha_inicio" class="form-control bg-light" value="{{ request('fecha_inicio') }}">
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted small">Hasta</span>
                    <input type="date" name="fecha_fin" class="form-control bg-light" value="{{ request('fecha_fin') }}">
                </div>
            </div>

            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary rounded-3 fw-semibold"><i class="bi bi-filter me-1"></i> Filtrar</button>
            </div>
        </form>
    </div>

    <!-- MODAL GESTIÓN DE ESTATUS (ESTILO IMAGEN CON ALERTAS PREVIAS) -->
    <div class="modal fade" id="modalCambiarEstatus" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-sliders text-primary me-2 fs-4"></i> Gestión de Estatus para Venta <span class="badge bg-primary ms-2" id="lbl_estatus_folio">#0000</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <small class="text-muted fw-bold d-block">CLIENTE:</small>
                        <strong class="text-dark fs-6" id="lbl_estatus_cliente">---</strong>
                    </div>

                    <div class="row g-4">
                        <!-- COLUMNA IZQUIERDA: GLOSARIO EXPLICATIVO -->
                        <div class="col-md-5 border-end pe-md-4">
                            <h6 class="fw-bold text-muted small text-uppercase mb-3">
                                <i class="bi bi-info-circle me-1"></i> Glosario de Acciones
                            </h6>
                            <ul class="list-unstyled small lh-base text-secondary m-0">
                                <li class="mb-3">
                                    <strong class="text-success d-block">Pagado:</strong> Se cancela la deuda por completo.
                                </li>
                                <li class="mb-3">
                                    <strong class="text-primary d-block">Devolución:</strong> Se regresan los artículos al inventario.
                                </li>
                                <li class="mb-3">
                                    <strong class="text-warning text-dark d-block">Recogido:</strong> Regresa producto a almacén y cliente a lista negra.
                                </li>
                                <li class="mb-3">
                                    <strong class="text-danger d-block">Fuga / Problema:</strong> La cuenta se suspende y el cliente va a lista negra.
                                </li>
                                <li class="mb-0">
                                    <strong class="text-dark d-block">Eliminar:</strong> Borra la venta físicamente y retorna stock.
                                </li>
                            </ul>
                        </div>

                        <!-- COLUMNA DERECHA: BOTONERA DE ACCIÓN DIRECTA CON ALERTA -->
                        <div class="col-md-7 ps-md-4">
                            <h6 class="fw-bold text-muted small text-uppercase mb-3">
                                <i class="bi bi-lightning me-1"></i> Seleccionar Nuevo Estado
                            </h6>

                            <div class="d-grid gap-2" id="contenedor_botones_estatus">
                                <button type="button" class="btn btn-outline-success text-start py-2 px-3 rounded-3 fw-semibold" onclick="confirmarCambioEstatus('Pagado', 'Marcar como Pagado', 'Se saldará la deuda por completo marcando el saldo restante en $0.00.')">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i> Marcar como Pagado
                                </button>

                                <button type="button" class="btn btn-outline-primary text-start py-2 px-3 rounded-3 fw-semibold" onclick="confirmarCambioEstatus('Devolucion', 'Aplicar Devolución', 'Se cancelará el cobro y los artículos serán retornados al inventario activo.')">
                                    <i class="bi bi-arrow-counterclockwise text-primary me-2"></i> Aplicar Devolución
                                </button>

                                <button type="button" class="btn btn-outline-warning text-dark text-start py-2 px-3 rounded-3 fw-semibold" onclick="confirmarCambioEstatus('Recogido', 'Registrar Recogido', 'Se retornarán los artículos al almacén y el cliente cambiará a estatus Recogido (Lista Negra).')">
                                    <i class="bi bi-box-arrow-in-left text-warning me-2"></i> Registrar Recogido
                                </button>

                                <button type="button" class="btn btn-outline-danger text-start py-2 px-3 rounded-3 fw-semibold" onclick="confirmarCambioEstatus('Fuga', 'Reportar Fuga', 'La cuenta quedará suspendida y el cliente será enviado inmediatamente a la Lista Negra.')">
                                    <i class="bi bi-person-x-fill text-danger me-2"></i> Reportar Fuga
                                </button>

                                <button type="button" class="btn btn-outline-secondary text-start py-2 px-3 rounded-3 fw-semibold" onclick="confirmarCambioEstatus('Problema', 'Marcar con Problema', 'Se registrará la observación técnica en la venta para seguimiento directo.')">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Marcar con Problema
                                </button>

                                <button type="button" class="btn btn-danger text-start py-2 px-3 rounded-3 fw-bold" onclick="confirmarCambioEstatus('Eliminar', 'Eliminar Venta Permanente', '⚠️ ¡Atención! Esta acción cancelará las mercancías y desvinculará los folios asociados.')">
                                    <i class="bi bi-trash-fill me-2"></i> Eliminar Venta Permanente
                                </button>

                                <button type="button" class="btn btn-outline-info text-dark text-start py-2 px-3 rounded-3 fw-semibold" onclick="confirmarCambioEstatus('Pendiente', 'Reactivar Venta (Regresar a Pendiente)', 'Se volverá a activar el cobro y la cuenta regresará a estado Pendiente.')">
                                    <i class="bi bi-arrow-repeat text-info me-2"></i> Reactivar Venta (Regresar a Pendiente)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULARIO OCULTO PARA PROCESAR EL ENVÍO POR POST -->
    <form id="form_procesar_estatus" method="POST" action="" class="d-none">
        @csrf
        <input type="hidden" name="nuevo_estatus" id="hidden_nuevo_estatus">
    </form>

    <!-- JAVASCRIPT DE MANEJO DE DETALLES Y CAMBIO DE SERIE -->
    <script>
        // Variable global para mantener la venta activa en modal
        let ventaSeleccionadaActual = null;

        // Cargar detalles de artículos en el modal Desglose Técnico
        function verDetallesVenta(ventaId) {
            ventaSeleccionadaActual = ventaId;
            
            fetch(`{{ url('ventas') }}/${ventaId}/detalles-ajax`)
                .then(res => {
                    if (!res.ok) throw new Error('Error al consultar detalles');
                    return res.json();
                })
                .then(data => {
                    document.getElementById('lbl_modal_folio').innerText = data.folio;
                    const tbody = document.getElementById('tbl_desglose_body');
                    tbody.innerHTML = '';

                    data.detalles.forEach(d => {
                        const nombreArt = d.nombre_articulo || (d.articulo ? d.articulo.nombre : 'Artículo');
                        const nSerie = d.numero_serie || 'S/N';
                        const subtotal = (parseFloat(d.precio_unitario) - parseFloat(d.descuento || 0)) * parseInt(d.cantidad);

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="fw-bold text-muted">#${data.folio}</td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1">${d.cantidad} pza</span></td>
                            <td class="fw-bold text-dark">${nombreArt}</td>
                            <td class="text-center" id="td_serie_${d.id}">
                                <span class="badge bg-light text-dark border px-2 py-1 me-1">${nSerie}</span>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-0" onclick="activarEdicionSerie('${d.id}', '${nSerie}')">
                                    <i class="bi bi-pencil-square"></i> Cambiar
                                </button>
                            </td>
                            <td class="text-end">$${parseFloat(d.precio_unitario).toFixed(2)}</td>
                            <td class="text-end fw-bold">$${subtotal.toFixed(2)}</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm border rounded-3 overflow-hidden" role="group">
                                    <a href="{{ url('ventas') }}/${ventaId}/pdf-boleta-pc/${d.id}" target="_blank" class="btn btn-light border-end">
                                        <i class="bi bi-display text-primary"></i> PC
                                    </a>
                                    <a href="{{ url('ventas') }}/${ventaId}/pdf-boleta-movil/${d.id}" target="_blank" class="btn btn-light">
                                        <i class="bi bi-phone text-success"></i> Móvil
                                    </a>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    const modal = new bootstrap.Modal(document.getElementById('modalDesgloseTecnico'));
                    modal.show();
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('❌ No se pudieron cargar los detalles de esta venta.');
                });
        }

        // Cargar el historial de abonos vía AJAX
        function verHistorialPagos(ventaId) {
            const tbody = document.getElementById('tbl_historial_body');
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> Cargando historial...</td></tr>`;

            fetch(`{{ url('ventas') }}/${ventaId}/historial-ajax`)
                .then(res => {
                    if (!res.ok) throw new Error('Error en el servidor al traer abonos');
                    return res.json();
                })
                .then(data => {
                    document.getElementById('lbl_historial_folio').innerText = data.folio;
                    document.getElementById('lbl_historial_cliente').innerText = data.cliente;
                    document.getElementById('lbl_historial_total').innerText = `$${parseFloat(data.total || 0).toFixed(2)}`;
                    document.getElementById('lbl_historial_saldo').innerText = `$${parseFloat(data.saldo_restante || 0).toFixed(2)}`;

                    tbody.innerHTML = '';

                    if (!data.abonos || data.abonos.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-inbox me-1"></i> No se han registrado abonos o cobros para esta venta.</td></tr>`;
                    } else {
                        data.abonos.forEach((a, index) => {
                            const npago = a.a_npago !== undefined ? a.a_npago : (index + 1);
                            const monto = a.a_pago !== undefined ? a.a_pago : (a.monto || a.pago || 0);
                            const fecha = a.created_at ? new Date(a.created_at).toLocaleDateString('es-MX') : (a.a_fecha || '---');

                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="ps-3"><span class="badge bg-secondary bg-opacity-10 text-dark border px-2 py-1">Pago #${npago}</span></td>
                                <td><small class="text-muted fw-semibold">${fecha}</small></td>
                                <td><span class="small text-dark">${a.observaciones || a.concepto || 'Abono realizado'}</span></td>
                                <td class="text-end pe-3 fw-bold text-success">+$${parseFloat(monto).toFixed(2)}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }

                    const modal = new bootstrap.Modal(document.getElementById('modalHistorialPagos'));
                    modal.show();
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('❌ No se pudo cargar el historial de pagos de esta venta.');
                });
        }

        // Transformar el badge en input para editar N° Serie
        function activarEdicionSerie(detalleId, serieActual) {
            const td = document.getElementById(`td_serie_${detalleId}`);
            td.innerHTML = `
                <div class="input-group input-group-sm">
                    <input type="text" id="input_serie_${detalleId}" class="form-control" placeholder="Escriba N/S..." value="${serieActual === 'S/N' ? '' : serieActual}">
                    <button type="button" class="btn btn-primary" onclick="guardarNuevoSerie('${detalleId}')">
                        <i class="bi bi-check-lg"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="verDetallesVenta(ventaSeleccionadaActual)">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `;
        }

        // Guardar número de serie por AJAX
        function guardarNuevoSerie(detalleId) {
            const nuevoSerie = document.getElementById(`input_serie_${detalleId}`).value;
            
            // Usamos url() de Laravel o ruta limpia en JavaScript
            const url = `{{ url('ventas/detalles') }}/${detalleId}/update-serie`;

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ numero_serie: nuevoSerie })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const td = document.getElementById(`td_serie_${detalleId}`);
                    td.innerHTML = `
                        <span class="badge bg-light text-dark border px-2 py-1 me-1">${data.numero_serie}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-0" onclick="activarEdicionSerie('${detalleId}', '${data.numero_serie}')">
                            <i class="bi bi-pencil-square"></i> Cambiar
                        </button>
                    `;
                } else {
                    alert('❌ No se pudo actualizar el número de serie.');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('❌ Ocurrió un error al intentar guardar el número de serie.');
            });
        }

        function abrirMenuEstatus() {
            alert('📌 El formulario flotante de Cambio de Estatus por período se integrará en el siguiente paso.');
        }

        // Cargar el historial de abonos vía AJAX y desplegar el Modal
        function verHistorialPagos(ventaId) {
            const tbody = document.getElementById('tbl_historial_body');
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> Cargando historial...</td></tr>`;

            fetch(`{{ url('ventas') }}/${ventaId}/historial-ajax`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('lbl_historial_folio').innerText = data.folio;
                    document.getElementById('lbl_historial_cliente').innerText = data.cliente;
                    document.getElementById('lbl_historial_total').innerText = `$${parseFloat(data.total).toFixed(2)}`;
                    document.getElementById('lbl_historial_saldo').innerText = `$${parseFloat(data.saldo_restante).toFixed(2)}`;

                    tbody.innerHTML = '';

                    if (!data.abonos || data.abonos.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-inbox me-1"></i> No se han registrado abonos o cobros para esta venta.</td></tr>`;
                    } else {
                        data.abonos.forEach((a, index) => {
                            const npago = a.a_npago !== undefined ? a.a_npago : (index + 1);
                            const monto = a.a_pago !== undefined ? a.a_pago : (a.monto || a.pago || 0);
                            const fecha = a.created_at ? new Date(a.created_at).toLocaleDateString('es-MX') : (a.a_fecha || '---');

                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="ps-3"><span class="badge bg-secondary bg-opacity-10 text-dark border px-2 py-1">Pago #${npago}</span></td>
                                <td><small class="text-muted fw-semibold">${fecha}</small></td>
                                <td><span class="small text-dark">${a.observaciones || a.concepto || 'Abono realizado'}</span></td>
                                <td class="text-end pe-3 fw-bold text-success">+$${parseFloat(monto).toFixed(2)}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }

                    const modal = new bootstrap.Modal(document.getElementById('modalHistorialPagos'));
                    modal.show();
                })
                .catch(err => {
                    console.error(err);
                    alert('❌ No se pudo cargar el historial de pagos de esta venta.');
                });
        }

        function abrirModalCambioEstatus(ventaId, folioActual, estatusActual, clienteNombre) {
            idVentaEstatusActiva = ventaId;
            folioVentaEstatusActiva = folioActual;

            document.getElementById('lbl_estatus_folio').innerText = '#' + folioActual;
            document.getElementById('lbl_estatus_cliente').innerText = clienteNombre || 'Sin Cliente Asignado';

            const modal = new bootstrap.Modal(document.getElementById('modalCambiarEstatus'));
            modal.show();
        }

        // Función de Confirmación de Estatus con SweetAlert2
        window.confirmarCambioEstatus = function(estatusClave, tituloAccion, explicacion) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: `¿${tituloAccion}?`,
                    text: explicacion,
                    icon: (estatusClave === 'Eliminar' || estatusClave === 'Fuga') ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonColor: estatusClave === 'Eliminar' ? '#d33' : '#3085d6',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, aplicar cambio',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.ejecutarEnvioEstatus(estatusClave);
                    }
                });
            } else {
                // Fallback nativo por si no ha cargado la librería SweetAlert2
                if (confirm(`¿${tituloAccion}?\n\n${explicacion}`)) {
                    window.ejecutarEnvioEstatus(estatusClave);
                }
            }
        };

        // Función global para enviar el formulario de cambio de estatus a Laravel
        window.ejecutarEnvioEstatus = function(nuevoEstatus) {
            if (typeof mostrarCargando === 'function') {
                mostrarCargando('Actualizando estatus...', 'Por favor espera mientras actualizamos la venta.');
            }

            const form = document.getElementById('form_procesar_estatus');
            const hiddenInput = document.getElementById('hidden_nuevo_estatus');

            if (form && hiddenInput && window.idVentaEstatusActiva) {
                form.action = `{{ url('ventas') }}/${window.idVentaEstatusActiva}/cambiar-estatus`;
                hiddenInput.value = nuevoEstatus;
                form.submit();
            } else {
                alert('❌ Error al identificar la venta activa.');
                if (typeof ocultarCargando === 'function') ocultarCargando();
            }
        };
    </script>

</div>

@endsection