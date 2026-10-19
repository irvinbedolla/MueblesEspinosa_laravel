@extends('layouts.app')

@section('title', 'Inventario - Muebles Espinosa')

@section('content')

<!-- ENCABEZADO UNIFICADO -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark m-0"><i class="bi bi-boxes text-primary me-2"></i>{{ $esAdmin ? 'Inventario General' : 'Mi Inventario' }}</h3>
        <p class="text-muted small m-0">
            {{ $esAdmin ? 'Control general de catálogo, existencias en almacén y distribución' : 'Artículos asignados a tu camioneta / ruta' }}
        </p>
    </div>
    
    <!-- ACCIONES SOLO VISIBLES PARA ADMINISTRADORES -->
    @if($esAdmin)
        <div class="d-flex gap-2">
            <a href="{{ route('articulos.kardex') }}" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
                <i class="bi bi-journal-text me-1"></i> Movimientos
            </a>
            <a href="{{ route('articulos.traspaso') }}" class="btn btn-warning rounded-pill px-3 fw-semibold text-dark">
                <i class="bi bi-arrow-left-right me-1"></i> Mover Stock
            </a>
            <a href="{{ route('articulos.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Nuevo Artículo
            </a>
        </div>
    @endif
</div>

<!-- TARJETAS DE RESUMEN MÉTRICO -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <small class="text-muted fw-bold d-block">{{ $esAdmin ? 'TOTAL PRODUCTOS' : 'MIS PRODUCTOS' }}</small>
            <h4 class="fw-bold text-dark m-0 mt-1">{{ $resumen['total_articulos'] }}</h4>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <small class="text-muted fw-bold d-block">{{ $esAdmin ? 'STOCK GLOBAL' : 'MI STOCK ASIGNADO' }}</small>
            <h4 class="fw-bold text-primary m-0 mt-1">{{ $resumen['stock_general'] }} pzas.</h4>
        </div>
    </div>
    
    @if($esAdmin)
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <small class="text-muted fw-bold d-block">EN ALMACÉN GENERAL</small>
                <h4 class="fw-bold text-success m-0 mt-1">{{ $resumen['stock_almacen'] }} pzas.</h4>
            </div>
        </div>
        <!-- TARJETA CLIQUEABLE PARA APARTADO DE BAJO STOCK -->
        <div class="col-6 col-md-3">
            <a href="{{ route('articulos.bajo_stock') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger shadow-hover">
                    <small class="text-muted fw-bold d-block text-danger">ALERTA STOCK BAJO <i class="bi bi-arrow-right-short"></i></small>
                    <h4 class="fw-bold text-danger m-0 mt-1">{{ $resumen['stock_bajo'] }} prod.</h4>
                </div>
            </a>
        </div>
    @endif
</div>

<!-- BUSCADOR / FILTRO -->
<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
    <form action="{{ route('articulos.index') }}" method="GET" class="row g-2 align-items-center">
        <div class="col-md-10">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="buscar" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar por código de barras, nombre, marca o modelo..." value="{{ $busqueda }}">
            </div>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary rounded-3 fw-semibold">Filtrar</button>
        </div>
    </form>
</div>

<!-- TABLA DE ARTÍCULOS -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th class="ps-4">Producto</th>
                    <th>Costo Compra</th>
                    <th>Precio Contado</th>
                    <th>Precio Crédito Base</th>
                    <th class="text-center">{{ $esAdmin ? 'Stock Almacén' : 'Mi Stock' }}</th>
                    @if($esAdmin)
                        <th class="text-center">Stock Vendedores</th>
                        <th class="text-end pe-4">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($articulos as $art)
                    @php
                        $stockAlmacen = $art->stock_almacen;
                        $miStock = $art->stocks->where('user_id', auth()->id())->first()->cantidad ?? 0;
                        $stocksVendedores = $art->stocks->filter(fn($s) => $s->user_id !== null && $s->cantidad > 0);
                        $totalVendedores = $stocksVendedores->sum('cantidad');
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <strong class="text-dark d-block fs-6">{{ $art->nombre }}</strong>
                            <small class="text-muted">{{ $art->marca }} {{ $art->modelo ? '('.$art->modelo.')' : '' }} | Cod: {{ $art->barras ?? 'S/C' }}</small>
                        </td>
                        <td class="text-muted">${{ number_format($art->pcompra, 2) }}</td>
                        <td class="fw-bold text-success">${{ number_format($art->pcontado, 2) }}</td>
                        <td class="fw-bold text-primary">${{ number_format($art->pcredito, 2) }}</td>
                        
                        <!-- STOCK (ALMACEN O VENDEDOR) -->
                        <td class="text-center">
                            @if($esAdmin)
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span class="badge {{ $stockAlmacen <= $art->stock_min ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success' }} px-3 py-2 rounded-pill fw-bold">
                                        {{ $stockAlmacen }}
                                    </span>
                                    <button type="button" class="btn btn-sm btn-light border rounded-circle shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSurtir_{{ $art->id }}" title="Surtir Almacén">
                                        <i class="bi bi-plus-lg text-success"></i>
                                    </button>
                                </div>
                            @else
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold fs-6">
                                    {{ $miStock }} pzas.
                                </span>
                            @endif
                        </td>

                        <!-- COLUMNAS EXCLUSIVAS DE ADMIN -->
                        @if($esAdmin)
                            <td class="text-center">
                                @if($totalVendedores > 0)
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 py-1 fw-bold border-0 bg-info bg-opacity-10 text-info" data-bs-toggle="modal" data-bs-target="#modalStock_{{ $art->id }}">
                                        <i class="bi bi-eye-fill me-1"></i> {{ $totalVendedores }} pza{{ $totalVendedores > 1 ? 's' : '' }}
                                    </button>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>

                            <td class="text-end pe-4">
                                <a href="{{ route('articulos.edit', $art->id) }}" class="btn btn-sm btn-outline-primary rounded-circle me-1" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('articulos.destroy', $art->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar artículo?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        @endif
                    </tr>

                    @if($esAdmin)
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
                                            <div class="text-muted extra-small mb-3">
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
                                            <div class="text-muted extra-small mb-3 border-bottom pb-2">
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
                    @endif

                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No se encontraron artículos disponibles.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- PIE DE TABLA CON PAGINACIÓN EN ESPAÑOL DE BOOTSTRAP 5 -->
    @if($articulos->hasPages())
        <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">Mostrando {{ $articulos->firstItem() }} al {{ $articulos->lastItem() }} de {{ $articulos->total() }} artículos</small>
            <div>{{ $articulos->links() }}</div>
        </div>
    @endif
</div>
@endsection