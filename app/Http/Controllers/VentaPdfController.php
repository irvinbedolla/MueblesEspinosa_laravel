<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\VentaArticulo;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Articulo;
use Codedge\Fpdf\Fpdf\Fpdf; // O FPDF nativo incluido en tu proyecto

class VentaPdfController extends Controller
{
    /**
     * Documento 1: Ticket Resumen de la Venta (54mm x 130mm)
     */
    public function ticket($id)
    {
        $venta = Venta::with(['cliente', 'vendedor', 'articulos.articulo'])->findOrFail($id);

        $pdf = new Fpdf('P', 'mm', array(54, 130));
        $pdf->AddPage();
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(true, 0);

        $pdf->SetY(5);
        $pdf->SetX(5);

        $pdf->SetDrawColor(0,0,0);
        $pdf->SetFillColor(255,255,255);
        $pdf->SetTextColor(0,0,0);
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(50, 8, iconv('UTF-8', 'ISO-8859-1', 'MUEBLES ESPINOSA'), 0, 1, 'C', 0);
        
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Venta N° ' . $venta->folio), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Calle Revolución #104'), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Col. Heberto Castillo'), 0, 1, 'L', 1);
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Cd. Altamirano Gro.'), 0, 1, 'L', 1);

        $fechaFormato = \Carbon\Carbon::parse($venta->fecha_venta)->format('d / m / Y');
        $pdf->Cell(28, 4, iconv('UTF-8', 'ISO-8859-1', 'Fecha: ' . $fechaFormato . " Hora: " . $venta->venta_hora), 0, 1, 'L', 1);
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
        foreach ($venta->articulos as $va) {
            $cp = ($va->precio_unitario - $va->descuento) * $va->cantidad;
            $totalCalc += $cp;
            $nombreArt = $va->articulo->nombre ?? 'Articulo';

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
        $venta = Venta::with(['cliente', 'vendedor']).findOrFail($ventaId);
        $va = VentaArticulo::with('articulo')->where('venta_id', $ventaId)->where('id', $detalleId)->firstOrFail();

        $frecuencia = $venta->frecuencia_nombre ?? 'Semanal';
        $pagos = $venta->num_pagos ?? 1;

        $n_pagos = match($frecuencia) {
            'Semanal' => $pagos * 4,
            'Quincenal' => $pagos * 2,
            default => $pagos,
        };

        $venta_total = $venta->total;
        $abono_precio = $venta->anticipo;

        $utilidades = [1=>40, 2=>35, 3=>30, 4=>25, 5=>20, 6=>15, 7=>10];
        $pct = $utilidades[$pagos] ?? 5;

        $venta_descuento = $venta_total * $pct / 100;
        $temp = $venta_total - $venta_descuento;

        $pago = ($temp - $abono_precio);
        $pagof = $n_pagos > 0 ? ($pago / $n_pagos) : 0;

        $pdf = new Fpdf('P', 'mm', array(103, 175));
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
        $pdf0->Cell(10, 3, ' ');
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
        $pdf->Cell(54, 3, iconv('UTF-8', 'ISO-8859-1', $va->nserie ?? 'S/N'), 0);

        // Primer renglon
        $pdf->SetY(103); $pdf->SetX(5);
        $pdf->Cell(7, 3, '', 0);
        $pdf->Cell(59, 3, $va->cantidad . "   " . iconv('UTF-8', 'ISO-8859-1', $va->articulo->nombre ?? ''), 0);
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
}