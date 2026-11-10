<?php

namespace App\Http\Controllers;

use App\Models\Articulo;
use App\Models\ArticuloPrecio;
use App\Models\InventarioStock;
use App\Models\Kardex;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArticuloController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $busqueda = $request->get('buscar');
        $esAdmin = $user->hasRole('admin') || $user->role === 1; // Ajustar según tu manejo de roles

        // 1. Resumen según el rol
        if ($esAdmin) {
            $resumen = [
                'total_articulos' => Articulo::count(),
                'stock_general'   => InventarioStock::sum('cantidad'),
                'stock_almacen'   => InventarioStock::whereNull('user_id')->sum('cantidad'),
                'stock_bajo'      => Articulo::whereHas('stocks', function($q) {
                                        $q->whereNull('user_id');
                                     })->get()->filter(fn($a) => $a->stock_almacen <= $a->stock_min)->count()
            ];
        } else {
            // Vendedor: solo ve sus métricas personales
            $stockVendedor = InventarioStock::where('user_id', $user->id)->sum('cantidad');
            $resumen = [
                'total_articulos' => InventarioStock::where('user_id', $user->id)->where('cantidad', '>', 0)->count(),
                'stock_general'   => $stockVendedor,
                'stock_almacen'   => 0,
                'stock_bajo'      => 0
            ];
        }

        // 2. Consulta filtrada por Rol
        $articulos = Articulo::with(['stocks.usuario', 'precios'])
            ->when(!$esAdmin, function ($q) use ($user) {
                // VENDEDOR: solo ve artículos asignados a su cuenta con stock > 0
                return $q->whereHas('stocks', function ($sk) use ($user) {
                    $sk->where('user_id', $user->id)->where('cantidad', '>', 0);
                });
            })
            ->when($busqueda, function ($q, $busqueda) {
                return $q->where('nombre', 'like', "%{$busqueda}%")
                         ->orWhere('marca', 'like', "%{$busqueda}%")
                         ->orWhere('barras', 'like', "%{$busqueda}%")
                         ->orWhere('modelo', 'like', "%{$busqueda}%");
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('articulos.index', compact('articulos', 'busqueda', 'resumen', 'esAdmin'));
    }

    /**
     * Vista dedicada exclusivamente a Artículos con BAJO STOCK en Almacén General
     */
    public function bajoStock(Request $request)
    {
        if (!auth()->user()->hasRole('admin') && auth()->user()->role !== 1) {
            abort(403, 'No tienes permisos para acceder a este apartado.');
        }

        $busqueda = $request->get('buscar');

        // Consulta con join/whereHas directo en la base de datos para habilitar paginación limpia
        $articulosBajoStock = Articulo::with(['stocks' => function($q) {
                $q->whereNull('user_id');
            }])
            ->whereHas('stocks', function ($q) {
                $q->whereNull('user_id')
                ->whereRaw('cantidad <= articulos.stock_min');
            })
            ->when($busqueda, function ($q, $busqueda) {
                return $q->where(function($sub) use ($busqueda) {
                    $sub->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('marca', 'like', "%{$busqueda}%")
                        ->orWhere('barras', 'like', "%{$busqueda}%")
                        ->orWhere('modelo', 'like', "%{$busqueda}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('articulos.bajo_stock', compact('articulosBajoStock', 'busqueda'));
    }

    public function create()
    {
        return view('articulos.create');
    }

    /**
     * Registrar artículo, generar tabla de precios a crédito y asignar stock inicial a Almacén
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'     => 'required|string|max:100',
            'pcompra'    => 'required|numeric|min:0',
            'pcontado'   => 'required|numeric|min:0',
            'porcentaje' => 'required|numeric|min:0',
            'stock_inicial' => 'required|integer|min:0',
            'stock_min'  => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request) {
            // 1. Calcular precio de crédito base
            $pcredito = $request->pcompra + ($request->pcompra * ($request->porcentaje / 100));

            // 2. Crear artículo
            $articulo = Articulo::create([
                'id'          => Str::uuid()->toString(),
                'nombre'      => mb_strtoupper(trim($request->nombre)),
                'marca'       => $request->marca,
                'modelo'      => $request->modelo,
                'barras'      => $request->barras,
                'n_serie'     => $request->n_serie,
                'descripcion' => $request->descripcion,
                'categoria'   => $request->categoria,
                'pcompra'     => $request->pcompra,
                'pcontado'    => $request->pcontado,
                'pcredito'    => $pcredito,
                'porcentaje'  => $request->porcentaje,
                'stock_min'   => $request->stock_min,
            ]);

            // 3. Crear stock inicial en Almacén General (user_id = null)
            InventarioStock::create([
                'id'          => Str::uuid()->toString(),
                'articulo_id' => $articulo->id,
                'user_id'     => null,
                'cantidad'    => $request->stock_inicial,
            ]);

            // 4. Generar tabla de precios sugeridos de 1 a 12 meses usando la lógica original
            $this->generarPreciosCredito($articulo->id, $pcredito, $request->input('precios_custom', []));

            // 5. Registrar en Kardex
            Kardex::create([
                'id'              => Str::uuid()->toString(),
                'articulo_id'     => $articulo->id,
                'user_id'         => auth()->id(),
                'origen_user_id'  => null,
                'destino_user_id' => null,
                'tipo_movimiento' => 'Entrada_Inicial',
                'cantidad'        => $request->stock_inicial,
                'stock_anterior'  => 0,
                'stock_nuevo'     => $request->stock_inicial,
                'observaciones'   => 'Alta inicial del artículo en almacén general',
            ]);
        });

        return redirect()->route('articulos.index')->with('success', 'Artículo registrado correctamente.');
    }

    public function edit(Articulo $articulo)
    {
        $articulo->load('precios', 'stocks');
        return view('articulos.edit', compact('articulo'));
    }

    public function update(Request $request, Articulo $articulo)
    {
        $request->validate([
            'nombre'     => 'required|string|max:100',
            'pcompra'    => 'required|numeric|min:0',
            'pcontado'   => 'required|numeric|min:0',
            'porcentaje' => 'nullable|numeric|min:0',
            'stock_min'  => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $articulo) {
            $porcentaje = floatval($request->porcentaje ?? 0);
            
            // 1. Corrección del error de sintaxis en el cálculo de pcredito
            $pcredito = $request->pcompra + ($request->pcompra * ($porcentaje / 100));

            $articulo->update([
                'nombre'      => mb_strtoupper(trim($request->nombre)),
                'marca'       => $request->marca,
                'modelo'      => $request->modelo,
                'barras'      => $request->barras,
                'n_serie'     => $request->n_serie,
                'descripcion' => $request->descripcion,
                'categoria'   => $request->categoria,
                'pcompra'     => $request->pcompra,
                'pcontado'    => $request->pcontado,
                'pcredito'    => $pcredito,
                'porcentaje'  => $porcentaje,
                'stock_min'   => $request->stock_min,
            ]);

            // 2. Re-calcular o actualizar la tabla de precios
            $this->generarPreciosCredito($articulo->id, floatval($request->pcompra), $request->input('precios_custom', []));
        });

        return redirect()->route('articulos.index')->with('success', 'Artículo actualizado con éxito.');
    }

    /**
     * Lógica para calcular la tabla de precios sugeridos a meses
     */
    private function generarPreciosCredito(string $articuloId, float $pcompra, array $preciosCustom = [])
    {
        // Porcentajes de utilidad directa sobre el precio de compra
        $porcentajesUtilidad = [
            1 => 40, 2 => 35, 3 => 30, 4 => 25, 
            5 => 20, 6 => 15, 7 => 10, 8 => 5,
            9 => 0,  10 => 0, 11 => 0, 12 => 0
        ];

        for ($m = 1; $m <= 12; $m++) {
            // Prioriza el precio manual introducido por el usuario en la vista
            if (isset($preciosCustom[$m]) && floatval($preciosCustom[$m]) > 0) {
                $precioTotal = floatval($preciosCustom[$m]);
            } else {
                $utilidadPct = $porcentajesUtilidad[$m] ?? 0;
                // Corrección del error de sintaxis en el cálculo de la utilidad
                $montoUtilidad = $pcompra * ($utilidadPct / 100);
                $precioTotal = $pcompra + $montoUtilidad;
            }

            // Evitar regenerar UUIDs al actualizar los precios existentes
            $registroExistente = ArticuloPrecio::where('articulo_id', $articuloId)
                ->where('meses', $m)
                ->first();

            $idUsar = $registroExistente ? $registroExistente->id : Str::uuid()->toString();

            ArticuloPrecio::updateOrCreate(
                [
                    'articulo_id' => $articuloId, 
                    'meses'       => $m
                ],
                [
                    'id'           => $idUsar,
                    'precio_total' => round($precioTotal, 2),
                    'pago_mensual' => round($precioTotal / $m, 2),
                ]
            );
        }
    }

    /**
     * Vista de Traspasos (Mover stock entre Almacén y Vendedores)
     */
    public function traspasoForm()
    {
        $articulos = Articulo::with('stocks')->get();
        $vendedores = User::where('is_active', true)->get();

        return view('articulos.traspaso', compact('articulos', 'vendedores'));
    }

    /**
     * Procesar traspaso de stock
     */
    public function procesarTraspaso(Request $request)
    {
        $request->validate([
            'articulo_id' => 'required|exists:articulos,id',
            'origen'      => 'required|string', // 'almacen' o ID de usuario
            'destino'     => 'required|string', // 'almacen' o ID de usuario
            'cantidad'    => 'required|integer|min:1',
        ]);

        if ($request->origen === $request->destino) {
            return back()->withErrors(['msg' => 'El origen y el destino no pueden ser iguales.']);
        }

        DB::transaction(function () use ($request) {
            $origenUserId  = $request->origen === 'almacen' ? null : $request->origen;
            $destinoUserId = $request->destino === 'almacen' ? null : $request->destino;

            // Stock Origen
            $stockOrigen = InventarioStock::firstOrCreate([
                'articulo_id' => $request->articulo_id,
                'user_id'     => $origenUserId
            ], ['id' => Str::uuid()->toString(), 'cantidad' => 0]);

            if ($stockOrigen->cantidad < $request->cantidad) {
                throw new \Exception('No hay suficiente stock en el origen seleccionado.');
            }

            // Stock Destino
            $stockDestino = InventarioStock::firstOrCreate([
                'articulo_id' => $request->articulo_id,
                'user_id'     => $destinoUserId
            ], ['id' => Str::uuid()->toString(), 'cantidad' => 0]);

            // Descontar origen e incrementar destino
            $stockOrigen->decrement('cantidad', $request->cantidad);
            $stockDestino->increment('cantidad', $request->cantidad);

            // Determinar tipo de movimiento
            $tipo = ($origenUserId === null) ? 'Asignacion_Vendedor' : (($destinoUserId === null) ? 'Devolucion_Almacen' : 'Traspaso_Entre_Vendedores');

            // Auditoría Kardex
            Kardex::create([
                'id'              => Str::uuid()->toString(),
                'articulo_id'     => $request->articulo_id,
                'user_id'         => auth()->id(),
                'origen_user_id'  => $origenUserId,
                'destino_user_id' => $destinoUserId,
                'tipo_movimiento' => $tipo,
                'cantidad'        => $request->cantidad,
                'stock_anterior'  => $stockOrigen->cantidad + $request->cantidad,
                'stock_nuevo'     => $stockOrigen->cantidad,
                'observaciones'   => $request->observaciones ?? 'Traspaso de inventario',
            ]);
        });

        return redirect()->route('articulos.index')->with('success', 'Traspaso realizado con éxito.');
    }

    /**
     * Historial de Kardex de Movimientos
     */
    public function kardex(Request $request)
    {
        $movimientos = Kardex::with(['articulo', 'usuario', 'origen', 'destino'])
            ->latest()
            ->paginate(20);

        return view('articulos.kardex', compact('movimientos'));
    }

    /**
     * Sumar stock/surtir mercancía directamente al Almacén General
     */
    public function surtirAlmacen(Request $request)
    {
        $request->validate([
            'articulo_id'   => 'required|exists:articulos,id',
            'cantidad_suma' => 'required|integer|min:1',
            'motivo'        => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            $articulo = Articulo::findOrFail($request->articulo_id);

            // Buscar o crear registro de stock en Almacén General (user_id = null)
            $stockAlmacen = InventarioStock::firstOrCreate([
                'articulo_id' => $articulo->id,
                'user_id'     => null,
            ], [
                'id'       => Str::uuid()->toString(),
                'cantidad' => 0,
            ]);

            $stockAnterior = $stockAlmacen->cantidad;
            $stockNuevo = $stockAnterior + $request->cantidad_suma;

            // Sumar al Almacén
            $stockAlmacen->increment('cantidad', $request->cantidad_suma);

            // Registrar en Kardex
            Kardex::create([
                'id'              => Str::uuid()->toString(),
                'articulo_id'     => $articulo->id,
                'user_id'         => auth()->id(),
                'origen_user_id'  => null,
                'destino_user_id' => null, // Permanece en Almacén
                'tipo_movimiento' => 'Reabastecimiento_Almacen',
                'cantidad'        => $request->cantidad_suma,
                'stock_anterior'  => $stockAnterior,
                'stock_nuevo'     => $stockNuevo,
                'observaciones'   => $request->motivo ?: 'Entrada de mercancía a Almacén General',
            ]);
        });

        return redirect()->back()->with('success', 'Stock agregado correctamente al Almacén General.');
    }

    private function autorizarAdmin() {
        if (!auth()->user()->hasRole('admin') && auth()->user()->role !== 1) {
            abort(403, 'Acción no autorizada para tu perfil.');
        }
    }
}