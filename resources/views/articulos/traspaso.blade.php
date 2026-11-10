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
        <form action="{{ route('articulos.traspaso.store') }}" method="POST" id="formTraspaso">
            @csrf
            <div class="row g-4">
                
                <!-- BUSCADOR CON AUTOCOMPLETADO DE ARTÍCULOS -->
                <div class="col-md-12 position-relative">
                    <label class="form-label fw-semibold text-secondary small">Seleccionar Artículo (*)</label>

                    <!-- Campo Oculto para enviar el ID del Artículo al Backend -->
                    <input type="hidden" name="articulo_id" id="articulo_id" required>

                    <!-- Input de Búsqueda -->
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               id="input_buscar_articulo" 
                               class="form-control bg-light border-start-0 rounded-end-3" 
                               placeholder="Escribe el nombre o código del producto para filtrar..." 
                               autocomplete="off"
                               required>
                    </div>

                    <!-- Menú Desplegable Flotante de Resultados -->
                    <div id="dropdownArticulos" 
                         class="list-group shadow-lg rounded-3 position-absolute w-100 mt-1 overflow-auto" 
                         style="max-height: 250px; display: none; z-index: 1050; left: 0;">
                        @foreach($articulos as $art)
                            <button type="button" 
                                    class="list-group-item list-group-item-action item-articulo d-flex justify-content-between align-items-center py-2 px-3 border-bottom"
                                    data-id="{{ $art->id }}"
                                    data-text="{{ strtolower($art->nombre) }}"
                                    onclick="seleccionarArticulo('{{ $art->id }}', '{{ addslashes($art->nombre) }} (Almacén: {{ $art->stock_almacen }} pzas.)')">
                                <div>
                                    <strong class="text-dark d-block small">{{ $art->nombre }}</strong>
                                    <small class="text-muted extra-small">Stock en Almacén: {{ $art->stock_almacen }} pzas.</small>
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold">Seleccionar</span>
                            </button>
                        @endforeach
                        <div id="sinResultadosArticulos" class="list-group-item text-muted text-center py-3 small" style="display: none;">
                            <i class="bi bi-search me-1"></i> No se encontraron coincidencias.
                        </div>
                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputBuscar = document.getElementById('input_buscar_articulo');
    if (inputBuscar) {
        inputBuscar.addEventListener('focus', mostrarListaArticulos);
        inputBuscar.addEventListener('keyup', filtrarArticulos);
    }

    // Ocultar la lista flotante al hacer clic fuera
    document.addEventListener('click', function(e) {
        const container = document.getElementById('dropdownArticulos');
        const input = document.getElementById('input_buscar_articulo');
        if (container && input && !input.contains(e.target) && !container.contains(e.target)) {
            container.style.display = 'none';
        }
    });
});

function mostrarListaArticulos() {
    const dropdown = document.getElementById('dropdownArticulos');
    if (dropdown) {
        dropdown.style.display = 'block';
        filtrarArticulos();
    }
}

function filtrarArticulos() {
    const input = document.getElementById('input_buscar_articulo');
    const dropdown = document.getElementById('dropdownArticulos');
    const hiddenInput = document.getElementById('articulo_id');
    const sinResultados = document.getElementById('sinResultadosArticulos');

    if (!input || !dropdown) return;

    const filter = input.value.toLowerCase().trim();
    const items = dropdown.getElementsByClassName('item-articulo');

    if (filter === '' && hiddenInput) {
        hiddenInput.value = '';
    }

    let visibles = 0;
    for (let i = 0; i < items.length; i++) {
        const itemText = (items[i].getAttribute('data-text') || '').toLowerCase();
        if (itemText.includes(filter)) {
            items[i].style.setProperty('display', 'flex', 'important');
            visibles++;
        } else {
            items[i].style.setProperty('display', 'none', 'important');
        }
    }

    dropdown.style.display = 'block';
    if (sinResultados) {
        sinResultados.style.display = (visibles === 0) ? 'block' : 'none';
    }
}

function seleccionarArticulo(id, textoCompleto) {
    const hiddenInput = document.getElementById('articulo_id');
    const inputBuscar = document.getElementById('input_buscar_articulo');
    const dropdown = document.getElementById('dropdownArticulos');

    if (hiddenInput) hiddenInput.value = id;
    if (inputBuscar) inputBuscar.value = textoCompleto;
    if (dropdown) dropdown.style.display = 'none';
}
</script>
@endsection