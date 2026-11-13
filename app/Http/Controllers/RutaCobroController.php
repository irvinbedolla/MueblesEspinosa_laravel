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
    /*
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
    */

    public function index(Request $request)
    {
        $user = auth()->user();
        $esAdmin = $user->isAdmin(); // Usa el mismo método de tu CierreController
        $hoy = date('Y-m-d');
        $fechaSeleccionada = $request->input('fecha', $hoy);
        $coloniaFiltro = $request->input('colonia', '');

        // Control estricto de acceso por rol
        if ($esAdmin) {
            // Admin puede elegir 'todos' o un UUID específico de cobrador
            $cobradorId = $request->input('cobrador_id', 'todos');
            $cobradores = DB::table('users')
                ->select('id', 'username')
                ->where('is_active', 1)
                ->orderBy('username')
                ->get();
        } else {
            // Cobrador: bloqueado exclusivamente a su propio ID
            $cobradorId = $user->id;
            $cobradores = collect();
        }

        // Consulta base de cuentas activas con saldo
        $query = DB::table('ventas as v')
            ->select(
                'v.id',
                'v.folio',
                'v.venta_id_legacy',
                'v.total',
                'v.saldo_restante',
                'v.pago_cuota',
                'v.frecuencia_nombre',
                'v.frecuencia_dias',
                'v.fecha_proximo_pago',
                'v.nota_cobranza',
                'v.cobrador_id',
                'c.nombre as cliente_nombre',
                'c.telefono as cliente_telefono',
                'c.calle',
                'c.colonia',
                'c.ubicacion',
                'c.latitud',
                'c.longitud',
                'u.username as cobrador_nombre',
                DB::raw("DATEDIFF('{$hoy}', v.fecha_proximo_pago) as dias_atraso")
            )
            ->join('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('users as u', 'v.cobrador_id', '=', 'u.id')
            ->where('v.status', 'Pendiente')
            ->where('v.saldo_restante', '>', 0);

        // Aplicar filtro de cobrador
        if ($cobradorId !== 'todos') {
            $query->where('v.cobrador_id', $cobradorId);
        }

        // Filtro opcional por colonia (orden geográfico en calle)
        if (!empty($coloniaFiltro)) {
            $query->where('c.colonia', $coloniaFiltro);
        }

        // Regla de acumulación: HOY incluye atrasadas (<= hoy); días futuros solo su fecha exacta
        if ($fechaSeleccionada === $hoy) {
            $query->whereDate('v.fecha_proximo_pago', '<=', $hoy);
        } else {
            $query->whereDate('v.fecha_proximo_pago', '=', $fechaSeleccionada);
        }

        $cuentas = $query->orderBy('c.colonia', 'asc')
                         ->orderBy('v.fecha_proximo_pago', 'asc')
                         ->get();

        // Colonias disponibles en la ruta actual para el selector rápido
        $colonias = $cuentas->pluck('colonia')->filter()->unique()->sort()->values();

        // Contadores para los próximos 7 días (barra superior)
        $agendaQuery = DB::table('ventas')
            ->select('fecha_proximo_pago', DB::raw('COUNT(*) as total_cuentas'))
            ->where('status', 'Pendiente')
            ->where('saldo_restante', '>', 0)
            ->whereBetween('fecha_proximo_pago', [$hoy, Carbon::parse($hoy)->addDays(6)->format('Y-m-d')]);

        if ($cobradorId !== 'todos') {
            $agendaQuery->where('cobrador_id', $cobradorId);
        }

        $agendaSemanal = $agendaQuery->groupBy('fecha_proximo_pago')
            ->pluck('total_cuentas', 'fecha_proximo_pago');

        return view('rutas.index', compact(
            'cuentas',
            'fechaSeleccionada',
            'agendaSemanal',
            'cobradorId',
            'cobradores',
            'esAdmin',
            'colonias',
            'coloniaFiltro'
        ));
    }

    /**
     * Reagendar cuenta para otro día (ej. mover visita del domingo al miércoles)
     */
    public function reagendar(Request $request, string $ventaId)
    {
        $request->validate([
            'nueva_fecha'   => 'required|date|after_or_equal:today',
            'nota_cobranza' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $venta = DB::table('ventas')->where('id', $ventaId)->first();

        if (!$venta) {
            return back()->with('error', 'La venta no existe.');
        }

        // Seguridad: un cobrador solo puede reagendar cuentas asignadas a él
        if (!$user->isAdmin() && $venta->cobrador_id !== $user->id) {
            abort(403, 'No tienes permiso para modificar una cuenta que no está en tu ruta.');
        }

        DB::table('ventas')
            ->where('id', $ventaId)
            ->update([
                'fecha_proximo_pago' => $request->nueva_fecha,
                'nota_cobranza'      => $request->nota_cobranza ?: 'Reagendado para el ' . Carbon::parse($request->nueva_fecha)->format('d/m/Y'),
                'updated_at'         => now(),
            ]);

        return back()->with('success', 'Cuenta reagendada para el ' . Carbon::parse($request->nueva_fecha)->format('d/m/Y'));
    }

    /**
     * Exclusivo Administrador: Reasignar una cuenta a otro cobrador
     */
    public function asignarCobrador(Request $request, string $ventaId)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Solo el administrador puede reasignar cobradores.');
        }

        $request->validate([
            'cobrador_id' => 'required|exists:users,id',
        ]);

        DB::table('ventas')
            ->where('id', $ventaId)
            ->update([
                'cobrador_id' => $request->cobrador_id,
                'updated_at'  => now(),
            ]);

        return back()->with('success', 'Cobrador reasignado correctamente.');
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
     * VISTA TABLET / MÓVIL: Carga la ruta dinámica del día (+ atrasadas) para el cobrador
     */
    public function miRutaCobrador()
    {
        $user = auth()->user();
        $hoy = now()->toDateString();

        $cuentas = DB::table('ventas as v')
            ->select(
                'v.id as venta_id',
                'v.folio',
                'v.venta_id_legacy',
                'v.saldo_restante',
                'v.pago_cuota',
                'v.frecuencia_dias',
                'v.fecha_proximo_pago',
                'v.nota_cobranza',
                'c.id as cliente_id',
                'c.nombre as cliente_nombre',
                'c.telefono',
                'c.calle',
                'c.colonia',
                'c.ubicacion',
                'c.latitud',
                'c.longitud'
            )
            ->join('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->where('v.cobrador_id', $user->id)
            ->where('v.status', 'Pendiente')
            ->where('v.saldo_restante', '>', 0)
            ->whereDate('v.fecha_proximo_pago', '<=', $hoy)
            ->orderBy('c.colonia', 'asc')
            ->orderBy('v.fecha_proximo_pago', 'asc')
            ->get();

        return view('cobrador.mi_ruta', compact('cuentas', 'hoy'));
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
                            $venta->fecha_proximo_pago = null;
                        } else {
                            $dias = max(1, (int) ($venta->frecuencia_dias ?? 7));
                            $venta->fecha_proximo_pago = Carbon::parse($fechaSoloFecha)->addDays($dias)->format('Y-m-d');
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