@extends('layouts.app')

@section('title', 'Nuevo Artículo - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0" style="max-width: 1000px;">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('articulos.index') }}" class="btn btn-light border rounded-circle shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-arrow-left text-muted fs-5"></i>
            </a>
            <div>
                <h3 class="fw-bold text-dark m-0">Registrar Nuevo Artículo</h3>
                <p class="text-muted small m-0">Ingresa los datos generales, costos y precios sugeridos a crédito</p>
            </div>
        </div>
    </div>

    <form action="{{ route('articulos.store') }}" method="POST">
        @csrf
        
        <!-- DATOS GENERALES -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 p-4 p-md-5">
            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-box-seam text-primary me-2"></i>Información del Producto</h5>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">Nombre del Producto (*)</label>
                    <input type="text" name="nombre" class="form-control bg-light rounded-3" placeholder="Ej. RECÁMARA KING SIZE MADERA" value="{{ old('nombre') }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold text-secondary small">Marca</label>
                    <input type="text" name="marca" class="form-control bg-light rounded-3" placeholder="Ej. Troncoso" value="{{ old('marca') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold text-secondary small">Modelo</label>
                    <input type="text" name="modelo" class="form-control bg-light rounded-3" placeholder="Ej. Moderno 2026" value="{{ old('modelo') }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-secondary small">Código de Barras</label>
                    <input type="text" name="barras" class="form-control bg-light rounded-3" placeholder="750123456789" value="{{ old('barras') }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-secondary small">Número de Serie</label>
                    <input type="text" name="n_serie" class="form-control bg-light rounded-3" placeholder="SN-987654" value="{{ old('n_serie') }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-secondary small">Categoría</label>
                    <input type="text" name="categoria" class="form-control bg-light rounded-3" placeholder="Ej. Salas, Comedores" value="{{ old('categoria') }}">
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold text-secondary small">Descripción del Artículo</label>
                    <textarea name="descripcion" class="form-control bg-light rounded-3" rows="2" placeholder="Detalles de materiales, color o dimensiones">{{ old('descripcion') }}</textarea>
                </div>
            </div>
        </div>

        <!-- PRECIOS Y STOCK -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 p-4 p-md-5">
            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-currency-dollar text-success me-2"></i>Costos, Utilidad y Stock Inicial</h5>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-secondary small">Precio Compra ($) (*)</label>
                    <input type="number" step="0.01" id="pcompra" name="pcompra" class="form-control bg-light rounded-3 fw-bold" placeholder="0.00" value="{{ old('pcompra', '0') }}" required oninput="calcularPrecios()">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold text-secondary small">Precio Contado ($) (*)</label>
                    <input type="number" step="0.01" name="pcontado" class="form-control bg-light rounded-3 fw-bold text-success" placeholder="0.00" value="{{ old('pcontado', '0') }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold text-secondary small">Stock Inicial Almacén (*)</label>
                    <input type="number" name="stock_inicial" class="form-control bg-light rounded-3 fw-bold" value="{{ old('stock_inicial', '1') }}" min="0" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold text-secondary small">Stock Mínimo Alerta (*)</label>
                    <input type="number" name="stock_min" class="form-control bg-light rounded-3" value="{{ old('stock_min', '2') }}" min="0" required>
                </div>
            </div>

            <!-- TABLA DE PRECIOS A MESES -->
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calendar-range text-primary me-2"></i>Precios Sugeridos a Crédito (1 a 12 Meses)</h6>
            <p class="text-muted small">Los precios se calculan automáticamente con el porcentaje asignado. Puedes modificar libremente cualquier mes si deseas un valor personalizado.</p>

            <div class="row g-3" id="contenedor_precios">
                @for($m = 1; $m <= 12; $m++)
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded-3 bg-light">
                            <small class="fw-bold text-primary d-block mb-1">{{ $m }} Mes(es)</small>
                            <input type="number" step="0.01" id="precio_mes_{{ $m }}" name="precios_custom[{{ $m }}]" class="form-control form-control-sm rounded-2 bg-white fw-semibold" placeholder="0.00">
                            <small class="text-muted extra-small d-block mt-1" id="pago_mes_{{ $m }}">Mensual: $0.00</small>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        <div class="d-flex justify-content-end align-items-center gap-3">
            <a href="{{ route('articulos.index') }}" class="btn btn-light border rounded-pill px-4 fw-semibold text-muted">Cancelar</a>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                <i class="bi bi-save me-2"></i> Guardar Artículo
            </button>
        </div>
    </form>
</div>

<script>
    function calcularPrecios() {
        const pcompra = parseFloat(document.getElementById('pcompra').value) || 0;

        // Porcentajes de utilidad sumados al precio de compra por cada mes
        const utilidades = { 
            1: 40, 2: 35, 3: 30, 4: 25, 
            5: 20, 6: 15, 7: 10, 8: 5, 
            9: 0, 10: 0, 11: 0, 12: 0 
        };

        for (let m = 1; m <= 12; m++) {
            const inputMes = document.getElementById(`precio_mes_${m}`);
            if (inputMes) {
                const pct = utilidades[m] || 0;
                const montoUtilidad = pcompra * (pct / 100);
                const total = Math.round((pcompra + montoUtilidad) * 100) / 100;
                const mensual = Math.round((total / m) * 100) / 100;

                inputMes.value = total.toFixed(2);
                document.getElementById(`pago_mes_${m}`).innerText = `Mensual: $${mensual.toFixed(2)}`;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', calcularPrecios);
</script>
@endsection