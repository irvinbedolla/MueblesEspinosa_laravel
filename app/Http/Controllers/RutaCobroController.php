<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ruta;
use App\Models\RutaDetalle;
use App\Models\Venta;
use App\Models\User;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Str;

class RutaCobroController extends Controller
{
    /**
     * Expresión SQL unificada para la fecha de cobro:
     * Prioriza fecha_proximo_pago y usa fecha_final_pago como respaldo (igual que Gestión de Abonos).
     */
    private function getFechaCobroSql(string $alias = 'v'): string
    {
        $prefix = $alias !== '' ? "{$alias}." : '';
        return "DATE(COALESCE(NULLIF({$prefix}fecha_proximo_pago, '0000-00-00'), NULLIF({$prefix}fecha_final_pago, '0000-00-00')))";
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $esAdmin = $user->isAdmin();
        $hoy = date('Y-m-d');

        // 'agenda' = fechas específicas (Hoy, Mañana, etc.) | 'atrasados' = cuentas vencidas (< hoy)
        $vista = $request->input('vista', 'agenda');
        $fechaSeleccionada = $request->input('fecha', $hoy);
        $coloniaFiltro = $request->input('colonia', '');
        $buscar = trim($request->input('buscar', ''));

        // Control estricto de acceso por rol
        if ($esAdmin) {
            $cobradorId = $request->input('cobrador_id', 'todos');
            $cobradores = DB::table('users')
                ->select('id', 'username')
                ->where('is_active', 1)
                ->orderBy('username')
                ->get();
        } else {
            $cobradorId = $user->id;
            $cobradores = collect();
        }

        $fechaCobroV = $this->getFechaCobroSql('v');
        $fechaCobroSinAlias = $this->getFechaCobroSql('');

        // Consulta base de cuentas activas con saldo (leftJoin para no perder ventas migradas)
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
                DB::raw("{$fechaCobroV} as fecha_proximo_pago"),
                'v.nota_cobranza',
                'v.cobrador_id',
                DB::raw("COALESCE(c.nombre, 'SIN CLIENTE ASIGNADO') as cliente_nombre"),
                'c.telefono as cliente_telefono',
                'c.calle',
                'c.colonia',
                'c.ubicacion',
                'c.latitud',
                'c.longitud',
                'u.username as cobrador_nombre',
                DB::raw("DATEDIFF('{$hoy}', {$fechaCobroV}) as dias_atraso")
            )
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('users as u', 'v.cobrador_id', '=', 'u.id')
            ->where('v.status', 'Pendiente')
            ->where('v.saldo_restante', '>', 0)
            ->whereRaw("{$fechaCobroV} IS NOT NULL");

        // Aplicar filtro de cobrador
        if ($cobradorId !== 'todos') {
            $query->where('v.cobrador_id', $cobradorId);
        }

