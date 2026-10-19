@extends('layouts.app')

@section('title', 'Clientes - Muebles Espinosa')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark m-0"><i class="bi bi-people text-primary me-2"></i>Catálogo de Clientes</h3>
        <p class="text-muted small m-0">Administra los datos de contacto y ubicaciones geográficas</p>
    </div>
    <a href="{{ route('clientes.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
        <i class="bi bi-person-plus me-1"></i> Nuevo Cliente
    </a>
</div>

<!-- TARJETAS DE RESUMEN METRICO -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">TOTAL CLIENTES</small>
                    <h4 class="fw-bold text-dark m-0">{{ $resumen['total'] }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-success bg-opacity-10 text-success rounded-4">
                    <i class="bi bi-person-check-fill fs-4"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">ACTIVOS</small>
                    <h4 class="fw-bold text-success m-0">{{ $resumen['activos'] }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-4">
                    <i class="bi bi-person-x-fill fs-4"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">LISTA NEGRA</small>
                    <h4 class="fw-bold text-danger m-0">{{ $resumen['lista_negra'] }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-info bg-opacity-10 text-info rounded-4">
                    <i class="bi bi-geo-alt-fill fs-4"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">CON GPS RUTA</small>
                    <h4 class="fw-bold text-info m-0">{{ $resumen['con_gps'] }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <label class="form-label fw-semibold text-secondary small">Enlace Ubicación (Google Maps)</label>
        <div class="input-group">
            <input type="url" id="ubicacion_input" name="ubicacion" class="form-control bg-light rounded-start-3" value="{{ old('ubicacion', $cliente->ubicacion ?? '') }}" placeholder="https://maps.google.com/?q=...">
            <button type="button" class="btn btn-outline-info" onclick="obtenerUbicacionGPS()" title="Obtener mi posición GPS actual">
                <i class="bi bi-crosshair me-1"></i> Mi Ubicación
            </button>
        </div>
    </div>
</div>

<!-- BUSCADOR -->
<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
    <form action="{{ route('clientes.index') }}" method="GET" class="row g-2 align-items-center">
        <div class="col-md-10">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="buscar" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar por nombre, teléfono, colonia o ciudad..." value="{{ $busqueda }}">
            </div>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary rounded-3 fw-semibold">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
        </div>
    </form>
</div>

<!-- TABLA DE CLIENTES -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th class="ps-4">Cliente</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th>Ciudad</th>
                    <th class="text-center">Estatus</th>
                    <th class="text-center">Ubicación GPS</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $cliente)
                    <tr>
                        <td class="ps-4">
                            <strong class="text-dark d-block fs-6">{{ $cliente->nombre }}</strong>
                            <small class="text-muted">INE: {{ $cliente->ine ?? 'Sin registro' }}</small>
                        </td>
                        <td class="fw-medium text-dark">{{ $cliente->telefono ?? '-' }}</td>
                        <td class="small text-muted">
                            {{ $cliente->calle }} {{ $cliente->colonia ? 'Col. '.$cliente->colonia : '' }}
                        </td>
                        <td class="small text-muted">{{ $cliente->ciudad ?? '-' }}</td>
                        <td class="text-center">
                            @if($cliente->status === 'Activo')
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill">Activo</span>
                            @elseif($cliente->status === 'Lista_Negra')
                                <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-1 rounded-pill">Lista Negra</span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1 rounded-pill">Inactivo</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($cliente->ubicacion && $cliente->latitud)
                                <a href="{{ $cliente->ubicacion ?? 'https://maps.google.com/?q='.$cliente->latitud.','.$cliente->longitud }}" target="_blank" class="btn btn-sm btn-outline-info rounded-circle" title="Ver en Mapa">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </a>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <a href="{{ route('clientes.edit', $cliente->id) }}" class="btn btn-sm btn-outline-primary rounded-circle me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('clientes.destroy', $cliente->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este cliente?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No se encontraron registros de clientes.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- PAGINACIÓN CON ESTILO DE BOOTSTRAP -->
    @if($clientes->hasPages())
        <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <small class="text-muted">Mostrando {{ $clientes->firstItem() }} al {{ $clientes->lastItem() }} de {{$clientes->total() }} clientes</small>
            <div>
                {{ $clientes->links() }}
            </div>
        </div>
    @endif
</div>
@endsection

<script>
    function obtenerUbicacionGPS() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                document.getElementById('ubicacion_input').value = `https://maps.google.com/?q=${lat},${lng}`;
                alert('📍 Coordenadas GPS capturadas con éxito');
            }, function(error) {
                alert('⚠️ No se pudo acceder al GPS del dispositivo');
            });
        } else {
            alert('⚠️ Su navegador no soporta geolocalización');
        }
    }
</script>