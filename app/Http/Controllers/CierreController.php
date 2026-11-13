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
}