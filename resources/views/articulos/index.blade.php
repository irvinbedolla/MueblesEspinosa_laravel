@extends('layouts.app')

@section('title', 'Inventario - Muebles Espinosa')

@section('content')

<!-- ENCABEZADO UNIFICADO -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark m-0">
            <i class="bi bi-boxes text-primary me-2"></i>
            @if(!$esAdmin)
                Mi Inventario
            @elseif($usuarioSeleccionado)
                Inventario de {{ $usuarioSeleccionado->username }}
            @else
                Inventario General
            @endif
        </h3>
        <p class="text-muted small m-0">
            @if(!$esAdmin)
                Artículos asignados a tu camioneta / ruta
            @elseif($usuarioSeleccionado)
                Existencias asignadas actualmente a {{ $usuarioSeleccionado->username }}
            @else
                Control general de catálogo, existencias en almacén y distribución
            @endif
        </p>
    </div>
    
    <!-- ACCIONES SOLO VISIBLES PARA ADMINISTRADORES -->
    @if($esAdmin)
        <div class="d-flex flex-wrap gap-2">
            @if(auth()->user()->tienePermiso('inventory.kardex'))
                <a href="{{ route('articulos.kardex') }}" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
                    <i class="bi bi-journal-text me-1"></i> Movimientos
                </a>
            @endif
            @if(auth()->user()->tienePermiso('inventory.transfer'))
                <a href="{{ route('articulos.traspaso') }}" class="btn btn-warning rounded-pill px-3 fw-semibold text-dark">
                    <i class="bi bi-arrow-left-right me-1"></i> Mover Stock
                </a>
            @endif
            @if(auth()->user()->tienePermiso('inventory.create'))
                <a href="{{ route('articulos.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo Artículo
                </a>
            @endif
        </div>
    @endif
</div>

<!-- TARJETAS DE RESUMEN MÉTRICO -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <small class="text-muted fw-bold d-block">
                {{ (!$esAdmin || $usuarioSeleccionado) ? 'PRODUCTOS ASIGNADOS' : 'TOTAL PRODUCTOS' }}
            </small>
            <h4 class="fw-bold text-dark m-0 mt-1">{{ $resumen['total_articulos'] }}</h4>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <small class="text-muted fw-bold d-block">
                @if(!$esAdmin)
                    MI STOCK ASIGNADO
                @elseif($usuarioSeleccionado)
                    STOCK DE {{ strtoupper($usuarioSeleccionado->username) }}
                @else
                    STOCK GLOBAL
                @endif
            </small>
            <h4 class="fw-bold text-primary m-0 mt-1">{{ $resumen['stock_general'] }} pzas.</h4>
        </div>
    </div>
    
    @if($esAdmin)
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <small class="text-muted fw-bold d-block">EN ALMACÉN GENERAL</small>
                <h4 class="fw-bold text-success m-0 mt-1">{{ $resumen['stock_almacen'] }} pzas.</h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('articulos.bajo_stock') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger shadow-hover h-100">
                    <small class="text-muted fw-bold d-block text-danger">ALERTA STOCK BAJO <i class="bi bi-arrow-right-short"></i></small>
                    <h4 class="fw-bold text-danger m-0 mt-1">{{ $resumen['stock_bajo'] }} prod.</h4>
                </div>
            </a>
        </div>
    @endif
</div>

<!-- BUSCADOR / FILTRO POR INVENTARIO DE USUARIO -->
<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
    <form id="form_filtro_inventario" action="{{ route('articulos.index') }}" method="GET" class="row g-2 align-items-center">
        @if($esAdmin)
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-primary">
                        <i class="bi bi-person-badge"></i>
                    </span>
                    <select name="ubicacion" class="form-select bg-light border-start-0 fw-semibold text-dark" onchange="document.getElementById('form_filtro_inventario').submit()">
                        <option value="general" {{ $ubicacion === 'general' ? 'selected' : '' }}>
                            🏢 Inventario General (Almacén)
                        </option>
                        <optgroup label="Inventario por Usuario / Vendedor">
                            @foreach($usuariosInventario as $usr)
                                <option value="{{ $usr->id }}" {{ (string)$ubicacion === (string)$usr->id ? 'selected' : '' }}>
                                    👤 {{ $usr->username }}
                                </option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
            </div>
        @endif

        <div class="{{ $esAdmin ? 'col-md-6' : 'col-md-10' }}">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="buscar" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar por código de barras, nombre, marca o modelo..." value="{{ $busqueda }}">
            </div>
        </div>

        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary rounded-3 fw-semibold">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
        </div>
    </form>
</div>

