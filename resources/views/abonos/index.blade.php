@extends('layouts.app')

@section('title', 'Gestión de Abonos - Muebles Espinosa')

@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/2.2.1/css/dataTables.bootstrap5.css">

<style>
    html, body { width: 100%; overflow-x: hidden; }
    .table-responsive-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; }
    .table-custom-style th, .table-custom-style td { white-space: nowrap; padding: 10px 12px !important; }
    .col-nombre { min-width: 190px; white-space: normal !important; }
    .dt-search, .dt-length { display: none !important; }
</style>

@php
    // Evaluamos el permiso UNA SOLA VEZ fuera del ciclo para evitar consultas N+1
    $puedeCobrar = auth()->user()->tienePermiso('cobranza.register');
@endphp

<div class="container-fluid px-2 px-md-4 mt-4 mb-5">
    
    <!-- Panel Superior Informativo y Controles -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h4 class="fw-bold text-dark mb-1"><i class="bi bi-wallet2 text-primary me-2"></i>Gestión de Abonos</h4>
                <p class="text-muted small mb-0">Total Encontrado: <span class="badge bg-primary bg-opacity-10 text-primary fw-bold">{{ count($ventas) }} Ventas</span></p>
            </div>
            <div class="col-md-8">
                <div class="row g-2 justify-content-end">
                    <div class="col-sm-5 col-md-4">
                        <div class="input-group input-group-sm shadow-sm rounded-3">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="buscadorGeneralInput" class="form-control border-start-0" placeholder="Filtrar en tabla...">
                        </div>
                    </div>
                    
                    <div class="col-sm-3 col-md-4">
                        <form action="{{ route('abonos.index') }}" method="GET" class="d-flex gap-1">
                            <input type="hidden" name="tipo" value="{{ $tipoAbono }}">
                            <select name="Dia" class="form-select form-select-sm shadow-sm" required>
                                <option value="">Cambiar Día</option>
                                <option value="Todos" {{ $dia == 'Todos' ? 'selected' : '' }}>Todos</option>
                                <option value="Lunes" {{ $dia == 'Lunes' ? 'selected' : '' }}>Lunes</option>
                                <option value="Martes" {{ $dia == 'Martes' ? 'selected' : '' }}>Martes</option>
                                <option value="Miercoles" {{ $dia == 'Miercoles' ? 'selected' : '' }}>Miércoles</option>
                                <option value="Jueves" {{ $dia == 'Jueves' ? 'selected' : '' }}>Jueves</option>
                                <option value="Viernes" {{ $dia == 'Viernes' ? 'selected' : '' }}>Viernes</option>
                                <option value="Sabado" {{ $dia == 'Sabado' ? 'selected' : '' }}>Sábado</option>
                                <option value="Domingo" {{ $dia == 'Domingo' ? 'selected' : '' }}>Domingo</option>
                            </select>
                            <button class="btn btn-sm btn-primary shadow-sm px-2" type="submit"><i class="bi bi-funnel"></i></button>
                        </form>
                    </div>

                    @if($tipoSeccion == 0)
                        <div class="col-sm-4 col-md-4">
                            <form action="{{ route('abonos.index') }}" method="GET" class="d-flex gap-1">
                                <input type="hidden" name="tipo" value="{{ $tipoAbono }}">
                                <input type="hidden" name="Dia" value="{{ $dia }}">
                                <select name="cobrador" class="form-select form-select-sm shadow-sm" required>
                                    <option value="">Filtrar Cobrador</option>
                                    <option value="Todos" {{ (string)$filtroCobrador === '0' || $filtroCobrador === 'Todos' ? 'selected' : '' }}>Ver Todos</option>
                                    @foreach($cobradores as $c)
                                        @php
                                            $cobId   = is_object($c) ? ($c->id ?? '') : (is_array($c) ? ($c['id'] ?? '') : $c);
                                            $cobName = is_object($c) ? ($c->username ?? $cobId) : (is_array($c) ? ($c['username'] ?? $cobId) : $c);
                                        @endphp
                                        <option value="{{ $cobId }}" {{ (string)$filtroCobrador === (string)$cobId ? 'selected' : '' }}>
                                            {{ $cobName }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-dark shadow-sm text-nowrap px-2" type="submit">Filtrar</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Principal Conectada a DataTables -->
    <div class="card border-0 shadow-sm rounded-4 bg-white">
        <div class="card-body p-2 p-md-3">
            <div class="table-responsive-scroll">
                <table id="tablaAbonosGlobal" class="table table-hover align-middle table-custom-style mb-0">
                    <thead class="table-light text-secondary text-uppercase small">
                        <tr>
                            <th>N° Venta</th>
                            <th>Fecha</th>
                            <th>Vendedor</th>
                            <th>Cobrador</th>
                            <th class="col-nombre">Cliente</th>
                            <th>Teléfono</th>
                            <th>Ciudad</th>
                            <th class="text-center">Pagos</th>
                            <th>Próx. Pago</th>
                            <th class="text-end">Restante</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Día Pago</th>
                            <th class="text-center">Abonar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ventas as $row)
                            @php
                                $rowObj = is_array($row) ? (object) $row : $row;
                            @endphp
                            @if(is_object($rowObj))
                                <tr>
                                    <td class="fw-bold text-dark">#{{ $rowObj->folio ?? $rowObj->venta_id_legacy ?? $rowObj->id }}</td>
                                    <td class="text-muted small">{{ $rowObj->fecha_formato ?? '' }}</td>
                                    <td>{{ $rowObj->vendedor_nombre ?? 'Sistema' }}</td>
                                    <td>{{ $rowObj->cobrador_nombre ?? 'Sin asignar' }}</td>
                                    <td class="col-nombre fw-semibold text-dark">{{ $rowObj->cliente_nombre ?? 'Sin Cliente' }}</td>
                                    <td>
                                        @if(!empty($rowObj->cliente_telefono))
                                            <a href="tel:+52{{ $rowObj->cliente_telefono }}" class="btn btn-sm btn-light border px-2 py-1 rounded-3 small text-nowrap">
                                                <i class="bi bi-telephone text-success me-1"></i>{{ $rowObj->cliente_telefono }}
                                            </a>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-muted small">{{ $rowObj->cliente_ciudad ?? '-' }}</td>
                                    <td class="text-center fw-medium">{{ $rowObj->num_pagos ?? '0' }}</td>
                                    <td class="small fw-semibold text-secondary">{{ $rowObj->fpago_formato ?? '-' }}</td>
                                    <td class="text-end fw-bold text-danger">${{ number_format($rowObj->saldo_restante ?? 0, 2) }}</td>
                                    <td class="text-end fw-semibold text-dark">${{ number_format($rowObj->total ?? 0, 2) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">{{ $rowObj->status ?? 'Pendiente' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">{{ $rowObj->dia_semana ?? '-' }}</span>
                                    </td>
                                    <td class="text-center pe-4 text-nowrap">
                                        @if($puedeCobrar)
                                            <a href="{{ route('abonos.show', ['id' => $rowObj->id, 'tipo' => $tipoAbono]) }}" class="btn btn-sm btn-success px-3 fw-bold rounded-3 shadow-sm text-nowrap">
                                                <i class="bi bi-cash me-1"></i> Cobrar
                                            </a>
                                        @else
                                            <span class="badge bg-light text-muted border">Solo lectura</span>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap mt-3 px-2" id="paginacionDivInterno"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.2.1/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.2.1/js/dataTables.bootstrap5.js"></script>

<script>
    var $j = jQuery.noConflict();
    $j.fn.dataTable.ext.errMode = 'none';

    $j(document).ready(function() {
        var table = $j('#tablaAbonosGlobal').DataTable({
            "pageLength": 15,
            "order": [[0, "desc"]],
            "deferRender": true,
            "language": {
                "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "zeroRecords": "No se encontraron resultados",
                "paginate": {
                    "first": "Primero",
                    "last": "Último",
                    "next": "Siguiente",
                    "previous": "Anterior"
                }
            },
            "dom": 'rt<"d-flex justify-content-between align-items-center w-100 flex-wrap"ip>',
            "initComplete": function() {
                $j('.dt-paging, .dt-info').appendTo('#paginacionDivInterno');
            }
        });

        // Debounce de 250ms para que el buscador no trabe al escribir rápido
        var searchTimer;
        $j('#buscadorGeneralInput').on('keyup', function() {
            var val = this.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                table.search(val).draw();
            }, 250);
        });
    });
</script>
@endsection