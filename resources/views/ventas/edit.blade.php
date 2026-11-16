@extends('layouts.app')

@section('title', 'Editar Venta #' . $venta->folio . ' - Muebles Espinosa')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">

    <!-- ENCABEZADO -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 p-md-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-15 p-3 rounded-4 text-warning">
                    <i class="bi bi-pencil-square fs-3"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1">Editar Venta <span class="badge bg-primary">#{{ $venta->folio }}</span></h4>
                    <p class="text-muted small mb-0">Modifica cliente, artículos, precios o condiciones de crédito</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('ventas.index', ['filtro' => 'Pendiente']) }}" class="btn btn-light border rounded-3 px-3 fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Volver sin cambios
                </a>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger rounded-4 shadow-sm">{{ session('error') }}</div>
    @endif

    <form action="{{ route('ventas.update', $venta->id) }}" method="POST" id="formEditarVenta">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <!-- DATOS GENERALES DE LA VENTA -->
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                    <h6 class="fw-bold text-uppercase text-muted small mb-3"><i class="bi bi-person-lines-fill me-1"></i> Datos de la Operación</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Cliente</label>
                            <select name="cliente_id" class="form-select rounded-3" required>
                                @foreach($clientes as $cli)
                                    <option value="{{ $cli->id }}" {{ $venta->cliente_id == $cli->id ? 'selected' : '' }}>
                                        {{ $cli->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Fecha Venta</label>
                            <input type="date" name="fecha_venta" class="form-control rounded-3" value="{{ \Carbon\Carbon::parse($venta->fecha_venta)->format('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Tipo de Venta</label>
                            <select name="tipo_venta" id="edit_tipo_venta" class="form-select rounded-3" onchange="recalcularTotalesEdicion()">
                                <option value="Credito" {{ $venta->tipo_venta === 'Credito' ? 'selected' : '' }}>Crédito</option>
                                <option value="Contado" {{ $venta->tipo_venta === 'Contado' ? 'selected' : '' }}>Contado</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Vendedor</label>
                            <select name="vendedor_id" class="form-select rounded-3" required>
                                @foreach($vendedores as $vend)
                                    <option value="{{ $vend->id }}" {{ $venta->vendedor_id == $vend->id ? 'selected' : '' }}>
                                        {{ strtoupper($vend->username) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Cobrador Asignado</label>
                            <select name="cobrador_id" class="form-select rounded-3">
                                <option value="">-- Sin Cobrador --</option>
                                @foreach($cobradores as $cob)
                                    <option value="{{ $cob->id }}" {{ $venta->cobrador_id == $cob->id ? 'selected' : '' }}>
                                        {{ strtoupper($cob->username) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Forma de Pago</label>
                            <select name="forma_pago" class="form-select rounded-3">
                                @foreach(['Efectivo', 'Tdebito', 'Tcredito', 'Transferencia'] as $fp)
                                    <option value="{{ $fp }}" {{ $venta->forma_pago === $fp ? 'selected' : '' }}>{{ $fp }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ARTÍCULOS DE LA VENTA -->
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="fw-bold text-uppercase text-muted small m-0"><i class="bi bi-box-seam me-1"></i> Artículos de la Venta</h6>
                        <div class="d-flex gap-2">
                            <select id="selectAgregarArticulo" class="form-select form-select-sm rounded-3" style="max-width: 280px;">
                                <option value="">+ Agregar otro artículo...</option>
                                @foreach($articulosDisponibles as $art)
                                    <option value="{{ $art->id }}"
                                            data-nombre="{{ $art->nombre }}"
                                            data-precio="{{ $art->pcredito ?? $art->pcontado ?? 0 }}"
                                            data-stock="{{ $art->stock_disponible_edicion }}">
                                        {{ $art->nombre }} (Disp: {{ $art->stock_disponible_edicion }})
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-primary rounded-3 fw-semibold" onclick="agregarFilaArticulo()">
                                <i class="bi bi-plus-lg"></i> Añadir
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle" id="tablaArticulosEdicion">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>Artículo</th>
                                    <th style="width: 110px;">Cant.</th>
                                    <th style="width: 140px;">N° Serie</th>
                                    <th style="width: 130px;">Precio U.</th>
                                    <th class="text-end" style="width: 130px;">Subtotal</th>
                                    <th class="text-center" style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="tbodyArticulosEdicion">
                                @foreach($venta->detalles as $idx => $det)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="articulos[{{ $idx }}][id]" value="{{ $det->articulo_id }}">
                                            <strong class="text-dark d-block">{{ $det->nombre_articulo ?? ($det->articulo->nombre ?? 'Artículo') }}</strong>
                                        </td>
                                        <td>
                                            <input type="number" name="articulos[{{ $idx }}][cantidad]" class="form-control form-control-sm inp-cant" min="1" value="{{ $det->cantidad }}" oninput="recalcularTotalesEdicion()" required>
                                        </td>
                                        <td>
                                            <input type="text" name="articulos[{{ $idx }}][numero_serie]" class="form-control form-control-sm" value="{{ $det->numero_serie }}" placeholder="S/N">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="articulos[{{ $idx }}][precio_unitario]" class="form-control form-control-sm inp-precio" min="0" value="{{ number_format($det->precio_unitario, 2, '.', '') }}" oninput="recalcularTotalesEdicion()" required>
                                        </td>
                                        <td class="text-end fw-bold text-dark td-subtotal">
                                            ${{ number_format($det->subtotal, 2) }}
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-3" onclick="eliminarFilaArticulo(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RESUMEN FINANCIERO Y PLAN DE PAGOS -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                    <h6 class="fw-bold text-uppercase text-muted small mb-3"><i class="bi bi-calculator me-1"></i> Plan de Pagos y Saldos</h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Frecuencia de Cobro</label>
                        <select name="frecuencia_nombre" class="form-select rounded-3">
                            @foreach(['Semanal', 'Quincenal', 'Mensual'] as $frec)
                                <option value="{{ $frec }}" {{ $venta->frecuencia_nombre === $frec ? 'selected' : '' }}>{{ $frec }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">N° Pagos</label>
                            <input type="number" name="num_pagos" id="edit_num_pagos" class="form-control rounded-3" min="1" value="{{ $venta->num_pagos ?? 1 }}" oninput="recalcularTotalesEdicion()">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Cuota ($)</label>
                            <input type="number" step="0.01" name="pago_cuota" id="edit_pago_cuota" class="form-control rounded-3" value="{{ number_format($venta->pago_cuota ?? 0, 2, '.', '') }}">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Próximo Pago</label>
                            <input type="date" name="fecha_primer_pago" class="form-control rounded-3" value="{{ $venta->fecha_primer_pago ? \Carbon\Carbon::parse($venta->fecha_primer_pago)->format('Y-m-d') : '' }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Día Semana</label>
                            <select name="dia_semana" class="form-select rounded-3">
                                <option value="">-- Día --</option>
                                @foreach(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $dia)
                                    <option value="{{ $dia }}" {{ $venta->dia_semana === $dia ? 'selected' : '' }}>{{ $dia }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fw-semibold">Total Venta:</span>
                        <strong class="fs-5 text-dark" id="lbl_edit_total">${{ number_format($venta->total, 2) }}</strong>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">Enganche / Anticipo ($)</label>
                        <input type="number" step="0.01" name="anticipo" id="edit_anticipo" class="form-control rounded-3" min="0" value="{{ number_format($venta->anticipo ?? 0, 2, '.', '') }}" oninput="recalcularTotalesEdicion()">
                    </div>

                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Abonos posteriores cobrados:</span>
                        <span class="fw-bold text-success">-${{ number_format($abonosPrevios, 2) }}</span>
                    </div>

                    <div class="p-3 bg-danger bg-opacity-10 rounded-3 border border-danger d-flex justify-content-between align-items-center mt-3">
                        <span class="text-danger fw-bold">SALDO RESTANTE:</span>
                        <strong class="text-danger fs-4" id="lbl_edit_saldo">${{ number_format($venta->saldo_restante, 2) }}</strong>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary rounded-3 py-2 fw-bold shadow-sm">
                            <i class="bi bi-check-circle-fill me-1"></i> Guardar Cambios de la Venta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- BOTÓN CANCELAR / ELIMINAR VENTA -->
    @if(auth()->user()->tienePermiso('ventas.delete'))
        <div class="card border-danger border-opacity-25 shadow-sm rounded-4 bg-danger bg-opacity-10 p-3 mt-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-danger small">
                    <i class="bi bi-exclamation-octagon-fill me-1"></i>
                    <strong>¿Deseas cancelar y eliminar esta venta por completo?</strong> Se devolverá la mercancía al inventario del vendedor.
                </div>
                <form action="{{ route('ventas.cambiar_estatus', $venta->id) }}" method="POST" onsubmit="return confirm('⚠️ ¡Atención! ¿Confirmas que deseas CANCELAR Y ELIMINAR por completo la Venta #{{ $venta->folio }}?')">
                    @csrf
                    <input type="hidden" name="nuevo_estatus" value="Eliminar">
                    <button type="submit" class="btn btn-danger btn-sm rounded-3 px-3 fw-bold">
                        <i class="bi bi-trash3-fill me-1"></i> Cancelar y Eliminar Venta
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>

<script>
    let contadorFilasEdicion = {{ $venta->detalles->count() }};
    const abonosPreviosEdicion = {{ floatval($abonosPrevios) }};

    function agregarFilaArticulo() {
        const select = document.getElementById('selectAgregarArticulo');
        const option = select.options[select.selectedIndex];
        if (!option || !option.value) return;

        const id     = option.value;
        const nombre = option.getAttribute('data-nombre');
        const precio = parseFloat(option.getAttribute('data-precio') || 0).toFixed(2);
        const idx    = contadorFilasEdicion++;

        const tbody = document.getElementById('tbodyArticulosEdicion');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="hidden" name="articulos[${idx}][id]" value="${id}">
                <strong class="text-dark d-block">${nombre}</strong>
            </td>
            <td>
                <input type="number" name="articulos[${idx}][cantidad]" class="form-control form-control-sm inp-cant" min="1" value="1" oninput="recalcularTotalesEdicion()" required>
            </td>
            <td>
                <input type="text" name="articulos[${idx}][numero_serie]" class="form-control form-control-sm" placeholder="S/N">
            </td>
            <td>
                <input type="number" step="0.01" name="articulos[${idx}][precio_unitario]" class="form-control form-control-sm inp-precio" min="0" value="${precio}" oninput="recalcularTotalesEdicion()" required>
            </td>
            <td class="text-end fw-bold text-dark td-subtotal">$${precio}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger rounded-3" onclick="eliminarFilaArticulo(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        select.selectedIndex = 0;
        recalcularTotalesEdicion();
    }

    function eliminarFilaArticulo(btn) {
        const tbody = document.getElementById('tbodyArticulosEdicion');
        if (tbody.rows.length <= 1) {
            alert('⚠️ La venta debe conservar al menos un artículo. Si deseas cancelarla por completo usa el botón rojo inferior.');
            return;
        }
        btn.closest('tr').remove();
        recalcularTotalesEdicion();
    }

    function recalcularTotalesEdicion() {
        let total = 0;
        document.querySelectorAll('#tbodyArticulosEdicion tr').forEach(tr => {
            const cant   = parseFloat(tr.querySelector('.inp-cant').value || 0);
            const precio = parseFloat(tr.querySelector('.inp-precio').value || 0);
            const sub    = cant * precio;
            total += sub;
            tr.querySelector('.td-subtotal').innerText = '$' + sub.toFixed(2);
        });

        const anticipo = parseFloat(document.getElementById('edit_anticipo').value || 0);
        const saldo    = Math.max(0, total - anticipo - abonosPreviosEdicion);
        const numPagos = Math.max(1, parseInt(document.getElementById('edit_num_pagos').value || 1));

        document.getElementById('lbl_edit_total').innerText = '$' + total.toFixed(2);
        document.getElementById('lbl_edit_saldo').innerText = '$' + saldo.toFixed(2);
        document.getElementById('edit_pago_cuota').value    = (saldo / numPagos).toFixed(2);
    }
</script>
@endsection