<!-- TABLA DE ARTÍCULOS (SIN MODALES DENTRO PARA EVITAR BLOQUEO EN MÓVIL) -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th class="ps-4">Producto</th>
                    <th class="text-nowrap">Costo Compra</th>
                    <th class="text-nowrap">Precio Contado</th>
                    <th class="text-nowrap">Precio Crédito Base</th>
                    <th class="text-center text-nowrap">
                        @if(!$esAdmin)
                            Mi Stock
                        @elseif($usuarioSeleccionado)
                            Stock ({{ $usuarioSeleccionado->username }})
                        @else
                            Stock Almacén
                        @endif
                    </th>
                    @if(auth()->user()->tienePermiso('inventory.edit') || auth()->user()->tienePermiso('inventory.delete'))
                        <th class="text-end pe-4 text-nowrap">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($articulos as $art)
                    @php
                        $stockAlmacen = $art->stock_almacen;
                        $targetUserId = $esAdmin ? ($usuarioSeleccionado->id ?? null) : auth()->id();
                        $stockUsuario = $targetUserId
                            ? ($art->stocks->where('user_id', $targetUserId)->first()->cantidad ?? 0)
                            : 0;
                    @endphp
                    <tr>
                        <td class="ps-4" style="min-width: 190px;">
                            <strong class="text-dark d-block fs-6">{{ $art->nombre }}</strong>
                            <small class="text-muted">{{ $art->marca }} {{ $art->modelo ? '('.$art->modelo.')' : '' }} | Cod: {{ $art->barras ?? 'S/C' }}</small>
                        </td>
                        <td class="text-muted text-nowrap">${{ number_format($art->pcompra, 2) }}</td>
                        <td class="fw-bold text-success text-nowrap">${{ number_format($art->pcontado, 2) }}</td>
                        <td class="fw-bold text-primary text-nowrap">${{ number_format($art->pcredito, 2) }}</td>
                        
                        <!-- STOCK -->
                        <td class="text-center text-nowrap">
                            @if($esAdmin && !$usuarioSeleccionado)
                                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                    <span class="badge {{ $stockAlmacen <= $art->stock_min ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success' }} px-3 py-2 rounded-pill fw-bold">
                                        {{ $stockAlmacen }}
                                    </span>
                                    <button type="button" class="btn btn-sm btn-light border rounded-circle shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSurtir_{{ $art->id }}" title="Surtir Almacén">
                                        <i class="bi bi-plus-lg text-success"></i>
                                    </button>
                                </div>
                            @else
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold fs-6">
                                    {{ $stockUsuario }} pzas.
                                </span>
                            @endif
                        </td>

                        <!-- ACCIONES SIEMPRE EN LÍNEA HORIZONTAL -->
                        @if(auth()->user()->tienePermiso('inventory.edit') || auth()->user()->tienePermiso('inventory.delete'))
                            <td class="text-end pe-4 text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1">
                                    @if(auth()->user()->tienePermiso('inventory.edit'))
                                        <a href="{{ route('articulos.edit', $art->id) }}" class="btn btn-sm btn-outline-primary rounded-circle" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->tienePermiso('inventory.delete'))
                                        <form action="{{ route('articulos.destroy', $art->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar artículo?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No se encontraron artículos disponibles.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- PIE DE TABLA CON PAGINACIÓN -->
    @if($articulos->hasPages())
        <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">Mostrando {{ $articulos->firstItem() }} al {{ $articulos->lastItem() }} de {{ $articulos->total() }} artículos</small>
            <div>{{ $articulos->links() }}</div>
        </div>
    @endif
</div>

<!-- MODALES FUERA DE LA TABLA Y DE CONTENEDORES CON OVERFLOW-HIDDEN -->
@if($esAdmin)
    @foreach($articulos as $art)
        @php
            $stockAlmacen = $art->stock_almacen;
            $stocksVendedores = $art->stocks->filter(fn($s) => $s->user_id !== null && $s->cantidad > 0);
            $totalVendedores = $stocksVendedores->sum('cantidad');
        @endphp

        <!-- MODAL PARA SURTIR ALMACÉN -->
        <div class="modal fade" id="modalSurtir_{{ $art->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('articulos.surtir') }}" method="POST">
                        @csrf
                        <input type="hidden" name="articulo_id" value="{{ $art->id }}">
                        <div class="modal-header border-0 pb-0">
                            <h6 class="modal-title fw-bold text-dark">
                                <i class="bi bi-box-arrow-in-down text-success me-2"></i>Surtir a Almacén
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="text-muted small mb-3">
                                <strong>{{ $art->nombre }}</strong> (Existencia: {{ $stockAlmacen }} pzas.)
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary small">Piezas a Agregar (*)</label>
                                <input type="number" name="cantidad_suma" class="form-control rounded-3 fw-bold" min="1" value="1" required>
                            </div>
                            <div>
                                <label class="form-label fw-semibold text-secondary small">Notas / Proveedor</label>
                                <input type="text" name="motivo" class="form-control rounded-3" placeholder="Ej. Entrada por compra">
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-semibold text-muted" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success btn-sm rounded-pill px-4 fw-bold">Agregar Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL DE DESGLOSE DE VENDEDORES -->
        @if($totalVendedores > 0)
            <div class="modal fade" id="modalStock_{{ $art->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-0 pb-0">
                            <h6 class="modal-title fw-bold text-dark">
                                <i class="bi bi-person-badge text-info me-2"></i>Asignación a Vendedores
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="text-muted small mb-3 border-bottom pb-2">
                                <strong>{{ $art->nombre }}</strong>
                            </div>
                            <ul class="list-group list-group-flush small">
                                @foreach($stocksVendedores as $st)
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                        <span class="fw-semibold text-dark">
                                            <i class="bi bi-person me-1 text-secondary"></i> {{ $st->usuario->username ?? 'Desconocido' }}
                                        </span>
                                        <span class="badge bg-primary rounded-pill px-3 py-1 fw-bold">
                                            {{ $st->cantidad }} pzas.
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light btn-sm rounded-pill px-3 w-100 fw-semibold text-muted" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endif

@endsection