        // Filtro de búsqueda por cliente, folio o calle
        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $q->where('c.nombre', 'like', "%{$buscar}%")
                  ->orWhere('c.calle', 'like', "%{$buscar}%")
                  ->orWhere('v.folio', 'like', "%{$buscar}%")
                  ->orWhere('v.venta_id_legacy', 'like', "%{$buscar}%");
            });
        }

        // Separación estricta: Atrasados (< hoy) vs Fecha exacta (= fechaSeleccionada)
        if ($vista === 'atrasados') {
            $query->whereRaw("{$fechaCobroV} < ?", [$hoy]);
        } else {
            $query->whereRaw("{$fechaCobroV} = ?", [$fechaSeleccionada]);
        }

        // Obtener colonias disponibles antes de filtrar por colonia para no vaciar el select
        $colonias = (clone $query)->pluck('colonia')->filter()->unique()->sort()->values();

        // Filtro opcional por colonia
        if (!empty($coloniaFiltro)) {
            $query->where('c.colonia', $coloniaFiltro);
        }

        $cuentas = $query->orderBy('c.colonia', 'asc')
                         ->orderByRaw("{$fechaCobroV} asc")
                         ->get();

        // Contador total de atrasados (< hoy) para la pestaña roja
        $atrasadosQuery = DB::table('ventas')
            ->where('status', 'Pendiente')
            ->where('saldo_restante', '>', 0)
            ->whereRaw("{$fechaCobroSinAlias} IS NOT NULL")
            ->whereRaw("{$fechaCobroSinAlias} < ?", [$hoy]);

        if ($cobradorId !== 'todos') {
            $atrasadosQuery->where('cobrador_id', $cobradorId);
        }
        $totalAtrasados = $atrasadosQuery->count();

        // Contadores exactos para los próximos 7 días (Hoy + 6 días)
        $fechaFinSemana = Carbon::parse($hoy)->addDays(6)->format('Y-m-d');
        $agendaQuery = DB::table('ventas')
            ->select(DB::raw("{$fechaCobroSinAlias} as fecha_dia"), DB::raw('COUNT(*) as total_cuentas'))
            ->where('status', 'Pendiente')
            ->where('saldo_restante', '>', 0)
            ->whereRaw("{$fechaCobroSinAlias} BETWEEN ? AND ?", [$hoy, $fechaFinSemana]);

        if ($cobradorId !== 'todos') {
            $agendaQuery->where('cobrador_id', $cobradorId);
        }

        $agendaSemanal = $agendaQuery->groupBy('fecha_dia')
            ->pluck('total_cuentas', 'fecha_dia');

        return view('rutas.index', compact(
            'cuentas',
            'vista',
            'totalAtrasados',
            'fechaSeleccionada',
            'agendaSemanal',
            'cobradorId',
            'cobradores',
            'esAdmin',
            'colonias',
            'coloniaFiltro',
            'buscar'
        ));
    }

    /**
     * Reagendar cuenta para otro día (sincroniza ambas columnas de fecha)
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

        if (!$user->isAdmin() && $venta->cobrador_id !== $user->id) {
            abort(403, 'No tienes permiso para modificar una cuenta que no está en tu ruta.');
        }

        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $nuevoDia = $diasSemana[Carbon::parse($request->nueva_fecha)->dayOfWeek];

        $updateData = [
            'fecha_proximo_pago' => $request->nueva_fecha,
            'nota_cobranza'      => $request->nota_cobranza ?: 'Reagendado para el ' . Carbon::parse($request->nueva_fecha)->format('d/m/Y'),
            'updated_at'         => now(),
        ];

        if (Schema::hasColumn('ventas', 'fecha_final_pago')) {
            $updateData['fecha_final_pago'] = $request->nueva_fecha;
        }
        if (Schema::hasColumn('ventas', 'dia_semana')) {
            $updateData['dia_semana'] = $nuevoDia;
        }

        DB::table('ventas')->where('id', $ventaId)->update($updateData);

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
            'nombre'      => 'required|string|max:255',
            'cobrador_id' => 'required|exists:users,id',
            'fecha_ruta'  => 'required|date',
            'ventas'      => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $ruta = Ruta::create([
                'nombre'      => $request->nombre,
                'cobrador_id' => $request->cobrador_id,
                'fecha_ruta'  => $request->fecha_ruta,
                'status'      => 'Pendiente'
            ]);

            foreach ($request->ventas as $index => $ventaId) {
                $venta = Venta::find($ventaId);
                if ($venta) {
                    RutaDetalle::create([
                        'ruta_id'        => $ruta->id,
                        'venta_id'       => $venta->id,
                        'orden'          => $index + 1,
                        'monto_sugerido' => $venta->saldo_restante,
                        'status'         => 'Pendiente'
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
            'nombre'      => 'required|string|max:255',
            'cobrador_id' => 'required|exists:users,id',
            'fecha_ruta'  => 'required|date',
        ]);

        try {
            $ruta = Ruta::findOrFail($id);
            $ruta->update([
                'nombre'      => $request->nombre,
                'cobrador_id' => $request->cobrador_id,
                'fecha_ruta'  => $request->fecha_ruta,
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
     * VISTA TABLET / MÓVIL: Carga la ruta dinámica de HOY y ATRASADAS por separado para el cobrador
     */
    public function miRutaCobrador()
    {
        $user = auth()->user();
        $hoy = now()->toDateString();
        $fechaCobroV = $this->getFechaCobroSql('v');

        $baseQuery = DB::table('ventas as v')
            ->select(
                'v.id as venta_id',
                'v.folio',
                'v.venta_id_legacy',
                'v.saldo_restante',
                'v.pago_cuota',
                'v.frecuencia_dias',
                DB::raw("{$fechaCobroV} as fecha_proximo_pago"),
                'v.nota_cobranza',
                'c.id as cliente_id',
                DB::raw("COALESCE(c.nombre, 'SIN CLIENTE ASIGNADO') as cliente_nombre"),
                'c.telefono',
                'c.calle',
                'c.colonia',
                'c.ubicacion',
                'c.latitud',
                'c.longitud',
                DB::raw("DATEDIFF('{$hoy}', {$fechaCobroV}) as dias_atraso")
            )
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->where('v.cobrador_id', $user->id)
            ->where('v.status', 'Pendiente')
            ->where('v.saldo_restante', '>', 0)
            ->whereRaw("{$fechaCobroV} IS NOT NULL");

        // 1. Solo cobros programados exactamente para HOY
        $cuentasHoy = (clone $baseQuery)
            ->whereRaw("{$fechaCobroV} = ?", [$hoy])
            ->orderBy('c.colonia', 'asc')
            ->get();

        // 2. Cuentas vencidas de días anteriores (< HOY)
        $cuentasAtrasadas = (clone $baseQuery)
            ->whereRaw("{$fechaCobroV} < ?", [$hoy])
            ->orderBy('c.colonia', 'asc')
            ->orderByRaw("{$fechaCobroV} asc")
            ->get();

        return view('cobrador.mi_ruta', compact('cuentasHoy', 'cuentasAtrasadas', 'hoy'));
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

                $rawFecha       = $item['fecha_local'] ?? $item['fecha'] ?? null;
                $fechaHoraLocal = $rawFecha ? Carbon::parse($rawFecha)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
                $fechaSoloFecha = $rawFecha ? Carbon::parse($rawFecha)->format('Y-m-d') : now()->format('Y-m-d');

                if (empty($ventaId)) {
                    continue;
                }

                if (!empty($gpsCoords)) {
                    $ventaObj = Venta::find($ventaId);
                    if ($ventaObj && $ventaObj->cliente_id) {
                        Cliente::where('id', $ventaObj->cliente_id)->update([
                            'cliente_ubicacion' => $gpsCoords
                        ]);
                    }
                }

                if ($montoAbono > 0 && $statusItem === 'Cobrado') {
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
                        'a_npago'       => $totalAbonosPrevios + 1,
                        'a_cobrador'    => auth()->id(),
                        'a_tipopago'    => 'Efectivo',
                        'a_tipo'        => 'Abono',
                        'a_hora'        => $fechaHoraLocal,
                        'observaciones' => $observaciones,
                        'created_at'    => $fechaHoraLocal,
                        'updated_at'    => now(),
                    ]);

                    $venta = Venta::find($ventaId);
                    if ($venta) {
                        $venta->saldo_restante = max(0, $venta->saldo_restante - $montoAbono);
                        if ($venta->saldo_restante == 0) {
                            $venta->status = 'Pagado';
                            $venta->fecha_proximo_pago = null;
                        } else {
                            $dias = max(1, (int) ($venta->frecuencia_dias ?? 7));
                            $nuevaFecha = Carbon::parse($fechaSoloFecha)->addDays($dias)->format('Y-m-d');
                            $venta->fecha_proximo_pago = $nuevaFecha;
                            if (Schema::hasColumn('ventas', 'fecha_final_pago')) {
                                $venta->fecha_final_pago = $nuevaFecha;
                            }
                        }
                        $venta->save();
                    }
                }

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

    /**
     * Reagendar múltiples cuentas atrasadas de un solo golpe (o pasarlas a Cartera Vencida)
     */
    public function reagendarMasivo(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Solo el administrador puede reorganizar cuentas de forma masiva.');
        }

        $request->validate([
            'venta_ids'     => 'required|array|min:1',
            'accion'        => 'required|in:reagendar,cartera_vencida',
            'nueva_fecha'   => 'required_if:accion,reagendar|nullable|date|after_or_equal:today',
            'nota_cobranza' => 'nullable|string|max:255',
        ]);

        $ids = $request->venta_ids;

        if ($request->accion === 'cartera_vencida') {
            DB::table('ventas')
                ->whereIn('id', $ids)
                ->update([
                    'status'        => 'Vencido',
                    'nota_cobranza' => $request->nota_cobranza ?: 'Movido a cartera vencida el ' . now()->format('d/m/Y'),
                    'updated_at'    => now(),
                ]);

            return back()->with('success', count($ids) . ' cuentas movidas a Cartera Vencida (fuera de ruta diaria).');
        }

        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $nuevoDia = $diasSemana[Carbon::parse($request->nueva_fecha)->dayOfWeek];

        $updateData = [
            'fecha_proximo_pago' => $request->nueva_fecha,
            'nota_cobranza'      => $request->nota_cobranza ?: 'Reagendado masivamente para el ' . Carbon::parse($request->nueva_fecha)->format('d/m/Y'),
            'updated_at'         => now(),
        ];

        if (Schema::hasColumn('ventas', 'fecha_final_pago')) {
            $updateData['fecha_final_pago'] = $request->nueva_fecha;
        }
        if (Schema::hasColumn('ventas', 'dia_semana')) {
            $updateData['dia_semana'] = $nuevoDia;
        }

        DB::table('ventas')->whereIn('id', $ids)->update($updateData);

        return back()->with('success', count($ids) . ' cuentas reagendadas para el ' . Carbon::parse($request->nueva_fecha)->format('d/m/Y') . '.');
    }
}