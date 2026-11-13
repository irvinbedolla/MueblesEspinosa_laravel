<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Cierre de Caja</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; margin: 15px; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .header-title { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
        .header-sub { font-size: 10px; margin-bottom: 10px; }
        .divider { border-bottom: 1px solid #000; margin: 8px 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 4px 6px; font-size: 10px; }
        th { background-color: #f0f0f0; text-transform: uppercase; }
        
        .box-total { border: 1px solid #000; background-color: #f0f0f0; padding: 8px; font-size: 13px; font-weight: bold; text-align: center; margin-top: 10px; }
        .signatures { margin-top: 40px; }
        .signatures td { border: none; text-align: center; }
    </style>
</head>
<body onload="window.print();">

    <div class="text-center">
        <div class="header-title">MUEBLES ESPINOSA</div>
        <div class="header-sub">Calle Revolución #104 | Col. Heberto Castillo | Cd. Altamirano Gro.</div>
    </div>

    <div class="divider"></div>

    <table style="border:none; margin-bottom: 10px;">
        <tr style="border:none;">
            <td style="border:none;" class="fw-bold">REPORTE GENERAL DE INGRESOS POR PERIODO</td>
            <td style="border:none;" class="text-end">Fecha Reporte: {{ date('d/m/Y') }} | Hora: {{ $horaActual }}</td>
        </tr>
        <tr style="border:none;">
            <td style="border:none;">Generado por: <strong>{{ $nombreVendedor }}</strong></td>
            <td style="border:none;" class="text-end">Rango del Periodo: <strong>{{ $fecha1 }} al {{ $fecha2 }}</strong></td>
        </tr>
    </table>

    <!-- TABLA DE DETALLES DE ABONOS -->
    <table>
        <thead>
            <tr>
                <th>ID Vta</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>N° Pago</th>
                <th>Met. Pago</th>
                <th>Abono</th>
                <th>S. Anterior</th>
                <th>S. Restante</th>
            </tr>
        </thead>
        <tbody>
            @forelse($abonos as $ab)
                <tr>
                    <td class="text-center">{{ $ab->venta_numero ?? 'S/F' }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($ab->a_fecha)->format('d/m/Y') }}</td>
                    <td>{{ Str::limit($ab->cliente_nombre ?? 'N/A', 28) }}</td>
                    <td class="text-center">#{{ $ab->a_npago }}</td>
                    <td class="text-center">{{ $ab->metodo_imprimir }}</td>
                    <td class="text-end">${{ number_format($ab->a_pago, 2) }}</td>
                    <td class="text-end">${{ number_format($ab->saldo_anterior, 2) }}</td>
                    <td class="text-end">${{ number_format($ab->saldo_restante, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-3">No existen movimientos registrados en el periodo seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 15px;">
        <div class="fw-bold" style="border-bottom: 1px solid #000; padding-bottom: 3px;">RESUMEN GENERAL DEL PERIODO FILTRADO</div>
        <table style="border:none; margin-top: 5px;">
            <tr style="border:none;">
                <td style="border:none;">Ventas Pendientes en Periodo: <strong>{{ number_format($totalPendientes) }}</strong></td>
                <td style="border:none;">Cantidad de Enganches (E): <strong>{{ $cantEnganches }}</strong></td>
                <td style="border:none;">Cantidad de Abonos (A): <strong>{{ $cantAbonos }}</strong></td>
            </tr>
            <tr style="border:none;">
                <td style="border:none;">Total de Enganches: <strong>${{ number_format($totalEnganches, 2) }}</strong></td>
                <td style="border:none;">Total en Efectivo: <strong>${{ number_format($totalEfectivo, 2) }}</strong></td>
                <td style="border:none;">Total de Abonos: <strong>${{ number_format($totalAbonos, 2) }}</strong></td>
            </tr>
            <tr style="border:none;">
                <td style="border:none;"></td>
                <td style="border:none;">Total en Tarjetas/Bancos: <strong>${{ number_format($totalTarjeta, 2) }}</strong></td>
                <td style="border:none;"></td>
            </tr>
        </table>
    </div>

    <div class="box-total">
        TOTAL BRUTO RECAUDADO EN CAJA: ${{ number_format($totalGeneral, 2) }}
    </div>

    <!-- FIRMAS DE CONTROL -->
    <table class="signatures">
        <tr>
            <td style="width: 50%;">
                ___________________________<br>
                Firma de Encargado de Caja
            </td>
            <td style="width: 50%;">
                ___________________________<br>
                Sello / Auditoría de Cierre
            </td>
        </tr>
    </table>

</body>
</html>