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

                    <div class="w-100" id="bloqueCierreFechas">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small text-muted text-uppercase fw-bold d-block text-start mb-1">Desde</label>
                                <input type="date" id="fechas_fecha1" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted text-uppercase fw-bold d-block text-start mb-1">Hasta</label>
                                <input type="date" id="fechas_fecha2" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <button type="button" onclick="abrirReporteFechas()" class="btn btn-warning text-white rounded-3 w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-search me-1"></i> Generar Reporte
                        </button>
                    </div>
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

                        <div class="w-100" id="bloqueCierreUsuario">
                            <div class="mb-2">
                                <select id="usuario_cobrador_id" class="form-select">
                                    <option value="" selected>Seleccione Cobrador...</option>
                                    @foreach($cobradores as $cob)
                                        <option value="{{ $cob->id }}">{{ $cob->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <input type="date" id="usuario_fecha1" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-6">
                                    <input type="date" id="usuario_fecha2" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                            <button type="button" onclick="abrirReporteUsuario()" class="btn btn-info text-white rounded-3 w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-person-check me-1"></i> Filtrar Usuario
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>

    <!-- 4. NUEVO APARTADO: CIERRE EJECUTIVO -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-5 d-flex align-items-start gap-3">
                    <div class="bg-dark text-white p-3 rounded-4 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="bi bi-bar-chart-line-fill fs-2"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="fw-bold text-dark mb-0">Cierre Ejecutivo</h5>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill">Detallado</span>
                        </div>
                        <p class="text-muted small mb-0">
                            Reporte gerencial con indicadores de efectividad, desglose por usuario (cobrado vs. pendiente de pago), métodos de pago (efectivo, transferencia, tarjeta) y arqueo consolidado.
                        </p>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="row g-2 align-items-end">
                        <div class="{{ $puedeVerTodos ? 'col-6 col-md-3' : 'col-6 col-md-4' }}">
                            <label class="form-label small text-muted text-uppercase fw-bold mb-1">Desde</label>
                            <input type="date" id="ejec_fecha1" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="{{ $puedeVerTodos ? 'col-6 col-md-3' : 'col-6 col-md-4' }}">
                            <label class="form-label small text-muted text-uppercase fw-bold mb-1">Hasta</label>
                            <input type="date" id="ejec_fecha2" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>

                        @if($puedeVerTodos)
                            <div class="col-12 col-md-3">
                                <label class="form-label small text-muted text-uppercase fw-bold mb-1">Usuario</label>
                                <select id="ejec_cobrador_id" class="form-select">
                                    <option value="">Todos los usuarios</option>
                                    @foreach($cobradores as $cob)
                                        <option value="{{ $cob->id }}">{{ $cob->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="{{ $puedeVerTodos ? 'col-12 col-md-3' : 'col-12 col-md-4' }}">
                            <button type="button" onclick="abrirReporteEjecutivo()" class="btn btn-dark rounded-3 w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-file-earmark-bar-graph me-1"></i> Generar Ejecutivo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function abrirReporteFechas() {
            const f1 = document.getElementById('fechas_fecha1').value;
            const f2 = document.getElementById('fechas_fecha2').value;
            if (!f1 || !f2) {
                alert('Selecciona ambas fechas para generar el reporte.');
                return;
            }
            const url = `{{ route('cierres.pdf') }}?fecha1=${encodeURIComponent(f1)}&fecha2=${encodeURIComponent(f2)}`;
            window.open(url, '_blank');
        }

        function abrirReporteUsuario() {
            const cobradorId = document.getElementById('usuario_cobrador_id').value;
            const f1 = document.getElementById('usuario_fecha1').value;
            const f2 = document.getElementById('usuario_fecha2').value;
            if (!cobradorId) {
                alert('Por favor selecciona un cobrador de la lista.');
                document.getElementById('usuario_cobrador_id').focus();
                return;
            }
            const url = `{{ route('cierres.pdf') }}?cobrador_id=${encodeURIComponent(cobradorId)}&fecha1=${encodeURIComponent(f1)}&fecha2=${encodeURIComponent(f2)}`;
            window.open(url, '_blank');
        }

        function abrirReporteEjecutivo() {
            const f1 = document.getElementById('ejec_fecha1').value;
            const f2 = document.getElementById('ejec_fecha2').value;
            const selectCob = document.getElementById('ejec_cobrador_id');
            const cobradorId = selectCob ? selectCob.value : '';
            if (!f1 || !f2) {
                alert('Selecciona ambas fechas para generar el reporte ejecutivo.');
                return;
            }
            let url = `{{ route('cierres.ejecutivo') }}?fecha1=${encodeURIComponent(f1)}&fecha2=${encodeURIComponent(f2)}`;
            if (cobradorId) {
                url += `&cobrador_id=${encodeURIComponent(cobradorId)}`;
            }
            window.open(url, '_blank');
        }
    </script>

@endsection