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
                    @if($vista === 'atrasados')
                        Mostrando <strong class="text-danger">cuentas atrasadas</strong> vencidas de días anteriores
                    @elseif($fechaSeleccionada === date('Y-m-d'))
                        Mostrando cobros programados exclusivamente para <strong>hoy</strong>
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

        <!-- BARRA DE FILTROS (RESTRINGIDA POR ROL + BUSCADOR DINÁMICO EN VIVO) -->
        <form action="{{ route('rutas.index') }}" method="GET" class="row g-2 align-items-end" id="formFiltrosRuta">
            <input type="hidden" name="vista" value="{{ $vista }}">

            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Buscar Cliente / Folio</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-2 border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" id="inputBuscarDinamico" class="form-control border-2 border-start-0 border-end-0 ps-0" placeholder="Escribe nombre, folio o calle..." value="{{ $buscar }}" autocomplete="off">
                    <button type="button" id="btnLimpiarTexto" class="btn bg-white border-2 border-start-0 text-muted {{ empty($buscar) ? 'd-none' : '' }}" title="Limpiar texto" onclick="limpiarBusquedaDinamica()">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
            </div>

            @if($esAdmin)
                <div class="col-12 col-md-3">
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

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Fecha de Visita</label>
                <input type="date" name="fecha" class="form-control border-2" value="{{ $fechaSeleccionada }}"
                       onchange="this.form.querySelector('input[name=vista]').value='agenda'; this.form.submit();">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Colonia / Zona</label>
                <select name="colonia" class="form-select border-2" onchange="this.form.submit()">
                    <option value="">Todas ({{ $colonias->count() }})</option>
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
                @if(!empty($coloniaFiltro) || !empty($buscar))
                    <a href="{{ route('rutas.index', ['vista' => $vista, 'fecha' => $fechaSeleccionada, 'cobrador_id' => $cobradorId]) }}"
                       class="btn btn-light border rounded-3" title="Limpiar todos los filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- SELECTOR RÁPIDO: ATRASADOS + PRÓXIMOS 7 DÍAS -->
    <div class="d-flex gap-2 overflow-auto pb-2 mb-3">
        <!-- Pestaña Atrasados -->
        @php
            $esAtrasadosActivo = ($vista === 'atrasados');
        @endphp
        <a href="{{ route('rutas.index', ['vista' => 'atrasados', 'cobrador_id' => $cobradorId, 'buscar' => $buscar]) }}"
           class="btn {{ $esAtrasadosActivo ? 'btn-danger shadow-sm' : 'btn-white bg-white border border-danger text-danger' }} rounded-4 px-3 py-2 text-nowrap d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span class="fw-bold">Atrasados</span>
            <span class="badge {{ $esAtrasadosActivo ? 'bg-white text-danger' : 'bg-danger text-white' }} rounded-pill">
                {{ $totalAtrasados }}
            </span>
        </a>

        <!-- Pestañas Hoy, Mañana y siguientes días -->
        @php
            $diasNombres = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        @endphp
        @for($i = 0; $i < 7; $i++)
            @php
                $fechaIter = \Carbon\Carbon::today()->addDays($i);
                $fechaStr  = $fechaIter->format('Y-m-d');
                $esActivo  = ($vista === 'agenda' && $fechaSeleccionada === $fechaStr);
                $conteo    = $agendaSemanal[$fechaStr] ?? 0;
                $etiqueta  = ($i === 0) ? 'Hoy' : (($i === 1) ? 'Mañana' : $diasNombres[$fechaIter->dayOfWeek] . ' ' . $fechaIter->format('d'));
            @endphp
            <a href="{{ route('rutas.index', ['vista' => 'agenda', 'fecha' => $fechaStr, 'cobrador_id' => $cobradorId, 'buscar' => $buscar]) }}"
               class="btn {{ $esActivo ? 'btn-primary shadow-sm' : 'btn-white bg-white border text-dark' }} rounded-4 px-3 py-2 text-nowrap d-flex align-items-center gap-2">
                <span class="fw-bold">{{ $etiqueta }}</span>
                <span class="badge {{ $esActivo ? 'bg-white text-primary' : 'bg-secondary bg-opacity-10 text-dark' }} rounded-pill">
                    {{ $conteo }}
                </span>
            </a>
        @endfor
    </div>

    <!-- RESUMEN DE LA RUTA DEL DÍA (ACTUALIZA EN VIVO AL FILTRAR) -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <small class="text-muted fw-bold text-uppercase d-block">Cuentas por Visitar</small>
                <h4 class="fw-bold {{ $vista === 'atrasados' ? 'text-danger' : 'text-primary' }} m-0" id="resumen_clientes">{{ $cuentas->count() }} clientes</h4>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <small class="text-muted fw-bold text-uppercase d-block">Cobro Estimado (Cuotas)</small>
                <h4 class="fw-bold text-success m-0" id="resumen_cuotas">${{ number_format($cuentas->sum('pago_cuota'), 2) }}</h4>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <small class="text-muted fw-bold text-uppercase d-block">Cartera Total en Ruta</small>
                <h4 class="fw-bold text-dark m-0" id="resumen_cartera">${{ number_format($cuentas->sum('saldo_restante'), 2) }}</h4>
            </div>
        </div>
    </div>

    <!-- BARRA DE ACCIONES MASIVAS (SOLO ADMIN EN PESTAÑA ATRASADOS) -->
    @if($vista === 'atrasados' && $esAdmin && $cuentas->count() > 0)
        <div class="card border-danger border-opacity-25 shadow-sm rounded-4 bg-danger bg-opacity-10 p-3 mb-3">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <input type="checkbox" class="form-check-input border-2 border-danger m-0" id="check_todos_atrasados" style="width: 1.25rem; height: 1.25rem;" onchange="toggleSeleccionarTodos(this)">
                    <label for="check_todos_atrasados" class="fw-bold text-dark mb-0" style="cursor: pointer;">
                        Seleccionar visibles (<span id="lbl_seleccionados">0</span> seleccionadas)
                    </label>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-warning text-dark btn-sm rounded-3 fw-bold px-3" onclick="abrirModalMasivo('reagendar')" id="btn_masivo_reagendar" disabled>
                        <i class="bi bi-calendar-check me-1"></i> Reagendar Seleccionadas
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-3 fw-bold px-3 bg-white" onclick="abrirModalMasivo('cartera_vencida')" id="btn_masivo_vencida" disabled>
                        <i class="bi bi-archive me-1"></i> Archivar Venta(s) como Incobrables
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- LISTADO DE CUENTAS A COBRAR -->
    <div class="row g-3" id="contenedorTarjetasRuta">
        @forelse($cuentas as $cta)
            @php
                $folioMostrar = $cta->venta_id_legacy ?? $cta->folio ?? 'S/F';
                $atraso = (int) $cta->dias_atraso;
                $textoBusqueda = mb_strtolower(
                    trim(($cta->cliente_nombre ?? '') . ' ' . $folioMostrar . ' ' . ($cta->folio ?? '') . ' ' . ($cta->calle ?? '') . ' ' . ($cta->colonia ?? '') . ' ' . ($cta->cliente_telefono ?? '')),
                    'UTF-8'
                );
            @endphp
            <div class="col-12 col-md-6 col-xl-4 tarjeta-cuenta-ruta"
                 data-search="{{ $textoBusqueda }}"
                 data-cuota="{{ (float) $cta->pago_cuota }}"
                 data-saldo="{{ (float) $cta->saldo_restante }}">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100 d-flex flex-column justify-content-between">
                    <div class="card-body p-3">
                        <!-- Cabecera Tarjeta -->
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex align-items-center flex-wrap gap-1">
                                @if($vista === 'atrasados' && $esAdmin)
                                    <input type="checkbox" class="form-check-input border-2 check-cuenta-atrasada me-1" value="{{ $cta->id }}" style="width: 1.15rem; height: 1.15rem;" onchange="actualizarContadorSeleccion()">
                                @endif
                                <span class="badge bg-dark rounded-pill">Vta #{{ $folioMostrar }}</span>
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

                        @if($atraso > 0)
                            <form action="{{ route('rutas.reagendar', $cta->id) }}" method="POST" class="m-0">
                                @csrf
                                <input type="hidden" name="nueva_fecha" value="{{ date('Y-m-d') }}">
                                <input type="hidden" name="nota_cobranza" value="Movido de atrasados a ruta de hoy">
                                <button type="submit" class="btn btn-outline-primary btn-sm rounded-3 fw-bold px-2 py-2" title="Pasar cobro a Hoy">
                                    <i class="bi bi-calendar-event"></i> Hoy
                                </button>
                            </form>
                        @endif

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
                    <p class="small mb-0">No hay cuentas pendientes de cobro para la vista y filtros seleccionados.</p>
                </div>
            </div>
        @endforelse

        <!-- Mensaje cuando el buscador dinámico no encuentra coincidencias -->
        <div class="col-12 d-none" id="sinCoincidenciasBusqueda">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-5 text-center text-muted">
                <i class="bi bi-search fs-1 text-secondary d-block mb-2"></i>
                <h6 class="fw-bold text-dark">Sin coincidencias</h6>
                <p class="small mb-0">Ningún cliente, folio o calle coincide con "<strong id="textoNoEncontrado"></strong>".</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL REAGENDAR VISITA (INDIVIDUAL) -->
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

                    <label class="form-label small fw-bold text-muted">Atajos rápidos:</label>
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="setFechaReagendo(0)">Hoy</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="setFechaReagendo(1)">Mañana</button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="setFechaReagendo(2)">En 2 días</button>
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
<!-- MODAL REAGENDAR MASIVO / CARTERA VENCIDA (SOLO ADMINISTRADOR) -->
<div class="modal fade" id="modalReagendarMasivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="titulo_modal_masivo">Reorganizar Cuentas Seleccionadas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('rutas.reagendarMasivo') }}" method="POST" id="formReagendarMasivo">
                @csrf
                <input type="hidden" name="accion" id="input_accion_masiva" value="reagendar">
                <div id="contenedor_inputs_ids"></div>

                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-3">
                        Se actualizarán <strong id="lbl_total_modal_masivo">0</strong> cuentas seleccionadas.
                    </div>

                    <div class="mb-3" id="bloque_fecha_masiva">
                        <label class="form-label small fw-bold text-muted">Nueva Fecha de Cobro (*)</label>
                        <input type="date" name="nueva_fecha" id="input_fecha_masiva" class="form-control border-2" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}">
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted">Nota de seguimiento (opcional)</label>
                        <input type="text" name="nota_cobranza" class="form-control border-2" placeholder="Ej. Depuración de rezago noviembre">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-3 fw-bold px-4" id="btn_confirmar_masivo">Aplicar Cambio</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    // Normaliza texto (quita acentos y pasa a minúsculas) para búsqueda flexible
    function normalizarTexto(str) {
        return (str || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function filtrarTarjetasEnVivo() {
        const input = document.getElementById('inputBuscarDinamico');
        const btnLimpiar = document.getElementById('btnLimpiarTexto');
        const terminoRaw = input ? input.value : '';
        const termino = normalizarTexto(terminoRaw);

        if (btnLimpiar) {
            btnLimpiar.classList.toggle('d-none', terminoRaw.length === 0);
        }

        const tarjetas = document.querySelectorAll('.tarjeta-cuenta-ruta');
        let visibles = 0;
        let sumaCuotas = 0;
        let sumaSaldo = 0;

        tarjetas.forEach(tarjeta => {
            const contenido = normalizarTexto(tarjeta.getAttribute('data-search'));
            const coincide = (termino === '' || contenido.includes(termino));

            if (coincide) {
                tarjeta.classList.remove('d-none');
                visibles++;
                sumaCuotas += parseFloat(tarjeta.getAttribute('data-cuota') || 0);
                sumaSaldo += parseFloat(tarjeta.getAttribute('data-saldo') || 0);
            } else {
                tarjeta.classList.add('d-none');
                // Desmarcar si estaba seleccionada y quedó oculta por el filtro
                const cb = tarjeta.querySelector('.check-cuenta-atrasada');
                if (cb && cb.checked) cb.checked = false;
            }
        });

        // Actualizar tarjetas de resumen en vivo
        const formatoMoneda = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const elClientes = document.getElementById('resumen_clientes');
        const elCuotas = document.getElementById('resumen_cuotas');
        const elCartera = document.getElementById('resumen_cartera');

        if (elClientes) elClientes.innerText = `${visibles} ${visibles === 1 ? 'cliente' : 'clientes'}`;
        if (elCuotas) elCuotas.innerText = `$${formatoMoneda.format(sumaCuotas)}`;
        if (elCartera) elCartera.innerText = `$${formatoMoneda.format(sumaSaldo)}`;

        // Mostrar/ocultar aviso de "Sin coincidencias"
        const avisoVacio = document.getElementById('sinCoincidenciasBusqueda');
        const textoNoEncontrado = document.getElementById('textoNoEncontrado');
        if (avisoVacio && tarjetas.length > 0) {
            if (visibles === 0) {
                avisoVacio.classList.remove('d-none');
                if (textoNoEncontrado) textoNoEncontrado.innerText = terminoRaw;
            } else {
                avisoVacio.classList.add('d-none');
            }
        }

        // Sincronizar checkbox maestro y contador de selección
        const checkTodos = document.getElementById('check_todos_atrasados');
        if (checkTodos) checkTodos.checked = false;
        actualizarContadorSeleccion();
    }

    function limpiarBusquedaDinamica() {
        const input = document.getElementById('inputBuscarDinamico');
        if (input) {
            input.value = '';
            input.focus();
            filtrarTarjetasEnVivo();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const inputBuscar = document.getElementById('inputBuscarDinamico');
        if (inputBuscar) {
            inputBuscar.addEventListener('input', filtrarTarjetasEnVivo);
            // Evita recargar la página al presionar Enter dentro del buscador dinámico
            inputBuscar.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filtrarTarjetasEnVivo();
                }
            });
        }
    });

    function abrirModalReagendar(ventaId, clienteNombre, folio) {
        document.getElementById('formReagendar').action = `{{ url('rutas') }}/${ventaId}/reagendar`;
        document.getElementById('reagendar_cliente').innerText = clienteNombre;
        document.getElementById('reagendar_folio').innerText = folio;
        setFechaReagendo(1);
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
        document.getElementById('formAsignarCobrador').action = `{{ url('rutas') }}/${ventaId}/asignar-cobrador`;
        document.getElementById('asignar_cliente').innerText = clienteNombre;
        if (cobradorActualId) {
            document.getElementById('select_nuevo_cobrador').value = cobradorActualId;
        }
        new bootstrap.Modal(document.getElementById('modalAsignarCobrador')).show();
    }

    function toggleSeleccionarTodos(source) {
        document.querySelectorAll('.tarjeta-cuenta-ruta:not(.d-none) .check-cuenta-atrasada').forEach(cb => {
            cb.checked = source.checked;
        });
        actualizarContadorSeleccion();
    }

    function actualizarContadorSeleccion() {
        const seleccionados = document.querySelectorAll('.check-cuenta-atrasada:checked').length;
        const lbl = document.getElementById('lbl_seleccionados');
        if (lbl) lbl.innerText = seleccionados;

        const btnReagendar = document.getElementById('btn_masivo_reagendar');
        const btnVencida = document.getElementById('btn_masivo_vencida');
        if (btnReagendar) btnReagendar.disabled = (seleccionados === 0);
        if (btnVencida) btnVencida.disabled = (seleccionados === 0);
    }

    function abrirModalMasivo(accion) {
        const checks = document.querySelectorAll('.check-cuenta-atrasada:checked');
        if (checks.length === 0) return;

        const contenedor = document.getElementById('contenedor_inputs_ids');
        contenedor.innerHTML = '';
        checks.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'venta_ids[]';
            input.value = cb.value;
            contenedor.appendChild(input);
        });

        document.getElementById('input_accion_masiva').value = accion;
        document.getElementById('lbl_total_modal_masivo').innerText = checks.length;

        const bloqueFecha = document.getElementById('bloque_fecha_masiva');
        const inputFecha = document.getElementById('input_fecha_masiva');
        const titulo = document.getElementById('titulo_modal_masivo');
        const btnConfirmar = document.getElementById('btn_confirmar_masivo');

        if (accion === 'cartera_vencida') {
            titulo.innerText = 'Mandar a Cartera Vencida (Incobrables)';
            bloqueFecha.style.display = 'none';
            inputFecha.required = false;
            btnConfirmar.className = 'btn btn-danger rounded-3 fw-bold px-4';
            btnConfirmar.innerText = 'Confirmar y Sacar de Ruta';
        } else {
            titulo.innerText = 'Reagendar Cuentas Seleccionadas';
            bloqueFecha.style.display = 'block';
            inputFecha.required = true;
            btnConfirmar.className = 'btn btn-primary rounded-3 fw-bold px-4';
            btnConfirmar.innerText = 'Guardar Nueva Fecha';
        }

        new bootstrap.Modal(document.getElementById('modalReagendarMasivo')).show();
    }
</script>
@endsection