@extends('layouts.app')

@section('title', 'Detalle de Venta #' . $venta->folio - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0" style="max-width: 900px;">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('ventas.index') }}" class="btn btn-light border rounded-circle shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-arrow-left text-muted fs-5"></i>
            </a>
            <div>
                <h3 class="fw-bold text-dark m-0">Venta Folio #{{ $venta->folio }}</h3>
                <p class="text-muted small m-0">Fecha: {{ \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y') }} | Registrado por: {{ $venta->vendedor->username ?? 'Sistema' }}</p>
            </div>
        </div>
        
        <div>
            <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">{{ $venta->tipo_venta }}</span>
            <span class="badge bg-warning fs-6 px-3 py-2 rounded-pill text-dark ms-1">{{ $venta->status }}</span>
        </div>
    </div>

    <!-- DATOS DE CLIENTE Y COBRANZA -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
        <div class="row g-3">
            <div class="col-md-6 border-end">
                <h6 class="fw-bold text-secondary extra-small text-uppercase mb-2">Datos del Cliente</h6>
                <h5 class="fw-bold text-dark m-0">{{ $venta->cliente->nombre ?? 'N/A' }}</h5>
                <p class="text-muted small m-0 mt-1"><i class="bi bi-geo-alt me-1"></i> {{ $venta->cliente->calle }}, {{ $venta->cliente->colonia }}, {{ $venta->cliente->ciudad }}</p>
                <p class="text-muted small m-0"><i class="bi bi-telephone me-1"></i> {{ $venta->cliente->telefono ?? 'Sin teléfono' }}</p>
            </div>
            
            <div class="col-md-6 ps-md-4">
                <h6 class="fw-bold text-secondary extra-small text-uppercase mb-2">Parámetros de Crédito / Pago</h6>
                <div class="row g-2 small">
                    <div class="col-6"><strong>Cobrador:</strong> {{ $venta->cobrador->username ?? 'No asignado' }}</div>
                    <div class="col-6"><strong>Frecuencia:</strong> {{ $venta->frecuencia_nombre }} ({{ $venta->frecuencia_dias }} días)</div>
                    <div class="col-6"><strong>N° de Pagos:</strong> {{ $venta->num_pagos }} cuota(s)</div>
                    <div class="col-6"><strong>Día de Cobro:</strong> {{ $venta->dia_semana ?? 'N/S' }}</div>
                    <div class="col-12"><strong>1er Pago:</strong> {{ $venta->fecha_primer_pago ? \Carbon\Carbon::parse($venta->fecha_primer_pago)->format('d/m/Y') : 'N/A' }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- DETALLE DE ARTÍCULOS -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-light border-0 py-3 ps-4">
            <h6 class="fw-bold text-dark m-0"><i class="bi bi-boxes me-2"></i>Artículos Incluidos</h6>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-muted small">
                    <tr>
                        <th class="ps-4">Producto</th>
                        <th class="text-center">Cant.</th>
                        <th>N° Serie</th>
                        <th class="text-end">Precio U.</th>
                        <th class="text-end">Descuento</th>
                        <th class="text-end pe-4">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($venta->detalles as $d)
                        <tr>
                            <td class="ps-4">
                                <strong class="text-dark d-block">{{ $d->nombre_articulo }}</strong>
                            </td>
                            <td class="text-center fw-bold">{{ $d->cantidad }}</td>
                            <td class="small text-muted">{{ $d->numero_serie ?? 'S/N' }}</td>
                            <td class="text-end">${{ number_format($d->precio_unitario, 2) }}</td>
                            <td class="text-end text-muted">${{ number_format($d->descuento, 2) }}</td>
                            <td class="text-end pe-4 fw-bold text-primary">${{ number_format($d->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- RESUMEN FINANCIERO -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <div class="row justify-content-end text-end g-2">
            <div class="col-md-4">
                <small class="text-muted fw-bold d-block">SUBTOTAL</small>
                <h5 class="fw-bold text-dark m-0">${{ number_format($venta->subtotal, 2) }}</h5>
            </div>
            <div class="col-md-4">
                <small class="text-muted fw-bold d-block">ANTICIPO PAGADO</small>
                <h5 class="fw-bold text-success m-0">${{ number_format($venta->anticipo, 2) }}</h5>
            </div>
            <div class="col-md-4">
                <small class="text-muted fw-bold d-block text-danger">SALDO PENDIENTE</small>
                <h3 class="fw-bold text-danger m-0">${{ number_format($venta->saldo_restante, 2) }}</h3>
            </div>
        </div>
    </div>

</div>
@endsection