<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf; // Asegúrate de tener barryvdh/laravel-dompdf o FPDF

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

        // Nombre de usuario encargador
        $nombreVendedor = $user->username ?? 'Desconocido';

        // Cantidad de ventas pendientes dentro del rango
        $totalPendientes = DB::table('ventas')
            ->where('status', 'Pendiente')
            ->whereBetween('fecha_final_pago', [$fecha1, $fecha2])
            ->count();

        // Consulta de abonos clasificada
        $query = DB::table('abonos as a')
            ->select(
                'a.*',
                'c.nombre as cliente_nombre',
                'v.fecha_venta',
                'v.total as venta_total',
                'v.saldo_restante as venta_restante',
                DB::raw("COALESCE(u.username, a.a_cobrador, 'N/A') as nombre_cobrador"),
                DB::raw("IF(a.a_fecha = v.fecha_venta, 'Enganche', 'Abono') as tipo_movimiento")
            )
            ->leftJoin('ventas as v', function($join) {
                $join->on('a.venta_id', '=', 'v.id')
                     ->orOn('a.a_venta', '=', 'v.venta_id_legacy');
            })
            ->leftJoin('clientes as c', function($join) {
                $join->on('v.cliente_id', '=', 'c.id')
                     ->orOn('v.cliente_id', '=', 'c.cliente_id');
            })
            ->leftJoin('users as u', function($join) {
                $join->on('a.a_cobrador', '=', 'u.id')
                     ->orOn('a.a_cobrador', '=', 'u.user_id_legacy')
                     ->orOn('a.a_cobrador', '=', 'u.username');
            })
            ->whereBetween('a.a_fecha', [$fecha1, $fecha2]);

        // Filtrar por cobrador si se especificó
        if (!empty($cobradorId)) {
            $query->where(function($q) use ($cobradorId) {
                $q->where('a.a_cobrador', $cobradorId)
                  ->orWhere('u.id', $cobradorId);
            });
        } elseif ($tipoSeccion != 0) {
            // Si es usuario regular, solo sus cobros
            $query->where(function($q) use ($idUsuario, $user) {
                $q->where('a.a_cobrador', $idUsuario)
                  ->orWhere('a.a_cobrador', $user->username);
            });
        }

        $abonos = $query->orderBy('a.created_at', 'asc')->get();

        // Totales y acumuladores
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

        // Retornar vista HTML formateada para impresión o PDF
        return view('cierres.pdf_template', $datos);
    }
}