@extends('layouts.app')

@section('title', 'Dashboard - Muebles Espinosa')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark m-0">Panel de Control</h3>
            <p class="text-muted small m-0">Bienvenido de nuevo, {{ $user->username }}</p>
        </div>
        <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm fw-bold">
            <i class="bi bi-calendar3 me-1 text-primary"></i> {{ date('d/m/Y') }}
        </span>
    </div>

    {{-- ==========================================
         DASHBOARD PARA ADMINISTRADOR
    ========================================== --}}
    @if(method_exists($user, 'isAdmin') && $user->isAdmin())
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-bold">VENTAS DEL MES (MONTO)</small>
                    <h3 class="fw-bold text-success my-1">{{ $data['ventas_mes_monto'] }}</h3>
                    <small class="text-muted">{{ $data['ventas_mes_cant'] }} Ventas realizadas</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-bold">ABONOS RECUPERADOS</small>
                    <h3 class="fw-bold text-primary my-1">{{ $data['abonos_mes_monto'] }}</h3>
                    <small class="text-muted">{{ $data['abonos_mes_cant'] }} Abonos cobrados</small>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <!-- Gráfica de Comportamiento de Ventas (30 Días) -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3">
                        <i class="bi bi-graph-up-arrow text-primary me-2"></i> Comportamiento de Ventas (Últimos 30 días)
                    </h5>
                    <canvas id="chartAdmin" height="120"></canvas>
                </div>
            </div>

            <!-- Tabla Resumen: Rutas del Día / Cobros Asignados por Cobrador -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-map-fill text-warning me-2"></i> Rutas del Día</h5>
                        <span class="badge bg-light text-dark border">{{ date('d/m/Y') }}</span>
                    </div>

                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="small text-muted text-uppercase border-bottom">
                                    <th>Cobrador / Ruta</th>
                                    <th class="text-center">Visitas</th>
                                    <th class="text-end">Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data['rutas_hoy'] as $ruta)
                                    <tr>
                                        <td>
                                            <span class="fw-bold d-block text-dark">{{ $ruta->cobrador_nombre ?? 'Sin Cobrador' }}</span>
                                            <small class="text-muted">{{ $ruta->nombre_ruta ?? 'Ruta del Día' }}</small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill px-3 py-1">
                                                {{ $ruta->total_clientes ?? 0 }} Visitas
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if(isset($ruta->estatus) && strtolower($ruta->estatus) === 'completada')
                                                <span class="badge bg-success rounded-pill px-3 py-1">COMPLETADA</span>
                                            @else
                                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1">EN RUTA</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4 small">
                                            <i class="bi bi-calendar-x fs-3 d-block text-secondary mb-1"></i>
                                            No hay rutas asignadas para el día de hoy.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('chartAdmin').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($data['chart_labels']),
                        datasets: [{
                            label: 'Ventas ($)',
                            data: @json($data['chart_ventas']),
                            borderColor: '#031b33',
                            backgroundColor: 'rgba(3, 27, 51, 0.08)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } }
                    }
                });
            });
        </script>

    {{-- ==========================================
         DASHBOARD PARA VENDEDOR
    ========================================== --}}
    @elseif(method_exists($user, 'isVendedor') && $user->isVendedor())
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-bold">MIS VENTAS DEL MES</small>
                    <h3 class="fw-bold text-success my-1">{{ $data['ventas_propias_cant'] }} Unidades</h3>
                    <small class="text-muted">Total generado: {{ $data['ventas_propias_monto'] }}</small>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill text-success me-2"></i> Rendimiento de Ventas por Semana</h5>
            <canvas id="chartVendedor" height="100"></canvas>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('chartVendedor').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: @json($data['chart_labels']),
                        datasets: [{
                            label: 'Cantidad de Ventas',
                            data: @json($data['chart_rendimiento']),
                            backgroundColor: '#198754',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } }
                    }
                });
            });
        </script>

    {{-- ==========================================
         DASHBOARD PARA COBRADOR
    ========================================== --}}
    @else
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-bold">CLIENTES ASIGNADOS HOY</small>
                    <h3 class="fw-bold text-primary my-1">{{ $data['cobros_hoy_cant'] }} Visitas</h3>
                    <small class="text-muted">Ruta de cobro activa</small>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <!-- Gráfica de Avance -->
            <div class="col-md-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill text-warning me-2"></i> Avance de Cobranza</h5>
                    <div style="max-height: 250px;" class="d-flex justify-content-center">
                        <canvas id="chartCobrador"></canvas>
                    </div>
                </div>
            </div>

            <!-- Listado Operativo del Cobrador (Sin Montos) -->
            <div class="col-md-7">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i> Lista de Visitas del Día</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="small text-muted text-uppercase">
                                    <th>Cliente</th>
                                    <th>Dirección</th>
                                    <th class="text-end">Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data['mi_ruta_hoy'] as $visita)
                                    <tr>
                                        <td class="fw-bold text-dark">{{ $visita->cliente_nombre }}</td>
                                        <td class="small text-muted">{{ $visita->direccion }}</td>
                                        <td class="text-end">
                                            @if($visita->status === 'Pagado')
                                                <span class="badge bg-success rounded-pill">Cobrado</span>
                                            @else
                                                <span class="badge bg-warning text-dark rounded-pill">Pendiente</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4 small">No tienes clientes programados en tu ruta para hoy.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('chartCobrador').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completados', 'Pendientes'],
                        datasets: [{
                            data: @json($data['chart_cobranza_hoy']),
                            backgroundColor: ['#198754', '#ffc107']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            });
        </script>
    @endif
@endsection