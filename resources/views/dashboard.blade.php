@extends('layouts.app')

@section('title', 'Dashboard - Muebles Espinosa')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark m-0">Panel de Control</h3>
            <p class="text-muted small m-0">Bienvenido de nuevo, {{ $user->username }}</p>
        </div>
    </div>

    {{-- DASHBOARD PARA ADMINISTRADOR --}}
    @if($user->isAdmin())
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

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up-arrow text-primary me-2"></i> Comportamiento de Ventas Mensuales</h5>
            <canvas id="chartAdmin" height="100"></canvas>
        </div>

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
                            backgroundColor: 'rgba(3, 27, 51, 0.1)',
                            fill: true,
                            tension: 0.3
                        }]
                    }
                });
            });
        </script>

    {{-- DASHBOARD PARA VENDEDOR --}}
    @elseif($user->isVendedor())
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-bold">MIS VENTAS DEL MES</small>
                    <h3 class="fw-bold text-success my-1">{{ $data['ventas_propias_cant'] }} Unidades</h3>
                    <small class="text-muted">Total: {{ $data['ventas_propias_monto'] }}</small>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill text-success me-2"></i> Rendimiento de Ventas por Semana</h5>
            <canvas id="chartVendedor" height="100"></canvas>
        </div>

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
                            backgroundColor: '#198754'
                        }]
                    }
                });
            });
        </script>

    {{-- DASHBOARD PARA COBRADOR --}}
    @else
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-bold">COBROS PARA HOY</small>
                    <h3 class="fw-bold text-primary my-1">{{ $data['cobros_hoy_cant'] }} Clientes</h3>
                    <small class="text-muted">Monto Estimado: {{ $data['cobros_hoy_monto'] }}</small>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white" style="max-width: 500px;">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill text-warning me-2"></i> Avance de Cobranza del Día</h5>
            <canvas id="chartCobrador"></canvas>
        </div>

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
                    }
                });
            });
        </script>
    @endif
@endsection