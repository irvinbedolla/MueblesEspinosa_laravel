<?php 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exports\ReporteGenericoExport; // Si usas Laravel-Excel / Maatwebsite
use Maatwebsite\Excel\Facades\Excel;

class ReporteController extends Controller
{
    /**
     * Dashboard Principal de Reportes (Imagen 1)
     */
    public function index()
    {
        $cobradores = DB::table('users')->where('is_active', 1)->get();
        $clientes   = DB::table('clientes')->orderBy('nombre', 'asc')->get();

        return view('reportes.index', compact('cobradores', 'clientes'));
    }

    /**
     * Búsqueda dinámica AJAX de Clientes para el Reporte de Clientes (Imagen 3)
     */
    public function buscarClienteAjax(Request $request)
    {
        $q = trim($request->input('q', ''));
        $clientes = DB::table('clientes')
            ->where('nombre', 'LIKE', "%{$q}%")
            ->orWhere('telefono', 'LIKE', "%{$q}%")
            ->limit(10)
            ->get(['id', 'nombre', 'telefono', 'ciudad']);

        return response()->json($clientes);
    }

    /**
     * Generador unificado de consultas según la pestaña activa
     */
    public function obtenerDatosReporte(Request $request)
    {
        $tipo     = $request->input('tipo', 'ventas'); // usuario, cliente, ventas, gastos, abonos
        $fecha1   = $request->input('fecha1', date('Y-m-01'));
        $fecha2   = $request->input('fecha2', date('Y-m-d'));
        $targetId = $request->input('target_id'); // user_id o cliente_id

        $resultados = collect();
        $totalSum   = 0;

        switch ($tipo) {
            case 'usuario': // Imagen 2
                $query = DB::table('ventas as v')
                    ->select(
                        'v.id', 'v.venta_id_legacy', 'v.folio', 'v.created_at', 'v.total', 'v.saldo_restante',
                        'c.nombre as cliente_nombre', 'u.username as vendedor_nombre'
                    )
                    ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
                    ->leftJoin('users as u', 'v.vendedor_id', '=', 'u.id')
                    ->whereBetween(DB::raw('DATE(v.created_at)'), [$fecha1, $fecha2]);

                if (!empty($targetId)) {
                    $query->where('v.vendedor_id', $targetId)->orWhere('v.cobrador_id', $targetId);
                }

                $resultados = $query->get();
                $totalSum   = $resultados->sum('total');
                break;

            case 'cliente': // Imagen 4
                $query = DB::table('ventas as v')
                    ->select(
                        'v.id', 'v.venta_id_legacy', 'v.folio', 'v.created_at', 'v.total', 'v.saldo_restante',
                        'c.nombre as cliente_nombre', 'u.username as vendedor_nombre'
                    )
                    ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
                    ->leftJoin('users as u', 'v.vendedor_id', '=', 'u.id')
                    ->whereBetween(DB::raw('DATE(v.created_at)'), [$fecha1, $fecha2]);

                if (!empty($targetId)) {
                    $query->where('v.cliente_id', $targetId);
                }

                $resultados = $query->get();
                $totalSum   = $resultados->sum('total');
                break;

            case 'ventas': // Imagen 5
                $resultados = DB::table('ventas as v')
                    ->select(
                        'v.id', 'v.venta_id_legacy', 'v.folio', 'v.created_at', 'v.total', 'v.saldo_restante',
                        'c.nombre as cliente_nombre', 'u.username as vendedor_nombre'
                    )
                    ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
                    ->leftJoin('users as u', 'v.vendedor_id', '=', 'u.id')
                    ->whereBetween(DB::raw('DATE(v.created_at)'), [$fecha1, $fecha2])
                    ->get();

                $totalSum = $resultados->sum('total');
                break;

            case 'gastos': // Imagen 6 y 7
                $resultados = DB::table('gastos')
                    ->whereBetween('fecha', [$fecha1, $fecha2])
                    ->get();

                $totalSum = $resultados->sum('monto');
                break;

            case 'abonos': // Imagen 8
                $resultados = DB::table('abonos as a')
                    ->select(
                        'a.*', 'c.nombre as cliente_nombre',
                        DB::raw("COALESCE(u.username, a.a_cobrador) as nombre_cobrador")
                    )
                    ->leftJoin('ventas as v', 'a.venta_id', '=', 'v.id')
                    ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
                    ->leftJoin('users as u', 'a.a_cobrador', '=', 'u.id')
                    ->whereBetween('a.a_fecha', [$fecha1, $fecha2])
                    ->get();

                $totalSum = $resultados->sum('a_pago');
                break;
        }

        return response()->json([
            'data'  => $resultados,
            'total' => number_format($totalSum, 2)
        ]);
    }

    /**
     * Exportar a PDF (Plantilla imprimible con formato de vista previa)
     */
    public function exportarPdf(Request $request)
    {
        $tipo     = $request->input('tipo', 'ventas');
        $fecha1   = $request->input('fecha1', date('Y-m-01'));
        $fecha2   = $request->input('fecha2', date('Y-m-d'));
        $targetId = $request->input('target_id');

        // Lógica de obtención idéntica a obtenerDatosReporte...
        $datos = $this->obtenerEstructuraReporte($tipo, $fecha1, $fecha2, $targetId);

        return view('reportes.pdf_template', $datos);
    }
}