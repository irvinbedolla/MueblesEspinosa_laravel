@extends('layouts.app')

@section('title', 'Agenda y Rutas de Cobro - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0 px-md-2">

    <!-- ENCABEZADO Y FILTROS PRINCIPALES -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 p-md-4 mb-3">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
            <div>
                <h4 class="fw-bold text-dark m-0">
                    <i class="bi bi-geo-alt-fill text-primary me-2"></i>Agenda de Cobranza en Ruta
                </h4>
                <p class="text-muted small m-0">
                    @if($fechaSeleccionada === date('Y-m-d'))
                        Mostrando cobros programados para <strong>hoy</strong> más cuentas arrastradas de días anteriores
                    @else
                        Mostrando cobros agendados para el <strong>{{ \Carbon\Carbon::parse($fechaSeleccionada)->format('d/m/Y') }}</strong>
                    @endif
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('rutas.index', ['cobrador_id' => $cobradorId]) }}" class="btn btn-outline-primary rounded-3 fw-bold px-3 py-2">
                    <i class="bi bi-calendar-event me-1"></i> Ir a Hoy
                </a>
                <button type="button" class="btn btn-outline-secondary rounded-3 fw-bold px-3 py-2" onclick="window.location.reload();">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>

        <!-- BARRA DE FILTROS (RESTRINGIDA POR ROL) -->
        <form action="{{ route('rutas.index') }}" method="GET" class="row g-2 align-items-end">
            @if($esAdmin)
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Cobrador Asignado</label>
                    <select name="cobrador_id" class="form-select border-2" onchange="this.form.submit()">
                        <option value="todos" {{ $cobradorId === 'todos' ? 'selected' : '' }}>👥 Todos los Cobradores</option>
                        @foreach($cobradores as $c)
                            <option value="{{ $c->id }}" {{ $cobradorId == $c->id ? 'selected' : '' }}>
                                👤 {{ strtoupper($c->username) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Fecha de Visita</label>
                <input type="date" name="fecha" class="form-control border-2" value="{{ $fechaSeleccionada }}" onchange="this.form.submit()">
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Filtrar por Colonia / Zona</label>
                <select name="colonia" class="form-select border-2" onchange="this.form.submit()">
                    <option value="">Todas las colonias ({{ $colonias->count() }})</option>
                    @foreach($colonias as $col)
                        <option value="{{ $col }}" {{ $coloniaFiltro === $col ? 'selected' : '' }}>
                            {{ $col }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold">
                    <i class="bi bi-filter me-1"></i> Filtrar
                </button>
                @if(!empty($coloniaFiltro))
                    <a href="{{ route('rutas.index', ['fecha' => $fechaSeleccionada, 'cobrador_id' => $cobradorId]) }}" class="btn btn-light border rounded-3" title="Quitar filtro de colonia">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- SELECTOR RÁPIDO DE LOS PRÓXIMOS 7 DÍAS (CARGA SEMANAL) -->
    <div class="d-flex gap-2 overflow-auto pb-2 mb-3">
        @php
            $diasNombres = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        @endphp
        @for($i = 0; $i < 7; $i++)
            @php
                $fechaIter = \Carbon\Carbon::today()->addDays($i);
                $fechaStr  = $fechaIter->format('Y-m-d');
                $esActivo  = ($fechaSeleccionada === $fechaStr);
                $conteo    = ($i === 0) ? $cuentas->count() : ($agendaSemanal[$fechaStr] ?? 0);
                $etiqueta  = ($i === 0) ? 'Hoy' : (($i === 1) ? 'Mañana' : $diasNombres[$fechaIter->dayOfWeek] . ' ' . $fechaIter->format('d'));
            @endphp
            <a href="{{ route('rutas.index', ['fecha' => $fechaStr, 'cobrador_id' => $cobradorId]) }}"
               class="btn {{ $esActivo ? 'btn-primary shadow-sm' : 'btn-white bg-white border text-dark' }} rounded-4 px-3 py-2 text-nowrap d-flex align-items-center gap-2">
                <span class="fw-bold">{{ $etiqueta }}</span>
                <span class="badge {{ $esActivo ? 'bg-white text-primary' : 'bg-secondary bg-opacity-10 text-dark' }} rounded-pill">
                    {{ $conteo }}
                </span>
            </a>
        @endfor
    </div>

    <!-- RESUMEN DE LA RUTA DEL DÍA -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <small class="text-muted fw-bold text-uppercase d-block">Cuentas por Visitar</small>
                <h4 class="fw-bold text-primary m-0">{{ $cuentas->count() }} clientes</h4>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <small class="text-muted fw-bold text-uppercase d-block">Cobro Estimado (Cuotas)</small>
                <h4 class="fw-bold text-success m-0">${{ number_format($cuentas->sum('pago_cuota'), 2) }}</h4>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <small class="text-muted fw-bold text-uppercase d-block">Cartera Total en Ruta</small>
                <h4 class="fw-bold text-dark m-0">${{ number_format($cuentas->sum('saldo_restante'), 2) }}</h4>
            </div>
        </div>
    </div>

    <!-- LISTADO DE CUENTAS A COBRAR -->
    <div class="row g-3">
        @forelse($cuentas as $cta)
            @php
                $folioMostrar = $cta->venta_id_legacy ?? $cta->folio ?? 'S/F';
                $atraso = (int) $cta->dias_atraso;
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100 d-flex flex-column justify-content-between">
                    <div class="card-body p-3">
                        <!-- Cabecera Tarjeta -->
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <span class="badge bg-dark rounded-pill me-1">Vta #{{ $folioMostrar }}</span>
                                @if($atraso > 0)
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill">
                                        Atrasado {{ $atraso }} {{ $atraso === 1 ? 'día' : 'días' }}
                                    </span>
                                @elseif($atraso === 0)
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary rounded-pill">
                                        Toca Hoy
                                    </span>
                                @else
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info rounded-pill">
                                        Agendado
                                    </span>
                                @endif
                            </div>

                            @if($esAdmin)
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-person-badge me-1"></i>{{ strtoupper($cta->cobrador_nombre ?? 'Sin Asignar') }}
                                </span>
                            @endif
                        </div>

                        <!-- Nombre y Dirección -->
                        <h6 class="fw-bold text-dark mb-1">{{ $cta->cliente_nombre }}</h6>
                        <p class="text-muted small mb-2">
                            <i class="bi bi-geo-alt text-danger me-1"></i>
                            {{ $cta->calle ?: 'Sin calle' }}{{ $cta->colonia ? ', Col. ' . $cta->colonia : '' }}
                        </p>

                        @if(!empty($cta->cliente_telefono))
                            <p class="small mb-2">
                                <a href="tel:{{ $cta->cliente_telefono }}" class="text-decoration-none text-secondary fw-semibold">
                                    <i class="bi bi-telephone-fill text-success me-1"></i>{{ $cta->cliente_telefono }}
                                </a>
                            </p>
                        @endif

                        @if(!empty($cta->nota_cobranza))
                            <div class="alert alert-warning py-1 px-2 small mb-2 rounded-3">
                                <i class="bi bi-chat-left-text me-1"></i>{{ $cta->nota_cobranza }}
                            </div>
                        @endif

                        <!-- Montos -->
                        <div class="bg-light rounded-3 p-2 d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <small class="text-muted d-block extra-small">Cuota ({{ $cta->frecuencia_nombre }})</small>
                                <strong class="text-primary">${{ number_format($cta->pago_cuota, 2) }}</strong>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block extra-small">Saldo Restante</small>
                                <strong class="text-danger">${{ number_format($cta->saldo_restante, 2) }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción en Calle -->
                    <div class="card-footer bg-white border-top p-2 d-flex gap-2">
                        <a href="{{ route('abonos.show', $cta->id) }}" class="btn btn-success btn-sm rounded-3 fw-bold flex-grow-1 py-2">
                            <i class="bi bi-cash-coin me-1"></i> Abonar
                        </a>

                        <button type="button"
                                class="btn btn-outline-warning text-dark btn-sm rounded-3 fw-bold px-3 py-2"
                                onclick="abrirModalReagendar('{{ $cta->id }}', '{{ addslashes($cta->cliente_nombre) }}', '{{ $folioMostrar }}')">
                            <i class="bi bi-calendar-plus me-1"></i> Reagendar
                        </button>

                        @if($esAdmin)
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm rounded-3 px-2 py-2"
                                    title="Cambiar Cobrador"
                                    onclick="abrirModalCobrador('{{ $cta->id }}', '{{ addslashes($cta->cliente_nombre) }}', '{{ $cta->cobrador_id }}')">
                                <i class="bi bi-person-gear"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-5 text-center text-muted">
                    <i class="bi bi-check2-circle fs-1 text-success d-block mb-2"></i>
                    <h6 class="fw-bold text-dark">¡Todo al día!</h6>
                    <p class="small mb-0">No hay cuentas pendientes de cobro para la fecha y filtros seleccionados.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>

<!-- MODAL REAGENDAR VISITA (PASAR PARA OTRO DÍA) -->
<div class="modal fade" id="modalReagendar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-calendar-event text-warning me-2"></i>Reagendar Cobro
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formReagendar" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Cliente: <strong id="reagendar_cliente" class="text-dark"></strong> (Vta #<span id="reagendar_folio"></span>)
                    </p>

                    <!-- Botones rápidos de días -->
                    <label class="form-label small fw-bold text-muted">Atajos rápidos:</label>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="setFechaReagendo(1)">Mañana</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="setFechaReagendo(2)">En 2 días</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="setFechaReagendo(3)">En 3 días</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="setFechaReagendo(7)">Próx. Semana</button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nueva Fecha de Cobro (*)</label>
                        <input type="date" name="nueva_fecha" id="input_nueva_fecha" class="form-control border-2" min="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted">Motivo / Nota en ruta</label>
                        <input type="text" name="nota_cobranza" class="form-control border-2" placeholder="Ej. No se encontraba, pide pasar el miércoles">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark rounded-3 fw-bold px-4">
                        <i class="bi bi-check-circle me-1"></i> Guardar Nueva Fecha
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($esAdmin)
<!-- MODAL REASIGNAR COBRADOR (SOLO ADMINISTRADOR) -->
<div class="modal fade" id="modalAsignarCobrador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-person-gear text-primary me-2"></i>Reasignar Cobrador
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAsignarCobrador" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Cuenta de: <strong id="asignar_cliente" class="text-dark"></strong></p>
                    <label class="form-label small fw-bold text-muted">Seleccionar Nuevo Cobrador (*)</label>
                    <select name="cobrador_id" id="select_nuevo_cobrador" class="form-select border-2" required>
                        @foreach($cobradores as $c)
                            <option value="{{ $c->id }}">{{ strtoupper($c->username) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-3 fw-bold px-4">Actualizar Cobrador</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
    function abrirModalReagendar(ventaId, clienteNombre, folio) {
        document.getElementById('formReagendar').action = `/rutas/${ventaId}/reagendar`;
        document.getElementById('reagendar_cliente').innerText = clienteNombre;
        document.getElementById('reagendar_folio').innerText = folio;
        setFechaReagendo(1); // Por defecto mañana
        new bootstrap.Modal(document.getElementById('modalReagendar')).show();
    }

    function setFechaReagendo(diasSumar) {
        const fecha = new Date();
        fecha.setDate(fecha.getDate() + diasSumar);
        const yyyy = fecha.getFullYear();
        const mm = String(fecha.getMonth() + 1).padStart(2, '0');
        const dd = String(fecha.getDate()).padStart(2, '0');
        document.getElementById('input_nueva_fecha').value = `${yyyy}-${mm}-${dd}`;
    }

    function abrirModalCobrador(ventaId, clienteNombre, cobradorActualId) {
        document.getElementById('formAsignarCobrador').action = `/rutas/${ventaId}/asignar-cobrador`;
        document.getElementById('asignar_cliente').innerText = clienteNombre;
        if (cobradorActualId) {
            document.getElementById('select_nuevo_cobrador').value = cobradorActualId;
        }
        new bootstrap.Modal(document.getElementById('modalAsignarCobrador')).show();
    }
</script>
@endsection