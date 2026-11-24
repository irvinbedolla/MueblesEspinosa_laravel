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

    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 p-sm-4 p-md-5">
        <form action="{{ route('articulos.traspaso.store') }}" method="POST" id="formTraspaso" onsubmit="return validarSeleccionArticulo()">
            @csrf
            <div class="row g-3 g-md-4">

                <!-- 1. ORIGEN Y DESTINO PRIMERO -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Origen del Stock (*)</label>
                    <select name="origen" id="select_origen" class="form-select bg-light rounded-3 fw-semibold" required onchange="actualizarInventarioPorOrigen()">
                        <option value="almacen" data-label="Almacén General" selected>🏬 Almacén General</option>
                        @foreach($vendedores as $v)
                            <option value="{{ $v->id }}" data-label="{{ $v->username }}">👤 Vendedor: {{ $v->username }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Destino del Stock (*)</label>
                    <select name="destino" id="select_destino" class="form-select bg-light rounded-3" required>
                        <option value="" disabled selected>Selecciona destino...</option>
                        <option value="almacen">🏬 Almacén General</option>
                        @foreach($vendedores as $v)
                            <option value="{{ $v->id }}">👤 Vendedor: {{ $v->username }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- 2. BUSCADOR CON AUTOCOMPLETADO DINÁMICO SEGÚN EL ORIGEN -->
                <div class="col-12 position-relative">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-semibold text-secondary small m-0">Seleccionar Artículo (*)</label>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1" id="badge_origen_actual" style="font-size: 0.72rem;">
                            Mostrando stock en: Almacén General
                        </span>
                    </div>

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
                               placeholder="Escribe nombre, modelo o código..." 
                               autocomplete="off"
                               required>
                    </div>

                    <!-- Menú Desplegable Flotante de Resultados -->
                    <div id="dropdownArticulos" 
                         class="list-group shadow-lg rounded-3 position-absolute w-100 mt-1 overflow-auto border" 
                         style="max-height: 280px; display: none; z-index: 1050; left: 0; background-color: #fff;">
                        @foreach($articulos as $art)
                            @php
                                $modeloTxt = !empty($art->modelo) ? $art->modelo : 'S/M';
                                $marcaTxt  = !empty($art->marca) ? $art->marca : '';
                                $nombreBase = $art->nombre . ($art->modelo ? ' - Mod: ' . $art->modelo : '');
                                $textoBusqueda = strtolower(trim($art->nombre . ' ' . $modeloTxt . ' ' . $marcaTxt . ' ' . ($art->barras ?? '')));

                                // Mapa de stock por ubicación: 'almacen' y cada user_id
                                $mapaStocks = [
                                    'almacen' => (int) ($art->stocks->whereNull('user_id')->sum('cantidad')),
                                ];
                                foreach ($art->stocks->whereNotNull('user_id')->groupBy('user_id') as $uid => $grupo) {
                                    $mapaStocks[(string) $uid] = (int) $grupo->sum('cantidad');
                                }
                            @endphp
                            <button type="button" 
                                    class="list-group-item list-group-item-action item-articulo d-flex justify-content-between align-items-center gap-2 py-2 px-3 border-bottom"
                                    data-id="{{ $art->id }}"
                                    data-nombre-base="{{ $nombreBase }}"
                                    data-text="{{ $textoBusqueda }}"
                                    data-stocks='@json($mapaStocks)'
                                    onclick="seleccionarArticuloDesdeElemento(this)">
                                <div class="text-start">
                                    <strong class="text-dark d-block small">{{ $art->nombre }}</strong>
                                    <small class="text-primary fw-semibold d-block" style="font-size: 0.78rem;">
                                        {{ $marcaTxt ? $marcaTxt . ' | ' : '' }}Modelo: {{ $modeloTxt }}
                                    </small>
                                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                                        <span class="lbl-origen-item">Stock en Almacén General</span>: 
                                        <strong class="lbl-stock-item text-dark">{{ $mapaStocks['almacen'] }} pzas.</strong>
                                    </small>
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold text-nowrap">
                                    Seleccionar
                                </span>
                            </button>
                        @endforeach
                        <div id="sinResultadosArticulos" class="list-group-item text-muted text-center py-3 small" style="display: none;">
                            <i class="bi bi-search me-1"></i> No hay artículos con existencias en este origen.
                        </div>
                    </div>
                </div>

                <!-- 3. CANTIDAD Y OBSERVACIONES -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Cantidad a Mover (*)</label>
                    <input type="number" name="cantidad" id="input_cantidad" class="form-control bg-light rounded-3" min="1" placeholder="Ej. 5" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Observaciones / Motivo</label>
                    <input type="text" name="observaciones" class="form-control bg-light rounded-3" placeholder="Ej. Carga de camioneta para ruta">
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 gap-sm-3 mt-4 mt-md-5 pt-3 border-top">
                <a href="{{ route('articulos.index') }}" class="btn btn-light border rounded-pill px-4 fw-semibold text-muted">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-warning rounded-pill px-4 px-sm-5 fw-bold shadow-sm text-dark text-nowrap">
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

    // Inicializar etiquetas y filtro según el origen por defecto
    actualizarInventarioPorOrigen();

    // Ocultar la lista flotante al hacer clic fuera
    document.addEventListener('click', function(e) {
        const container = document.getElementById('dropdownArticulos');
        const input = document.getElementById('input_buscar_articulo');
        if (container && input && !input.contains(e.target) && !container.contains(e.target)) {
            container.style.display = 'none';
        }
    });
});

