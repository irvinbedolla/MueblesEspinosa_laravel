<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VentaPdfController extends Controller
{
    /**
     * Helper interno para convertir UTF-8 a ISO-8859-1 de forma segura en FPDF
     */
    private function txt($texto): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($texto ?? ''));
    }

    /**
     * Documento 1: Ticket Resumen de la Venta (58mm ancho x alto dinámico)
     */
    public function ticket($id)
    {
        // 1. Obtener los datos generales de la Venta
        $venta = DB::table('ventas')
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                  ->orWhere('folio', $id);
                if (Schema::hasColumn('ventas', 'venta_id_legacy')) {
                    $q->orWhere('venta_id_legacy', $id);
                }
            })
            ->first();

        if (!$venta) {
            abort(404, "No se encontró la venta con el identificador: {$id}");
        }

        // 2. Cliente y Vendedor
        $cliente = DB::table('clientes')->where('id', $venta->cliente_id)->first();
        $vendedor = DB::table('users')
            ->where('id', $venta->vendedor_id)
            ->orWhere('user_id_legacy', $venta->vendedor_id)
            ->first();

        // 3. Detalles de los artículos
        $detalles = DB::table('venta_detalles as vd')
            ->select('vd.*', 'a.nombre as nombre_catalogo', 'a.marca as marca_catalogo', 'a.modelo as modelo_catalogo')
            ->leftJoin('articulos as a', 'vd.articulo_id', '=', 'a.id')
            ->where('vd.venta_id', $venta->id)
            ->get();

        // Calcular altura dinámica del ticket (evita cortes de página en rollo térmico)
        $altoDinamico = max(135, 95 + ($detalles->count() * 10));
        $anchoTicket = 58; // 58mm estándar térmico
        $margen = 3;
        $anchoUtil = $anchoTicket - ($margen * 2); // 52mm útiles

        $pdf = new \FPDF('P', 'mm', [$anchoTicket, $altoDinamico]);
        $pdf->SetMargins($margen, 4, $margen);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(40, 40, 40);

        // --- ENCABEZADO ---
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($anchoUtil, 5, $this->txt('MUEBLES ESPINOSA'), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 6.5);
        $pdf->Cell($anchoUtil, 3.2, $this->txt('Muebles y Artículos para el Hogar'), 0, 1, 'C');
        $pdf->Cell($anchoUtil, 3.2, $this->txt('Calle Caltzontzin #50, Col. Linda Vista'), 0, 1, 'C');
        $pdf->Cell($anchoUtil, 3.2, $this->txt('Huetamo, Mich. | Tel. (435) 556-32-81'), 0, 1, 'C');

        $pdf->Ln(1.5);
        $pdf->Line($margen, $pdf->GetY(), $anchoTicket - $margen, $pdf->GetY());
        $pdf->Ln(1.5);

        // --- DATOS DEL TICKET ---
        $fechaFormato = Carbon::parse($venta->fecha_venta)->format('d/m/Y');
        $horaFormato  = $venta->venta_hora ?? ($venta->created_at ? Carbon::parse($venta->created_at)->format('H:i') : date('H:i'));
        $tipoVenta    = strtoupper($venta->tipo_venta ?? 'CREDITO');

        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($anchoUtil / 2, 4, $this->txt('FOLIO: #' . $venta->folio), 0, 0, 'L');
        $pdf->Cell($anchoUtil / 2, 4, $this->txt($tipoVenta), 0, 1, 'R');

        $pdf->SetFont('Arial', '', 6.5);
        $pdf->Cell($anchoUtil, 3.5, $this->txt("Fecha: {$fechaFormato}   Hora: {$horaFormato}"), 0, 1, 'L');
        $pdf->Cell($anchoUtil, 3.5, $this->txt('Atendió: ' . ($vendedor->username ?? 'S/A')), 0, 1, 'L');
        $pdf->Cell($anchoUtil, 3.5, $this->txt('Forma Pago: ' . ($venta->forma_pago ?? 'Efectivo')), 0, 1, 'L');

        $pdf->Ln(1);
        $pdf->Line($margen, $pdf->GetY(), $anchoTicket - $margen, $pdf->GetY());
        $pdf->Ln(1.5);

        // --- DATOS DEL CLIENTE ---
        $pdf->SetFont('Arial', 'B', 6.5);
        $pdf->Cell($anchoUtil, 3.5, $this->txt('CLIENTE:'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->MultiCell($anchoUtil, 3.3, $this->txt($cliente->nombre ?? 'Público General'), 0, 'L');

        $direccionParts = array_filter([
            $cliente->calle ?? '',
            !empty($cliente->colonia) ? 'Col. ' . $cliente->colonia : '',
            $cliente->ciudad ?? '',
        ]);
        if (!empty($direccionParts)) {
            $pdf->MultiCell($anchoUtil, 3.2, $this->txt(implode(', ', $direccionParts)), 0, 'L');
        }
        if (!empty($cliente->telefono)) {
            $pdf->Cell($anchoUtil, 3.3, $this->txt('Tel: ' . $cliente->telefono), 0, 1, 'L');
        }

        $pdf->Ln(1.5);

        // --- TABLA DE ARTÍCULOS (Ancho total = 52mm: 27 + 6 + 9 + 10) ---
        $pdf->SetFillColor(235, 235, 235);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(27, 4.5, $this->txt('ARTÍCULO'), 'TB', 0, 'L', true);
        $pdf->Cell(6, 4.5, $this->txt('CANT'), 'TB', 0, 'C', true);
        $pdf->Cell(9, 4.5, $this->txt('P.U.'), 'TB', 0, 'R', true);
        $pdf->Cell(10, 4.5, $this->txt('IMPORTE'), 'TB', 1, 'R', true);

        $pdf->SetFont('Arial', '', 6);
        $subtotalCalc = 0;

        foreach ($detalles as $va) {
            $precioNeto = max(0, floatval($va->precio_unitario) - floatval($va->descuento ?? 0));
            $importe    = $precioNeto * intval($va->cantidad);
            $subtotalCalc += $importe;

            $nombreArt = $va->nombre_articulo ?? $va->nombre_catalogo ?? 'Artículo';
            $nombreCorto = mb_strimwidth($nombreArt, 0, 22, '..');

            $pdf->Cell(27, 4, $this->txt($nombreCorto), 0, 0, 'L');
            $pdf->Cell(6, 4, $va->cantidad, 0, 0, 'C');
            $pdf->Cell(9, 4, '$' . number_format($precioNeto, 0), 0, 0, 'R');
            $pdf->Cell(10, 4, '$' . number_format($importe, 2), 0, 1, 'R');

            if (!empty($va->numero_serie)) {
                $pdf->SetFont('Arial', 'I', 5.5);
                $pdf->Cell($anchoUtil, 3, $this->txt('  S/N: ' . $va->numero_serie), 0, 1, 'L');
                $pdf->SetFont('Arial', '', 6);
            }
        }

        $pdf->Ln(1);
        $pdf->Line($margen, $pdf->GetY(), $anchoTicket - $margen, $pdf->GetY());
        $pdf->Ln(1.5);

        // --- TOTALES Y PLAN DE PAGOS ---
        $totalVenta    = floatval($venta->total ?? $subtotalCalc);
        $anticipo      = floatval($venta->anticipo ?? 0);
        $saldoRestante = floatval($venta->saldo_restante ?? max(0, $totalVenta - $anticipo));

        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(30, 4.5, $this->txt('TOTAL VENTA:'), 0, 0, 'R');
        $pdf->Cell(22, 4.5, '$' . number_format($totalVenta, 2), 0, 1, 'R');

        if ($anticipo > 0) {
            $pdf->SetFont('Arial', '', 6.5);
            $pdf->Cell(30, 3.8, $this->txt('Anticipo / Enganche:'), 0, 0, 'R');
            $pdf->Cell(22, 3.8, '-$' . number_format($anticipo, 2), 0, 1, 'R');

            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(30, 4, $this->txt('SALDO RESTANTE:'), 0, 0, 'R');
            $pdf->Cell(22, 4, '$' . number_format($saldoRestante, 2), 0, 1, 'R');
        }

        if (strtolower($venta->tipo_venta ?? '') === 'credito') {
            $frecuencia = $venta->frecuencia_nombre ?? 'Semanal';
            $numPagos   = intval($venta->num_pagos ?? 1);
            $cuota      = floatval($venta->pago_cuota ?? ($numPagos > 0 ? $saldoRestante / $numPagos : $saldoRestante));

            $pdf->Ln(1);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetFont('Arial', 'B', 6.5);
            //$pdf->Cell($anchoUtil, 4, $this->txt("PLAN: {$numPagos} Pagos ({$frecuencia})"), 0, 1, 'C', true);
            //$pdf->Cell($anchoUtil, 4, $this->txt('Abono Estimado: $' . number_format($cuota, 2)), 0, 1, 'C', true);
        }

        // --- PIE DE TICKET ---
        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 6.5);
        $pdf->Cell($anchoUtil, 3.5, $this->txt('¡GRACIAS POR SU PREFERENCIA!'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 5.5);
        $pdf->Cell($anchoUtil, 3, $this->txt('Conserve este comprobante para cualquier aclaración.'), 0, 1, 'C');

        return response($pdf->Output('S'), 200)->header('Content-Type', 'application/pdf');
    }

    /**
     * Documento 2: Boleta de Cobro / Pagaré (10.5 cm ancho x 17.0 cm alto = 105mm x 170mm)
     */
    public function boleta($ventaId, $detalleId)
    {
        // 1. Consultar la Venta
        $venta = DB::table('ventas')
            ->where(function ($q) use ($ventaId) {
                $q->where('id', $ventaId)
                  ->orWhere('folio', $ventaId);
                if (Schema::hasColumn('ventas', 'venta_id_legacy')) {
                    $q->orWhere('venta_id_legacy', $ventaId);
                }
            })
            ->first();

        if (!$venta) {
            abort(404, "No se encontró la venta especificada: {$ventaId}");
        }

        // 2. Cargar Cliente y Vendedor
        $cliente = DB::table('clientes')->where('id', $venta->cliente_id)->first();
        $vendedor = DB::table('users')
            ->where('id', $venta->vendedor_id)
            ->orWhere('user_id_legacy', $venta->vendedor_id)
            ->first();

        // 3. Consultar el Detalle de Venta específico con su Artículo
        $va = DB::table('venta_detalles as vd')
            ->select('vd.*', 'a.nombre as articulo_nombre', 'a.marca as articulo_marca', 'a.modelo as articulo_modelo')
            ->leftJoin('articulos as a', 'vd.articulo_id', '=', 'a.id')
            ->where('vd.id', $detalleId)
            ->where(function ($q) use ($venta) {
                $q->where('vd.venta_id', $venta->id);
                if (!empty($venta->folio)) {
                    $q->orWhere('vd.venta_id', $venta->folio);
                }
            })
            ->first();

        if (!$va) {
            abort(404, "No se encontró el detalle de la venta especificado.");
        }

        // 4. Datos calculados para la boleta
        $fechaCarbon = Carbon::parse($venta->fecha_venta);
        $mesesEs = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $diaStr  = $fechaCarbon->format('d');
        $mesStr  = $mesesEs[(int) $fechaCarbon->format('m')];
        $anioStr = $fechaCarbon->format('y'); // Ej. "26" para "de 2 26"

        $fechaLimiteStr = !empty($venta->fecha_final_pago)
            ? Carbon::parse($venta->fecha_final_pago)->format('d/m/Y')
            : (!empty($venta->fecha_proximo_pago) ? Carbon::parse($venta->fecha_proximo_pago)->format('d/m/Y') : '');

        $precioUnitarioNeto = max(0, floatval($va->precio_unitario) - floatval($va->descuento ?? 0));
        $importeArticulo    = $precioUnitarioNeto * intval($va->cantidad);

        $frecuencia = $venta->frecuencia_nombre ?? 'Semanal';
        $numPagos   = max(1, intval($venta->num_pagos ?? 1));

        // Proporción del abono correspondiente a este artículo (si la venta tiene varios)
        $totalVenta    = floatval($venta->total) > 0 ? floatval($venta->total) : $importeArticulo;
        $proporcion    = $totalVenta > 0 ? ($importeArticulo / $totalVenta) : 1;
        $anticipoArt   = floatval($venta->anticipo ?? 0) * $proporcion;
        $saldoArt      = max(0, $importeArticulo - $anticipoArt);
        $abonoArticulo = $numPagos > 0 ? ($saldoArt / $numPagos) : $saldoArt;

        // 5. Crear PDF exacto: Ancho 105mm (10.5cm) x Alto 170mm (17cm)
        $pdf = new \FPDF('P', 'mm', [105, 170]);
        $pdf->SetMargins(6, 5, 6);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $anchoPage = 105;
        $mIzq      = 6;
        $mDer      = 99;
        $anchoUtil = 93; // 105 - 12

        // =========================================================================
        // BLOQUE 1: ENCABEZADO (Logo + Título Mueblería + Dirección + Cuenta Núm.)
        // =========================================================================
        $logoPath = public_path('images/logo_espinosa.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 7, 6, 30, 17);
        } else {
            // Silueta decorativa limpia si aún no subes el PNG del logo
            $pdf->SetDrawColor(110, 110, 110);
            $pdf->Rect(7, 6, 29, 16);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetXY(7, 11);
            $pdf->Cell(29, 3.5, $this->txt('MUEBLERÍA'), 0, 2, 'C');
            $pdf->Cell(29, 3.5, $this->txt('ESPINOSA'), 0, 0, 'C');
        }

        // Título derecha
        $pdf->SetTextColor(25, 25, 25);
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->SetXY(39, 6);
        $pdf->Cell(60, 4, $this->txt('Muebles y Artículos'), 0, 2, 'C');
        $pdf->Cell(60, 4, $this->txt('para el Hogar'), 0, 2, 'C');

        $pdf->SetFont('Arial', 'B', 13.5);
        $pdf->Cell(60, 5.5, $this->txt('"ESPINOSA"'), 0, 2, 'C');

        $pdf->SetFont('Arial', 'B', 6.5);
        $pdf->Cell(60, 3.2, $this->txt('A SUS ÓRDENES EN:'), 0, 2, 'C');

        // Dirección en dos columnas debajo del encabezado
        $pdf->SetFont('Arial', '', 7.2);
        $pdf->SetXY($mIzq, 24.5);
        $pdf->Cell(46, 3.5, $this->txt('Calle Caltzontzin # 50'), 0, 0, 'L');
        $pdf->Cell(47, 3.5, $this->txt('Col. Linda Vista'), 0, 1, 'R');

        $pdf->SetX($mIzq);
        $pdf->Cell(46, 3.5, $this->txt('Tels. 01(435) 556-32-81'), 0, 0, 'L');
        $pdf->Cell(47, 3.5, $this->txt('Huetamo, Mich.'), 0, 1, 'R');

        // CUENTA NÚM.
        $pdf->SetXY(52, 32.5);
        $pdf->SetFont('Arial', 'B', 6.8);
        $pdf->Cell(22, 4, $this->txt('CUENTA NÚM.'), 0, 0, 'R');
        $pdf->SetFont('Courier', 'B', 9.5);
        $pdf->SetTextColor(180, 20, 20);
        $pdf->Cell(25, 4, $this->txt($venta->folio), 'B', 1, 'C');
        $pdf->SetTextColor(25, 25, 25);

        // Fecha: "a ___ de _________ de 2 ___"
        $pdf->SetXY(33, 38.5);
        $pdf->SetFont('Arial', '', 7.8);
        $pdf->Cell(3, 4, 'a', 0, 0, 'L');
        $pdf->SetFont('Times', 'B', 8.5);
        $pdf->Cell(9, 4, $diaStr, 'B', 0, 'C');
        $pdf->SetFont('Arial', '', 7.8);
        $pdf->Cell(6, 4, 'de', 0, 0, 'C');
        $pdf->SetFont('Times', 'B', 8.5);
        $pdf->Cell(24, 4, $this->txt($mesStr), 'B', 0, 'C');
        $pdf->SetFont('Arial', '', 7.8);
        $pdf->Cell(8, 4, 'de 2', 0, 0, 'R');
        $pdf->SetFont('Times', 'B', 8.5);
        $pdf->Cell(16, 4, $anioStr, 'B', 1, 'C');

        // =========================================================================
        // BLOQUE 2: CAMPOS DE DATOS DEL CLIENTE Y GARANTÍA
        // =========================================================================
        $yActual = 44.5;
        $altoFila = 5.3;

        $campos = [
            ['label' => 'Sr.',                   'valor' => $cliente->nombre ?? ''],
            ['label' => 'Calle',                 'valor' => $cliente->calle ?? ''],
            ['label' => 'Colonia',               'valor' => $cliente->colonia ?? ''],
            ['label' => 'Pueblo ó Lugar',        'valor' => trim(($cliente->ciudad ?? '') . ' ' . ($cliente->estado ?? ''))],
            ['label' => 'Vendedor',              'valor' => $vendedor->username ?? ''],
            ['label' => 'Tiempo de Garantía',    'valor' => '30 Días (Defectos de fábrica)'],
            ['label' => 'Fecha Límite de Pago',  'valor' => $fechaLimiteStr],
            ['label' => 'Número de Serie',       'valor' => $va->numero_serie ?? 'S/N'],
        ];

        foreach ($campos as $campo) {
            $pdf->SetXY($mIzq, $yActual);
            $pdf->SetFont('Arial', '', 7.8);
            $anchoLabel = $pdf->GetStringWidth($this->txt($campo['label'])) + 2;
            $pdf->Cell($anchoLabel, 4.2, $this->txt($campo['label']), 0, 0, 'L');

            $pdf->SetFont('Times', 'B', 8.5);
            $anchoValor = $anchoUtil - $anchoLabel;
            $pdf->Cell($anchoValor, 4.2, $this->txt(mb_strimwidth($campo['valor'], 0, 48, '...')), 'B', 1, 'L');

            $yActual += $altoFila;
        }

        // =========================================================================
        // BLOQUE 3: TABLA DE MERCANCÍA (4 Renglones igual que el formato impreso)
        // =========================================================================
        $yTabla = $yActual + 2; // ~89mm
        $colCant = 11;
        $colMerc = 62;
        $colImp  = 20;

        $pdf->SetDrawColor(30, 30, 30);
        $pdf->SetLineWidth(0.25);

        // Cabecera de tabla
        $pdf->SetXY($mIzq, $yTabla);
        $pdf->SetFont('Arial', 'B', 6.5);
        $pdf->Cell($colCant, 5, $this->txt('CANT.'), 1, 0, 'C');
        $pdf->Cell($colMerc, 5, $this->txt('MERCANCIA'), 1, 0, 'C');
        $pdf->Cell($colImp, 5, $this->txt('IMPORTE'), 1, 1, 'C');

        $nombreArt = $va->nombre_articulo ?? $va->articulo_nombre ?? 'Artículo';
        $marcaArt  = $va->articulo_marca ?? 'N/A';
        $modeloArt = $va->articulo_modelo ?? 'N/A';

        $renglones = [
            [
                'cant' => $va->cantidad,
                'desc' => mb_strimwidth($nombreArt, 0, 36, '...'),
                'imp'  => '$' . number_format($importeArticulo, 2),
            ],
            [
                'cant' => '',
                'desc' => 'Marca: ' . $marcaArt,
                'imp'  => '',
            ],
            [
                'cant' => '',
                'desc' => 'Modelo: ' . $modeloArt,
                'imp'  => '',
            ],
            [
                'cant' => '',
                'desc' => $anticipoArt > 0 ? ('Enganche: $' . number_format($anticipoArt, 2) . '  |  Saldo: $' . number_format($saldoArt, 2)) : '',
                'imp'  => '',
            ],
        ];

        $pdf->SetFont('Times', '', 8.2);
        foreach ($renglones as $idx => $r) {
            $pdf->SetX($mIzq);
            $pdf->Cell($colCant, 5.2, $this->txt($r['cant']), 1, 0, 'C');
            $pdf->Cell($colMerc, 5.2, $this->txt(' ' . $r['desc']), 1, 0, 'L');
            $pdf->SetFont('Times', $idx === 0 ? 'B' : '', 8.2);
            $pdf->Cell($colImp, 5.2, $this->txt($r['imp'] . ' '), 1, 1, 'R');
            $pdf->SetFont('Times', '', 8.2);
        }

        // =========================================================================
        // BLOQUE 4: PAGARÉ LEGAL AL PIE DE LA BOLETA
        // =========================================================================
        $yPagare = $pdf->GetY() + 3; // ~118mm
        $pdf->SetXY($mIzq, $yPagare);

        // Sangría inicial + título DEBO Y PAGARÉ
        $pdf->SetFont('Arial', '', 7.3);
        $pdf->Cell(16, 3.8, '', 0, 0);
        $pdf->SetFont('Arial', 'B', 7.3);
        $pdf->Cell(26, 3.8, $this->txt('DEBO Y PAGARÉ'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 7.3);
        $pdf->Cell(22, 3.8, $this->txt('a la orden de'), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 7.3);
        $pdf->Cell(29, 3.8, $this->txt('"MUEBLERÍA'), 0, 1, 'R');

        // Línea 2: ESPINOSA", la cantidad de $________
        $pdf->SetX($mIzq);
        $pdf->SetFont('Arial', 'B', 7.3);
        $pdf->Cell(18, 3.8, $this->txt('ESPINOSA",'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 7.3);
        $pdf->Cell(21, 3.8, $this->txt('la cantidad de'), 0, 0, 'L');
        $pdf->SetFont('Times', 'B', 8.5);
        $pdf->Cell(54, 3.8, '$' . number_format($saldoArt, 2), 'B', 1, 'C');

        // Línea 3 y 4
        $pdf->SetX($mIzq);
        $pdf->SetFont('Arial', '', 7.2);
        $pdf->Cell($anchoUtil, 3.7, $this->txt('valor de mercancías recibidas en buen estado y a mi entera'), 0, 1, 'J');

        // Línea 5: satisfacción, dicho pago lo haré en abonos de $_____
        $pdf->SetX($mIzq);
        $pdf->Cell(54, 3.8, $this->txt('satisfacción, dicho pago lo haré en abonos de'), 0, 0, 'L');
        $pdf->SetFont('Times', 'B', 8.3);
        $pdf->Cell(39, 3.8, '$' . number_format($abonoArticulo, 2), 'B', 1, 'C');

        // Línea 6: [Semanal / Quincenal / Mensual] a partir de esta fecha...
        $pdf->SetX($mIzq);
        $pdf->SetFont('Times', 'B', 8);
        $pdf->Cell(25, 3.8, $this->txt($frecuencia), 'B', 0, 'C');
        $pdf->SetFont('Arial', '', 7.2);
        $pdf->Cell(68, 3.8, $this->txt('a partir de esta fecha, queda entendi-'), 0, 1, 'R');

        // Párrafo final de condiciones
        $pdf->SetX($mIzq);
        $pdf->SetFont('Arial', '', 7.1);
        $textoFinal = "do que por falta de pago de dos abonos me OBLIGO al inmediato pago de toda la cantidad o en su defecto devolver la mercancía SIN DERECHO A RECLAMAR LO ABONADO siempre que la casa lo crea conveniente.";
        $pdf->MultiCell($anchoUtil, 3.6, $this->txt($textoFinal), 0, 'J');

        return response($pdf->Output('S'), 200)->header('Content-Type', 'application/pdf');
    }
}