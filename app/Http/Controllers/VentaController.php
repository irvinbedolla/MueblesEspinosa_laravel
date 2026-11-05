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
use Illuminate\Support\Facades\Schema;

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

                    // Asignación de estatus 'Vendido' y número de venta/folio
                    if (Schema::hasColumn('inventario_stock', 'condicion')) {
                        $stock->condicion = 'Vendido';
                    } elseif (Schema::hasColumn('inventario_stock', 'status')) {
                        $stock->status = 'Vendido';
                    }

                    if (Schema::hasColumn('inventario_stock', 'numero_venta')) {
                        $stock->numero_venta = $venta->folio;
                    } elseif (Schema::hasColumn('inventario_stock', 'num_venta')) {
                        $stock->num_venta = $venta->folio;
                    } elseif (Schema::hasColumn('inventario_stock', 'venta_id')) {
                        $stock->venta_id = $venta->id;
                    }
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
     * Procesa el cambio de estatus de una venta y gestiona inventario/kardex/condición
     */
    public function cambiarEstatusStatus(Request $request, $id)
    {
        $request->validate([
            'nuevo_estatus' => 'required|string',
        ]);

        $nuevoEstatus = $request->nuevo_estatus;

        try {
            DB::transaction(function () use ($id, $nuevoEstatus, $request) {
                $venta = Venta::with(['detalles.articulo', 'cliente'])->findOrFail($id);
                $estatusAnterior = $venta->status;
                $vendedorId = $venta->vendedor_id ?? $venta->user_id ?? auth()->id();

                switch ($nuevoEstatus) {

                    case 'Pagado':
                        $venta->status = 'Pagado';
                        $venta->saldo_restante = 0;
                        break;

                    case 'Devolucion':
                    case 'Recogido':
                        $venta->status = $nuevoEstatus;
                        $condicionReingreso = ($nuevoEstatus === 'Devolucion') ? 'Devolución' : 'Recogido';

                        foreach ($venta->detalles as $detalle) {

                            // 2. Reintegrar piezas al inventario_stock en la columna 'condicion'
                            $stockItem = InventarioStock::firstOrNew([
                                'user_id'     => $vendedorId,
                                'articulo_id' => $detalle->articulo_id,
                                'condicion'   => $condicionReingreso,
                            ]);

                            $stockAnterior = $stockItem->exists ? $stockItem->cantidad : 0;
                            $stockItem->cantidad = $stockAnterior + $detalle->cantidad;
                            $stockItem->save();

                            // 3. Registrar en Kardex
                            Kardex::create([
                                'articulo_id'     => $detalle->articulo_id,
                                'user_id'         => $vendedorId,
                                'tipo_movimiento' => 'ENTRADA_DEVOLUCION',
                                'cantidad'        => $detalle->cantidad,
                                'stock_anterior'  => $stockAnterior,
                                'stock_nuevo'     => $stockItem->cantidad,
                                'referencia'      => "Devolución/Recogido de Venta #" . $venta->folio,
                                'observaciones'   => "Mercancía reintegrada con condición '{$condicionReingreso}'",
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

                        foreach ($venta->detalles as $detalle) {

                            Kardex::create([
                                'articulo_id'     => $detalle->articulo_id,
                                'user_id'         => $vendedorId,
                                'tipo_movimiento' => ($nuevoEstatus === 'Fuga') ? 'INCIDENCIA_FUGA' : 'INCIDENCIA_PROBLEMA',
                                'cantidad'        => $detalle->cantidad,
                                'stock_anterior'  => 0,
                                'stock_nuevo'     => 0,
                                'referencia'      => "Incidencia Venta Folio #" . $venta->folio,
                                'observaciones'   => "Detalle de venta actualizado con estatus '{$nuevoEstatus}'",
                                'fecha_movimiento' => now()
                            ]);
                        }
                        break;

                    case 'Eliminar':
                        $venta->status = 'Eliminar';

                        foreach ($venta->detalles as $detalle) {
                            
                            $condicionRetorno = 'Nuevo'; // Condición para devolver a inventario disponible

                            // 1. Buscar si ya existe la fila en inventario_stock para esta llave compuesta
                            $stockExistente = InventarioStock::where('user_id', $vendedorId)
                                ->where('articulo_id', $detalle->articulo_id)
                                ->where('condicion', $condicionRetorno)
                                ->first();

                            $stockAnterior = $stockExistente ? $stockExistente->cantidad : 0;
                            $nuevoStock = $stockAnterior + $detalle->cantidad;

                            // 2. updateOrCreate garantiza INSERT si no existe o UPDATE sumando la cantidad si ya existe
                            $stockItem = InventarioStock::updateOrCreate(
                                [
                                    'user_id'     => $vendedorId,
                                    'articulo_id' => $detalle->articulo_id,
                                    'condicion'   => $condicionRetorno,
                                ],
                                [
                                    'cantidad'    => $nuevoStock
                                ]
                            );

                            // 3. Registrar el movimiento en Kardex para auditoría
                            Kardex::create([
                                'articulo_id'     => $detalle->articulo_id,
                                'user_id'         => $vendedorId,
                                'tipo_movimiento' => 'CANCELACION_VENTA',
                                'cantidad'        => $detalle->cantidad,
                                'stock_anterior'  => $stockAnterior,
                                'stock_nuevo'     => $nuevoStock,
                                'referencia'      => "Cancelación Venta #" . $venta->folio,
                                'observaciones'   => "Artículo reintegrado al inventario en condición '{$condicionRetorno}' por eliminación de la venta",
                                'fecha_movimiento' => now()
                            ]);
                        }
                        break;

                    case 'Pendiente':
                        if ((float)$venta->saldo_restante <= 0) {
                            throw new \Exception("No se puede pasar a Pendiente una venta con Saldo Pendiente de $0.00.");
                        }
                        $estatusConStockDevuelto = ['Devolucion', 'Recogido', 'Eliminar', 'Descompuesto'];

                        if (in_array($estatusAnterior, $estatusConStockDevuelto)) {
                            
                            // Buscar disponibilidad en inventario_stock (asumiendo 'Nuevo' si fue devolución/cancelación)
                            $condicionBuscada = ($estatusAnterior === 'Devolucion') ? 'Devolución' : (($estatusAnterior === 'Recogido') ? 'Recogido' : 'Nuevo');

                            // 1. Validación previa de existencia
                            foreach ($venta->detalles as $detalle) {
                                $stockItem = InventarioStock::where('user_id', $vendedorId)
                                    ->where('articulo_id', $detalle->articulo_id)
                                    ->where('condicion', $condicionBuscada)
                                    ->first();

                                $disponible = $stockItem ? $stockItem->cantidad : 0;

                                if ($disponible < $detalle->cantidad) {
                                    $nombreProd = $detalle->nombre_articulo ?? ($detalle->articulo->nombre ?? 'Artículo');
                                    throw new \Exception("Stock insuficiente para reactivar. El vendedor solo cuenta con {$disponible} pza(s) de '{$nombreProd}' en condición '{$condicionBuscada}', pero se requieren {$detalle->cantidad} pza(s).");
                                }
                            }

                            // 2. Descontar stock y asignar el numero_venta en inventario_stock
                            foreach ($venta->detalles as $detalle) {
                                $stockItem = InventarioStock::where('user_id', $vendedorId)
                                    ->where('articulo_id', $detalle->articulo_id)
                                    ->where('condicion', $condicionBuscada)
                                    ->first();

                                $stockAnterior = $stockItem->cantidad;
                                $nuevoStock = max(0, $stockAnterior - $detalle->cantidad);

                                $stockItem->cantidad = $nuevoStock;
                                $stockItem->numero_venta = $venta->folio; // Se asigna el folio en inventario_stock
                                $stockItem->save();

                                Kardex::create([
                                    'articulo_id'     => $detalle->articulo_id,
                                    'user_id'         => $vendedorId,
                                    'tipo_movimiento' => 'SALIDA_REACTIVACION_VENTA',
                                    'cantidad'        => $detalle->cantidad,
                                    'stock_anterior'  => $stockAnterior,
                                    'stock_nuevo'     => $nuevoStock,
                                    'referencia'      => "Reactivación Venta #" . $venta->folio,
                                    'observaciones'   => "Salida de inventario por reactivación a estatus Pendiente",
                                    'fecha_movimiento' => now()
                                ]);
                            }
                        } else {
                            foreach ($venta->detalles as $detalle) {
                                $detalle->status = 'Vendido';
                                $detalle->save();
                            }
                        }

                        if ($venta->cliente && in_array($venta->cliente->status, ['Recogido', 'Fuga', 'Problema'])) {
                            $venta->cliente->status = 'Activo';
                            $venta->cliente->save();
                        }

                        $venta->status = 'Pendiente';
                        break;

                    default:
                        $venta->status = $nuevoEstatus;
                        break;
                }

                $venta->save();
            });

            return redirect()->back()->with('success', "Estatus de venta y control de inventario actualizados correctamente.");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', "❌ Ocurrió un inconveniente: " . $e->getMessage());
        }
    }


    /**
     * Realiza el cambio de artículo en una venta:
     * - Retorna existencia del artículo anterior al inventario del vendedor.
     * - Descuenta existencia del nuevo artículo del inventario del vendedor.
     * - Actualiza el detalle de la venta (VentaDetalle).
     * - Recalcula totales de la Venta (Venta).
     * - Registra movimientos en Kardex para trazabilidad.
    */
    public function cambiarArticuloVenta(Request $request, $ventaId)
    {
        $request->validate([
            'detalle_id' => 'required|exists:venta_detalles,id',
            'nuevo_articulo_id' => 'required|exists:articulos,id',
            'nuevo_precio' => 'required|numeric|min:0',
            'motivo' => 'nullable|string|max:255'
        ]);

        try {
            DB::beginTransaction();

            $venta = Venta::with('detalles')->findOrFail($ventaId);
            $vendedorId = $venta->vendedor_id;

            $detalle = VentaDetalle::where('venta_id', $ventaId)
                ->where('id', $request->detalle_id)
                ->firstOrFail();

            $articuloAnteriorId = $detalle->articulo_id;
            $nuevoArticuloId = $request->nuevo_articulo_id;
            $cantidad = $detalle->cantidad;

            // Si es el mismo artículo solo se actualizó precio
            if ($articuloAnteriorId != $nuevoArticuloId) {
                
                // 1. Verificar que el vendedor tenga stock suficiente del NUEVO artículo
                $stockNuevoItem = InventarioStock::where('user_id', $vendedorId)
                    ->where('articulo_id', $nuevoArticuloId)
                    ->first();

                if (!$stockNuevoItem || $stockNuevoItem->cantidad < $cantidad) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'El vendedor asignado no tiene stock suficiente de este nuevo artículo.'
                    ], 422);
                }

                // 2. Regresar la cantidad del ARTÍCULO ANTERIOR al inventario del vendedor
                $stockAnteriorItem = InventarioStock::where('user_id', $vendedorId)
                    ->where('articulo_id', $articuloAnteriorId)
                    ->first();

                $stockAntViejo = $stockAnteriorItem ? $stockAnteriorItem->cantidad : 0;
                $stockAntNuevo = $stockAntViejo + $cantidad;

                if ($stockAnteriorItem) {
                    $stockAnteriorItem->cantidad = $stockAntNuevo;
                    $stockAnteriorItem->save();
                } else {
                    InventarioStock::create([
                        'user_id' => $vendedorId,
                        'articulo_id' => $articuloAnteriorId,
                        'cantidad' => $cantidad
                    ]);
                }

                // Registrar en Kardex la devolución/retorno del artículo anterior
                Kardex::create([
                    'articulo_id' => $articuloAnteriorId,
                    'user_id' => $vendedorId,
                    'tipo_movimiento' => 'RETORNO_CAMBIO_ARTICULO',
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAntViejo,
                    'stock_nuevo' => $stockAntNuevo,
                    'referencia' => "Cambio de artículo en Venta Folio #" . $venta->folio,
                    'observaciones' => "Entrada por devolución al sustituir artículo en venta. Motivo: " . ($request->motivo ?? 'Cambio directo'),
                    'fecha_movimiento' => now()
                ]);

                // 3. Descontar la cantidad del NUEVO ARTÍCULO del inventario del vendedor
                $stockNueViejo = $stockNuevoItem->cantidad;
                $stockNueNuevo = max(0, $stockNueViejo - $cantidad);

                $stockNuevoItem->cantidad = $stockNueNuevo;
                $stockNuevoItem->save();

                // Registrar en Kardex la salida del nuevo artículo
                Kardex::create([
                    'articulo_id' => $nuevoArticuloId,
                    'user_id' => $vendedorId,
                    'tipo_movimiento' => 'SALIDA_CAMBIO_ARTICULO',
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockNueViejo,
                    'stock_nuevo' => $stockNueNuevo,
                    'referencia' => "Cambio de artículo en Venta Folio #" . $venta->folio,
                    'observaciones' => "Salida por entrega de nuevo artículo sustituto en venta. Motivo: " . ($request->motivo ?? 'Cambio directo'),
                    'fecha_movimiento' => now()
                ]);

                // Actualizar los datos del modelo de artículo en el detalle
                $nuevoArticuloBD = Articulo::find($nuevoArticuloId);
                $detalle->articulo_id = $nuevoArticuloId;
                $detalle->nombre_articulo = $nuevoArticuloBD ? $nuevoArticuloBD->nombre : 'Sin Nombre';
            }

            // 4. Actualizar precios y subtotales en VentaDetalle
            $nuevoPrecioUnitario = $request->nuevo_precio;
            $nuevoSubtotal = ($nuevoPrecioUnitario - ($detalle->descuento ?? 0)) * $cantidad;

            $detalle->precio_unitario = $nuevoPrecioUnitario;
            $detalle->subtotal = $nuevoSubtotal;
            $detalle->save();

            // 5. Recalcular Totales de la Venta Principal
            $subtotalVentaNuevo = VentaDetalle::where('venta_id', $ventaId)->sum('subtotal');
            $totalVentaNuevo = $subtotalVentaNuevo;
            
            // Recalcular saldo restante considerando abonos ya pagados
            $montoAbonado = $venta->total - $venta->saldo_restante;
            $nuevoSaldoRestante = max(0, $totalVentaNuevo - $montoAbonado);

            $venta->subtotal = $subtotalVentaNuevo;
            $venta->total = $totalVentaNuevo;
            $venta->saldo_restante = $nuevoSaldoRestante;
            $venta->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => '¡Artículo cambiado exitosamente y montos/stocks actualizados!',
                'venta_id' => $venta->id,
                'nuevo_total' => $venta->total,
                'nuevo_saldo' => $venta->saldo_restante
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al realizar el cambio de artículo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el inventario con stock disponible del vendedor asignado a la venta
     * Desglosado por artículo y por condición (Nuevo, Devolución, Recogido)
     */
    public function obtenerInventarioVendedorAjax($ventaId)
    {
        try {
            $venta = Venta::findOrFail($ventaId);
            
            // Determinar ID del vendedor de la venta
            $vendedorId = $venta->vendedor_id ?? $venta->user_id ?? auth()->id();

            // Consultar existencias > 0 en inventario_stock
            $stocks = InventarioStock::where('user_id', $vendedorId)
                ->where('cantidad', '>', 0)
                ->get()
                ->map(function($st) {
                    // Buscar el artículo de forma directa por su UUID
                    $articulo = Articulo::find($st->articulo_id);

                    $nombreArticulo = $articulo ? $articulo->nombre : 'Artículo sin nombre';
                    $codigoArticulo = $articulo ? ($articulo->barras ?? $articulo->codigo ?? 'S/C') : 'S/C';
                    $precioArticulo = $articulo ? ($articulo->pcontado ?? $articulo->pcompra ?? 0) : 0;

                    return [
                        'id'         => $st->articulo_id,
                        'stock_id'   => $st->id,
                        'nombre'     => $nombreArticulo . " (" . $st->condicion . ")",
                        'codigo'     => $codigoArticulo,
                        'precio'     => (float)$precioArticulo,
                        'stock'      => (int)$st->cantidad,
                        'condicion'  => $st->condicion
                    ];
                });

            // RESPUESTA CORRECTA EN LARAVEL
            return response()->json([
                'status'      => 'success',
                'vendedor_id' => $vendedorId,
                'articulos'   => $stocks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al consultar inventario: ' . $e->getMessage()
            ], 500);
        }
    }
}