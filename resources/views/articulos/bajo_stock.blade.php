@extends('layouts.app')

@section('title', 'Artículos con Bajo Stock - Muebles Espinosa')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('articulos.index') }}" class="btn btn-light border rounded-circle shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Volver al Inventario">
            <i class="bi bi-arrow-left text-muted fs-5"></i>
        </a>
        <div>
            <h3 class="fw-bold text-dark m-0"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Artículos con Bajo Stock</h3>
            <p class="text-muted small m-0">Catálogo de productos que han alcanzado o están por debajo del límite mínimo en Almacén General</p>
        </div>
    </div>
</div>

<!-- BUSCADOR -->
<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
    <form action="{{ route('articulos.bajo_stock') }}" method="GET" class="row g-2 align-items-center">
        <div class="col-md-10">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="buscar" class="form-control bg-light border-start-0 ps-0" placeholder="Filtrar por nombre, marca, modelo o código..." value="{{ $busqueda }}">
            </div>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-danger rounded-3 fw-semibold">Buscar</button>
        </div>
    </form>
</div>

<!-- TABLA DE ALERTA -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden border-start border-4 border-danger">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th class="ps-4">Producto</th>
                    <th>Costo Compra</th>
                    <th>Precio Contado</th>
                    <th class="text-center">Stock Mínimo</th>
                    <th class="text-center">Existencia en Almacén</th>
                    <th class="text-end pe-4">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($articulosBajoStock as $art)
                    @php $stockAlmacen = $art->stock_almacen; @endphp
                    <tr>
                        <td class="ps-4">
                            <strong class="text-dark d-block fs-6">{{ $art->nombre }}</strong>
                            <small class="text-muted">{{ $art->marca }} {{ $art->modelo ? '('.$art->modelo.')' : '' }} | Código: {{ $art->barras ?? 'S/C' }}</small>
                        </td>
                        <td class="text-muted">${{ number_format($art->pcompra, 2) }}</td>
                        <td class="fw-bold text-success">${{ number_format($art->pcontado, 2) }}</td>
                        <td class="text-center fw-semibold text-secondary">{{ $art->stock_min }} pzas.</td>
                        <td class="text-center">
                            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-bold">
                                <i class="bi bi-exclamation-circle-fill me-1"></i> {{ $stockAlmacen }} pza{{ $stockAlmacen != 1 ? 's' : '' }}
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSurtir_{{ $art->id }}">
                                <i class="bi bi-plus-lg me-1"></i> Surtir
                            </button>
                        </td>
                    </tr>

                    <!-- MODAL DE SURTIR STOCK -->
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
                                            <strong>{{ $art->nombre }}</strong><br>Existencia actual: {{ $stockAlmacen }} pzas.
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-secondary small">Piezas a Agregar (*)</label>
                                            <input type="number" name="cantidad_suma" class="form-control rounded-3 fw-bold" min="1" value="5" required>
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

                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                            ¡Excelente! No hay artículos con stock bajo en Almacén General.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- PIE DE TABLA CON PAGINACIÓN EN BOOTSTRAP 5 -->
    @if($articulosBajoStock->hasPages())
        <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">Mostrando {{ $articulosBajoStock->firstItem() }} al {{ $articulosBajoStock->lastItem() }} de {{ $articulosBajoStock->total() }} artículos con stock bajo</small>
            <div>{{ $articulosBajoStock->links() }}</div>
        </div>
    @endif
</div>
@endsection