function obtenerOrigenActual() {
    const selectOrigen = document.getElementById('select_origen');
    const opt = selectOrigen.options[selectOrigen.selectedIndex];
    return {
        id: selectOrigen.value,
        label: opt ? (opt.getAttribute('data-label') || opt.text) : 'Almacén General'
    };
}

function actualizarInventarioPorOrigen() {
    const origen = obtenerOrigenActual();
    const badgeOrigen = document.getElementById('badge_origen_actual');
    const hiddenInput = document.getElementById('articulo_id');
    const inputBuscar = document.getElementById('input_buscar_articulo');
    const inputCantidad = document.getElementById('input_cantidad');

    if (badgeOrigen) {
        badgeOrigen.textContent = 'Mostrando stock en: ' + origen.label;
    }

    // Limpiar artículo seleccionado previamente al cambiar de origen
    if (hiddenInput) hiddenInput.value = '';
    if (inputBuscar) inputBuscar.value = '';
    if (inputCantidad) inputCantidad.removeAttribute('max');

    // Actualizar texto de stock en cada fila del desplegable
    const items = document.getElementsByClassName('item-articulo');
    for (let i = 0; i < items.length; i++) {
        const stocks = JSON.parse(items[i].getAttribute('data-stocks') || '{}');
        const disp = parseInt(stocks[origen.id] || 0, 10);

        const lblOrigen = items[i].querySelector('.lbl-origen-item');
        const lblStock = items[i].querySelector('.lbl-stock-item');

        if (lblOrigen) lblOrigen.textContent = 'Stock en ' + origen.label;
        if (lblStock) lblStock.textContent = disp + ' pzas.';
    }
}

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

    const origen = obtenerOrigenActual();
    const filter = input.value.toLowerCase().trim();
    const items = dropdown.getElementsByClassName('item-articulo');

    if (filter === '' && hiddenInput) {
        hiddenInput.value = '';
    }

    let visibles = 0;
    for (let i = 0; i < items.length; i++) {
        const itemText = (items[i].getAttribute('data-text') || '').toLowerCase();
        const stocks = JSON.parse(items[i].getAttribute('data-stocks') || '{}');
        const disp = parseInt(stocks[origen.id] || 0, 10);

        // Solo muestra artículos que coincidan con la búsqueda Y tengan stock > 0 en el origen elegido
        if (disp > 0 && itemText.includes(filter)) {
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

function seleccionarArticuloDesdeElemento(btn) {
    const origen = obtenerOrigenActual();
    const id = btn.getAttribute('data-id');
    const nombreBase = btn.getAttribute('data-nombre-base');
    const stocks = JSON.parse(btn.getAttribute('data-stocks') || '{}');
    const disp = parseInt(stocks[origen.id] || 0, 10);

    const hiddenInput = document.getElementById('articulo_id');
    const inputBuscar = document.getElementById('input_buscar_articulo');
    const dropdown = document.getElementById('dropdownArticulos');
    const inputCantidad = document.getElementById('input_cantidad');

    if (hiddenInput) hiddenInput.value = id;
    if (inputBuscar) inputBuscar.value = nombreBase + ' (' + origen.label + ': ' + disp + ' pzas.)';
    if (inputCantidad) {
        inputCantidad.max = disp;
        if (parseInt(inputCantidad.value || 0, 10) > disp) {
            inputCantidad.value = disp;
        }
    }
    if (dropdown) dropdown.style.display = 'none';
}

function validarSeleccionArticulo() {
    const hiddenInput = document.getElementById('articulo_id');
    if (!hiddenInput || !hiddenInput.value) {
        alert('Por favor selecciona un artículo válido de la lista desplegable.');
        document.getElementById('input_buscar_articulo').focus();
        return false;
    }
    return true;
}
</script>
@endsection