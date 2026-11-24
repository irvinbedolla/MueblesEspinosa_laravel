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
use Illuminate\Support\Facades\Schema;

class ArticuloController extends Controller
{

    public function index(Request $request)
    {
        $user = auth()->user();
        $busqueda = $request->get('buscar');
        $esAdmin = $user->hasRole('admin') || $user->role === 1;

        // Filtro de ubicación/usuario: 'general' (Almacén General) o el UUID/ID de un usuario
        $ubicacion = $esAdmin ? $request->get('ubicacion', 'general') : (string) $user->id;
        $usuariosInventario = collect();
        $usuarioSeleccionado = null;

        if ($esAdmin) {
            $usuariosInventario = User::where('is_active', 1)
                ->orderBy('username', 'asc')
                ->get(['id', 'username']);

            if ($ubicacion !== 'general' && $ubicacion !== '') {
                $usuarioSeleccionado = $usuariosInventario->firstWhere('id', $ubicacion);
                if (!$usuarioSeleccionado) {
                    $ubicacion = 'general';
                }
            } else {
                $ubicacion = 'general';
            }
        }

        // Scope reutilizable: excluye artículos desactivados/eliminados lógicamente
        $tieneActivo = Schema::hasColumn('articulos', 'activo');
        $tieneStatus = Schema::hasColumn('articulos', 'status');

        $soloActivos = function ($q) use ($tieneActivo, $tieneStatus) {
            if ($tieneActivo) {
                $q->where('articulos.activo', 1);
            }
            if ($tieneStatus) {
                $q->where(function ($sub) {
                    $sub->whereNull('articulos.status')
                        ->orWhereNotIn('articulos.status', ['Inactivo', 'Eliminado', 'Baja']);
                });
            }
        };

        // 1. Resumen según el rol y el filtro seleccionado (solo artículos activos y disponibles)
        if ($esAdmin) {
            if ($usuarioSeleccionado) {
                $stockUsuario = InventarioStock::where('user_id', $usuarioSeleccionado->id)
                    ->whereHas('articulo', $soloActivos)
                    ->sum('cantidad');

                $resumen = [
                    'total_articulos' => Articulo::where($soloActivos)
                        ->whereHas('stocks', function ($q) use ($usuarioSeleccionado) {
                            $q->where('user_id', $usuarioSeleccionado->id)->where('cantidad', '>', 0);
                        })->count(),
                    'stock_general'   => $stockUsuario,
                    'stock_almacen'   => InventarioStock::whereNull('user_id')->whereHas('articulo', $soloActivos)->sum('cantidad'),
                    'stock_bajo'      => Articulo::where($soloActivos)->whereHas('stocks', function ($q) {
                        $q->whereNull('user_id')->where('cantidad', '>', 0)->whereRaw('cantidad <= articulos.stock_min');
                    })->count(),
                ];
            } else {
                // Solo cuenta productos activos que tengan al menos 1 pieza disponible en Almacén o con vendedores
                $resumen = [
                    'total_articulos' => Articulo::where($soloActivos)
                        ->whereHas('stocks', function ($q) {
                            $q->where('cantidad', '>', 0);
                        })->count(),
                    'stock_general'   => InventarioStock::whereHas('articulo', $soloActivos)->sum('cantidad'),
                    'stock_almacen'   => InventarioStock::whereNull('user_id')->whereHas('articulo', $soloActivos)->sum('cantidad'),
                    'stock_bajo'      => Articulo::where($soloActivos)->whereHas('stocks', function ($q) {
                        $q->whereNull('user_id')->where('cantidad', '>', 0)->whereRaw('cantidad <= articulos.stock_min');
                    })->count(),
                ];
            }
        } else {
            // Vendedor: solo ve sus artículos activos con existencias > 0
            $stockVendedor = InventarioStock::where('user_id', $user->id)
                ->whereHas('articulo', $soloActivos)
                ->sum('cantidad');

            $resumen = [
                'total_articulos' => Articulo::where($soloActivos)
                    ->whereHas('stocks', function ($q) use ($user) {
                        $q->where('user_id', $user->id)->where('cantidad', '>', 0);
                    })->count(),
                'stock_general'   => $stockVendedor,
                'stock_almacen'   => 0,
                'stock_bajo'      => 0,
            ];
        }

        // 2. Consulta filtrada por Rol / Usuario seleccionado, activos y con stock disponible (> 0)
        $articulos = Articulo::with(['stocks.usuario', 'precios'])
            ->where($soloActivos)
            ->when(!$esAdmin, function ($q) use ($user) {
                return $q->whereHas('stocks', function ($sk) use ($user) {
                    $sk->where('user_id', $user->id)->where('cantidad', '>', 0);
                });
            })
            ->when($esAdmin && $usuarioSeleccionado, function ($q) use ($usuarioSeleccionado) {
                return $q->whereHas('stocks', function ($sk) use ($usuarioSeleccionado) {
                    $sk->where('user_id', $usuarioSeleccionado->id)->where('cantidad', '>', 0);
                });
            })
            ->when($esAdmin && !$usuarioSeleccionado, function ($q) {
                // En Inventario General (Almacén): solo muestra artículos con stock > 0
                // (si quieres que incluya stock de vendedores aunque almacén sea 0, quita ->whereNull('user_id'))
                return $q->whereHas('stocks', function ($sk) {
                    $sk->whereNull('user_id')->where('cantidad', '>', 0);
                });
            })
            ->when($busqueda, function ($q, $busqueda) {
                return $q->where(function ($sub) use ($busqueda) {
                    $sub->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('marca', 'like', "%{$busqueda}%")
                        ->orWhere('barras', 'like', "%{$busqueda}%")
                        ->orWhere('modelo', 'like', "%{$busqueda}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('articulos.index', compact(
            'articulos',
            'busqueda',
            'resumen',
            'esAdmin',
            'ubicacion',
            'usuariosInventario',
            'usuarioSeleccionado'
        ));
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
            'nombre'        => 'required|string|max:100',
            'pcompra'       => 'required|numeric|min:0',
            'pcredito'      => 'required|numeric|min:0',
            'pcontado'      => 'required|numeric|min:0',
            'porcentaje'    => 'nullable|numeric|min:0',
            'stock_inicial' => 'required|integer|min:0',
            'stock_min'     => 'required|integer|min:0',
        ]);

        // Dentro de store() en ArticuloController.php (después de $request->validate)
        $nombreNorm = mb_strtoupper(trim($request->nombre));
        $marcaNorm  = mb_strtoupper(trim((string) $request->marca));
        $modeloNorm = mb_strtoupper(trim((string) $request->modelo));
        $barrasNorm = trim((string) $request->barras);

        // 1. Validar código de barras único (si no viene vacío)
        if ($barrasNorm !== '') {
            $existeBarras = Articulo::where('barras', $barrasNorm)
                ->when(Schema::hasColumn('articulos', 'activo'), fn ($q) => $q->where('activo', 1))
                ->exists();

            if ($existeBarras) {
                return back()->withInput()->with('error', "Ya existe un artículo registrado con el código de barras '{$barrasNorm}'.");
            }
        }

        // 2. Validar misma combinación de Nombre + Marca + Modelo
        $existeArticulo = Articulo::whereRaw('UPPER(TRIM(nombre)) = ?', [$nombreNorm])
            ->whereRaw("UPPER(TRIM(COALESCE(marca, ''))) = ?", [$marcaNorm])
            ->whereRaw("UPPER(TRIM(COALESCE(modelo, ''))) = ?", [$modeloNorm])
            ->when(Schema::hasColumn('articulos', 'activo'), fn ($q) => $q->where('activo', 1))
            ->exists();

        if ($existeArticulo) {
            return back()->withInput()->with('error', "El artículo '{$nombreNorm}' (Marca: " . ($marcaNorm ?: 'S/M') . ", Modelo: " . ($modeloNorm ?: 'S/M') . ") ya está registrado en el catálogo.");
        }

        try {
            DB::transaction(function () use ($request) {
                $pcompra  = floatval($request->pcompra);
                $pcredito = floatval($request->pcredito);
                $pcontado = floatval($request->pcontado);

                // Calcula el porcentaje y lo acota a 999.99 para respetar DECIMAL(5,2) en MySQL
                $porcentajeCalc = ($pcompra > 0 && $pcredito >= $pcompra)
                    ? (($pcredito - $pcompra) / $pcompra) * 100
                    : 0;
                $porcentaje = min(999.99, round($porcentajeCalc, 2));

                // 1. Crear artículo
                $articulo = Articulo::create([
                    'id'          => Str::uuid()->toString(),
                    'nombre'      => mb_strtoupper(trim($request->nombre)),
                    'marca'       => $request->marca,
                    'modelo'      => $request->modelo,
                    'barras'      => $request->barras,
                    'n_serie'     => $request->n_serie,
                    'descripcion' => $request->descripcion,
                    'categoria'   => $request->categoria,
                    'pcompra'     => $pcompra,
                    'pcontado'    => $pcontado,
                    'pcredito'    => $pcredito,
                    'porcentaje'  => $porcentaje,
                    'stock_min'   => $request->stock_min,
                ]);

                // 2. Crear stock inicial en Almacén General (user_id = null)
                InventarioStock::create([
                    'id'          => Str::uuid()->toString(),
                    'articulo_id' => $articulo->id,
                    'user_id'     => null,
                    'cantidad'    => $request->stock_inicial,
                ]);

                // 3. Generar tabla de precios sugeridos de 1 a 12 meses
                $this->generarPreciosCredito($articulo->id, $pcredito, $request->input('precios_custom', []));

                // 4. Registrar en Kardex
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

        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Error al guardar: ' . $e->getMessage());
        }
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
            'pcredito'   => 'required|numeric|min:0',
            'pcontado'   => 'required|numeric|min:0',
            'porcentaje' => 'nullable|numeric|min:0',
            'stock_min'  => 'required|integer|min:0',
        ]);

        // Dentro de store() en ArticuloController.php (después de $request->validate)
        $nombreNorm = mb_strtoupper(trim($request->nombre));
        $marcaNorm  = mb_strtoupper(trim((string) $request->marca));
        $modeloNorm = mb_strtoupper(trim((string) $request->modelo));
        $barrasNorm = trim((string) $request->barras);

        // 1. Validar código de barras único (excluyendo el artículo actual)
        if ($barrasNorm !== '') {
            $existeBarras = Articulo::where('barras', $barrasNorm)
                ->where('id', '!=', $articulo->id)
                ->when(Schema::hasColumn('articulos', 'activo'), fn ($q) => $q->where('activo', 1))
                ->exists();

            if ($existeBarras) {
                return back()->withInput()->with('error', "Ya existe otro artículo registrado con el código de barras '{$barrasNorm}'.");
            }
        }

        // 2. Validar misma combinación de Nombre + Marca + Modelo (excluyendo el artículo actual)
        $existeArticulo = Articulo::whereRaw('UPPER(TRIM(nombre)) = ?', [$nombreNorm])
            ->whereRaw("UPPER(TRIM(COALESCE(marca, ''))) = ?", [$marcaNorm])
            ->whereRaw("UPPER(TRIM(COALESCE(modelo, ''))) = ?", [$modeloNorm])
            ->where('id', '!=', $articulo->id)
            ->when(Schema::hasColumn('articulos', 'activo'), fn ($q) => $q->where('activo', 1))
            ->exists();

        if ($existeArticulo) {
            return back()->withInput()->with('error', "Ya existe otro artículo '{$nombreNorm}' (Marca: " . ($marcaNorm ?: 'S/M') . ", Modelo: " . ($modeloNorm ?: 'S/M') . ") en el catálogo.");
        }

        try {
            DB::transaction(function () use ($request, $articulo) {
                $pcompra  = floatval($request->pcompra);
                $pcredito = floatval($request->pcredito);
                $pcontado = floatval($request->pcontado);

                // Calcula el porcentaje y lo acota a 999.99 para respetar DECIMAL(5,2) en MySQL
                $porcentajeCalc = $request->filled('porcentaje')
                    ? floatval($request->porcentaje)
                    : (($pcompra > 0 && $pcredito >= $pcompra) ? (($pcredito - $pcompra) / $pcompra) * 100 : 0);
                $porcentaje = min(999.99, round($porcentajeCalc, 2));

                $articulo->update([
                    'nombre'      => mb_strtoupper(trim($request->nombre)),
                    'marca'       => $request->marca,
                    'modelo'      => $request->modelo,
                    'barras'      => $request->barras,
                    'n_serie'     => $request->n_serie,
                    'descripcion' => $request->descripcion,
                    'categoria'   => $request->categoria,
                    'pcompra'     => $pcompra,
                    'pcontado'    => $pcontado,
                    'pcredito'    => $pcredito,
                    'porcentaje'  => $porcentaje,
                    'stock_min'   => $request->stock_min,
                ]);

                // Re-calcular o actualizar la tabla de precios partiendo de $pcredito
                $this->generarPreciosCredito($articulo->id, $pcredito, $request->input('precios_custom', []));
            });

            return redirect()->route('articulos.index')->with('success', 'Artículo actualizado con éxito.');

        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Lógica para calcular la tabla de precios sugeridos a meses (descuentos sobre pcredito)
     */
    private function generarPreciosCredito(string $articuloId, float $pcredito, array $preciosCustom = [])
    {
        $descuentosMes = [
            1 => 40, 2 => 35, 3 => 30, 4 => 25, 
            5 => 20, 6 => 15, 7 => 10, 8 => 5,
            9 => 0,  10 => 0, 11 => 0, 12 => 0
        ];

        for ($m = 1; $m <= 12; $m++) {
            if (isset($preciosCustom[$m]) && floatval($preciosCustom[$m]) > 0) {
                $precioTotal = floatval($preciosCustom[$m]);
            } else {
                $pctDesc = $descuentosMes[$m] ?? 0;
                $precioTotal = $pcredito - ($pcredito * ($pctDesc / 100));
            }

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
            return back()
                ->withInput()
                ->with('error', 'El origen y el destino no pueden ser iguales.');
        }

        try {
            DB::transaction(function () use ($request) {
                $origenUserId  = $request->origen === 'almacen' ? null : $request->origen;
                $destinoUserId = $request->destino === 'almacen' ? null : $request->destino;

                // Consultar Stock Origen (sin crear registro vacío si no existe)
                $stockOrigen = InventarioStock::where('articulo_id', $request->articulo_id)
                    ->where('user_id', $origenUserId)
                    ->lockForUpdate()
                    ->first();

                $disponible = $stockOrigen ? (int) $stockOrigen->cantidad : 0;

                if ($disponible < (int) $request->cantidad) {
                    $nombreOrigen = $origenUserId === null
                        ? 'Almacén General'
                        : (User::where('id', $origenUserId)->value('username') ?? 'el vendedor seleccionado');

                    throw new \Exception("Stock insuficiente en {$nombreOrigen}. Disponible: {$disponible} pza(s), solicitadas: {$request->cantidad} pza(s).");
                }

                // Stock Destino
                $stockDestino = InventarioStock::firstOrCreate(
                    [
                        'articulo_id' => $request->articulo_id,
                        'user_id'     => $destinoUserId,
                    ],
                    [
                        'id'       => Str::uuid()->toString(),
                        'cantidad' => 0,
                    ]
                );

                // Descontar origen e incrementar destino
                $stockOrigen->decrement('cantidad', $request->cantidad);
                $stockDestino->increment('cantidad', $request->cantidad);

                // Determinar tipo de movimiento
                $tipo = ($origenUserId === null)
                    ? 'Asignacion_Vendedor'
                    : (($destinoUserId === null) ? 'Devolucion_Almacen' : 'Traspaso_Entre_Vendedores');

                // Auditoría Kardex
                Kardex::create([
                    'id'              => Str::uuid()->toString(),
                    'articulo_id'     => $request->articulo_id,
                    'user_id'         => auth()->id(),
                    'origen_user_id'  => $origenUserId,
                    'destino_user_id' => $destinoUserId,
                    'tipo_movimiento' => $tipo,
                    'cantidad'        => $request->cantidad,
                    'stock_anterior'  => $disponible,
                    'stock_nuevo'     => $disponible - (int) $request->cantidad,
                    'observaciones'   => $request->observaciones ?? 'Traspaso de inventario',
                ]);
            });

            return redirect()->route('articulos.index')->with('success', 'Traspaso realizado con éxito.');

        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
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

    /**
     * Elimina o desactiva un artículo del catálogo.
     */
    public function destroy($id)
    {
        try {
            $articulo = DB::table('articulos')
                ->where('id', $id)
                ->first();

            if (!$articulo) {
                return back()->with('error', 'No se encontró el artículo especificado.');
            }

            // Verificar si el artículo ya tiene ventas o movimientos asociados
            $tieneVentas = DB::table('venta_detalles')
                ->where('articulo_id', $articulo->id)
                ->exists();

            if ($tieneVentas) {
                // Si tiene historial de ventas, no se borra físicamente para no romper reportes; se desactiva
                if (Schema::hasColumn('articulos', 'activo')) {
                    DB::table('articulos')->where('id', $articulo->id)->update([
                        'activo'     => 0,
                        'updated_at' => now(),
                    ]);
                    return back()->with('success', 'El artículo tiene historial de ventas, por lo que fue desactivado correctamente.');
                }

                if (Schema::hasColumn('articulos', 'status')) {
                    DB::table('articulos')->where('id', $articulo->id)->update([
                        'status'     => 'Inactivo',
                        'updated_at' => now(),
                    ]);
                    return back()->with('success', 'El artículo tiene historial de ventas, por lo que fue marcado como Inactivo.');
                }

                return back()->with('error', 'No se puede eliminar este artículo porque ya está registrado en una o más ventas.');
            }

            // Si no tiene ventas asociadas, se elimina de forma segura
            DB::table('articulos')->where('id', $articulo->id)->delete();

            return back()->with('success', 'Artículo eliminado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'No se pudo eliminar el artículo: ' . $e->getMessage());
        }
    }
}