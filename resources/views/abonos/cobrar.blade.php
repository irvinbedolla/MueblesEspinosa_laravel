@extends('layouts.app')

@section('title', 'Registro de Abono - Muebles Espinosa')

@section('content')
<style>
    .stat-box { padding: 1rem; border-radius: 12px; background-color: #fff; border: 1px solid #eef2f5; box-shadow: 0 4px 6px rgba(0,0,0,0.01); text-align: center; height: 100%; }
    .stat-label { font-size: 0.72rem; color: #8a92a6; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .stat-value { font-size: 1.25rem; font-weight: 800; color: #232d42; margin-top: 0.25rem; display: block; }
</style>

<div class="container-fluid px-4 mt-4 mb-5">
    
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-sm-8">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-2 mb-2 rounded-pill">
                        VENTA #{{ $venta->folio ?? $venta->venta_id_legacy ?? $venta->id }}
                    </span>
                    <h3 class="fw-bold text-dark mb-1">{{ $cliente->nombre ?? $cliente->cliente_nombre ?? 'Sin Cliente' }}</h3>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                        {{ ($cliente->colonia ?? $cliente->cliente_colonia ?? '') . ', ' . ($cliente->ciudad ?? $cliente->cliente_ciudad ?? '') }}
                    </p>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <span class="badge rounded-pill px-4 py-2 text-uppercase fs-6 {{ ($venta->status == 'Pagado' || $aux4 <= 0) ? 'bg-success bg-opacity-10 text-success border border-success' : 'bg-warning bg-opacity-10 text-warning border border-warning' }}">
                        ● {{ $venta->status }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Cajas Estadísticas -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box"><span class="stat-label">Total Venta</span><span class="stat-value">${{ number_format($venta->total, 2) }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box"><span class="stat-label">Total Abonado</span><span class="stat-value text-success">${{ number_format($suma, 2) }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box"><span class="stat-label">Saldo Restante</span><span class="stat-value text-danger">${{ number_format($aux4, 2) }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box bg-light"><span class="stat-label">Último Pago</span><span class="stat-value text-dark">${{ number_format($ultimoAbono->a_pago ?? 0, 2) }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box bg-light"><span class="stat-label">Fecha Pago</span><span class="stat-value text-secondary fs-6 mt-2">{{ $ultimoAbono->a_fecha ?? 'N/A' }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box"><span class="stat-label">Descuento</span><span class="stat-value text-warning">${{ number_format($ventaDescuento, 2) }}</span></div></div>
    </div>

    <!-- Formulario Registrar Abono -->
    <form method="POST" action="{{ route('abonos.store') }}" id="formRegistrarAbono" onsubmit="return validarMontoAbono()">
        @csrf
        <input type="hidden" name="id" value="{{ $venta->id }}">
        
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                @if($aux4 > 0 && $venta->status == 'Pendiente')
                    <h5 class="fw-bold text-primary mb-4"><i class="bi bi-wallet2 me-2"></i>Registrar Abono o Liquidación</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Monto del Abono (Máx: ${{ number_format($aux4, 2) }})</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="abono" id="abono" class="form-control" required step="any" placeholder="0.00" data-restante="{{ $aux4 }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Fecha de Abono</label>
                            <input type="date" name="fecha" class="form-control" value="{{ date('Y-m-d') }}" {{ $tipoSeccion != 0 ? 'readonly' : '' }}>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">N° de Pago</label>
                            <input type="text" name="npago" class="form-control" value="{{ $ultimoNPago }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Forma de Pago</label>
                            <select name="fpago" class="form-select">
                                <option value="Efectivo" selected>Efectivo</option>
                                <option value="Tcredito">T. Crédito</option>
                                <option value="Tdebito">T. Débito</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Deposito">Depósito</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Acción</label>
                            <select name="tipo" class="form-select">
                                <option value="1">Abono Normal</option>
                                @if($tipoSeccion == 0) <option value="2">Liquidar Venta</option> @endif
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-lg w-100 rounded-3 py-2 fw-bold shadow-sm" type="submit">
                        <i class="bi bi-check-circle-fill me-2"></i> GUARDAR ABONO
                    </button>
                @else
                    <div class="alert alert-success border-0 text-center mb-0">
                        <h5 class="fw-bold text-success mb-0"><i class="bi bi-patch-check-fill me-2"></i>¡CUENTA LIQUIDADA!</h5>
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>

<script>
    function validarMontoAbono() {
        var inputAbono = document.getElementById('abono');
        var montoAbono = parseFloat(inputAbono.value) || 0;
        var saldoRestante = parseFloat(inputAbono.getAttribute('data-restante')) || 0;
        var selectTipo = document.querySelector('select[name="tipo"]');
        var esLiquidation = (selectTipo && selectTipo.value === '2');

        if (!esLiquidation && montoAbono > saldoRestante) {
            alert("¡ATENCIÓN! La cantidad ingresada ($" + montoAbono.toFixed(2) + ") es mayor al saldo restante ($" + saldoRestante.toFixed(2) + ").");
            inputAbono.focus();
            return false;
        }
        return true;
    }
</script>
@endsection