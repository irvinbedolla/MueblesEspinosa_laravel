<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Kardex;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Articulo;
use App\Models\InventarioStock;

class VentaController extends Controller
{

    /**
     * Muestra la pantalla del Punto de Venta (POS)
     */
    public function pos(Request $request)
    {
        $usuarioLogueado = auth()->user();
        $esAdmin = $usuarioLogueado->hasRole('admin') || $usuarioLogueado->role === 1;

        // Si es Admin y seleccionó otro vendedor en la pantalla, consultar su inventario
        $vendedorIdEfectivo = ($esAdmin && $request->get('vendedor_id')) 
            ? $request->get('vendedor_id') 
            : $usuarioLogueado->id;

        $ultimoFolio = Venta::max('folio') ?? 1000;
        $nuevoFolio = $ultimoFolio + 1;

        $clientes = Cliente::where('status', 'Activo')->orderBy('nombre')->get();
        $cobradores = User::where('is_active', true)->orderBy('username')->get();
        $vendedores = User::where('is_active', true)->orderBy('username')->get();

        // Cargar los artículos asignados al vendedor activo
        $articulosEnStock = Articulo::with(['precios', 'stocks' => function($q) use ($vendedorIdEfectivo) {
                $q->where('user_id', $vendedorIdEfectivo);
            }])
            ->whereHas('stocks', function($q) use ($vendedorIdEfectivo) {
                $q->where('user_id', $vendedorIdEfectivo)->where('cantidad', '>', 0);
            })
            ->get()
            ->map(function($art) use ($vendedorIdEfectivo) {
                $stockItem = $art->stocks->first();
                $art->stock_vendedor = $stockItem ? $stockItem->cantidad : 0;
                return $art;
            });

        // Si es una petición AJAX para recargar inventario al cambiar de vendedor
        if ($request->ajax()) {
            return response()->json([
                'articulos' => $articulosEnStock
            ]);
        }

        return view('ventas.pos', compact(
            'nuevoFolio', 'clientes', 'cobradores', 'vendedores', 
            'usuarioLogueado', 'vendedorIdEfectivo', 'articulosEnStock', 'esAdmin'
        ));
    }

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
            $venta->hora_venta = date('H:i:s');
            $venta->forma_pago = $request->forma_pago ?? 'Efectivo';
            $venta->subtotal = $request->subtotal;
            $venta->total = $request->total;
            $venta->anticipo = $request->anticipo ?? 0;
            $venta->saldo_restante = max(0, $request->total - ($request->anticipo ?? 0));
            $venta->frecuencia_nombre = $request->frecuencia_nombre ?? 'Semanal';
            $venta->num_pagos = $request->num_pagos ?? 1;
            $venta->pago_cuota = $request->cuota_pago ?? 0;
            $venta->fecha_primer_pago = $request->fecha_primer_pago ?? null;
            $venta->dia_semana = $request->dia_semana ?? null;
            $venta->status = 'Abierta';
            $venta->save();

            $vendedorId = $request->vendedor_id;
            

