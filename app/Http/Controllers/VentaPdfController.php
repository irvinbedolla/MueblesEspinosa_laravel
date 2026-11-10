<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Articulo;
use FPDF;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class VentaPdfController extends Controller
{
    /**
     * Documento 1: Ticket Resumen de la Venta (54mm x 130mm)
     */
    public function ticket($id)
    {

        // 1. Obtener los datos generales de la Venta
        $venta = DB::table('ventas')
            ->where(function($q) use ($id) {
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

        // 2. Obtener la información del Cliente
        $venta->cliente = DB::table('clientes')
            ->where('id', $venta->cliente_id)
            ->first();

        // 3. Obtener la información del Vendedor
        $venta->vendedor = DB::table('users')
            ->where('id', $venta->vendedor_id)
            ->orWhere('user_id_legacy', $venta->vendedor_id)
            ->first();

        // 4. Obtener los detalles de los artículos (ASIGNA LA PROPIEDAD $detalles)
        $venta->detalles = DB::table('venta_detalles as vd')
            ->select('vd.*', 'a.nombre as nombre_catalogo')
            ->leftJoin('articulos as a', 'vd.articulo_id', '=', 'a.id')
            ->where('vd.venta_id', $venta->id)
            ->get();

        // 5. Generación del documento FPDF
        $pdf = new \FPDF('P', 'mm', array(54, 130));
        $pdf->AddPage();
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(true, 0);

        $pdf->SetY(5);
        $pdf->SetX(5);

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(50, 8, iconv('UTF-8', 'ISO-8859-1', 'MUEBLES ESPINOSA'), 0, 1, 'C', 0);

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Venta N° ' . $venta->folio), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Calle Revolución #104'), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Col. Heberto Castillo'), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Cd. Altamirano Gro.'), 0, 1, 'L', 1);

        $fechaFormato = \Carbon\Carbon::parse($venta->fecha_venta)->format('d / m / Y');
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Fecha: ' . $fechaFormato . " Hora: " . ($venta->venta_hora ?? date('H:i'))), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Atendio: ' . ($venta->vendedor->username ?? 'S/A')), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Cliente: ' . ($venta->cliente->nombre ?? 'Sin Cliente')), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Direccion: ' . ($venta->cliente->calle ?? 'N/A')), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Col: ' . ($venta->cliente->colonia ?? 'N/A')), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->ciudad ?? 'N/A'), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->estado ?? 'N/A'), 0, 1, 'L', 1);

        $pdf->Cell(54, 4, '', 0, 1, 'C', 1);
        $pdf->Cell(54, 4, iconv('UTF-8', 'ISO-8859-1', 'Lista de Articulos'), 0, 1, 'C', 1);
        $pdf->Cell(54, 4, '', 0, 1, 'C', 1);

        $pdf->Cell(54, 4, iconv('UTF-8', 'ISO-8859-1', 'Articulo                                 Cant    P.U.    Precio'), 1, 1, 'L', 1);
        $pdf->SetFont('Arial', 'B', 5);

        $totalCalc = 0;
        foreach ($venta->detalles as $va) {
            $cp = ($va->precio_unitario - $va->descuento) * $va->cantidad;
            $totalCalc += $cp;

            $nombreArt = $va->nombre_articulo ?? ($va->articulo->nombre ?? 'Articulo');

            $pdf->Cell(34, 4, iconv('UTF-8', 'ISO-8859-1', mb_strimwidth($nombreArt, 0, 25, "...")), 1, 0, 'L', 0);
            $pdf->Cell(6, 4, iconv('UTF-8', 'ISO-8859-1', $va->cantidad), 1, 0, 'L', 1);
            $pdf->Cell(7, 4, iconv('UTF-8', 'ISO-8859-1', '$' . number_format($va->precio_unitario)), 1, 0, 'L', 1);
            $pdf->Cell(7, 4, iconv('UTF-8', 'ISO-8859-1', '$' . number_format($cp)), 1, 1, 'L', 1);
        }

        $pdf->Cell(54, 4, '', 0, 1, 'C', 1);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell(54, 4, iconv('UTF-8', 'ISO-8859-1', 'Total: $' . number_format($totalCalc)), 0, 1, 'R', 1);

        $pdf->Cell(54, 4, '', 0, 1, 'C', 1);
        $pdf->Cell(54, 4, iconv('UTF-8', 'ISO-8859-1', 'Gracias Por Su Preferencia'), 0, 1, 'C', 1);

        return response($pdf->Output('S'), 200)->header('Content-Type', 'application/pdf');
    }

    /**
     * Documento 2: Boletas por Artículo Individual (103mm x 175mm)
     */
    public function boleta($ventaId, $detalleId)
    {
        // 1. Consultar la Venta de forma segura por UUID, Folio o ID Legacy
        $venta = DB::table('ventas')
            ->where(function($q) use ($ventaId) {
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
            ->where(function($q) use ($venta) {
                $q->where('vd.venta_id', $venta->id);
                if (!empty($venta->folio)) {
                    $q->orWhere('vd.venta_id', $venta->folio);
                }
            })
            ->first();

        if (!$va) {
            abort(404, "No se encontró el detalle de la venta especificado.");
        }

        // 4. Cálculos de Pagos y Amortizaciones
        $frecuencia = $venta->frecuencia_nombre ?? 'Semanal';
        $pagos = intval($venta->num_pagos ?? 1);

        $n_pagos = match($frecuencia) {
            'Semanal'   => $pagos * 4,
            'Quincenal' => $pagos * 2,
            default     => $pagos,
        };

        $venta_total = $venta->total;
        $abono_precio = $venta->anticipo;

        $utilidades = [1=>40, 2=>35, 3=>30, 4=>25, 5=>20, 6=>15, 7=>10];
        $pct = $utilidades[$pagos] ?? 5;

        $venta_descuento = $venta_total * $pct / 100;
        $temp = $venta_total - $venta_descuento;

        $pago = ($temp - $abono_precio);
        $pagof = $n_pagos > 0 ? ($pago / $n_pagos) : 0;

        $pdf = new FPDF('P', 'mm', array(103, 175));
        $pdf->AddPage();
        $pdf->SetFont('Times', '', 9);

        // Numero de venta
        $pdf->SetY(44); $pdf->SetX(60);
        $pdf->Cell(75, 3, ' ');
        $pdf->Cell(10, 3, $venta->folio, 0);

        // Fecha
        $pdf->SetY(50); $pdf->SetX(32);
        $pdf->Cell(47, 4, ' ');
        $pdf->Cell(40, 4, \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y'), 0);

        // Cliente
        $pdf->SetY(55); $pdf->SetX(40);
        $pdf->Cell(5, 3, ' ');
        $pdf->Cell(85, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->nombre ?? ''), 0);

        // Calle
        $pdf->SetY(59); $pdf->SetX(40);
        $pdf->Cell(10, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->calle ?? ''), 0);

        // Colonia
        $pdf->SetY(64); $pdf->SetX(40);
        $pdf->Cell(10, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->colonia ?? ''), 0);

        // Pueblo
        $pdf->SetY(69); $pdf->SetX(40);
        $pdf->Cell(22, 3, ' ');
        $pdf->Cell(70, 3, iconv('UTF-8', 'ISO-8859-1', ($venta->cliente->ciudad ?? '') . " " . ($venta->cliente->estado ?? '')), 0);

        // Vendedor
        $pdf->SetY(74); $pdf->SetX(40);
        $pdf->Cell(12, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->vendedor->username ?? ''), 0);

        // Numero Serie
        $pdf->SetY(88); $pdf->SetX(40);
        $pdf->Cell(30, 3, ' ');
        $pdf->Cell(54, 3, iconv('UTF-8', 'ISO-8859-1', $va->numero_serie ?? 'S/N'), 0);

        // Primer renglon
        $nombreArt = $va->nombre_articulo ?? ($va->articulo->nombre ?? '');
        $pdf->SetY(103); $pdf->SetX(5);
        $pdf->Cell(7, 3, '', 0);
        $pdf->Cell(59, 3, $va->cantidad . "   " . iconv('UTF-8', 'ISO-8859-1', $nombreArt), 0);
        $pdf->Cell(15, 3, "$" . number_format(($va->precio_unitario - $va->descuento) * $va->cantidad), 0);

        // Segundo renglon
        $pdf->SetY(110); $pdf->SetX(40);
        $pdf->Cell(13, 3, '', 0);
        $pdf->Cell(60, 3, "Marca: " . iconv('UTF-8', 'ISO-8859-1', $va->articulo->marca ?? 'N/A'), 0);

        // Tercer renglon
        $pdf->SetY(118); $pdf->SetX(40);
        $pdf->Cell(13, 3, '', 0);
        $pdf->Cell(60, 3, "Modelo: " . iconv('UTF-8', 'ISO-8859-1', $va->articulo->modelo ?? 'N/A'), 0);

        // Pago
        $pdf->SetY(128); $pdf->SetX(40);
        $pdf->Cell(70, 3, '', 0);
        $pdf->Cell(25, 3, "$" . number_format(($va->precio_unitario - $va->descuento) * $va->cantidad), 0);

        // Abono
        $pdf->SetY(137); $pdf->SetX(0);
        $pdf->Cell(74, 3, '', 0);
        $pdf->Cell(20, 3, "$" . number_format($pagof), 0);

        // Semanal
        $pdf->SetY(141); $pdf->SetX(0);
        $pdf->Cell(3, 3, '', 0);
        $pdf->Cell(20, 3, iconv('UTF-8', 'ISO-8859-1', $frecuencia), 0);

        return response($pdf->Output('S'), 200)->header('Content-Type', 'application/pdf');
    }

    /**
     * Boleta Formato PC (Primer código proporcionado)
     */
    public function boletaPC($ventaId, $detalleId)
    {
        $venta = Venta::with(['cliente', 'vendedor'])->findOrFail($ventaId);
        $va = VentaDetalle::with('articulo')->where('venta_id', $ventaId)->where('id', $detalleId)->firstOrFail();

        $frecuencia = match((int)$venta->frecuencia_nombre) {
            7 => 'Semanal',
            14 => 'Quincenal',
            default => $venta->frecuencia_nombre ?? 'Semanal'
        };

        $pagos = $venta->num_pagos ?? 1;
        $n_pagos = match($venta->frecuencia_nombre) {
            '7', 7 => $pagos * 4,
            '14', 14 => $pagos * 2,
            default => $pagos
        };

        $venta_total = $venta->total;
        $abono_precio = $venta->anticipo ?? 0;

        $pct = match($pagos) {
            1 => 40, 2 => 35, 3 => 30, 4 => 25, 5 => 20, 6 => 15, 7 => 10, default => 5
        };

        $venta_descuento = $venta_total * $pct / 100;
        $temp = $venta_total - $venta_descuento;
        $pago = ($temp - $abono_precio);
        $pagof = $n_pagos > 0 ? ($pago / $n_pagos) : 0;

        $pdf = new FPDF('P', 'mm', array(103, 175));
        $pdf->AddPage();
        $pdf->SetFont('Times', '', 9);

        // Numero de venta
        $pdf->SetY(44); $pdf->SetX(60);
        $pdf->Cell(75, 3, ' ');
        $pdf->Cell(10, 3, $venta->folio, 0);

        // Fecha
        $pdf->SetY(50); $pdf->SetX(32);
        $pdf->Cell(47, 4, ' ');
        $pdf->Cell(40, 4, \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y'), 0);

        // Cliente
        $pdf->SetY(55); $pdf->SetX(40);
        $pdf->Cell(5, 3, ' ');
        $pdf->Cell(85, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->nombre ?? ''), 0);

        // Calle
        $pdf->SetY(59); $pdf->SetX(40);
        $pdf->Cell(10, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->calle ?? ''), 0);

        // Colonia
        $pdf->SetY(64); $pdf->SetX(40);
        $pdf->Cell(10, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->colonia ?? ''), 0);

        // Pueblo
        $pdf->SetY(69); $pdf->SetX(40);
        $pdf->Cell(22, 3, ' ');
        $pdf->Cell(70, 3, iconv('UTF-8', 'ISO-8859-1', ($venta->cliente->ciudad ?? '') . " " . ($venta->cliente->estado ?? '')), 0);

        // Vendedor
        $pdf->SetY(74); $pdf->SetX(40);
        $pdf->Cell(12, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->vendedor->username ?? ''), 0);

        // Numero Serie
        $pdf->SetY(88); $pdf->SetX(40);
        $pdf->Cell(30, 3, ' ');
        $pdf->Cell(54, 3, iconv('UTF-8', 'ISO-8859-1', $va->numero_serie ?? 'S/N'), 0);

        // Renglones
        $pdf->SetY(103); $pdf->SetX(5);
        $pdf->Cell(7, 3, '', 0);
        $pdf->Cell(59, 3, $va->cantidad . "   " . iconv('UTF-8', 'ISO-8859-1', $va->nombre_articulo ?? ($va->articulo->nombre ?? '')), 0);
        $pdf->Cell(15, 3, "$" . number_format(($va->precio_unitario - $va->descuento) * $va->cantidad), 0);

        $pdf->SetY(110); $pdf->SetX(40);
        $pdf->Cell(13, 3, '', 0);
        $pdf->Cell(60, 3, "Marca: " . iconv('UTF-8', 'ISO-8859-1', $va->articulo->marca ?? 'N/A'), 0);

        $pdf->SetY(118); $pdf->SetX(40);
        $pdf->Cell(13, 3, '', 0);
        $pdf->Cell(60, 3, "Modelo: " . iconv('UTF-8', 'ISO-8859-1', $va->articulo->modelo ?? 'N/A'), 0);

        $pdf->SetY(128); $pdf->SetX(40);
        $pdf->Cell(70, 3, '', 0);
        $pdf->Cell(25, 3, "$" . number_format(($va->precio_unitario - $va->descuento) * $va->cantidad), 0);

        $pdf->SetY(137); $pdf->SetX(0);
        $pdf->Cell(74, 3, '', 0);
        $pdf->Cell(20, 3, "$" . number_format($pagof), 0);

        $pdf->SetY(141); $pdf->SetX(0);
        $pdf->Cell(3, 3, '', 0);
        $pdf->Cell(20, 3, iconv('UTF-8', 'ISO-8859-1', $frecuencia), 0);

        return response($pdf->Output('S'), 200)->header('Content-Type', 'application/pdf');
    }

    /**
     * Boleta Formato Móvil (Segundo código proporcionado)
     */
    public function boletaMovil($ventaId, $detalleId)
    {
        $venta = Venta::with(['cliente', 'vendedor'])->findOrFail($ventaId);
        $va = VentaDetalle::with('articulo')->where('venta_id', $ventaId)->where('id', $detalleId)->firstOrFail();

        $frecuencia = match((int)$venta->frecuencia_nombre) {
            7 => 'Semanal',
            14 => 'Quincenal',
            default => $venta->frecuencia_nombre ?? 'Semanal'
        };

        $pagos = $venta->num_pagos ?? 1;
        $n_pagos = match($venta->frecuencia_nombre) {
            '7', 7 => $pagos * 4,
            '14', 14 => $pagos * 2,
            default => $pagos
        };

        $venta_total = $venta->total;
        $abono_precio = $venta->anticipo ?? 0;

        $pct = match($pagos) {
            1 => 40, 2 => 35, 3 => 30, 4 => 25, 5 => 20, 6 => 15, 7 => 10, default => 5
        };

        $venta_descuento = $venta_total * $pct / 100;
        $temp = $venta_total - $venta_descuento;
        $pago = ($temp - $abono_precio);
        $pagof = $n_pagos > 0 ? ($pago / $n_pagos) : 0;

        $pdf = new FPDF('P', 'mm', array(100, 175));
        $pdf->AddPage();
        $pdf->SetFont('Times', '', 9);

        // Coordenadas ajustadas según script móvil
        $pdf->SetY(7); $pdf->SetX(60);
        $pdf->Cell(75, 3, ' ');
        $pdf->Cell(10, 3, $venta->folio, 0);

        $pdf->SetY(12); $pdf->SetX(32);
        $pdf->Cell(47, 4, ' ');
        $pdf->Cell(40, 4, \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y'), 0);

        $pdf->SetY(18); $pdf->SetX(40);
        $pdf->Cell(5, 3, ' ');
        $pdf->Cell(85, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->nombre ?? ''), 0);

        $pdf->SetY(23); $pdf->SetX(40);
        $pdf->Cell(10, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->calle ?? ''), 0);

        $pdf->SetY(27); $pdf->SetX(40);
        $pdf->Cell(11, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->cliente->colonia ?? ''), 0);

        $pdf->SetY(32); $pdf->SetX(40);
        $pdf0 = $pdf;
        $pdf0->Cell(22, 3, ' ');
        $pdf0->Cell(70, 3, iconv('UTF-8', 'ISO-8859-1', ($venta->cliente->ciudad ?? '') . " " . ($venta->cliente->estado ?? '')), 0);

        $pdf->SetY(38); $pdf->SetX(40);
        $pdf->Cell(12, 3, ' ');
        $pdf->Cell(80, 3, iconv('UTF-8', 'ISO-8859-1', $venta->vendedor->username ?? ''), 0);

        $pdf->SetY(54); $pdf->SetX(40);
        $pdf->Cell(30, 3, ' ');
        $pdf->Cell(54, 3, iconv('UTF-8', 'ISO-8859-1', $va->numero_serie ?? 'S/N'), 0);

        $pdf->SetY(68); $pdf->SetX(5);
        $pdf->Cell(7, 3, '', 0);
        $pdf->Cell(59, 3, $va->cantidad . "   " . iconv('UTF-8', 'ISO-8859-1', $va->nombre_articulo ?? ($va->articulo->nombre ?? '')), 0);
        $pdf->Cell(15, 3, "$" . number_format(($va->precio_unitario - $va->descuento) * $va->cantidad), 0);

        $pdf->SetY(76); $pdf->SetX(40);
        $pdf->Cell(13, 3, '', 0);
        $pdf->Cell(60, 3, "Marca: " . iconv('UTF-8', 'ISO-8859-1', $va->articulo->marca ?? 'N/A'), 0);

        $pdf->SetY(84); $pdf->SetX(40);
        $pdf->Cell(13, 3, '', 0);
        $pdf->Cell(60, 3, "Modelo: " . iconv('UTF-8', 'ISO-8859-1', $va->articulo->modelo ?? 'N/A'), 0);

        $pdf->SetY(95); $pdf->SetX(5);
        $pdf->Cell(71, 3, '', 0);
        $pdf->Cell(15, 3, "$" . number_format(($va->precio_unitario - $va->descuento) * $va->cantidad), 0);

        $pdf->SetY(103); $pdf->SetX(5);
        $pdf->Cell(71, 3, '', 0);
        $pdf->Cell(15, 3, "$" . number_format($pagof), 0);

        $pdf->SetY(108); $pdf->SetX(0);
        $pdf->Cell(3, 3, '', 0);
        $pdf->Cell(20, 3, iconv('UTF-8', 'ISO-8859-1', $frecuencia), 0);

        return response($pdf->Output('S'), 200)->header('Content-Type', 'application/pdf');
    }
}