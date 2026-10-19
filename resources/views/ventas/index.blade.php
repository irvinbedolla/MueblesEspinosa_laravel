@extends('layouts.app')

@section('title', 'Consulta de Ventas - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0">
    
    <!-- ENCABEZADO Y BARRA DE BOTONES SUPERIORES (ESTILO IMAGEN 2) -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 p-md-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h4 class="fw-bold text-dark m-0"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Consulta General de Ventas</h4>
                <p class="text-muted small m-0">Filtra y revisa el estado financiero de ventas de contado y crédito</p>
            </div>
        </div>

        <!-- BOTONERA SUPERIOR DE FILTRADO RÁPIDO (ESTILO IMAGEN 2) -->
        <div class="d-flex flex-wrap gap-2 pt-2 border-top">
            <a href="{{ route('ventas.index', ['filtro' => 'V_ABIERTAS']) }}" class="btn {{ $filtro === 'V_ABIERTAS' ? 'btn-primary' : 'btn-outline-primary' }} fw-bold px-3 py-2 rounded-3 text-uppercase small">
                V. Abiertas
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'V_PENDIENTES']) }}" class="btn {{ $filtro === 'V_PENDIENTES' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' }} fw-bold px-3 py-2 rounded-3 text-uppercase small">
                V. Pendientes
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'TODAS']) }}" class="btn {{ $filtro === 'TODAS' ? 'btn-secondary' : 'btn-outline-secondary' }} fw-bold px-3 py-2 rounded-3 text-uppercase small">
                Todas
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'V_PAGADAS']) }}" class="btn {{ $filtro === 'V_PAGADAS' ? 'btn-success' : 'btn-outline-success' }} fw-bold px-3 py-2 rounded-3 text-uppercase small">
                V. Pagadas
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'V_CREDITO']) }}" class="btn {{ $filtro === 'V_CREDITO' ? 'btn-info text-white' : 'btn-outline-info' }} fw-bold px-3 py-2 rounded-3 text-uppercase small">
                V. Crédito
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'V_ELIMINADAS']) }}" class="btn {{ $filtro === 'V_ELIMINADAS' ? 'btn-danger' : 'btn-outline-danger' }} fw-bold px-3 py-2 rounded-3 text-uppercase small">
                V. Eliminadas
            </a>
            <a href="{{ route('ventas.index', ['filtro' => 'LISTA_NEGRA']) }}" class="btn {{ $filtro === 'LISTA_NEGRA' ? 'btn-dark' : 'btn-outline-dark' }} fw-bold px-3 py-2 rounded-3 text-uppercase small">
                Lista Negra
            </a>
            <!-- BOTÓN CAMBIAR ESTATUS (PREPARADO PARA EL MENÚ DE LA IMAGEN 3) -->
            <button type="button" class="btn btn-primary fw-bold px-3 py-2 rounded-3 text-uppercase small ms-auto shadow-sm" onclick="abrirMenuEstatus()">
                <i class="bi bi-sliders me-1"></i> Cambiar Estatus
            </button>
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
                <thead class="table-light text-muted small">
                    <tr>
                        <th class="ps-4">Folio / Fecha</th>
                        <th>Cliente</th>
                        <th>Vendedor / Cobrador</th>
                        <th class="text-center">Tipo Venta</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Saldo Restante</th>
                        <th class="text-center">Estatus</th>
                        <th class="text-end pe-4">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ventas as $v)
                        <tr>
                            <td class="ps-4">
                                <strong class="text-dark d-block">Folio #{{ $v->folio ?? $v->venta_id_legacy }}</strong>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($v->fecha_venta)->format('d/m/Y') }}</small>
                            </td>
                            <td>
                                <strong class="text-dark d-block">{{ $v->cliente->nombre ?? 'Sin Nombre' }}</strong>
                                <small class="text-muted">{{ $v->cliente->ciudad ?? 'N/A' }}</small>
                            </td>
                            <td class="small">
                                <span class="d-block text-dark">👤 Vent: {{ $v->vendedor->username ?? 'S/A' }}</span>
                                <span class="text-muted">🚚 Cob: {{ $v->cobrador->username ?? 'S/A' }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $v->tipo_venta === 'Credito' ? 'bg-primary' : 'bg-success' }} bg-opacity-10 text-{{ $v->tipo_venta === 'Credito' ? 'primary' : 'success' }} px-3 py-1 rounded-pill fw-bold">
                                    {{ $v->tipo_venta }}
                                </span>
                            </td>
                            <td class="text-end fw-bold text-dark">${{ number_format($v->total, 2) }}</td>
                            <td class="text-end fw-bold text-danger">${{ number_format($v->saldo_restante, 2) }}</td>
                            <td class="text-center">
                                <span class="badge bg-secondary bg-opacity-10 text-dark px-3 py-1 rounded-pill fw-bold">
                                    {{ $v->status }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="{{ route('ventas.show', $v->id) }}" class="btn btn-sm btn-outline-primary rounded-circle" title="Ver Detalle / Ticket">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                No se encontraron ventas registradas bajo este filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($ventas->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center">
                <small class="text-muted">Mostrando {{ $ventas->firstItem() }} al {{ $ventas->lastItem() }} de {{ $ventas->total() }} ventas</small>
                <div>{{ $ventas->links() }}</div>
            </div>
        @endif
    </div>

</div>

<script>
function abrirMenuEstatus() {
    alert('📌 El formulario flotante de Cambio de Estatus por período se integrará en el siguiente paso.');
}
</script>
@endsection