@extends('layouts.app')

@section('title', 'Módulo de Cierres de Caja - Muebles Espinosa')

@section('content')

@php
        $puedeVerTodos = auth()->user()->tienePermiso('cierres.all');
        $colClass = $puedeVerTodos ? 'col-md-4' : 'col-md-6';
    @endphp

    <!-- TARJETAS CON ALTURA UNIFORME -->
    <div class="row g-4 align-items-stretch mb-4">
        
        <!-- 1. CIERRE DIARIO -->
        <div class="{{ $colClass }}">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-3">
                            <i class="bi bi-calendar-check fs-2"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Cierre Diario</h5>
                        <p class="text-muted small mb-4">
                            {{ $puedeVerTodos ? 'Genera el corte de caja global del día de hoy en tiempo real.' : 'Genera tu corte de caja personal del día de hoy en tiempo real.' }}
                        </p>
                    </div>

                    <div>
                        <div class="p-3 mb-3 bg-light rounded-3 text-muted small border border-dashed">
                            <i class="bi bi-clock-history me-1"></i> Fecha del reporte: <strong>{{ date('d/m/Y') }}</strong>
                        </div>
                        <a href="{{ route('cierres.pdf', ['fecha1' => date('Y-m-d'), 'fecha2' => date('Y-m-d')]) }}" target="_blank" class="btn btn-primary rounded-3 w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Imprimir Cierre Hoy
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. CIERRE POR FECHAS -->
        <div class="{{ $colClass }}">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle d-inline-block mb-3">
                            <i class="bi bi-calendar-range fs-2"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Cierre por Fechas</h5>
                        <p class="text-muted small mb-3">
                            {{ $puedeVerTodos ? 'Filtra por un rango específico de fechas de cobro.' : 'Filtra tus cobros en un rango específico de fechas.' }}
                        </p>
                    </div>

                    <form action="{{ route('cierres.pdf') }}" method="GET" target="_blank" class="w-100 form-reporte-cierre">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small text-muted text-uppercase fw-bold d-block text-start mb-1">Desde</label>
                                <input type="date" name="fecha1" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted text-uppercase fw-bold d-block text-start mb-1">Hasta</label>
                                <input type="date" name="fecha2" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning text-white rounded-3 w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-search me-1"></i> Generar Reporte
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 3. CIERRE POR USUARIO (SOLO CON PERMISO cierres.all) -->
        @if($puedeVerTodos)
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                        <div>
                            <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle d-inline-block mb-3">
                                <i class="bi bi-person-badge fs-2"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-2">Cierre por Usuario</h5>
                            <p class="text-muted small mb-3">Filtrar cobros realizados por un cobrador específico.</p>
                        </div>

                        <form action="{{ route('cierres.pdf') }}" method="GET" target="_blank" class="w-100 form-reporte-cierre">
                            <div class="mb-2">
                                <select name="cobrador_id" class="form-select" required>
                                    <option value="" selected>Seleccione Cobrador...</option>
                                    @foreach($cobradores as $cob)
                                        <option value="{{ $cob->id }}">{{ $cob->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <input type="date" name="fecha1" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-6">
                                    <input type="date" name="fecha2" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-info text-white rounded-3 w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-person-check me-1"></i> Filtrar Usuario
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

    </div>
@endsection