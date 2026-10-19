@extends('layouts.app')

@section('title', 'Mover Inventario - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0" style="max-width: 800px;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('articulos.index') }}" class="btn btn-light border rounded-circle shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
            <i class="bi bi-arrow-left text-muted fs-5"></i>
        </a>
        <div>
            <h3 class="fw-bold text-dark m-0">Traspaso de Stock</h3>
            <p class="text-muted small m-0">Asigna inventario a vendedores o regresa mercancía al Almacén General</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-md-5">
        <form action="{{ route('articulos.traspaso.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-secondary small">Seleccionar Artículo (*)</label>
                    <select name="articulo_id" class="form-select bg-light rounded-3" required>
                        <option value="" disabled selected>Selecciona un producto...</option>
                        @foreach($articulos as $art)
                            <option value="{{ $art->id }}">
                                {{ $art->nombre }} (Almacén: {{ $art->stock_almacen }} pzas.)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Origen del Stock (*)</label>
                    <select name="origen" class="form-select bg-light rounded-3" required>
                        <option value="almacen" selected>🏬 Almacén General</option>
                        @foreach($vendedores as $v)
                            <option value="{{ $v->id }}">👤 Vendedor: {{ $v->username }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Destino del Stock (*)</label>
                    <select name="destino" class="form-select bg-light rounded-3" required>
                        <option value="" disabled selected>Selecciona destino...</option>
                        <option value="almacen">🏬 Almacén General</option>
                        @foreach($vendedores as $v)
                            <option value="{{ $v->id }}">👤 Vendedor: {{ $v->username }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Cantidad a Mover (*)</label>
                    <input type="number" name="cantidad" class="form-control bg-light rounded-3" min="1" placeholder="Ej. 5" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Observaciones / Motivo</label>
                    <input type="text" name="observaciones" class="form-control bg-light rounded-3" placeholder="Ej. Carga de camioneta para ruta">
                </div>
            </div>

            <div class="d-flex justify-content-end align-items-center gap-3 mt-5 pt-3 border-top">
                <a href="{{ route('articulos.index') }}" class="btn btn-light border rounded-pill px-4 fw-semibold text-muted">Cancelar</a>
                <button type="submit" class="btn btn-warning rounded-pill px-5 fw-bold shadow-sm text-dark">
                    <i class="bi bi-arrow-left-right me-1"></i> Confirmar Traspaso
                </button>
            </div>
        </form>
    </div>
</div>
@endsection