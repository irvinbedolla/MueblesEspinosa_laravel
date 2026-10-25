@extends('layouts.app')

@section('title', 'Asignación de Rutas - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0 px-md-2">
    
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- ENCABEZADO Y BOTÓN DE NUEVA RUTA -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 p-md-4 mb-3">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h4 class="fw-bold text-dark m-0"><i class="bi bi-map text-primary me-2"></i>Gestión y Asignación de Rutas</h4>
                <p class="text-muted small m-0">Organiza y da seguimiento a las visitas de cobranza en campo</p>
            </div>
            
            <div class="d-flex gap-2">
                <!-- BOTÓN DE ACTUALIZACIÓN DE VISTA ADMINISTRADOR -->
                <button type="button" class="btn btn-outline-secondary rounded-3 fw-bold px-3 py-2 shadow-sm" onclick="window.location.reload();">
                    <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                </button>

                @if(auth()->user()->isAdmin())
                    <button type="button" class="btn btn-primary rounded-3 fw-bold px-3 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaRuta">
                        <i class="bi bi-plus-circle me-1"></i> Crear Nueva Ruta
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- FILTROS DE BÚSQUEDA -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 mb-4">
        <form action="{{ route('rutas.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <input type="text" name="buscar" class="form-control border-2" placeholder="Buscar por nombre de ruta..." value="{{ request('buscar') }}">
            </div>
            <div class="col-6 col-md-3">
                <select name="cobrador_id" class="form-select border-2">
                    <option value="">Todos los Cobradores</option>
                    @foreach($cobradores as $c)
                        <option value="{{ $c->id }}" {{ request('cobrador_id') == $c->id ? 'selected' : '' }}>
                            {{ strtoupper($c->username) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="fecha" class="form-control border-2" value="{{ request('fecha') }}">
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select border-2">
                    <option value="">Todos los Estatus</option>
                    <option value="Pendiente" {{ request('status') == 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="Completada" {{ request('status') == 'Completada' ? 'selected' : '' }}>Completada</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold"><i class="bi bi-filter me-1"></i> Filtrar</button>
                <a href="{{ route('rutas.index') }}" class="btn btn-light border rounded-3" title="Limpiar Filtros"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>
    </div>

    <!-- TABLA DE RUTAS REGISTRADAS -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold m-0 text-dark"><i class="bi bi-journal-text me-2"></i>Rutas Programadas</h6>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Ruta / Identificador</th>
                        <th>Cobrador Asignado</th>
                        <th class="text-center">Fecha Programada</th>
                        <th class="text-center">Total Clientes</th>
                        <th class="text-center">Estatus</th>
                        <th class="text-center pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rutas as $r)
                        <tr>
                            <td class="ps-4">
                                <strong class="text-dark d-block">{{ $r->nombre }}</strong>
                                <small class="text-muted">ID: {{ substr($r->id, 0, 8) }}...</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-3 py-1">
                                    <i class="bi bi-person-badge me-1"></i> {{ strtoupper($r->cobrador->username ?? 'SIN ASIGNAR') }}
                                </span>
                            </td>
                            <td class="text-center">
                                <small class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($r->fecha_ruta)->format('d / m / Y') }}</small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary rounded-pill px-3 py-1">{{ count($r->detalles) }} Visitas</span>
                            </td>
                            <td class="text-center">
                                @if($r->status === 'Completada')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-1 rounded-pill fw-bold">
                                        🟢 COMPLETADA
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark border border-warning px-3 py-1 rounded-pill fw-bold">
                                        🟠 PENDIENTE
                                    </span>
                                @endif
                            </td>
                            <td class="text-center pe-4">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-start-3 px-2 fw-semibold" onclick="verDetallesRuta('{{ $r->id }}')">
                                        <i class="bi bi-eye me-1"></i> Ver
                                    </button>
                                    @if(auth()->user()->isAdmin())
                                        <button type="button" class="btn btn-sm btn-outline-warning px-2" onclick="abrirModalEditar('{{ $r->id }}', '{{ $r->nombre }}', '{{ $r->cobrador_id }}', '{{ $r->fecha_ruta }}')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-end-3 px-2" onclick="confirmarEliminar('{{ $r->id }}', '{{ $r->nombre }}')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                No se encontraron rutas registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white border-top py-3 px-4">
            {{ $rutas->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<!-- MODAL CREAR RUTA CON DÍAS REDONDEADOS -->
<div class="modal fade" id="modalNuevaRuta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-map text-primary me-2 fs-4"></i> Crear y Asignar Ruta de Cobro
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="{{ route('rutas.asignar') }}" method="POST" id="form_crear_ruta">
                @csrf
                <div class="modal-body p-3 p-md-4">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small">Nombre de la Ruta:</label>
                            <input type="text" name="nombre" class="form-control border-2" placeholder="Ej. Ruta Centro / Sector Norte" required>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label fw-bold text-muted small">Cobrador Asignado:</label>
                            <select name="cobrador_id" class="form-select border-2" required>
                                <option value="">Seleccione...</option>
                                @foreach($cobradores as $c)
                                    <option value="{{ $c->id }}">{{ strtoupper($c->username) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label fw-bold text-muted small">Fecha de la Ruta:</label>
                            <input type="date" name="fecha_ruta" class="form-control border-2" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark m-0">Ventas por Cobrar (Ordenadas por Antigüedad):</h6>
                        <span class="badge bg-light text-muted border">De la más atrasada a la más nueva</span>
                    </div>

                    <div class="border rounded-3 p-2 bg-light overflow-auto" style="max-height: 280px;">
                        @forelse($ventasPendientes as $vp)
                            @php
                                // REDONDEO EXACTO DE DÍAS DE ATRASO
                                $diasAtraso = (int) floor(\Carbon\Carbon::parse($vp->created_at)->diffInDays(now()));
                            @endphp
                            <div class="form-check p-2 bg-white rounded-3 border mb-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="ventas[]" value="{{ $vp->id }}" id="chk_venta_{{ $vp->id }}">
                                    <label class="form-check-label fw-bold text-dark" for="chk_venta_{{ $vp->id }}">
                                        Folio #{{ $vp->folio }} - {{ $vp->cliente->nombre ?? 'Cliente N/A' }}
                                    </label>
                                    <small class="d-block text-muted ms-4">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $vp->cliente->calle ?? '' }}, {{ $vp->cliente->colonia ?? '' }}
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1 mb-1 d-inline-block">
                                        Saldo: ${{ number_format($vp->saldo_restante, 2) }}
                                    </span>
                                    <small class="d-block fw-bold {{ $diasAtraso > 30 ? 'text-danger' : 'text-warning' }}">
                                        <i class="bi bi-clock-history me-1"></i> {{ $diasAtraso }} {{ $diasAtraso == 1 ? 'día' : 'días' }} atrasado
                                    </small>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-3">
                                No hay ventas con saldo pendiente disponibles para asignar.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm">
                        <i class="bi bi-check-circle me-1"></i> Guardar y Asignar Ruta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR RUTA -->
<div class="modal fade" id="modalEditarRuta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-pencil-square text-warning me-2 fs-4"></i> Editar Datos de la Ruta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form_editar_ruta" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Nombre de la Ruta:</label>
                        <input type="text" id="edit_nombre" name="nombre" class="form-control border-2" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Cobrador Asignado:</label>
                        <select id="edit_cobrador_id" name="cobrador_id" class="form-select border-2" required>
                            <option value="">Seleccione...</option>
                            @foreach($cobradores as $c)
                                <option value="{{ $c->id }}">{{ strtoupper($c->username) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Fecha de la Ruta:</label>
                        <input type="date" id="edit_fecha_ruta" name="fecha_ruta" class="form-control border-2" required>
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark rounded-3 px-4 fw-bold shadow-sm">
                        <i class="bi bi-check-circle me-1"></i> Actualizar Ruta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORMULARIO OCULTO PARA ELIMINAR RUTA -->
<form id="form_eliminar_ruta" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- MODAL VER DETALLE DE VISITAS -->
<div class="modal fade" id="modalDetalleRuta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-journal-text text-primary me-2"></i> Visitas Programadas en la Ruta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th class="ps-3">Folio / Cliente</th>
                                <th>Dirección</th>
                                <th class="text-end">Sugerido</th>
                                <th class="text-end">Cobrado</th>
                                <th class="text-center">Estatus</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_detalle_ruta">
                            <!-- Carga dinámica JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const rutasColeccion = @json($rutas->items());

    function verDetallesRuta(rutaId) {
        const ruta = rutasColeccion.find(r => r.id === rutaId);
        const tbody = document.getElementById('tbody_detalle_ruta');
        tbody.innerHTML = '';

        if (!ruta || !ruta.detalles || ruta.detalles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay detalles registrados.</td></tr>';
        } else {
            ruta.detalles.forEach(d => {
                const v = d.venta || {};
                const c = v.cliente || {};
                
                let badgeStatus = '<span class="badge bg-secondary">Pendiente</span>';
                if (d.status === 'Cobrado') {
                    badgeStatus = '<span class="badge bg-success">Cobrado</span>';
                } else if (d.status === 'No_Encontrado') {
                    badgeStatus = '<span class="badge bg-warning text-dark">No Encontrado</span>';
                }

                tbody.innerHTML += `
                    <tr>
                        <td class="ps-3">
                            <strong class="d-block text-dark">Folio #${v.folio || 'N/A'}</strong>
                            <small class="text-muted">${c.nombre || 'Cliente N/A'}</small>
                        </td>
                        <td>
                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>${c.calle || ''}, ${c.colonia || ''}</small>
                        </td>
                        <td class="text-end fw-bold text-primary">$${parseFloat(d.monto_sugerido || 0).toFixed(2)}</td>
                        <td class="text-end fw-bold text-success">$${parseFloat(d.monto_cobrado || 0).toFixed(2)}</td>
                        <td class="text-center">${badgeStatus}</td>
                    </tr>
                `;
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalDetalleRuta'));
        modal.show();
    }

    function abrirModalEditar(id, nombre, cobradorId, fecha) {
        document.getElementById('form_editar_ruta').action = `/rutas/${id}`;
        document.getElementById('edit_nombre').value = nombre;
        document.getElementById('edit_cobrador_id').value = cobradorId;
        document.getElementById('edit_fecha_ruta').value = fecha;

        const modal = new bootstrap.Modal(document.getElementById('modalEditarRuta'));
        modal.show();
    }

    function confirmarEliminar(id, nombre) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Eliminar Ruta?',
                text: `¿Estás seguro de eliminar la ruta "${nombre}"? Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('form_eliminar_ruta');
                    form.action = `/rutas/${id}`;
                    form.submit();
                }
            });
        } else {
            if (confirm(`¿Estás seguro de eliminar la ruta "${nombre}"?`)) {
                const form = document.getElementById('form_eliminar_ruta');
                form.action = `/rutas/${id}`;
                form.submit();
            }
        }
    }
</script>
@endsection