            // 2. Procesar cada Artículo de la Venta, Stock y Kardex
            foreach ($request->articulos as $item) {
                // a) Detalle de la venta
                $articuloBD = \App\Models\Articulo::find($item['id']);
                $detalle = new VentaDetalle();
                $detalle->venta_id = $venta->id;
                $detalle->articulo_id = $item['id'];
                $detalle->cantidad = $item['cantidad'];
                $detalle->numero_serie = $item['n_serie'] ?? null;
                $detalle->precio_unitario = $item['precio_unitario'];
                $detalle->descuento = $item['descuento'] ?? 0;
                $detalle->subtotal = $item['subtotal'];
                $detalle->nombre_articulo = $articuloBD ? $articuloBD->nombre : 'Sin Nombre';
                $detalle->save();

                // b) Descontar del inventario asignado al vendedor
                $stock = InventarioStock::where('user_id', $vendedorId)
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
                $kardex->tipo_movimiento = "Venta Folio #" . $venta->folio;
                $kardex->observaciones = "Salida por venta realizada en Venta";
                //$kardex->fecha_movimiento = now();
                $kardex->save();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => '¡Venta registrada exitosamente!',
                'venta_id' => $venta->id,
                'detalles' => VentaDetalle::where('venta_id', $venta->id)->pluck('id')
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al procesar la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra el listado / consulta general de ventas
     */
    public function index(Request $request)
    {
        // Estatus por defecto al entrar
        $filtro = $request->get('filtro', 'Abierta');
        $busqueda = $request->get('buscar', '');
        $porPagina = $request->get('per_page', 10); // Mostrar 25 por página

        $query = Venta::with(['cliente', 'vendedor', 'cobrador', 'detalles.articulo'])
                    ->orderBy('created_at', 'desc');

        // Filtros basados en los valores exactos de tu BD
        switch ($filtro) {
            case 'Abierta':
            case 'V_ABIERTAS':
                $query->where('status', 'Abierta');
                break;

            case 'Pendiente':
            case 'V_PENDIENTES':
                $query->where('status', 'Pendiente');
                break;

            case 'Pagado':
            case 'V_PAGADAS':
                $query->where('status', 'Pagado');
                break;

            case 'V_CREDITO':
                $query->where('tipo_venta', 'Credito');
                break;

            case 'Eliminar':
            case 'V_ELIMINADAS':
                $query->where('status', 'Eliminar');
                break;

            case 'Validar':
                $query->where('status', 'Validar');
                break;

            case 'Devolucion':
                $query->where('status', 'Devolucion');
                break;

            case 'Recogido':
                $query->where('status', 'Recogido');
                break;

            case 'Fuga':
                $query->where('status', 'Fuga');
                break;

            case 'LISTA_NEGRA':
                $query->whereIn('status', ['Fuga', 'Recogido', 'Devolucion', 'Problema', 'Descompuesto']);
                break;

            case 'Problema':
                $query->where('status', 'Problema');
                break;

            case 'Descompuesto':
                $query->where('status', 'Descompuesto');
                break;

            case 'TODAS':
            default:
                // Carga las 2,462 ventas
                break;
        }

        if (!empty($busqueda)) {
            $query->where(function($q) use ($busqueda) {
                $q->where('folio', 'like', "%{$busqueda}%")
                ->orWhereHas('cliente', function($c) use ($busqueda) {
                    $c->where('nombre', 'like', "%{$busqueda}%");
                });
            });
        }

        // Paginación conservando el filtro activo en la URL
        $ventas = $query->paginate($porPagina)->appends($request->all());

        return view('ventas.index', compact('ventas', 'filtro', 'busqueda'));
    }

    /**
     * Reabre una venta en el POS para continuar editándola
     */
    public function continuarPOS($id)
    {
        // Redirige al POS pasando el ID de la venta abierta
        return redirect()->route('ventas.pos', ['venta_id' => $id]);
    }

    /**
     * Cambia el estatus de una Venta Abierta a Pendiente
     */
    public function pasarAPendiente($id)
    {
        $venta = Venta::findOrFail($id);
        $venta->status = 'Pendiente';
        $venta->save();

        return redirect()->back()->with('success', "Venta Folio #{$venta->folio} pasada a Pendiente correctamente.");
    }

    /**
     * Descarta / Elimina una Venta Abierta
     */
    public function descartar($id)
    {
        $venta = Venta::findOrFail($id);
        $venta->status = 'Eliminar';
        $venta->save();

        return redirect()->back()->with('success', "Venta Folio #{$venta->folio} descartada.");
    }

    /**
     * Obtiene los artículos/detalles de una venta para el modal "Desglose Técnico"
     */
    public function getDetallesAjax($id)
    {
        $venta = Venta::with(['detalles.articulo'])->findOrFail($id);
        return response()->json([
            'folio' => $venta->folio,
            'detalles' => $venta->detalles
        ]);
    }

    /**
     * Actualiza el número de serie de un detalle de venta específico
     */
    public function updateNumeroSerie(Request $request, $id)
    {
        $request->validate([
            'numero_serie' => 'required|string|max:100'
        ]);

        $detalle = VentaDetalle::findOrFail($id);
        $detalle->numero_serie = $request->numero_serie;
        $detalle->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Número de serie actualizado correctamente',
            'numero_serie' => $detalle->numero_serie
        ]);
    }

    /**
     * Obtiene el historial de abonos/pagos realizados a una venta
     */
    public function getHistorialAjax($id)
    {
        try {
            $venta = Venta::with(['cliente'])->findOrFail($id);

            $abonos = [];
            // Verificar si la tabla 'abonos' existe en la BD legacy
            if (\Schema::hasTable('abonos')) {
                $abonos = \DB::table('abonos')
                    ->where('a_venta', $id)
                    ->orWhere('venta_id', $id)
                    ->get();
            }

            return response()->json([
                'folio' => $venta->folio,
                'cliente' => $venta->cliente->nombre ?? 'Sin Cliente',
                'total' => $venta->total,
                'saldo_restante' => $venta->saldo_restante,
                'abonos' => $abonos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa el cambio de estatus de una venta y gestiona inventario/kardex
     */
    public function cambiarEstatusStatus(Request $request, $id)
    {
        $request->validate([
            'nuevo_estatus' => 'required|string',
        ]);

        $nuevoEstatus = $request->nuevo_estatus;

        DB::transaction(function () use ($id, $nuevoEstatus, $request) {
            $venta = Venta::with(['detalles.articulo', 'cliente'])->findOrFail($id);
            $estatusAnterior = $venta->status;

            // 1. Manejo según el nuevo estatus
            switch ($nuevoEstatus) {
                case 'Pagado':
                    $venta->status = 'Pagado';
                    $venta->saldo_restante = 0;
                    break;

                case 'Devolucion':
                case 'Recogido':
                    $venta->status = $nuevoEstatus;

                    // Reintegrar piezas al inventario/Kardex
                    foreach ($venta->detalles as $detalle) {
                        // Registrar devolución en Kardex
                        Kardex::create([
                            'articulo_id' => $detalle->articulo_id,
                            'user_id' => auth()->id() ?? $venta->vendedor_id,
                            'tipo_movimiento' => 'ENTRADA_DEVOLUCION',
                            'cantidad' => $detalle->cantidad,
                            'stock_anterior' => 0,
                            'stock_nuevo' => $detalle->cantidad,
                            'referencia' => "Devolución/Recogido de Venta #" . $venta->folio,
                            'observaciones' => "Mercancía devuelta por cambio de estatus a {$nuevoEstatus}",
                            'fecha_movimiento' => now()
                        ]);
                    }

                    if ($nuevoEstatus === 'Recogido' && $venta->cliente) {
                        $venta->cliente->status = 'Recogido';
                        $venta->cliente->save();
                    }
                    break;

                case 'Fuga':
                case 'Problema':
                    $venta->status = $nuevoEstatus;
                    if ($venta->cliente) {
                        $venta->cliente->status = $nuevoEstatus;
                        $venta->cliente->save();
                    }
                    break;

                case 'Eliminar':
                    $venta->status = 'Eliminar';
                    // Liberar los ítems
                    foreach ($venta->detalles as $detalle) {
                        Kardex::create([
                            'articulo_id' => $detalle->articulo_id,
                            'user_id' => auth()->id() ?? $venta->vendedor_id,
                            'tipo_movimiento' => 'CANCELACION_VENTA',
                            'cantidad' => $detalle->cantidad,
                            'referencia' => "Cancelación Venta #" . $venta->folio,
                            'fecha_movimiento' => now()
                        ]);
                    }
                    break;

                case 'Pendiente':
                    $venta->status = 'Pendiente';
                    break;

                default:
                    $venta->status = $nuevoEstatus;
                    break;
            }

            $venta->save();
        });

        return redirect()->back()->with('success', "El estatus de la venta se actualizó correctamente.");
    }

}