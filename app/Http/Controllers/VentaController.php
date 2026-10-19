<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Venta;
use App\Models\VentaArticulo;
use App\Models\Stock;
use App\Models\Kardex;
use App\Models\Cliente;
use App\Models\User;

class VentaController extends Controller
{
    /**
     * Guarda la venta en BD, descuenta inventario y genera movimientos en Kardex.
     */
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'vendedor_id' => 'required|exists:users,id',
            'tipo_venta' => 'required|in:Contado,Credito',
            'articulos' => 'required|array|min:1',
            'articulos.*.id' => 'required|exists:articulos,id',
            'articulos.*.cantidad' => 'required|integer|min:1',
            'articulos.*.precio_unitario' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            $ultimoFolio = Venta::max('folio') ?? 1000;
            $folio = $ultimoFolio + 1;

            // 1. Crear Registro de Venta Principal
            $venta = new Venta();
            $venta->folio = $folio;
            $venta->cliente_id = $request->cliente_id;
            $venta->vendedor_id = $request->vendedor_id;
            $venta->cobrador_id = $request->cobrador_id ?? null;
            $venta->tipo_venta = $request->tipo_venta;
            $venta->fecha_venta = $request->fecha_venta ?? date('Y-m-d');
            $venta->venta_hora = date('H:i:s');
            $venta->forma_pago = $request->forma_pago ?? 'Efectivo';
            $venta->subtotal = $request->subtotal;
            $venta->total = $request->total;
            $venta->anticipo = $request->anticipo ?? 0;
            $venta->saldo_restante = max(0, $request->total - ($request->anticipo ?? 0));
            $venta->frecuencia_nombre = $request->frecuencia_nombre ?? 'Semanal';
            $venta->num_pagos = $request->num_pagos ?? 1;
            $venta->cuota_pago = $request->cuota_pago ?? 0;
            $venta->fecha_primer_pago = $request->fecha_primer_pago ?? null;
            $venta->dia_semana = $request->dia_semana ?? null;
            $venta->status = 'V_ABIERTAS';
            $venta->save();

            $vendedorId = $request->vendedor_id;

            // 2. Procesar cada Artículo de la Venta, Stock y Kardex
            foreach ($request->articulos as $item) {
                // a) Detalle de la venta
                $detalle = new VentaArticulo();
                $detalle->venta_id = $venta->id;
                $detalle->articulo_id = $item['id'];
                $detalle->cantidad = $item['cantidad'];
                $detalle->nserie = $item['n_serie'] ?? null;
                $detalle->precio_unitario = $item['precio_unitario'];
                $detalle->descuento = $item['descuento'] ?? 0;
                $detalle->subtotal = $item['subtotal'];
                $detalle->save();

                // b) Descontar del inventario asignado al vendedor
                $stock = Stock::where('user_id', $vendedorId)
                              ->where('articulo_id', $item['id'])
                              ->first();

                $stockAnterior = $stock ? $stock->cantidad : 0;
                $nuevoStock = max(0, $stockAnterior - $item['cantidad']);

                if ($stock) {
                    $stock->cantidad = $nuevoStock;
                    $stock->save();
                }

                // c) Registrar movimiento en la tabla Kardex
                $kardex = new Kardex();
                $kardex->articulo_id = $item['id'];
                $kardex->user_id = $vendedorId;
                $kardex->tipo_movimiento = 'SALIDA_VENTA';
                $kardex->cantidad = $item['cantidad'];
                $kardex->stock_anterior = $stockAnterior;
                $kardex->stock_nuevo = $nuevoStock;
                $kardex->referencia = "Venta Folio #" . $venta->folio;
                $kardex->observaciones = "Salida por venta realizada en POS";
                $kardex->fecha_movimiento = now();
                $kardex->save();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => '¡Venta registrada exitosamente!',
                'venta_id' => $venta->id,
                'detalles' => VentaArticulo::where('venta_id', $venta->id)->pluck('id')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al procesar la venta: ' . $e->getMessage()
            ], 500);
        }
    }
}