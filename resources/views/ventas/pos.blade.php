@extends('layouts.app')

@section('title', 'Punto de Venta - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0" style="max-width: 1200px;">
    
    <form id="form_venta" action="{{ route('ventas.store') }}" method="POST">
        @csrf

        <!-- CABECERA PRINCIPAL POS -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
            
            <!-- TITULO Y ACCIONES -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold text-dark m-0"><i class="bi bi-cart3 text-primary me-2"></i>Módulo Punto de Venta</h4>
                    <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">Folio #{{ $nuevoFolio }}</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-3 fw-semibold" onclick="resetearVenta()">
                        <i class="bi bi-x-circle me-1"></i> Cancelar Venta
                    </button>
                    <button type="submit" id="btn_finalizar" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Finalizar Venta Crédito
                    </button>
                </div>
            </div>

            <!-- GRILLA DE PARÁMETROS CONFIGURABLES VÍA MODAL -->
            <div class="row g-3">
                
                <!-- 1. VENDEDOR (PRIMERA POSICIÓN) -->
                <div class="col-12 col-md-3">
                    <div class="p-2 border rounded-3 bg-light hover-card" data-bs-toggle="modal" data-bs-target="#modalVendedor">
                        <small class="text-muted fw-bold d-block extra-small">
                            Vendedor {!! $esAdmin ? '<i class="bi bi-pencil-fill text-primary ms-1"></i>' : '' !!}
                        </small>
                        <input type="hidden" id="input_vendedor_id" name="vendedor_id" value="{{ $vendedorIdEfectivo }}">
                        <span class="fw-bold text-dark d-block text-truncate mt-1" id="lbl_vendedor_nombre">
                            <i class="bi bi-person-badge-fill text-primary me-1"></i> {{ $usuarioLogueado->username }}
                        </span>
                    </div>
                </div>

                <!-- 2. TIPO VENTA (MODAL) -->
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded-3 bg-light hover-card" data-bs-toggle="modal" data-bs-target="#modalTipoVenta">
                        <small class="text-muted fw-bold d-block extra-small">Tipo Venta <i class="bi bi-pencil-fill text-primary ms-1"></i></small>
                        <input type="hidden" id="tipo_venta" name="tipo_venta" value="Credito">
                        <span class="fw-bold text-primary d-block mt-1" id="lbl_tipo_venta">📝 Crédito</span>
                    </div>
                </div>

                <!-- 3. FECHA VENTA (MODAL) -->
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded-3 bg-light {{ $esAdmin ? 'hover-card' : '' }}" {{ $esAdmin ? 'data-bs-toggle=modal data-bs-target=#modalFechaVenta' : '' }}>
                        <small class="text-muted fw-bold d-block extra-small">
                            Fecha Venta {!! $esAdmin ? '<i class="bi bi-pencil-fill text-primary ms-1"></i>' : '<i class="bi bi-lock-fill text-secondary ms-1"></i>' !!}
                        </small>
                        <input type="hidden" id="input_fecha_venta" name="fecha_venta" value="{{ date('Y-m-d') }}">
                        <span class="fw-semibold text-dark d-block mt-1" id="lbl_fecha_venta"><i class="bi bi-calendar-event me-1"></i> {{ date('d/m/Y') }}</span>
                    </div>
                </div>

                <!-- 4. FORMA PAGO (MODAL) -->
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded-3 bg-light hover-card" data-bs-toggle="modal" data-bs-target="#modalFormaPago">
                        <small class="text-muted fw-bold d-block extra-small">Forma Pago <i class="bi bi-pencil-fill text-primary ms-1"></i></small>
                        <input type="hidden" id="input_forma_pago" name="forma_pago" value="Efectivo">
                        <span class="fw-semibold text-dark d-block mt-1" id="lbl_forma_pago">💳 Efectivo</span>
                    </div>
                </div>

                <!-- 5. ANTICIPO (MODAL) -->
                <div class="col-6 col-md-1">
                    <div class="p-2 border rounded-3 bg-light hover-card" data-bs-toggle="modal" data-bs-target="#modalAnticipo">
                        <small class="text-muted fw-bold d-block extra-small">Anticipo</small>
                        <input type="hidden" id="anticipo" name="anticipo" value="0">
                        <span class="fw-bold text-success d-block mt-1" id="lbl_anticipo">$0</span>
                    </div>
                </div>

                <!-- 6. CLIENTE (MODAL) -->
                <div class="col-12 col-md-2">
                    <div class="p-2 border rounded-3 bg-light hover-card" data-bs-toggle="modal" data-bs-target="#modalBuscarCliente">
                        <small class="text-muted fw-bold d-block extra-small">Cliente (*)</small>
                        <input type="hidden" id="input_cliente_id" name="cliente_id" required>
                        <span class="fw-bold text-danger d-block text-truncate mt-1" id="lbl_cliente_nombre">
                            <i class="bi bi-person-plus-fill me-1"></i> Seleccionar...
                        </span>
                    </div>
                </div>

                <!-- 7. CATÁLOGOS/PRODUCTOS (MODAL) -->
                <div class="col-12 col-md-2">
                    <div class="p-2 border rounded-3 bg-light hover-card" data-bs-toggle="modal" data-bs-target="#modalCatalogo">
                        <small class="text-muted fw-bold d-block extra-small">Catálogo</small>
                        <span class="fw-bold text-primary d-block mt-1">
                            <i class="bi bi-box-seam-fill me-1"></i> Ver Productos (<span id="lbl_cant_articulos">{{ count($articulosEnStock) }}</span>)
                        </span>
                    </div>
                </div>

                <!-- 8. COBRADOR (MODAL EXCLUSIVO CRÉDITO) -->
                <div class="col-6 col-md-2 campo-credito">
                    <div class="p-2 border rounded-3 bg-light border-warning hover-card" data-bs-toggle="modal" data-bs-target="#modalBuscarCobrador">
                        <small class="text-muted fw-bold d-block extra-small">Cobrador Asignado</small>
                        <input type="hidden" id="input_cobrador_id" name="cobrador_id">
                        <span class="fw-bold text-danger d-block text-truncate mt-1" id="lbl_cobrador_nombre">
                            <i class="bi bi-truck me-1"></i> Seleccionar...
                        </span>
                    </div>
                </div>

                <!-- 9. FRECUENCIA (MODAL EXCLUSIVO CRÉDITO) -->
                <div class="col-6 col-md-2 campo-credito">
                    <div class="p-2 border rounded-3 bg-warning bg-opacity-10 border-warning hover-card" data-bs-toggle="modal" data-bs-target="#modalFrecuencia">
                        <small class="text-muted fw-bold d-block extra-small">Frecuencia</small>
                        <input type="hidden" id="frecuencia_nombre" name="frecuencia_nombre" value="Semanal">
                        <span class="fw-bold text-dark d-block mt-1" id="lbl_frecuencia">Semanal</span>
                    </div>
                </div>

                <!-- 10. PAGOS / MESES (MODAL EXCLUSIVO CRÉDITO) -->
                <div class="col-6 col-md-1 campo-credito">
                    <div class="p-2 border rounded-3 bg-warning bg-opacity-10 border-warning hover-card" data-bs-toggle="modal" data-bs-target="#modalPagos">
                        <small class="text-muted fw-bold d-block extra-small">Pagos</small>
                        <input type="hidden" id="num_pagos" name="num_pagos" value="1">
                        <span class="fw-bold text-dark d-block text-center mt-1" id="lbl_num_pagos">1</span>
                    </div>
                </div>

                <!-- 11. 1ER PAGO (MODAL EXCLUSIVO CRÉDITO) -->
                <div class="col-6 col-md-2 campo-credito">
                    <div class="p-2 border rounded-3 bg-warning bg-opacity-10 border-warning hover-card" data-bs-toggle="modal" data-bs-target="#modalPrimerPago">
                        <small class="text-muted fw-bold d-block extra-small">1er Pago</small>
                        <input type="hidden" id="input_fecha_primer_pago" name="fecha_primer_pago" value="{{ date('Y-m-d', strtotime('+7 days')) }}">
                        <span class="fw-bold text-dark d-block mt-1" id="lbl_fecha_primer_pago"><i class="bi bi-calendar-check me-1"></i> {{ date('d/m/Y', strtotime('+7 days')) }}</span>
                    </div>
                </div>

                <!-- 12. DÍA SELECCIÓN (MODAL EXCLUSIVO CRÉDITO) -->
                <div class="col-6 col-md-1 campo-credito">
                    <div class="p-2 border rounded-3 bg-warning bg-opacity-10 border-warning hover-card" data-bs-toggle="modal" data-bs-target="#modalDiaCobro">
                        <small class="text-muted fw-bold d-block extra-small">Día</small>
                        <input type="hidden" id="input_dia_semana" name="dia_semana" value="Sábado">
                        <span class="fw-bold text-dark d-block text-center mt-1" id="lbl_dia_semana">Sábado</span>
                    </div>
                </div>

            </div>
        </div>

        <!-- ESCANEO RÁPIDO DE CÓDIGO DE BARRAS -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3 mb-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-primary border-primary text-white"><i class="bi bi-barcode"></i></span>
                        <input type="text" id="scanner_input" class="form-control border-primary" placeholder="Escaneo de código de barras..." autofocus onkeypress="manejarScanner(event)">
                    </div>
                </div>
                <div class="col-md-5 text-end text-muted extra-small mt-2 mt-md-0">
                    <i class="bi bi-info-circle me-1"></i> Presione un punto <code>.</code> al final del código para registrar rápido.
                </div>
            </div>
        </div>

        <!-- TABLA DE PRODUCTOS EN EL CARRITO -->
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabla_venta">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 text-center" style="width: 70px;">Acción</th>
                            <th class="text-center" style="width: 120px;">Cantidad</th>
                            <th>Descripción del Artículo</th>
                            <th style="width: 180px;">Número de Serie</th>
                            <th class="text-end" style="width: 130px;">Descuento</th>
                            <th class="text-end" style="width: 140px;">Precio U.</th>
                            <th class="text-end pe-4" style="width: 150px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="body_articulos">
                        <!-- Carga dinámica -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RESUMEN FINAL DE TOTALES -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="row justify-content-end align-items-center g-3">
                <div class="col-md-3 text-end">
                    <small class="text-muted fw-bold d-block">SUBTOTAL</small>
                    <h5 class="fw-bold text-dark m-0" id="txt_subtotal">$0.00</h5>
                    <input type="hidden" id="input_subtotal" name="subtotal" value="0">
                </div>
                <div class="col-md-3 text-end">
                    <small class="text-muted fw-bold d-block">TOTAL VENTA</small>
                    <h3 class="fw-bold text-primary m-0" id="txt_total">$0.00</h3>
                    <input type="hidden" id="input_total" name="total" value="0">
                </div>
                <div class="col-md-3 text-end campo-credito">
                    <small class="text-muted fw-bold d-block text-warning">MENSUALIDAD</small>
                    <h4 class="fw-bold text-warning m-0" id="txt_cuota">$0.00</h4>
                    <input type="hidden" id="input_cuota" name="cuota_pago" value="0">
                </div>
            </div>
        </div>

    </form>
</div>

<!-- ================= MODALES FLOTANTES DE CONFIGURACIÓN ================= -->

<!-- MODAL VENDEDOR -->
<div class="modal fade" id="modalVendedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-person-badge text-primary me-2"></i>Seleccionar Vendedor</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                @if($esAdmin)
                    <div class="list-group list-group-flush">
                        @foreach($vendedores as $v)
                            <button type="button" class="list-group-item list-group-item-action border-0 rounded-3 py-2 mb-1" onclick="setVendedor('{{ $v->id }}', '{{ addslashes($v->username) }}')">
                                👤 {{ $v->username }}
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small m-0">Vendedor asignado: <strong>{{ $usuarioLogueado->username }}</strong></p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- MODAL TIPO VENTA -->
<div class="modal fade" id="modalTipoVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-cart-check me-2 text-primary"></i>Tipo de Venta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3 d-grid gap-2">
                <button type="button" class="btn btn-outline-primary fw-bold text-start py-2" onclick="setTipoVenta('Credito', '📝 Crédito')">📝 Venta a Crédito</button>
                <button type="button" class="btn btn-outline-success fw-bold text-start py-2" onclick="setTipoVenta('Contado', '💵 Contado')">💵 Venta de Contado</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FECHA VENTA (ADMIN) -->
@if($esAdmin)
<div class="modal fade" id="modalFechaVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-event me-2 text-primary"></i>Fecha de Venta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <input type="date" id="temp_fecha_venta" class="form-control rounded-3" value="{{ date('Y-m-d') }}">
                <button type="button" class="btn btn-primary btn-sm rounded-pill w-100 fw-bold mt-3" onclick="setFechaVenta()">Guardar Fecha</button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- MODAL FORMA PAGO -->
<div class="modal fade" id="modalFormaPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-credit-card me-2 text-primary"></i>Forma de Pago</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3 d-grid gap-2">
                <button type="button" class="btn btn-outline-dark fw-bold text-start py-2" onclick="setFormaPago('Efectivo', '💳 Efectivo')">💳 Efectivo</button>
                <button type="button" class="btn btn-outline-dark fw-bold text-start py-2" onclick="setFormaPago('Transferencia', '🏦 Transferencia')">🏦 Transferencia</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ANTICIPO -->
<div class="modal fade" id="modalAnticipo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-cash me-2 text-success"></i>Monto de Anticipo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <input type="number" step="0.01" id="temp_anticipo" class="form-control rounded-3 fw-bold text-success" value="0" min="0">
                <button type="button" class="btn btn-success btn-sm rounded-pill w-100 fw-bold mt-3" onclick="setAnticipo()">Aplicar Anticipo</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CLIENTE -->
<div class="modal fade" id="modalBuscarCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-people text-danger me-2"></i>Seleccionar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="input_buscar_cliente" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar por nombre, ciudad o teléfono..." onkeyup="filtrarClientesModal()">
                </div>
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted extra-small sticky-top">
                            <tr>
                                <th>Cliente</th>
                                <th>Ubicación</th>
                                <th>Teléfono</th>
                                <th class="text-end pe-3">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_clientes_modal">
                            @foreach($clientes as $c)
                                <tr class="item-cliente-modal" data-nombre="{{ strtolower($c->nombre) }}" data-ciudad="{{ strtolower($c->ciudad) }}" data-colonia="{{ strtolower($c->colonia) }}" data-tel="{{ $c->telefono }}">
                                    <td>
                                        <strong class="text-dark d-block">{{ $c->nombre }}</strong>
                                        <small class="text-muted">{{ $c->calle ?? '' }}</small>
                                    </td>
                                    <td class="small">{{ $c->colonia }}, {{$c->ciudad }}</td>
                                    <td class="small text-muted">{{ $c->telefono ?? 'Sin Teléfono' }}</td>
                                    <td class="text-end pe-3">
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" onclick="setCliente('{{ $c->id }}', '{{ addslashes($c->nombre) }}')">
                                            <i class="bi bi-check2 me-1"></i> Seleccionar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL COBRADOR -->
<div class="modal fade" id="modalBuscarCobrador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-truck text-warning me-2"></i>Asignar Cobrador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="list-group list-group-flush">
                    @foreach($cobradores as $cob)
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0 rounded-3 mb-1 bg-light" onclick="setCobrador('{{ $cob->id }}', '{{ addslashes($cob->username) }}')">
                            <span class="fw-bold text-dark"><i class="bi bi-person-check me-2 text-warning"></i> {{ $cob->username }}</span>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold">Seleccionar</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FRECUENCIA -->
<div class="modal fade" id="modalFrecuencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-clock me-2 text-warning"></i>Frecuencia de Pago</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3 d-grid gap-2">
                <button type="button" class="btn btn-outline-warning text-dark fw-bold py-2" onclick="setFrecuencia('Semanal')">Semanal (Cada 7 días)</button>
                <button type="button" class="btn btn-outline-warning text-dark fw-bold py-2" onclick="setFrecuencia('Quincenal')">Quincenal (Cada 15 días)</button>
                <button type="button" class="btn btn-outline-warning text-dark fw-bold py-2" onclick="setFrecuencia('Mensual')">Mensual (Cada 30 días)</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PAGOS (MESES) -->
<div class="modal fade" id="modalPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-hash me-2 text-warning"></i>Número de Pagos (Meses)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="row g-2">
                    @for($m = 1; $m <= 12; $m++)
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-dark fw-bold w-100 py-2" onclick="setPagos({{ $m }})">{{ $m }} Mes</button>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 1ER PAGO -->
<div class="modal fade" id="modalPrimerPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-check me-2 text-warning"></i>Fecha de Primer Pago</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <input type="date" id="temp_primer_pago" class="form-control rounded-3 fw-bold" value="{{ date('Y-m-d', strtotime('+7 days')) }}">
                <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill w-100 fw-bold mt-3" onclick="setPrimerPago()">Guardar Fecha</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DÍA DE COBRO -->
<div class="modal fade" id="modalDiaCobro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-day me-2 text-warning"></i>Día de Cobro</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3 d-grid gap-2">
                @foreach(['Sábado', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Domingo'] as $dia)
                    <button type="button" class="btn btn-outline-secondary fw-bold py-2 text-start" onclick="setDiaCobro('{{ $dia }}')">{{ $dia }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- MODAL CATÁLOGO DE PRODUCTOS (CON BUSCADOR Y PAGINACIÓN JS) -->
<div class="modal fade" id="modalCatalogo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-box-seam text-primary me-2"></i>Artículos Disponibles en Inventario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                
                <!-- BUSCADOR EN TIEMPO REAL -->
                <div class="input-group mb-3">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="input_buscar_catalogo" class="form-control bg-light border-start-0 ps-0" placeholder="Escribe nombre, marca, modelo o código de barras para filtrar..." onkeyup="filtrarCatalogoModal()">
                </div>

                <!-- TABLA DE ARTÍCULOS -->
                <div class="table-responsive" style="min-height: 280px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Stock</th>
                                <th class="text-end">P. Contado</th>
                                <th class="text-end pe-3">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_catalogo_modal">
                            <!-- Se renderiza dinámicamente con JS -->
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- CONTROLES DE PAGINACIÓN FLOTANTE EN EL PIE DEL MODAL -->
            <div class="modal-footer border-0 pt-0 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="lbl_info_paginacion">Mostrando 0 de 0 productos</small>
                <nav aria-label="Paginación catálogo">
                    <ul class="pagination pagination-sm m-0" id="ul_paginacion_catalogo">
                        <!-- Botones de páginas generados con JS -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- MODAL RESUMEN DE CONCLUSIÓN DE VENTA -->
<div class="modal fade" id="modalResumenVenta" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg rounded-4">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text text-primary fs-4"></i>
                    <span id="txt_titulo_resumen">Resumen de Venta</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body py-4 px-4">
                
                <!-- DETALLES DE LA VENTA -->
                <div class="mb-3">
                    <small class="text-muted fw-bold d-block">Cliente:</small>
                    <strong class="text-dark fs-6" id="resumen_cliente_nombre">---</strong>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <small class="text-muted fw-bold">Forma de Pago:</small>
                    <strong class="text-dark" id="resumen_forma_pago">Efectivo</strong>
                </div>

                <!-- TARJETA DEL TOTAL -->
                <div class="p-3 bg-success bg-opacity-10 rounded-4 text-center mb-4">
                    <small class="text-muted fw-bold d-block text-uppercase">Total de la Venta:</small>
                    <h2 class="fw-bold text-success m-0" id="resumen_total_venta">$0.00</h2>
                </div>

                <!-- BOTONERA DE DOCUMENTOS PDF -->
                <div class="d-flex justify-content-center gap-2 mb-2">
                    <button type="button" id="btn_pdf_ticket" class="btn btn-outline-secondary rounded-3 px-3 fw-bold" disabled onclick="abrirPdfTicket()">
                        <i class="bi bi-receipt me-1"></i> Ticket
                    </button>
                    <button type="button" id="btn_pdf_boletas" class="btn btn-outline-secondary rounded-3 px-3 fw-bold" disabled onclick="abrirPdfBoletas()">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Boletas
                    </button>
                </div>

            </div>

            <div class="modal-footer border-0 pt-0 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light rounded-3 fw-bold px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn_confirmar_guardar" class="btn btn-success rounded-3 fw-bold px-4 shadow-sm" onclick="enviarVentaBD()">
                    <i class="bi bi-check-lg me-1"></i> Aceptar Venta
                </button>
            </div>

        </div>
    </div>
</div>



<script>
let carrito = [];
let articulosDB = @json($articulosEnStock);
let ventaProcesadaId = null;
let detallesVentaIds = [];

// --- FUNCIONES ASIGNACIÓN DESDE MODALES ---

function setVendedor(id, nombre) {
    document.getElementById('input_vendedor_id').value = id;
    document.getElementById('lbl_vendedor_nombre').innerHTML = `<i class="bi bi-person-badge-fill text-primary me-1"></i> ${nombre}`;
    cerrarModal('modalVendedor');

    if (carrito.length > 0 && !confirm('⚠️ Cambiar de vendedor reiniciará el carrito. ¿Continuar?')) return;
    carrito = [];

    fetch(`{{ route('ventas.pos') }}?vendedor_id=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(res => res.json())
    .then(data => {
        articulosDB = data.articulos;
        articulosFiltrados = [...articulosDB];
        document.getElementById('lbl_cant_articulos').innerText = articulosDB.length;

        paginaActualCatalogo = 1;
        renderizarTablaCatalogoModal();
        recalcularPreciosTabla();
    });
}

function setTipoVenta(tipo, label) {
    document.getElementById('tipo_venta').value = tipo;
    document.getElementById('lbl_tipo_venta').innerText = label;
    cerrarModal('modalTipoVenta');
    alternarTipoVenta();
}

function setFechaVenta() {
    const val = document.getElementById('temp_fecha_venta').value;
    document.getElementById('input_fecha_venta').value = val;
    const partes = val.split('-');
    document.getElementById('lbl_fecha_venta').innerHTML = `<i class="bi bi-calendar-event me-1"></i> ${partes[2]}/${partes[1]}/${partes[0]}`;
    cerrarModal('modalFechaVenta');
}

function setFormaPago(val, label) {
    document.getElementById('input_forma_pago').value = val;
    document.getElementById('lbl_forma_pago').innerText = label;
    cerrarModal('modalFormaPago');
}

function setAnticipo() {
    const val = parseFloat(document.getElementById('temp_anticipo').value) || 0;
    document.getElementById('anticipo').value = val;
    document.getElementById('lbl_anticipo').innerText = `$${val.toFixed(2)}`;
    cerrarModal('modalAnticipo');
    calcularTotales();
}

function setCliente(id, nombre) {
    document.getElementById('input_cliente_id').value = id;
    document.getElementById('lbl_cliente_nombre').innerHTML = `<i class="bi bi-person-check-fill text-success me-1"></i> ${nombre}`;
    cerrarModal('modalBuscarCliente');
}

function setCobrador(id, nombre) {
    document.getElementById('input_cobrador_id').value = id;
    document.getElementById('lbl_cobrador_nombre').innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> ${nombre}`;
    cerrarModal('modalBuscarCobrador');
}

function setFrecuencia(val) {
    document.getElementById('frecuencia_nombre').value = val;
    document.getElementById('lbl_frecuencia').innerText = val;
    cerrarModal('modalFrecuencia');
    recalcularPreciosTabla();
}

function setPagos(val) {
    document.getElementById('num_pagos').value = val;
    document.getElementById('lbl_num_pagos').innerText = val;
    cerrarModal('modalPagos');
    recalcularPreciosTabla();
}

function setPrimerPago() {
    const val = document.getElementById('temp_primer_pago').value;
    document.getElementById('input_fecha_primer_pago').value = val;
    const partes = val.split('-');
    document.getElementById('lbl_fecha_primer_pago').innerHTML = `<i class="bi bi-calendar-check me-1"></i> ${partes[2]}/${partes[1]}/${partes[0]}`;
    cerrarModal('modalPrimerPago');
}

function setDiaCobro(val) {
    document.getElementById('input_dia_semana').value = val;
    document.getElementById('lbl_dia_semana').innerText = val;
    cerrarModal('modalDiaCobro');
}

function cerrarModal(modalId) {
    const modalEl = document.getElementById(modalId);
    if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.hide();
    }
}

function filtrarClientesModal() {
    const q = document.getElementById('input_buscar_cliente').value.toLowerCase().trim();
    document.querySelectorAll('.item-cliente-modal').forEach(f => {
        const match = f.getAttribute('data-nombre').includes(q) || f.getAttribute('data-ciudad').includes(q) || f.getAttribute('data-colonia').includes(q) || f.getAttribute('data-tel').includes(q);
        f.style.display = match ? '' : 'none';
    });
}

function alternarTipoVenta() {
    const tipo = document.getElementById('tipo_venta').value;
    const camposCredito = document.querySelectorAll('.campo-credito');
    const btnFinalizar = document.getElementById('btn_finalizar');

    if (tipo === 'Contado') {
        camposCredito.forEach(el => el.style.display = 'none');
        btnFinalizar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Finalizar Venta Contado';
        btnFinalizar.className = 'btn btn-success rounded-pill px-4 fw-bold shadow-sm';
    } else {
        camposCredito.forEach(el => el.style.display = 'block');
        btnFinalizar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Finalizar Venta Crédito';
        btnFinalizar.className = 'btn btn-primary rounded-pill px-4 fw-bold shadow-sm';
    }

    recalcularPreciosTabla();
}

function agregarArticulo(art) {
    const existe = carrito.find(item => item.id === art.id);

    if (existe) {
        if (existe.cantidad < art.stock_vendedor) {
            existe.cantidad++;
        } else {
            alert('⚠️ Has alcanzado el límite de stock disponible.');
        }
    } else {
        carrito.push({
            id: art.id,
            nombre: art.nombre,
            pcompra: parseFloat(art.pcompra),
            pcontado: parseFloat(art.pcontado),
            precios_credito: art.precios || [],
            cantidad: 1,
            n_serie: '',
            descuento: 0,
            stock_max: art.stock_vendedor
        });
    }

    recalcularPreciosTabla();
    cerrarModal('modalCatalogo');
}

function recalcularPreciosTabla() {
    const tipo = document.getElementById('tipo_venta').value;
    const meses = parseInt(document.getElementById('num_pagos').value) || 1;

    const tbody = document.getElementById('body_articulos');
    tbody.innerHTML = '';

    if (carrito.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No hay artículos agregados a la venta.</td></tr>`;
        calcularTotales();
        return;
    }

    carrito.forEach((item, index) => {
        let precioUnitario = item.pcontado;

        if (tipo === 'Credito') {
            const precioMesObj = item.precios_credito.find(p => p.meses === meses);
            if (precioMesObj) {
                precioUnitario = parseFloat(precioMesObj.precio_total);
            } else {
                const utilidades = { 1:40, 2:35, 3:30, 4:25, 5:20, 6:15, 7:10, 8:5, 9:0, 10:0, 11:0, 12:0 };
                const pct = utilidades[meses] || 0;
                precioUnitario = item.pcompra + (item.pcompra * (pct / 100));
            }
        }

        const subtotal = (precioUnitario * item.cantidad) - item.descuento;

        tbody.innerHTML += `
            <tr>
                <td class="text-center ps-4">
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="eliminarItem(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
                <td class="text-center">
                    <div class="input-group input-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="cambiarCantidad(${index}, -1)">-</button>
                        <input type="number" class="form-control text-center px-0 fw-bold" value="${item.cantidad}" readonly>
                        <button type="button" class="btn btn-outline-secondary" onclick="cambiarCantidad(${index}, 1)">+</button>
                    </div>
                </td>
                <td>
                    <strong class="text-dark d-block">${item.nombre}</strong>
                    <input type="hidden" name="articulos[${index}][id]" value="${item.id}">
                    <input type="hidden" name="articulos[${index}][cantidad]" value="${item.cantidad}">
                    <input type="hidden" name="articulos[${index}][precio_unitario]" value="${precioUnitario}">
                    <input type="hidden" name="articulos[${index}][subtotal]" value="${subtotal}">
                </td>
                <td>
                    <input type="text" name="articulos[${index}][n_serie]" class="form-control form-control-sm bg-light" placeholder="# Agregar S/N" value="${item.n_serie}" onchange="item.n_serie = this.value">
                </td>
                <td class="text-end">
                    <input type="number" step="0.01" name="articulos[${index}][descuento]" class="form-control form-control-sm text-end" value="${item.descuento}" oninput="actualizarDescuento(${index}, this.value)">
                </td>
                <td class="text-end fw-semibold">$${precioUnitario.toFixed(2)}</td>
                <td class="text-end pe-4 fw-bold text-primary">$${subtotal.toFixed(2)}</td>
            </tr>
        `;
    });

    calcularTotales();
}

function cambiarCantidad(index, delta) {
    const item = carrito[index];
    const nuevaCant = item.cantidad + delta;

    if (nuevaCant >= 1 && nuevaCant <= item.stock_max) {
        item.cantidad = nuevaCant;
        recalcularPreciosTabla();
    } else if (nuevaCant > item.stock_max) {
        alert('⚠️ Supera el stock disponible.');
    }
}

function actualizarDescuento(index, val) {
    carrito[index].descuento = parseFloat(val) || 0;
    recalcularPreciosTabla();
}

function eliminarItem(index) {
    carrito.splice(index, 1);
    recalcularPreciosTabla();
}

function calcularTotales() {
    const tipo = document.getElementById('tipo_venta').value;
    const meses = parseInt(document.getElementById('num_pagos').value) || 1;
    const anticipo = parseFloat(document.getElementById('anticipo').value) || 0;

    let subtotal = 0;
    carrito.forEach(item => {
        let precio = item.pcontado;
        if (tipo === 'Credito') {
            const precioMesObj = item.precios_credito.find(p => p.meses === meses);
            precio = precioMesObj ? parseFloat(precioMesObj.precio_total) : (item.pcompra + (item.pcompra * (( {1:40,2:35,3:30,4:25,5:20,6:15,7:10,8:5}[meses]||0) / 100)));
        }
        subtotal += (precio * item.cantidad) - item.descuento;
    });

    const total = Math.max(0, subtotal - anticipo);
    const cuota = (tipo === 'Credito' && meses > 0) ? (total / meses) : total;

    document.getElementById('txt_subtotal').innerText = `$${subtotal.toFixed(2)}`;
    document.getElementById('input_subtotal').value = subtotal.toFixed(2);

    document.getElementById('txt_total').innerText = `$${subtotal.toFixed(2)}`;
    document.getElementById('input_total').value = subtotal.toFixed(2);

    document.getElementById('txt_cuota').innerText = `$${cuota.toFixed(2)}`;
    document.getElementById('input_cuota').value = cuota.toFixed(2);
}

function manejarScanner(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = document.getElementById('scanner_input').value.trim();
        if (!code) return;

        const art = articulosDB.find(a => a.barras === code || a.nombre.toLowerCase().includes(code.toLowerCase()));
        if (art) {
            agregarArticulo(art);
            document.getElementById('scanner_input').value = '';
        } else {
            alert('⚠️ Producto no encontrado.');
        }
    }
}

function resetearVenta() {
    if (confirm('¿Deseas cancelar la venta actual y limpiar la pantalla?')) {
        carrito = [];
        document.getElementById('form_venta').reset();
        alternarTipoVenta();
    }
}

// --- PAGINACIÓN Y FILTRADO DEL CATÁLOGO ---
let paginaActualCatalogo = 1;
const articulosPorPagina = 6;
let articulosFiltrados = [...articulosDB];

function filtrarCatalogoModal() {
    const q = document.getElementById('input_buscar_catalogo').value.toLowerCase().trim();
    
    articulosFiltrados = articulosDB.filter(art => {
        const nombre = (art.nombre || '').toLowerCase();
        const marca = (art.marca || '').toLowerCase();
        const modelo = (art.modelo || '').toLowerCase();
        const barras = (art.barras || '').toLowerCase();
        
        return nombre.includes(q) || marca.includes(q) || modelo.includes(q) || barras.includes(q);
    });

    paginaActualCatalogo = 1;
    renderizarTablaCatalogoModal();
}

function renderizarTablaCatalogoModal() {
    const tbody = document.getElementById('tbody_catalogo_modal');
    const lblInfo = document.getElementById('lbl_info_paginacion');
    const ulPaginacion = document.getElementById('ul_paginacion_catalogo');

    tbody.innerHTML = '';
    ulPaginacion.innerHTML = '';

    const total = articulosFiltrados.length;

    if (total === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-search me-1"></i> No se encontraron productos que coincidan con la búsqueda.</td></tr>`;
        lblInfo.innerText = 'Mostrando 0 de 0 productos';
        return;
    }

    const totalPaginas = Math.ceil(total / articulosPorPagina);
    if (paginaActualCatalogo > totalPaginas) paginaActualCatalogo = totalPaginas;

    const inicio = (paginaActualCatalogo - 1) * articulosPorPagina;
    const fin = Math.min(inicio + articulosPorPagina, total);
    const articulosPagina = articulosFiltrados.slice(inicio, fin);

    articulosPagina.forEach(art => {
        tbody.innerHTML += `
            <tr>
                <td>
                    <strong class="text-dark d-block">${art.nombre}</strong>
                    <small class="text-muted">${art.marca || ''} ${art.modelo ? '('+art.modelo+')' : ''} | Cod: ${art.barras || 'S/C'}</small>
                </td>
                <td class="text-center">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-bold">
                        ${art.stock_vendedor} pzas.
                    </span>
                </td>
                <td class="text-end fw-bold text-success">$${parseFloat(art.pcontado).toFixed(2)}</td>
                <td class="text-end pe-3">
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm" onclick='agregarArticulo(${JSON.stringify(art)})'>
                        <i class="bi bi-plus-lg me-1"></i> Agregar
                    </button>
                </td>
            </tr>
        `;
    });

    lblInfo.innerText = `Mostrando ${inicio + 1} a ${fin} de ${total} productos`;

    ulPaginacion.innerHTML += `
        <li class="page-item ${paginaActualCatalogo === 1 ? 'disabled' : ''}">
            <button type="button" class="page-link" onclick="cambiarPaginaCatalogo(${paginaActualCatalogo - 1})">«</button>
        </li>
    `;

    for (let p = 1; p <= totalPaginas; p++) {
        ulPaginacion.innerHTML += `
            <li class="page-item ${p === paginaActualCatalogo ? 'active' : ''}">
                <button type="button" class="page-link fw-semibold" onclick="cambiarPaginaCatalogo(${p})">${p}</button>
            </li>
        `;
    }

    ulPaginacion.innerHTML += `
        <li class="page-item ${paginaActualCatalogo === totalPaginas ? 'disabled' : ''}">
            <button type="button" class="page-link" onclick="cambiarPaginaCatalogo(${paginaActualCatalogo + 1})">»</button>
        </li>
    `;
}

function cambiarPaginaCatalogo(nuevaPagina) {
    const totalPaginas = Math.ceil(articulosFiltrados.length / articulosPorPagina);
    if (nuevaPagina >= 1 && nuevaPagina <= totalPaginas) {
        paginaActualCatalogo = nuevaPagina;
        renderizarTablaCatalogoModal();
    }
}

// Variable de control para saber si la venta ya fue guardada en BD
let ventaFinalizadaExitosamente = false;

// Interceptar submit del formulario y abrir Modal Resumen
document.getElementById('form_venta').addEventListener('submit', function(e) {
    e.preventDefault();

    // Si la venta ya se guardó, no permitir reabrir para modificar
    if (ventaFinalizadaExitosamente) {
        alert('⚠️ Esta venta ya fue guardada correctamente. Haz clic en "Nueva Venta" para realizar otra operación.');
        return;
    }

    const clienteId = document.getElementById('input_cliente_id').value;
    if (!clienteId) {
        alert('⚠️ Por favor selecciona un Cliente antes de finalizar.');
        return;
    }

    if (carrito.length === 0) {
        alert('⚠️ Debes agregar al menos un artículo a la venta.');
        return;
    }

    const tipo = document.getElementById('tipo_venta').value;
    document.getElementById('txt_titulo_resumen').innerText = tipo === 'Contado' ? 'Resumen de Venta de Contado' : 'Resumen de Venta a Crédito';
    document.getElementById('resumen_cliente_nombre').innerText = document.getElementById('lbl_cliente_nombre').innerText;
    document.getElementById('resumen_forma_pago').innerText = document.getElementById('lbl_forma_pago').innerText;
    document.getElementById('resumen_total_venta').innerText = document.getElementById('txt_total').innerText;

    // Estado inicial de botones en el Modal antes de guardar
    document.getElementById('btn_pdf_ticket').disabled = true;
    document.getElementById('btn_pdf_boletas').disabled = true;
    
    const btnAceptar = document.getElementById('btn_confirmar_guardar');
    btnAceptar.disabled = false;
    btnAceptar.innerHTML = '<i class="bi bi-check-lg me-1"></i> Aceptar Venta';

    const modalResumen = new bootstrap.Modal(document.getElementById('modalResumenVenta'));
    modalResumen.show();
});

// Enviar datos por AJAX para guardar Venta, Kardex y Habilitar PDFs

function enviarVentaBD() {
    if (ventaFinalizadaExitosamente) return;

    const btnAceptar = document.getElementById('btn_confirmar_guardar');
    const footerModal = document.querySelector('#modalResumenVenta .modal-footer');

    btnAceptar.disabled = true;
    btnAceptar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

    const formData = new FormData(document.getElementById('form_venta'));

    fetch("{{ route('ventas.store') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            ventaProcesadaId = data.venta_id;
            detallesVentaIds = data.detalles;
            ventaFinalizadaExitosamente = true; // Marcar como guardada

            // 1. Habilitar botones de PDF (Ticket y Boletas)
            document.getElementById('btn_pdf_ticket').disabled = false;
            document.getElementById('btn_pdf_boletas').disabled = false;

            // 2. Bloquear toda la vista POS en el fondo
            document.getElementById('btn_finalizar').disabled = true;
            document.querySelectorAll('#form_venta input, #form_venta button').forEach(el => el.disabled = true);

            // 3. Cambiar la botonera inferior del Modal a "Nueva Venta"
            footerModal.innerHTML = `
                <button type="button" class="btn btn-primary rounded-3 fw-bold px-4 shadow-sm w-100" onclick="iniciarNuevaVenta()">
                    <i class="bi bi-plus-circle me-1"></i> Iniciar Nueva Venta
                </button>
            `;

        } else {
            alert('❌ Error: ' + data.message);
            btnAceptar.disabled = false;
            btnAceptar.innerHTML = '<i class="bi bi-check-lg me-1"></i> Aceptar Venta';
        }
    })
    .catch(err => {
        alert('❌ Ocurrió un error inesperado al procesar la venta.');
        btnAceptar.disabled = false;
        btnAceptar.innerHTML = '<i class="bi bi-check-lg me-1"></i> Aceptar Venta';
    });
}

// Función para reiniciar la pantalla para una nueva venta limpia
function iniciarNuevaVenta() {
    window.location.reload(); // Recarga la página actual para obtener nuevo Folio y limpiar datos
}

function abrirPdfTicket() {
    if (ventaProcesadaId) {
        // Usa la ruta absoluta basada en la raíz del servidor o Blade
        const url = "{{ url('ventas') }}/" + ventaProcesadaId + "/pdf-ticket";
        window.open(url, '_blank');
    }
}

function abrirPdfBoletas() {
    if (ventaProcesadaId && detallesVentaIds.length > 0) {
        detallesVentaIds.forEach(detalle => {
            const detalleId = (typeof detalle === 'object') ? (detalle.id || detalle.va_id) : detalle;
            const url = "{{ url('ventas') }}/" + ventaProcesadaId + "/pdf-boleta/" + detalleId;
            window.open(url, '_blank');
        });
    }
}

// INICIALIZACIÓN ÚNICA DEL DOM
document.addEventListener('DOMContentLoaded', () => {
    alternarTipoVenta();
    articulosFiltrados = [...articulosDB];
    renderizarTablaCatalogoModal();
});
</script>

@endsection