<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ruta;
use App\Models\RutaDetalle;
use App\Models\Venta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class RutaCobroController extends Controller
{
    public function index(Request $request)
    {
        $cobradores = User::whereHas('roles', function ($q) {
            $q->where('name', 'cobrador');
        })->get();

        $ventasPendientes = Venta::with('cliente')
            ->where('saldo_restante', '>', 0)
            ->whereNotIn('status', ['Pagado', 'Eliminar', 'Devolucion', 'Recogido'])
            ->orderBy('created_at', 'asc')
            ->get();

        $queryRutas = Ruta::with(['cobrador', 'detalles.venta.cliente']);

        if ($request->filled('cobrador_id')) {
            $queryRutas->where('cobrador_id', $request->cobrador_id);
        }

        if ($request->filled('fecha')) {
            $queryRutas->whereDate('fecha_ruta', $request->fecha);
        }

        if ($request->filled('status')) {
            $queryRutas->where('status', $request->status);
        }

        if ($request->filled('buscar')) {
            $busqueda = $request->buscar;
            $queryRutas->where('nombre', 'like', "%{$busqueda}%");
        }

        $rutas = $queryRutas->orderBy('fecha_ruta', 'desc')->paginate(15);

        // Actualización dinámica de estatus de ruta en la PC
        foreach ($rutas as $r) {
            if ($r->detalles->count() > 0) {
                $pendientes = $r->detalles->where('status', 'Pendiente')->count();
                $nuevoEstatus = ($pendientes === 0) ? 'Completada' : 'Pendiente';

                if ($r->status !== $nuevoEstatus) {
                    $r->status = $nuevoEstatus;
                    $r->save();
                }
            }
        }

        return view('rutas.index', compact('rutas', 'cobradores', 'ventasPendientes'));
    }

    public function asignarRuta(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'cobrador_id' => 'required|exists:users,id',
            'fecha_ruta' => 'required|date',
            'ventas' => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $ruta = Ruta::create([
                'nombre' => $request->nombre,
                'cobrador_id' => $request->cobrador_id,
                'fecha_ruta' => $request->fecha_ruta,
                'status' => 'Pendiente'
            ]);

            foreach ($request->ventas as $index => $ventaId) {
                $venta = Venta::find($ventaId);
                if ($venta) {
                    RutaDetalle::create([
                        'ruta_id' => $ruta->id,
                        'venta_id' => $venta->id,
                        'orden' => $index + 1,
                        'monto_sugerido' => $venta->saldo_restante,
                        'status' => 'Pendiente'
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('rutas.index')->with('success', '¡Ruta creada y asignada exitosamente!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al crear la ruta: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'cobrador_id' => 'required|exists:users,id',
            'fecha_ruta' => 'required|date',
        ]);

        try {
            $ruta = Ruta::findOrFail($id);
            $ruta->update([
                'nombre' => $request->nombre,
                'cobrador_id' => $request->cobrador_id,
                'fecha_ruta' => $request->fecha_ruta,
            ]);

            return redirect()->route('rutas.index')->with('success', 'Ruta actualizada correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar la ruta: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $ruta = Ruta::findOrFail($id);
            RutaDetalle::where('ruta_id', $ruta->id)->delete();
            $ruta->delete();

            DB::commit();
            return redirect()->route('rutas.index')->with('success', 'Ruta eliminada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al eliminar la ruta: ' . $e->getMessage());
        }
    }

    /**
     * VISTA TABLET: Carga y unifica TODAS las rutas asignadas al cobrador hoy
     */
    public function miRutaCobrador()
    {
        $user = auth()->user();
        
        $rutas = Ruta::with(['detalles.venta.cliente'])
            ->where('cobrador_id', $user->id)
            ->whereDate('fecha_ruta', now()->toDateString())
            ->get();

        if ($rutas->isEmpty()) {
            $ruta = null;
        } else {
            // Unificar detalles de múltiples rutas si le asignaron más de una el mismo día
            $primeraRuta = $rutas->first();
            $todosLosDetalles = collect();

            foreach ($rutas as $r) {
                $todosLosDetalles = $todosLosDetalles->concat($r->detalles);
            }

            $ruta = (object)[
                'id' => $primeraRuta->id,
                'nombre' => $rutas->count() > 1 ? 'Rutas del Día (' . $rutas->count() . ')' : $primeraRuta->nombre,
                'detalles' => $todosLosDetalles
            ];
        }

        return view('cobrador.mi_ruta', compact('ruta'));
    }

    /**
     * ENDPOINT DE SINCRONIZACIÓN
     */
    public function sincronizarAbonosOffline(Request $request)
    {
        try {
            $cobros = $request->json()->all();

            if (empty($cobros)) {
                $cobros = $request->get('abonos', $request->get('cobros', $request->all()));
            }

            if (empty($cobros) || !is_array($cobros)) {
                return response()->json([
                    'status'     => 'success',
                    'success'    => true,
                    'message'    => 'No hay cobros pendientes por sincronizar.',
                    'procesados' => []
                ], 200);
            }

            DB::beginTransaction();

            $procesados = [];
            $rutasAfectadas = [];

            foreach ($cobros as $item) {
                $ventaId       = $item['venta_id'] ?? $item['a_venta'] ?? null;
                $montoAbono    = (float)($item['monto'] ?? $item['a_pago'] ?? 0);
                $observaciones = $item['observaciones'] ?? 'Abono en ruta (Offline)';
                $statusItem    = $item['status'] ?? 'Cobrado';
                $detalleId     = $item['detalle_id'] ?? null;
                $gpsCoords     = $item['coordenadas_gps'] ?? null;

                // Formatear correctamente la fecha para MySQL (Y-m-d H:i:s y Y-m-d)
                $rawFecha       = $item['fecha_local'] ?? $item['fecha'] ?? null;
                $fechaHoraLocal = $rawFecha ? Carbon::parse($rawFecha)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
                $fechaSoloFecha = $rawFecha ? Carbon::parse($rawFecha)->format('Y-m-d') : now()->format('Y-m-d');

                if (empty($ventaId)) {
                    continue;
                }

                // 1. Guardar o actualizar la ubicación GPS del cliente si viene en la petición
                if (!empty($gpsCoords)) {
                    $ventaObj = Venta::find($ventaId);
                    if ($ventaObj && $ventaObj->cliente_id) {
                        Cliente::where('id', $ventaObj->cliente_id)->update([
                            'cliente_ubicacion' => $gpsCoords
                        ]);
                    }
                }

                // 2. Registrar abono en la tabla 'abonos' con campos de la estructura legacy
                if ($montoAbono > 0 && $statusItem === 'Cobrado') {
                    // Contar cuántos abonos tiene la venta para asignar el siguiente número de pago (a_npago)
                    $totalAbonosPrevios = DB::table('abonos')
                        ->where('venta_id', $ventaId)
                        ->orWhere('a_venta', $ventaId)
                        ->count();

                    DB::table('abonos')->insert([
                        'id'            => (string) Str::uuid(),
                        'a_venta'       => $ventaId,
                        'venta_id'      => $ventaId,
                        'a_pago'        => $montoAbono,
                        'a_fecha'       => $fechaHoraLocal,
                        'a_npago'       => $totalAbonosPrevios + 1, // Nuevo correlativo
                        'a_cobrador'    => auth()->user()->username ?? 'Cobrador',
                        'a_tipopago'    => 'Efectivo',
                        'a_tipo'        => 'Abono',
                        'a_hora'        => $fechaHoraLocal,
                        'observaciones' => $observaciones,
                        'created_at'    => $fechaHoraLocal,
                        'updated_at'    => now(),
                    ]);

                    // Descontar el saldo restante en la venta
                    $venta = Venta::find($ventaId);
                    if ($venta) {
                        $venta->saldo_restante = max(0, $venta->saldo_restante - $montoAbono);
                        if ($venta->saldo_restante == 0) {
                            $venta->status = 'Pagado';
                        }
                        $venta->save();
                    }
                }

                // 3. Actualizar la tabla 'ruta_detalles'
                if (!empty($detalleId)) {
                    $detalle = RutaDetalle::find($detalleId);
                    if ($detalle) {
                        $detalle->update([
                            'status'        => $statusItem,
                            'monto_cobrado' => $montoAbono,
                            'observaciones' => $observaciones,
                            'fecha_cobro'   => $fechaHoraLocal,
                        ]);
                        $rutasAfectadas[$detalle->ruta_id] = true;
                    }
                } else {
                    RutaDetalle::where('venta_id', $ventaId)->update([
                        'status'        => $statusItem,
                        'monto_cobrado' => $montoAbono,
                        'observaciones' => $observaciones,
                        'fecha_cobro'   => $fechaHoraLocal,
                    ]);
                }

                $procesados[] = $detalleId ?? $ventaId;
            }

            // 4. Evaluar si las rutas involucradas ya no tienen visitas pendientes para marcarlas como 'Completada'
            foreach (array_keys($rutasAfectadas) as $rutaId) {
                $pendientes = RutaDetalle::where('ruta_id', $rutaId)
                    ->where('status', 'Pendiente')
                    ->count();

                if ($pendientes === 0) {
                    Ruta::where('id', $rutaId)->update(['status' => 'Completada']);
                }
            }

            DB::commit();

            return response()->json([
                'status'     => 'success',
                'success'    => true,
                'message'    => 'Sincronización completada exitosamente.',
                'procesados' => $procesados
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'Error al guardar los abonos: ' . $e->getMessage()
            ], 500);
        }
    }
}