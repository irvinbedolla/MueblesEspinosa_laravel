<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CierreController extends Controller
{
    public function index()
    {
        $cobradores = DB::table('users')
            ->select('id', 'user_id_legacy', 'username')
            ->where('is_active', 1)
            ->get();

        return view('cierres.index', compact('cobradores'));
    }

    public function generarPdf(Request $request)
    {
        $fecha1 = $request->input('fecha1', date('Y-m-d'));
        $fecha2 = $request->input('fecha2', date('Y-m-d'));
        $cobradorId = $request->input('cobrador_id', null);

        $user = auth()->user();
        $tipoSeccion = $user->isAdmin() ? 0 : 1;
        $idUsuario = $user->id;
        $nombreVendedor = $user->username ?? 'Desconocido';

        $totalPendientes = DB::table('ventas')
            ->where('status', 'Pendiente')
            ->whereBetween('fecha_final_pago', [$fecha1, $fecha2])
            ->count();

        // Consulta limpia 1 a 1 sin colisiones UUID vs INT
        $query = DB::table('abonos as a')
            ->select(
                'a.*',
                DB::raw("COALESCE(v.venta_id_legacy, v.folio) as venta_numero"),
                'c.nombre as cliente_nombre',
                'v.fecha_venta',
                'v.total as venta_total',
                'v.saldo_restante as venta_restante',
                DB::raw("COALESCE(u.username, 'N/A') as nombre_cobrador"),
                DB::raw("IF(DATE(a.a_fecha) = v.fecha_venta, 'Enganche', 'Abono') as tipo_movimiento")
            )
            ->leftJoin('ventas as v', 'a.venta_id', '=', 'v.id')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('users as u', 'a.a_cobrador', '=', 'u.id')
            ->whereDate('a.a_fecha', '>=', $fecha1)
            ->whereDate('a.a_fecha', '<=', $fecha2);

        if (!empty($cobradorId)) {
            $query->where('a.a_cobrador', $cobradorId);
        } elseif ($tipoSeccion != 0) {
            $query->where('a.a_cobrador', $idUsuario);
        }

        $abonos = $query->orderBy('a.a_fecha', 'asc')
                        ->orderBy('a.created_at', 'asc')
                        ->get();

        $totalEfectivo = 0;
        $totalTarjeta = 0;
        $totalEnganches = 0;
        $totalAbonos = 0;
        $cantEnganches = 0;
        $cantAbonos = 0;
        $totalGeneral = 0;

        foreach ($abonos as $item) {
            $monto = floatval($item->a_pago);
            $totalGeneral += $monto;

            $tpRaw = strtolower(trim($item->a_tipopago ?? 'efectivo'));
            if ($tpRaw == 'efectivo') {
                $totalEfectivo += $monto;
                $item->metodo_imprimir = 'Efectivo';
            } else {
                $totalTarjeta += $monto;
                $item->metodo_imprimir = ($tpRaw == 'tdebito') ? 'T. Débito' : 'T. Crédito';
            }

            if ($item->tipo_movimiento == 'Enganche') {
                $totalEnganches += $monto;
                $cantEnganches++;
            } else {
                $totalAbonos += $monto;
                $cantAbonos++;
            }

            $item->saldo_restante = floatval($item->venta_restante ?? 0);
            $item->saldo_anterior = $item->saldo_restante + $monto;
        }

        $datos = [
            'fecha1'          => Carbon::parse($fecha1)->format('d/m/Y'),
            'fecha2'          => Carbon::parse($fecha2)->format('d/m/Y'),
            'horaActual'      => now()->format('H:i:s'),
            'nombreVendedor'  => $nombreVendedor,
            'totalPendientes' => $totalPendientes,
            'abonos'          => $abonos,
            'totalEfectivo'   => $totalEfectivo,
            'totalTarjeta'    => $totalTarjeta,
            'totalEnganches'  => $totalEnganches,
            'totalAbonos'     => $totalAbonos,
            'cantEnganches'   => $cantEnganches,
            'cantAbonos'      => $cantAbonos,
            'totalGeneral'    => $totalGeneral
        ];

        return view('cierres.pdf_template', $datos);
    }

    public function generarPdfEjecutivo(Request $request)
    {
        $fecha1     = $request->input('fecha1', date('Y-m-d'));
        $fecha2     = $request->input('fecha2', date('Y-m-d'));
        $cobradorId = $request->input('cobrador_id', null);

        $user           = auth()->user();
        $tipoSeccion    = $user->isAdmin() ? 0 : 1;
        $idUsuario      = $user->id;
        $nombreVendedor = $user->username ?? 'Desconocido';

        // 1. Consulta detallada de abonos en el periodo
        $query = DB::table('abonos as a')
            ->select(
                'a.*',
                DB::raw("COALESCE(v.venta_id_legacy, v.folio) as venta_numero"),
                'c.nombre as cliente_nombre',
                'v.fecha_venta',
                'v.total as venta_total',
                'v.saldo_restante as venta_restante',
                DB::raw("COALESCE(u.username, 'Sin Usuario') as nombre_cobrador"),
                DB::raw("IF(DATE(a.a_fecha) = DATE(v.fecha_venta), 'Enganche', COALESCE(NULLIF(a.a_tipo, ''), 'Abono')) as tipo_movimiento")
            )
            ->leftJoin('ventas as v', 'a.venta_id', '=', 'v.id')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('users as u', 'a.a_cobrador', '=', 'u.id')
            ->whereDate('a.a_fecha', '>=', $fecha1)
            ->whereDate('a.a_fecha', '<=', $fecha2);

        if (!empty($cobradorId)) {
            $query->where('a.a_cobrador', $cobradorId);
        } elseif ($tipoSeccion != 0) {
            $query->where('a.a_cobrador', $idUsuario);
        }

        $abonos = $query->orderBy('u.username', 'asc')
                        ->orderBy('a.a_fecha', 'asc')
                        ->orderBy('a.created_at', 'asc')
                        ->get();

        // 2. Consulta de ventas pendientes programadas en el periodo
        $ventasPendientesQuery = DB::table('ventas as v')
            ->select(
                'v.id',
                'v.cobrador_id',
                'v.pago_cuota',
                'v.saldo_restante',
                DB::raw("COALESCE(u.username, 'Sin Asignar') as nombre_cobrador")
            )
            ->leftJoin('users as u', 'v.cobrador_id', '=', 'u.id')
            ->where('v.status', 'Pendiente')
            ->where('v.saldo_restante', '>', 0)
            ->whereBetween(DB::raw('COALESCE(v.fecha_proximo_pago, v.fecha_final_pago)'), [$fecha1, $fecha2]);

        if (!empty($cobradorId)) {
            $ventasPendientesQuery->where('v.cobrador_id', $cobradorId);
        } elseif ($tipoSeccion != 0) {
            $ventasPendientesQuery->where('v.cobrador_id', $idUsuario);
        }

        $ventasPendientesLista = $ventasPendientesQuery->get();
        $totalPendientes       = $ventasPendientesLista->count();
        $montoPendienteCuotas  = $ventasPendientesLista->sum('pago_cuota');
        $saldoCarteraPendiente = $ventasPendientesLista->sum('saldo_restante');

        // 3. Acumuladores globales y desglose por usuario
        $totalEfectivo      = 0;
        $totalTransferencia = 0;
        $totalTarjeta       = 0;
        $totalEnganches     = 0;
        $totalAbonos        = 0;
        $totalLiquidaciones = 0;
        $cantEnganches      = 0;
        $cantAbonos         = 0;
        $cantLiquidaciones  = 0;
        $totalGeneral       = 0;

        $resumenUsuarios = [];

        foreach ($ventasPendientesLista as $vp) {
            $keyUser = $vp->cobrador_id ?: 'sin_asignar';
            if (!isset($resumenUsuarios[$keyUser])) {
                $resumenUsuarios[$keyUser] = [
                    'usuario'            => strtoupper($vp->nombre_cobrador),
                    'cant_cobros'        => 0,
                    'efectivo'           => 0,
                    'transferencia'      => 0,
                    'tarjeta'            => 0,
                    'total_cobrado'      => 0,
                    'cuentas_pendientes' => 0,
                    'monto_pendiente'    => 0,
                    'saldo_cartera'      => 0,
                ];
            }
            $resumenUsuarios[$keyUser]['cuentas_pendientes']++;
            $resumenUsuarios[$keyUser]['monto_pendiente'] += floatval($vp->pago_cuota ?? 0);
            $resumenUsuarios[$keyUser]['saldo_cartera']   += floatval($vp->saldo_restante ?? 0);
        }

        foreach ($abonos as $item) {
            $monto = floatval($item->a_pago);
            $totalGeneral += $monto;

            $keyUser = $item->a_cobrador ?: 'sin_asignar';
            if (!isset($resumenUsuarios[$keyUser])) {
                $resumenUsuarios[$keyUser] = [
                    'usuario'            => strtoupper($item->nombre_cobrador),
                    'cant_cobros'        => 0,
                    'efectivo'           => 0,
                    'transferencia'      => 0,
                    'tarjeta'            => 0,
                    'total_cobrado'      => 0,
                    'cuentas_pendientes' => 0,
                    'monto_pendiente'    => 0,
                    'saldo_cartera'      => 0,
                ];
            }

            $resumenUsuarios[$keyUser]['cant_cobros']++;
            $resumenUsuarios[$keyUser]['total_cobrado'] += $monto;

            $tpRaw = strtolower(trim($item->a_tipopago ?? 'efectivo'));
            if ($tpRaw === 'efectivo') {
                $totalEfectivo += $monto;
                $resumenUsuarios[$keyUser]['efectivo'] += $monto;
                $item->metodo_imprimir = 'Efectivo';
            } elseif (in_array($tpRaw, ['transferencia', 'deposito', 'depósito'])) {
                $totalTransferencia += $monto;
                $resumenUsuarios[$keyUser]['transferencia'] += $monto;
                $item->metodo_imprimir = ($tpRaw === 'transferencia') ? 'Transferencia' : 'Depósito';
            } else {
                $totalTarjeta += $monto;
                $resumenUsuarios[$keyUser]['tarjeta'] += $monto;
                $item->metodo_imprimir = ($tpRaw === 'tdebito') ? 'T. Débito' : 'T. Crédito';
            }

            $tipoMov = strtolower(trim($item->tipo_movimiento));
            if ($tipoMov === 'enganche') {
                $totalEnganches += $monto;
                $cantEnganches++;
                $item->tipo_imprimir = 'Enganche';
            } elseif ($tipoMov === 'liquidado') {
                $totalLiquidaciones += $monto;
                $cantLiquidaciones++;
                $item->tipo_imprimir = 'Liquidación';
            } else {
                $totalAbonos += $monto;
                $cantAbonos++;
                $item->tipo_imprimir = 'Abono';
            }

            $item->saldo_restante = floatval($item->venta_restante ?? 0);
            $item->saldo_anterior = $item->saldo_restante + $monto;
        }

        uasort($resumenUsuarios, function ($a, $b) {
            return $b['total_cobrado'] <=> $a['total_cobrado'];
        });

        $cobradorFiltroNombre = 'Todos los Usuarios';
        if (!empty($cobradorId)) {
            $cobUser = DB::table('users')->where('id', $cobradorId)->value('username');
            $cobradorFiltroNombre = $cobUser ? strtoupper($cobUser) : 'Filtrado';
        } elseif ($tipoSeccion != 0) {
            $cobradorFiltroNombre = strtoupper($nombreVendedor);
        }

        return view('cierres.pdf_ejecutivo', [
            'fecha1'                => Carbon::parse($fecha1)->format('d/m/Y'),
            'fecha2'                => Carbon::parse($fecha2)->format('d/m/Y'),
            'horaActual'            => now()->format('H:i:s'),
            'nombreVendedor'        => strtoupper($nombreVendedor),
            'cobradorFiltroNombre'  => $cobradorFiltroNombre,
            'totalPendientes'       => $totalPendientes,
            'montoPendienteCuotas'  => $montoPendienteCuotas,
            'saldoCarteraPendiente' => $saldoCarteraPendiente,
            'abonos'                => $abonos,
            'resumenUsuarios'       => $resumenUsuarios,
            'totalEfectivo'         => $totalEfectivo,
            'totalTransferencia'    => $totalTransferencia,
            'totalTarjeta'          => $totalTarjeta,
            'totalEnganches'        => $totalEnganches,
            'totalAbonos'           => $totalAbonos,
            'totalLiquidaciones'    => $totalLiquidaciones,
            'cantEnganches'         => $cantEnganches,
            'cantAbonos'            => $cantAbonos,
            'cantLiquidaciones'     => $cantLiquidaciones,
            'totalGeneral'          => $totalGeneral,
        ]);
    }
}