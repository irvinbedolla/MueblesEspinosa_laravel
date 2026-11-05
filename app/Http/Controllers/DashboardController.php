<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $data = [];

        Carbon::setLocale('es');
        $inicioMes    = Carbon::now()->startOfMonth()->toDateString();
        $finMes       = Carbon::now()->endOfMonth()->toDateString();
        $hoy          = Carbon::now()->toDateString();
        $diaSemanaHoy = strtolower(Carbon::now()->isoFormat('dddd'));

        // ==========================================
        // 1. DASHBOARD PARA ADMINISTRADOR
        // ==========================================
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            
            // Ventas acumuladas en el mes (usando fecha_venta)
            $ventasQuery = DB::table('ventas')
                ->whereBetween('fecha_venta', [$inicioMes, $finMes]);
            
            $cantVentas  = $ventasQuery->count();
            $montoVentas = $ventasQuery->sum('total');

            // Abonos/Pagos recuperados en el mes
            $cantAbonos  = 0;
            $montoAbonos = 0;
            if (DB::getSchemaBuilder()->hasTable('prestamo_abonos')) {
                $abonosQuery = DB::table('prestamo_abonos')->whereBetween('fecha', [$inicioMes, $finMes]);
                $cantAbonos  = $abonosQuery->count();
                $montoAbonos = $abonosQuery->sum('monto');
            }

            // Gráfica: Ventas diarias de los últimos 30 días
            $labelsChart = [];
            $ventasChart = [];

            for ($i = 29; $i >= 0; $i--) {
                $fechaLoop = Carbon::now()->subDays($i)->format('Y-m-d');
                $labelsChart[] = Carbon::now()->subDays($i)->format('d/m');

                $sumDia = DB::table('ventas')
                    ->whereDate('fecha_venta', $fechaLoop)
                    ->sum('total');

                $ventasChart[] = (float) $sumDia;
            }

            // Identificar el nombre exacto de la tabla de detalles de rutas
            $tablaDetalles = DB::getSchemaBuilder()->hasTable('ruta_detalles') ? 'ruta_detalles' : 'ruta_clientes';

            // Consulta exacta relacionando 'rutas' y 'ruta_detalles'
            $rutasHoy = DB::table('rutas as r')
                ->select(
                    'r.id',
                    'r.nombre as nombre_ruta',
                    'r.status as estatus',
                    'u.username as cobrador_nombre',
                    DB::raw("COUNT(rd.id) as total_clientes")
                )
                ->leftJoin('users as u', 'r.cobrador_id', '=', 'u.id')
                ->leftJoin("{$tablaDetalles} as rd", 'r.id', '=', 'rd.ruta_id')
                ->whereDate('r.fecha_ruta', $hoy)
                ->groupBy('r.id', 'r.nombre', 'r.status', 'u.username')
                ->orderBy('u.username', 'asc')
                ->get();

            // Si hoy no hay rutas programadas en la tabla 'rutas', consulta las ventas asignadas por cobrador
            if ($rutasHoy->isEmpty()) {
                $rutasHoy = DB::table('ventas as v')
                    ->select(
                        'u.username as cobrador_nombre',
                        DB::raw("COUNT(v.id) as total_clientes"),
                        DB::raw("'Pendiente' as estatus"),
                        DB::raw("CONCAT('Ruta ', UPPER(SUBSTRING('$diaSemanaHoy', 1, 1)), SUBSTRING('$diaSemanaHoy', 2)) as nombre_ruta")
                    )
                    ->leftJoin('users as u', 'v.cobrador_id', '=', 'u.id')
                    ->whereNotNull('v.cobrador_id')
                    ->where('v.saldo_restante', '>', 0)
                    ->whereNotIn('v.status', ['Pagado', 'Eliminar'])
                    ->groupBy('u.id', 'u.username')
                    ->orderBy('total_clientes', 'desc')
                    ->get();
            }

            $data = [
                'ventas_mes_monto' => '$' . number_format($montoVentas, 2),
                'ventas_mes_cant'  => $cantVentas,
                'abonos_mes_monto' => '$' . number_format($montoAbonos, 2),
                'abonos_mes_cant'  => $cantAbonos,
                'chart_labels'     => $labelsChart,
                'chart_ventas'     => $ventasChart,
                'rutas_hoy'        => $rutasHoy
            ];
        }

        // ==========================================
        // 2. DASHBOARD PARA VENDEDOR
        // ==========================================
        elseif (method_exists($user, 'isVendedor') && $user->isVendedor()) {
            $userRef = $user->id;

            $ventasVendedor = DB::table('ventas')
                ->where('vendedor_id', $userRef)
                ->whereBetween('fecha_venta', [$inicioMes, $finMes]);

            $cantPropias  = $ventasVendedor->count();
            $montoPropias = $ventasVendedor->sum('total');

            $labelsSemanas = ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'];
            $rendimiento   = [];

            for ($w = 1; $w <= 4; $w++) {
                $inicioW = Carbon::now()->startOfMonth()->addWeeks($w - 1)->toDateString();
                $finW    = Carbon::now()->startOfMonth()->addWeeks($w)->subDay()->toDateString();

                $countW = DB::table('ventas')
                    ->where('vendedor_id', $userRef)
                    ->whereBetween('fecha_venta', [$inicioW, $finW])
                    ->count();

                $rendimiento[] = $countW;
            }

            $data = [
                'ventas_propias_cant'  => $cantPropias,
                'ventas_propias_monto' => '$' . number_format($montoPropias, 2),
                'chart_labels'         => $labelsSemanas,
                'chart_rendimiento'    => $rendimiento
            ];
        }

        // ==========================================
        // 3. DASHBOARD PARA COBRADOR
        // ==========================================
        else {
            $userRef = $user->id;

            // 1. Validar dinámicamente cómo se llama el campo de dirección en la tabla 'clientes'
            $hasDireccion = DB::getSchemaBuilder()->hasColumn('clientes', 'direccion');
            $hasDomicilio = DB::getSchemaBuilder()->hasColumn('clientes', 'domicilio');
            $hasCalle     = DB::getSchemaBuilder()->hasColumn('clientes', 'calle');

            $columnaDireccion = "'Sin Dirección' as direccion";
            if ($hasDireccion) {
                $columnaDireccion = "c.direccion";
            } elseif ($hasDomicilio) {
                $columnaDireccion = "c.domicilio as direccion";
            } elseif ($hasCalle) {
                $columnaDireccion = "c.calle as direccion";
            }

            // 2. Consulta adaptada para el cobrador activo
            $queryCobros = DB::table('ventas as v')
                ->select(
                    'v.id', 
                    'c.nombre as cliente_nombre', 
                    DB::raw($columnaDireccion), 
                    'v.status'
                )
                ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
                ->where('v.cobrador_id', $userRef)
                ->where('v.saldo_restante', '>', 0);

            $misClientes = $queryCobros->get();

            $cobradosHoy = $misClientes->where('status', 'Pagado')->count();
            $pendientes  = $misClientes->where('status', '!=', 'Pagado')->count();

            $data = [
                'cobros_hoy_cant'    => $misClientes->count(),
                'chart_cobranza_hoy' => [$cobradosHoy, $pendientes],
                'mi_ruta_hoy'        => $misClientes
            ];
        }

        return view('dashboard', compact('user', 'data'));
    }
}