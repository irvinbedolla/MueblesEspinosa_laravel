<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Ejecutivo de Cierre de Caja</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #1a1a1a; margin: 0; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-start { text-align: left; }
        .fw-bold { font-weight: bold; }
        .text-muted { color: #555; }
        .text-success { color: #137333; }
        .text-danger { color: #c5221f; }
        .text-primary { color: #1a73e8; }

        /* Encabezado */
        .header-box { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
        .header-title { font-size: 18px; font-weight: bold; letter-spacing: 0.5px; margin: 0; }
        .header-sub { font-size: 9.5px; color: #444; margin-top: 2px; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; background: #f8f9fa; border: 1px solid #d0d7de; }
        .meta-table td { padding: 5px 8px; font-size: 9.5px; border: none; }

        /* Tarjetas KPI Superiores */
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; }
        .kpi-card { border: 1px solid #bbb; background: #fff; padding: 7px 6px; text-align: center; width: 20%; }
        .kpi-card.highlight { background: #eef4ff; border-color: #1a73e8; }
        .kpi-label { font-size: 8px; text-transform: uppercase; color: #555; font-weight: bold; display: block; margin-bottom: 3px; }
        .kpi-value { font-size: 13px; font-weight: bold; color: #000; }
        .kpi-sub { font-size: 8px; color: #666; display: block; margin-top: 2px; }

        /* Títulos de Sección */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            background: #222;
            color: #fff;
            padding: 5px 8px;
            margin-top: 12px;
            margin-bottom: 0;
            letter-spacing: 0.3px;
        }

        /* Tablas de Datos */
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        table.data-table th, table.data-table td { border: 1px solid #bbb; padding: 4px 6px; font-size: 9.5px; }
        table.data-table th { background-color: #eaecef; text-transform: uppercase; font-size: 8.5px; color: #222; }
        table.data-table tr:nth-child(even) td { background-color: #fafafa; }
        table.data-table tfoot td { background-color: #eaecef; font-weight: bold; border-top: 1.5px solid #000; }

        /* Badges de tipo/método */
        .badge { display: inline-block; padding: 1px 5px; font-size: 8.5px; font-weight: bold; border-radius: 3px; border: 1px solid #999; }
        .badge-eng { background: #e6f4ea; border-color: #137333; color: #137333; }
        .badge-liq { background: #e8f0fe; border-color: #1a73e8; color: #1a73e8; }
        .badge-abo { background: #f1f3f4; border-color: #5f6368; color: #3c4043; }

        /* Resumen Final y Firmas */
        .summary-grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .summary-grid td.col-half { width: 50%; vertical-align: top; padding: 0; }
        .box-total { border: 2px solid #000; background-color: #f0f4f8; padding: 10px; font-size: 13px; font-weight: bold; text-align: center; margin-top: 12px; }
        .signatures { width: 100%; margin-top: 45px; border-collapse: collapse; }
        .signatures td { border: none; text-align: center; font-size: 9.5px; }

        @media print {
            .no-print { display: none; }
            .section-title { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th, .kpi-card.highlight, .box-total, .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print();">

    <!-- ENCABEZADO EMPRESA -->
    <div class="header-box text-center">
        <div class="header-title">MUEBLES ESPINOSA</div>
        <div class="header-sub">Calle Revolución #104 | Col. Heberto Castillo | Cd. Altamirano, Gro.</div>
    </div>

    <!-- METADATOS DEL REPORTE -->
    <table class="meta-table">
        <tr>
            <td><strong>REPORTE EJECUTIVO DE CIERRE E INGRESOS</strong></td>
            <td class="text-end">Emisión: <strong>{{ date('d/m/Y') }} {{ $horaActual }}</strong></td>
        </tr>
        <tr>
            <td>Periodo Evaluado: <strong>{{ $fecha1 }} al {{ $fecha2 }}</strong> &nbsp;|&nbsp; Filtro Usuario: <strong>{{ $cobradorFiltroNombre }}</strong></td>
            <td class="text-end">Generado por: <strong>{{ $nombreVendedor }}</strong></td>
        </tr>
    </table>

    <!-- INDICADORES EJECUTIVOS (KPIs) -->
    @php
        $totalOperaciones = $abonos->count();
        $totalCarteraProgramada = $totalOperaciones + $totalPendientes;
        $efectividad = $totalCarteraProgramada > 0 ? round(($totalOperaciones / $totalCarteraProgramada) * 100, 1) : 100;
    @endphp
    <table class="kpi-table">
        <tr>
            <td class="kpi-card highlight">
                <span class="kpi-label">Total Recaudado</span>
                <span class="kpi-value text-primary">${{ number_format($totalGeneral, 2) }}</span>
                <span class="kpi-sub">{{ $totalOperaciones }} movimientos</span>
            </td>
            <td class="kpi-card">
                <span class="kpi-label">Efectivo en Caja</span>
                <span class="kpi-value text-success">${{ number_format($totalEfectivo, 2) }}</span>
                <span class="kpi-sub">Entrega física</span>
            </td>
            <td class="kpi-card">
                <span class="kpi-label">Bancos / Tarjetas</span>
                <span class="kpi-value">${{ number_format($totalTransferencia + $totalTarjeta, 2) }}</span>
                <span class="kpi-sub">Transf: ${{ number_format($totalTransferencia, 2) }} | Tarj: ${{ number_format($totalTarjeta, 2) }}</span>
            </td>
            <td class="kpi-card">
                <span class="kpi-label">Pendiente de Cobro</span>
                <span class="kpi-value text-danger">${{ number_format($montoPendienteCuotas, 2) }}</span>
                <span class="kpi-sub">{{ $totalPendientes }} cuentas sin pago</span>
            </td>
            <td class="kpi-card">
                <span class="kpi-label">Efectividad Cobro</span>
                <span class="kpi-value">{{ $efectividad }}%</span>
                <span class="kpi-sub">{{ $totalOperaciones }} de {{ $totalCarteraProgramada }} cuentas</span>
            </td>
        </tr>
    </table>

    <!-- APARTADO 1: DESGLOSE EJECUTIVO POR USUARIO / COBRADOR -->
    <div class="section-title">1. Desglose por Usuario / Cobrador (Cobrado vs. Pendiente)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-start">Usuario / Cobrador</th>
                <th class="text-center">Cobros</th>
                <th class="text-end">Efectivo</th>
                <th class="text-end">Transf. / Dep.</th>
                <th class="text-end">Tarjetas</th>
                <th class="text-end">Total Cobrado</th>
                <th class="text-center">Ctas. Pend.</th>
                <th class="text-end">Cuotas Pend.</th>
                <th class="text-center">Efectiv.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumenUsuarios as $ru)
                @php
                    $totalUserCtas = $ru['cant_cobros'] + $ru['cuentas_pendientes'];
                    $efecUser = $totalUserCtas > 0 ? round(($ru['cant_cobros'] / $totalUserCtas) * 100, 0) : 100;
                @endphp
                <tr>
                    <td class="fw-bold">{{ $ru['usuario'] }}</td>
                    <td class="text-center">{{ $ru['cant_cobros'] }}</td>
                    <td class="text-end">${{ number_format($ru['efectivo'], 2) }}</td>
                    <td class="text-end">${{ number_format($ru['transferencia'], 2) }}</td>
                    <td class="text-end">${{ number_format($ru['tarjeta'], 2) }}</td>
                    <td class="text-end fw-bold text-success">${{ number_format($ru['total_cobrado'], 2) }}</td>
                    <td class="text-center text-danger fw-bold">{{ $ru['cuentas_pendientes'] }}</td>
                    <td class="text-end text-danger">${{ number_format($ru['monto_pendiente'], 2) }}</td>
                    <td class="text-center fw-bold">{{ $efecUser }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">Sin actividad registrada para usuarios en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>TOTALES GENERALES</td>
                <td class="text-center">{{ $totalOperaciones }}</td>
                <td class="text-end">${{ number_format($totalEfectivo, 2) }}</td>
                <td class="text-end">${{ number_format($totalTransferencia, 2) }}</td>
                <td class="text-end">${{ number_format($totalTarjeta, 2) }}</td>
                <td class="text-end text-success">${{ number_format($totalGeneral, 2) }}</td>
                <td class="text-center text-danger">{{ $totalPendientes }}</td>
                <td class="text-end text-danger">${{ number_format($montoPendienteCuotas, 2) }}</td>
                <td class="text-center">{{ $efectividad }}%</td>
            </tr>
        </tfoot>
    </table>

    <!-- APARTADO 2: DETALLE DE MOVIMIENTOS COBRADOS -->
    <div class="section-title">2. Detalle Cronológico de Cobros Realizados</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID Vta</th>
                <th>Fecha</th>
                <th class="text-start">Cliente</th>
                <th class="text-start">Usuario</th>
                <th>Pago</th>
                <th>Tipo</th>
                <th>Método</th>
                <th class="text-end">Monto</th>
                <th class="text-end">S. Ant.</th>
                <th class="text-end">S. Rest.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($abonos as $ab)
                @php
                    $badgeClass = $ab->tipo_imprimir === 'Enganche' ? 'badge-eng' : ($ab->tipo_imprimir === 'Liquidación' ? 'badge-liq' : 'badge-abo');
                @endphp
                <tr>
                    <td class="text-center fw-bold">{{ $ab->venta_numero ?? 'S/F' }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($ab->a_fecha)->format('d/m/Y') }}</td>
                    <td>{{ Str::limit($ab->cliente_nombre ?? 'N/A', 26) }}</td>
                    <td>{{ strtoupper($ab->nombre_cobrador) }}</td>
                    <td class="text-center">#{{ $ab->a_npago }}</td>
                    <td class="text-center"><span class="badge {{ $badgeClass }}">{{ $ab->tipo_imprimir }}</span></td>
                    <td class="text-center">{{ $ab->metodo_imprimir }}</td>
                    <td class="text-end fw-bold">${{ number_format($ab->a_pago, 2) }}</td>
                    <td class="text-end text-muted">${{ number_format($ab->saldo_anterior, 2) }}</td>
                    <td class="text-end">${{ number_format($ab->saldo_restante, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">No existen movimientos registrados en el periodo seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- APARTADO 3: CONSOLIDADO FINANCIERO POR CONCEPTO Y MÉTODO -->
    <table class="summary-grid">
        <tr>
            <td class="col-half" style="padding-right: 6px;">
                <div class="section-title">3A. Desglose por Tipo de Ingreso</div>
                <table class="data-table">
                    <tr>
                        <td>Enganches ({{ $cantEnganches }})</td>
                        <td class="text-end fw-bold">${{ number_format($totalEnganches, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Abonos Regulares ({{ $cantAbonos }})</td>
                        <td class="text-end fw-bold">${{ number_format($totalAbonos, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Liquidaciones ({{ $cantLiquidaciones }})</td>
                        <td class="text-end fw-bold">${{ number_format($totalLiquidaciones, 2) }}</td>
                    </tr>
                    <tfoot>
                        <tr>
                            <td>TOTAL INGRESOS ({{ $totalOperaciones }})</td>
                            <td class="text-end">${{ number_format($totalGeneral, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
            <td class="col-half" style="padding-left: 6px;">
                <div class="section-title">3B. Arqueo por Método y Cartera</div>
                <table class="data-table">
                    <tr>
                        <td>Efectivo Físico</td>
                        <td class="text-end fw-bold">${{ number_format($totalEfectivo, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Transferencias / Depósitos</td>
                        <td class="text-end fw-bold">${{ number_format($totalTransferencia, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Tarjetas (Débito / Crédito)</td>
                        <td class="text-end fw-bold">${{ number_format($totalTarjeta, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-danger">Pendiente en Periodo ({{ $totalPendientes }} ctas)</td>
                        <td class="text-end text-danger fw-bold">${{ number_format($montoPendienteCuotas, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="box-total">
        TOTAL BRUTO RECAUDADO EN EL PERIODO: ${{ number_format($totalGeneral, 2) }}
        <span style="font-size: 10px; font-weight: normal; display: block; margin-top: 2px;">
            (Efectivo a entregar en caja: ${{ number_format($totalEfectivo, 2) }} &nbsp;|&nbsp; Ingresos en banco/terminal: ${{ number_format($totalTransferencia + $totalTarjeta, 2) }})
        </span>
    </div>

    <!-- FIRMAS DE CONTROL -->
    <table class="signatures">
        <tr>
            <td style="width: 33%;">
                ___________________________<br>
                Firma de Cobrador / Usuario
            </td>
            <td style="width: 33%;">
                ___________________________<br>
                Firma de Encargado de Caja
            </td>
            <td style="width: 33%;">
                ___________________________<br>
                Sello / Auditoría de Cierre
            </td>
        </tr>
    </table>

</body>
</html>