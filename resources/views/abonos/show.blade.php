@extends('layouts.app')

@section('title', 'Registro de Abono - Muebles Espinosa')

@section('content')
<style>
    .stat-box { padding: 1rem; border-radius: 12px; background-color: #fff; border: 1px solid #eef2f5; box-shadow: 0 4px 6px rgba(0,0,0,0.01); text-align: center; height: 100%; }
    .stat-label { font-size: 0.72rem; color: #8a92a6; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .stat-value { font-size: 1.25rem; font-weight: 800; color: #232d42; margin-top: 0.25rem; display: block; }
    
    /* Estilos del autocompletado del Modal */
    .search-results-list { max-height: 250px; overflow-y: auto; }
    .search-result-item { cursor: pointer; transition: background 0.2s ease; }
    .search-result-item:hover { background-color: #f8f9fa; }
</style>

<div class="container-fluid px-4 mt-4 mb-5">

    <!-- BARRA SUPERIOR DE ACCIONES -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('abonos.index') }}" class="btn btn-outline-secondary rounded-3 btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Regresar al Listado
        </a>

        <!-- BOTÓN PARA ABRIR EL MODAL DE BÚSQUEDA -->
        <button type="button" class="btn btn-primary rounded-3 btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalBuscarCliente">
            <i class="bi bi-search me-1"></i> Buscar Otro Cliente
        </button>
    </div>

    <!-- ENCABEZADO DE LA CUENTA -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-sm-8">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-2 mb-2 rounded-pill">
                        VENTA #{{ $venta->folio ?? $venta->venta_id_legacy ?? $venta->id }}
                    </span>
                    <h3 class="fw-bold text-dark mb-1">{{ $cliente->nombre ?? 'Sin Cliente' }}</h3>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                        {{ ($cliente->colonia ? $cliente->colonia . ', ' : '') . ($cliente->ciudad ?? '') }}
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

    <!-- BLOQUE: FECHA DE APERTURA Y TELÉFONO DE CONTACTO -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 me-3">
                        <i class="bi bi-calendar-event fs-3"></i>
                    </div>
                    <div>
                        <span class="stat-label text-uppercase text-muted fw-bold d-block" style="font-size: 0.75rem;">Fecha de Apertura</span>
                        <strong class="stat-value text-dark fs-5">
                            {{ \Carbon\Carbon::parse($venta->fecha_venta ?? $venta->created_at)->format('d/m/Y') }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3">
                    <label class="stat-label text-uppercase text-muted fw-bold mb-2 d-block" style="font-size: 0.75rem;">Teléfono de Contacto</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-2 border-end-0"><i class="bi bi-phone text-muted"></i></span>
                        <input type="text" id="inputTelefonoCliente" class="form-control border-2 border-start-0 border-end-0" value="{{ $cliente->telefono ?? $venta->cliente_telefono ?? '' }}" placeholder="Ingrese teléfono...">
                        <button class="btn btn-primary px-3" type="button" id="btnGuardarTelefono" onclick="actualizarTelefonoCliente('{{ $cliente->id ?? $venta->cliente_id }}')">
                            <i class="bi bi-floppy-fill"></i>
                        </button>
                    </div>
                    <small id="msgTelefonoStatus" class="d-block mt-1 text-success fw-bold" style="font-size: 0.75rem; display:none;"></small>
                </div>
            </div>
        </div>
    </div>

    <!-- BLOQUE: OPERACIONES EN CAMPO -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <span class="stat-label text-uppercase text-muted fw-bold d-block mb-3" style="font-size: 0.75rem;">
                <i class="bi bi-geo-alt me-1"></i> Operaciones en Campo
            </span>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-light border fw-bold rounded-3 px-3 shadow-sm text-secondary" id="btnFijarGPS" onclick="capturarUbicacionGPS('{{ $cliente->id ?? $venta->cliente_id }}')">
                    <i class="bi bi-pin-map-fill text-danger me-1"></i> Fijar GPS
                </button>

                <button type="button" class="btn btn-primary bg-opacity-10 text-primary border-0 fw-bold rounded-3 px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEvidencias">
                    <i class="bi bi-camera-fill me-1"></i> Evidencias
                </button>
            </div>
            <div id="statusGPS" class="mt-2 text-muted small"></div>
        </div>
    </div>

    <!-- CAJAS ESTADÍSTICAS DE LA CUENTA -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box"><span class="stat-label">Total Venta</span><span class="stat-value">${{ number_format($venta->total, 2) }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box"><span class="stat-label">Total Abonado</span><span class="stat-value text-success">${{ number_format($suma, 2) }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box"><span class="stat-label">Saldo Restante</span><span class="stat-value text-danger">${{ number_format($aux4, 2) }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box bg-light"><span class="stat-label">Último Pago</span><span class="stat-value text-dark">${{ number_format($ultimoAbono->a_pago ?? 0, 2) }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box bg-light"><span class="stat-label">Fecha Pago</span><span class="stat-value text-secondary fs-6 mt-2">{{ $ultimoAbono->a_fecha ?? 'N/A' }}</span></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-box"><span class="stat-label">Descuento</span><span class="stat-value text-warning">${{ number_format($ventaDescuento, 2) }}</span></div></div>
    </div>

    <!-- FORMULARIO PARA REGISTRAR ABONO O LIQUIDAR -->
    <form method="POST" action="{{ route('abonos.store') }}" id="formRegistrarAbono" onsubmit="return validarMontoAbono()">
        @csrf
        <input type="hidden" name="id" value="{{ $venta->id }}">
        
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                @if($aux4 > 0 && $venta->status != 'Pagado')
                    <h5 class="fw-bold text-primary mb-4"><i class="bi bi-wallet2 me-2"></i>Registrar Abono o Liquidación</h5>
                    
                    <div class="row g-3 mb-3">
                        <!-- MONTO DEL ABONO -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Monto del Abono (Máx: ${{ number_format($aux4, 2) }})</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="abono" id="abono" class="form-control form-control-lg fw-bold text-dark" required step="any" placeholder="0.00" data-restante="{{ $aux4 }}">
                            </div>
                        </div>

                    </div>

                    <div class="row g-3 mb-4">
                        <!-- FECHA DEL ABONO (NUEVO CAMPO) -->
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Fecha del Abono</label>
                            <input type="date" name="fecha" id="fechaAbono" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <!-- N° DE PAGO -->
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">N° de Pago</label>
                            <input type="text" name="npago" class="form-control" value="{{ $ultimoNPago }}">
                        </div>

                        <!-- FORMA DE PAGO -->
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

                        <!-- ACCIÓN / LIQUIDACIÓN -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Acción</label>
                            <select name="tipo" id="selectTipoAccion" class="form-select fw-bold text-primary" onchange="evaluarAccionLiquidar()">
                                <option value="1" selected>Abono Normal</option>
                                <option value="2">Liquidar Venta</option>
                            </select>
                        </div>

                        <!-- TIPO DE REAGENDADO -->
                        <div class="col-md-4" id="contenedorTipoReagendar">
                            <label class="form-label small fw-bold text-muted text-uppercase">Tipo de Reagendado</label>
                            <select name="tipo_reagendar" id="selectTipoReagendar" class="form-select" onchange="evaluarTipoReagendado()">
                                <option value="auto" selected>Reagenda Automática (+Frecuencia)</option>
                                <option value="fecha">Elegir Fecha Manual</option>
                            </select>
                        </div>

                        <!-- CAMPO DE FECHA DE REAGENDA MANUAL (OCULTO POR DEFECTO) -->
                        <div class="col-md-4" id="contenedorFechaReagendarManual" style="display: none;">
                            <label class="form-label small fw-bold text-muted text-uppercase">Fecha Próximo Cobro</label>
                            <input type="date" name="fecha_reagendar" id="inputFechaReagendarManual" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <button class="btn btn-primary btn-lg w-100 rounded-3 py-3 fw-bold shadow-sm" type="submit">
                        <i class="bi bi-check-circle-fill me-2"></i> GUARDAR ABONO
                    </button>
                @else
                    <div class="alert alert-success border-0 text-center mb-0 p-4">
                        <h5 class="fw-bold text-success mb-0"><i class="bi bi-patch-check-fill me-2"></i>¡CUENTA TOTALMENTE LIQUIDADA!</h5>
                    </div>
                @endif
            </div>
        </div>
    </form>

    <!-- BARRA DE BOTONES DE ACCIÓN Y AGENDA DE COBRO  -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-2">
                        <!-- 1. NOTAS -->
                        <button type="button" class="btn btn-light border fw-bold rounded-3 px-3 shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalNotas">
                            <i class="bi bi-journal-text text-primary me-1"></i> Notas
                        </button>

                        <!-- 2. AGENDAR -->
                        <button type="button" class="btn btn-light border fw-bold rounded-3 px-3 shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalAgendar">
                            <i class="bi bi-calendar-plus text-warning me-1"></i> Agendar
                        </button>

                        <!-- 3. PRECIOS -->
                        <button type="button" class="btn btn-light border fw-bold rounded-3 px-3 shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalPrecios">
                            <i class="bi bi-list-columns-reverse text-success me-1"></i> Precios
                        </button>

                        <!-- 4. HISTORIAL -->
                        <button type="button" class="btn btn-light border fw-bold rounded-3 px-3 shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalHistorial">
                            <i class="bi bi-clock-history text-info me-1"></i> Historial
                        </button>

                        <!-- 5. APLICAR DESC. -->
                        <button type="button" class="btn btn-light border fw-bold rounded-3 px-3 shadow-sm text-warning" data-bs-toggle="modal" data-bs-target="#modalDescuento">
                            <i class="bi bi-gift-fill me-1"></i> APLICAR DESC.
                        </button>
                    </div>
                </div>

                <!-- AGENDA DE COBRO (Panel Lateral Derecho) -->
                <div class="col-lg-4 text-lg-end border-start-lg">
                    <span class="stat-label text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 0.72rem;">AGENDA DE COBRO</span>
                    <div class="small fw-bold text-dark">
                        <i class="bi bi-calendar-week text-danger me-1"></i> Día de Cobro: 
                        <span class="text-uppercase">{{ !empty($diaCobro) ? $diaCobro : 'NO DEFINIDO' }}</span>
                    </div>
                    <div class="small fw-bold text-dark mt-1">
                        <i class="bi bi-alarm text-success me-1"></i> Próximo Abono: 
                        <span class="badge bg-success bg-opacity-10 text-success fw-bold fs-6 px-2 py-1">
                            {{ !empty($proximoPago) ? \Carbon\Carbon::parse($proximoPago)->format('d / m / Y') : 'SIN FECHA' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BLOQUE 1: COBRADOR Y VENDEDOR ASIGNADO (Imagen 1) -->
    <div class="row g-3 mb-4">
        <!-- COBRADOR ASIGNADO -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="stat-label text-uppercase text-muted fw-bold d-block" style="font-size: 0.72rem;">COBRADOR ASIGNADO</span>
                        <strong class="stat-value text-dark fs-5 mt-1 d-flex align-items-center">
                            <i class="bi bi-person-badge text-primary me-2"></i> {{ $cobradorAsignado }}
                        </strong>
                    </div>
                    <!-- Botón circular azul para abrir modal de Reasignar Cobrador -->
                    <button type="button" class="btn btn-primary rounded-circle p-2 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" data-bs-toggle="modal" data-bs-target="#modalReasignarCobrador" title="Cambiar Cobrador">
                        <i class="bi bi-arrow-repeat fs-5"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- VENDEDOR ASIGNADO -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3">
                    <span class="stat-label text-uppercase text-muted fw-bold d-block" style="font-size: 0.72rem;">VENDEDOR ASIGNADO</span>
                    <strong class="stat-value text-dark fs-5 mt-1 d-flex align-items-center">
                        <i class="bi bi-person-check text-secondary me-2"></i> {{ $vendedorAsignado }}
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <!-- BLOQUE 2: ARTÍCULOS A LIQUIDAR (Imagen 1 y 3) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold text-dark mb-4 d-flex align-items-center">
                <i class="bi bi-box-seam me-2 text-primary"></i> 
                Artículos a Liquidar (Mes de Amortización: {{ $mesesAmortizacion }})
            </h5>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-uppercase small text-muted border-bottom">
                            <th style="width: 120px;">CANTIDAD</th>
                            <th>PRODUCTO</th>
                            <th>MODELO</th>
                            <th>N° SERIE</th>
                            <th class="text-end">PRECIO LIQUIDACIÓN MES {{ $mesesAmortizacion }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($detallesArticulos as $art)
                            @php
                                // Cálculo de escala de descuento según mes de amortización
                                $escalas = [1 => 0.40, 2 => 0.35, 3 => 0.30, 4 => 0.25, 5 => 0.20, 6 => 0.15, 7 => 0.10, 8 => 0.05];
                                $pctDesc = $escalas[$mesesAmortizacion] ?? 0.00;
                                $precioUnitario = floatval($art->precio_unitario ?? 0);
                                $precioLiquidacion = $precioUnitario - ($precioUnitario * $pctDesc);
                            @endphp
                            <tr>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-2 rounded-pill">
                                        {{ $art->cantidad ?? 1 }} pza
                                    </span>
                                </td>
                                <td class="fw-bold text-dark text-uppercase">
                                    {{ $art->nombre_articulo ?? 'Producto' }}
                                </td>
                                <td class="text-secondary text-uppercase">
                                    {{ $art->modelo ?? 'N/A' }}
                                </td>
                                <td class="text-muted">
                                    {{ $art->numero_serie ?? '-' }}
                                </td>
                                <td class="text-end fw-bold text-primary fs-5">
                                    ${{ number_format($precioLiquidacion, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No se encontraron artículos registrados en los detalles de esta venta.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>


<!-- ==========================================
     MODAL 1: BITÁCORA DE NOTAS 
========================================== -->
<div class="modal fade" id="modalNotas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-journal-text text-primary me-2 fs-4"></i> Bitácora de Notas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('abonos.notas.store') }}" method="POST">
                @csrf
                <input type="hidden" name="venta_id" value="{{ $venta->id }}">
                <div class="modal-body p-4">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Comentarios de la Venta</label>
                    <textarea name="notas" class="form-control rounded-3 p-3" rows="5" placeholder="Escriba aquí las anotaciones o detalles del cliente...">{{ $venta->notas ?? '' }}</textarea>
                </div>
                <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3 px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm">
                        <i class="bi bi-floppy me-1"></i> Guardar Notas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ==========================================
     MODAL 2: REAGENDAR SIGUIENTE VISITA 
========================================== -->
<div class="modal fade" id="modalAgendar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Reagendar Siguiente Visita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('abonos.reagendar.store') }}" method="POST">
                @csrf
                <input type="hidden" name="venta_id" value="{{ $venta->id }}">
                <div class="modal-body p-4">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Nueva Fecha de Visita</label>
                    <input type="date" name="proximo_pago" class="form-control form-control-lg rounded-3" value="{{ $venta->proximo_pago ?? date('Y-m-d') }}" required>
                </div>
                <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                    <button type="submit" class="btn btn-warning text-white rounded-3 px-4 fw-bold shadow-sm w-100 py-2">
                        Confirmar Fecha
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ==========================================
     MODAL 3: ESCALA DE PRECIOS Y DESCUENTOS 
========================================== -->
<div class="modal fade" id="modalPrecios" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Escala de Descuentos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                @php
                    // Obtener los detalles de los productos pertenecientes a esta venta
                    $detallesVenta = DB::table('venta_detalles')->where('venta_id', $venta->id)->get();
                @endphp

                @forelse($detallesVenta as $art)
                    <div class="bg-light p-3 rounded-3 mb-3 border">
                        <strong class="text-dark">Artículo:</strong> {{ $art->nombre_articulo ?? 'Producto' }} 
                        <span class="text-muted ms-2">|</span> 
                        <strong class="text-dark ms-2">Precio Base:</strong> ${{ number_format($art->precio_unitario, 2) }}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-borderless table-striped align-middle mb-4">
                            <thead class="border-bottom">
                                <tr class="text-muted small text-uppercase">
                                    <th>Mes</th>
                                    <th class="text-center">Descuento</th>
                                    <th class="text-end">Precio Líquido</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $porcentajes = [1 => 40, 2 => 35, 3 => 30, 4 => 25, 5 => 20, 6 => 15, 7 => 10, 8 => 5];
                                    $pBase = floatval($art->precio_unitario);
                                @endphp
                                @foreach($porcentajes as $mes => $pct)
                                    @php
                                        $descMonto = $pBase * ($pct / 100);
                                        $pLiquido = $pBase - $descMonto;
                                    @endphp
                                    <tr>
                                        <td class="fw-bold text-secondary">Mes {{ $mes }}</td>
                                        <td class="text-center text-success fw-bold">-{{ $pct }}%</td>
                                        <td class="text-end fw-bold text-dark">${{ number_format($pLiquido, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No se encontraron artículos registrados para calcular la escala.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>


<!-- ==========================================
     MODAL HISTORIAL DE ABONOS CON NOMBRE DE COBRADOR
========================================== -->
<div class="modal fade" id="modalHistorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-clock-history text-primary me-2"></i> Historial de Abonos Recibidos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-uppercase small text-muted border-bottom">
                                <th>N° Pago</th>
                                <th>Fecha / Hora</th>
                                <th>Cobrador</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($historialAbonos as $ab)
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border px-3 py-2 fw-bold">#{{ $ab->a_npago }}</span>
                                    </td>
                                    <td class="small text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        {{ \Carbon\Carbon::parse($ab->a_fecha ?? $ab->created_at)->format('d/m/Y h:i A') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-2 rounded-pill">
                                            <i class="bi bi-person-badge me-1"></i> {{ $ab->nombre_cobrador }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-success fs-6">
                                        ${{ number_format($ab->a_pago, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No existen abonos registrados en el historial para esta venta.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 5: APLICAR BONO DESCUENTO (Imagen 6)
========================================== -->
<div class="modal fade" id="modalDescuento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">Aplicar Bono Descuento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('abonos.descuento.store') }}" method="POST">
                @csrf
                <input type="hidden" name="venta_id" value="{{ $venta->id }}">
                <div class="modal-body p-4">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Cantidad a deducir ($)</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="descuento" class="form-control form-control-lg rounded-end" step="any" min="0.01" placeholder="0.00" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-bold shadow-sm w-100 py-2">
                        Aplicar Descuento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

<!-- MODAL: BUSCAR OTRO CLIENTE -->
<div class="modal fade" id="modalBuscarCliente" tabindex="-1" aria-labelledby="modalBuscarClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalBuscarClienteLabel">Buscar Otro Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Escribe el nombre del cliente para abrir su estado de cuenta rápidamente.</p>
                
                <label class="form-label small fw-bold text-muted text-uppercase">Nombre del Cliente</label>
                <div class="input-group mb-3">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="inputBuscarModal" class="form-control border-start-0" placeholder="Ej. Juan Pérez..." autocomplete="off">
                </div>

                <!-- CONTENEDOR DE RESULTADOS DINÁMICOS -->
                <div id="listaResultadosModal" class="list-group search-results-list shadow-sm rounded-3"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: SUBIR Y VER EVIDENCIAS -->
<div class="modal fade" id="modalEvidencias" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-camera me-2 text-primary"></i>Evidencias Fotográficas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('abonos.evidencia.store') }}" method="POST" enctype="multipart/form-data" class="mb-4">
                    @csrf
                    <input type="hidden" name="venta_id" value="{{ $venta->id }}">
                    <label class="form-label small fw-bold text-muted text-uppercase">Subir Nueva Foto / Fachada</label>
                    <div class="input-group mb-2">
                        <input type="file" name="evidencia" class="form-control" accept="image/*" capture="environment" required>
                        <button type="submit" class="btn btn-primary fw-bold">Subir</button>
                    </div>
                </form>

                <h6 class="fw-bold text-muted small text-uppercase mb-2">Imágenes Guardadas</h6>
                <div class="row g-2" id="galeriaEvidencias">
                    @php
                        $evidencias = DB::table('venta_evidencias')->where('venta_id', $venta->id)->get();
                    @endphp
                    @forelse($evidencias as $ev)
                        @php
                            // Generar la URL accesible desde el acceso público
                            $urlImagen = asset('storage/' . ltrim($ev->ruta_imagen, '/'));
                        @endphp
                        <div class="col-4">
                            <a href="{{ $urlImagen }}" target="_blank">
                                <img src="{{ $urlImagen }}" class="img-fluid rounded-3 border shadow-sm" style="height: 90px; object-fit: cover; width: 100%;" alt="Evidencia">
                            </a>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted py-3 small">No hay evidencias guardadas para esta venta.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: REASIGNAR COBRADOR -->
<div class="modal fade" id="modalReasignarCobrador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-people-fill text-primary me-2"></i> Reasignar Cobrador
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 350px; overflow-y: auto;">
                <div class="list-group list-group-flush">
                    @foreach($cobradoresDisponibles as $cob)
                        <form action="{{ route('abonos.cobrador.update') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="venta_id" value="{{ $venta->id }}">
                            {{-- Se envía el UUID primario del usuario ($cob->id) para no violar la Foreign Key --}}
                            <input type="hidden" name="cobrador_user_id" value="{{ $cob->id }}">
                            <button type="submit" class="list-group-item list-group-item-action border-0 rounded-3 py-3 px-3 mb-1 d-flex align-items-center fw-bold text-dark hover-bg-light">
                                <div class="bg-light p-2 rounded-3 me-3 text-secondary border">
                                    <i class="bi bi-person-badge fs-5"></i>
                                </div>
                                {{ $cob->username ?? $cob->name }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-3 px-4 w-100" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Búsqueda interactiva AJAX dentro del Modal
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('inputBuscarModal');
        const lista = document.getElementById('listaResultadosModal');
        let timeout = null;

        // 1. Foco automático y limpieza al abrir el modal de búsqueda
        const modalElement = document.getElementById('modalBuscarCliente');
        if (modalElement) {
            modalElement.addEventListener('shown.bs.modal', function () {
                if (input) {
                    input.value = '';
                    input.focus();
                }
                if (lista) lista.innerHTML = '';
            });
        }

        // 2. Búsqueda en tiempo real (debounce 300ms)
        if (input) {
            input.addEventListener('keyup', function() {
                clearTimeout(timeout);
                const query = input.value.trim();

                if (query.length < 2) {
                    lista.innerHTML = '';
                    return;
                }

                timeout = setTimeout(() => {
                    fetch(`{{ route('abonos.buscar.ajax') }}?q=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            lista.innerHTML = '';
                            if (!data || data.length === 0) {
                                lista.innerHTML = '<div class="list-group-item text-muted small py-3 text-center">No se encontraron clientes o ventas coincidentes.</div>';
                                return;
                            }

                            data.forEach(item => {
                                // Toma de identificadores resguardando UUID primario > legacy > folio
                                const targetId = item.id_venta || item.venta_uuid || item.venta_id || item.venta_id_legacy;
                                const folioStr = item.folio || item.venta_id_legacy || 'S/N';
                                const direccion = (item.cliente_colonia ? item.cliente_colonia + ', ' : '') + (item.cliente_ciudad || '');
                                const saldo = parseFloat(item.saldo_restante || item.venta_restante || 0).toFixed(2);
                                
                                const option = document.createElement('div');
                                option.className = 'list-group-item list-group-item-action search-result-item py-2 px-3 border-bottom';
                                option.style.cursor = 'pointer';
                                
                                // Forzar navegación limpia por JS al ID único seleccionado
                                option.onclick = function() {
                                    window.location.href = `{{ url('/abonos') }}/${targetId}`;
                                };

                                option.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="text-dark d-block text-uppercase">${item.cliente_nombre || 'Sin Nombre'}</strong>
                                            <small class="text-muted"><i class="bi bi-geo-alt me-1 text-danger"></i>${direccion}</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary bg-opacity-10 text-primary mb-1 fw-bold">Folio #${folioStr}</span>
                                            <small class="d-block text-danger fw-bold">$${saldo}</small>
                                        </div>
                                    </div>
                                `;
                                lista.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error al realizar la búsqueda:', error);
                        });
                }, 300);
            });
        }
    });

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
    // 1. Guardar Teléfono vía AJAX
    function actualizarTelefonoCliente(clienteId) {
        const telInput = document.getElementById('inputTelefonoCliente');
        const tel = telInput.value;
        const msg = document.getElementById('msgTelefonoStatus');

        fetch(`{{ route('abonos.telefono.update') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ cliente_id: clienteId, telefono: tel })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                msg.innerText = data.message;
                msg.style.display = 'block';
                telInput.value = data.telefono;
                setTimeout(() => msg.style.display = 'none', 3000);
            }
        })
        .catch(err => console.error('Error al actualizar teléfono:', err));
    }

    // 2. Capturar y fijar geolocalización GPS
    function capturarUbicacionGPS(clienteId) {
        const status = document.getElementById('statusGPS');
        if (!navigator.geolocation) {
            alert('La geolocalización no es soportada por este navegador.');
            return;
        }

        status.innerHTML = '<span class="text-primary"><i class="bi bi-hourglass-split"></i> Obteniendo coordenadas...</span>';

        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            fetch(`{{ route('abonos.gps.store') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ cliente_id: clienteId, latitud: lat, longitud: lng })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    status.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> GPS Guardado: ${data.ubicacion}</span>`;
                }
            });
        }, function(error) {
            status.innerHTML = '<span class="text-danger">Error al obtener ubicación GPS. Verifique los permisos del navegador.</span>';
        });
    }

    // Evaluación dinámica de Acción Liquidar
    function evaluarAccionLiquidar() {
        const selectAccion = document.getElementById('selectTipoAccion');
        const inputAbono = document.getElementById('abono');
        const contenedorReagendar = document.getElementById('contenedorTipoReagendar');
        const contenedorManual = document.getElementById('contenedorFechaReagendarManual');
        const saldoRestante = parseFloat(inputAbono.getAttribute('data-restante')) || 0;

        if (selectAccion.value === '2') {
            // Cargar el saldo completo automáticamente para liquidar
            inputAbono.value = saldoRestante.toFixed(2);
            inputAbono.readOnly = true;
            if(contenedorReagendar) contenedorReagendar.style.display = 'none';
            if(contenedorManual) contenedorManual.style.display = 'none';
        } else {
            inputAbono.value = '';
            inputAbono.readOnly = false;
            if(contenedorReagendar) contenedorReagendar.style.display = 'block';
            evaluarTipoReagendado();
        }
    }

    // Evaluación dinámica de Tipo de Reagendado (Auto / Fecha)
    function evaluarTipoReagendado() {
        const selectReagendar = document.getElementById('selectTipoReagendar');
        const contenedorManual = document.getElementById('contenedorFechaReagendarManual');

        if (selectReagendar && selectReagendar.value === 'fecha') {
            contenedorManual.style.display = 'block';
        } else if (contenedorManual) {
            contenedorManual.style.display = 'none';
        }
    }

    // Validación de abonos
    function validarMontoAbono() {
        const inputAbono = document.getElementById('abono');
        const montoAbono = parseFloat(inputAbono.value) || 0;
        const saldoRestante = parseFloat(inputAbono.getAttribute('data-restante')) || 0;
        const selectAccion = document.getElementById('selectTipoAccion');
        const esLiquidacion = (selectAccion && selectAccion.value === '2');

        if (!esLiquidacion && montoAbono > saldoRestante) {
            alert("¡ATENCIÓN! La cantidad ingresada ($" + montoAbono.toFixed(2) + ") es mayor al saldo restante ($" + saldoRestante.toFixed(2) + ").");
            inputAbono.focus();
            return false;
        }

        if (montoAbono <= 0 && !esLiquidacion) {
            alert("¡ATENCIÓN! Ingrese un monto de abono mayor a cero.");
            inputAbono.focus();
            return false;
        }

        return true;
    }
</script>
@endsection