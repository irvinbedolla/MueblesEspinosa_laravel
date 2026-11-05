<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GastoController extends Controller
{
    public function index()
    {
        $usuarioLogueado = auth()->user();
        $cobradores = DB::table('users')->where('is_active', 1)->get();

        return view('gastos.index', compact('usuarioLogueado', 'cobradores'));
    }

    /**
     * Guardar un nuevo Gasto Operativo
     */
    public function storeGasto(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|string|max:255',
            'costo'       => 'required|numeric|min:0.01',
            'fecha'       => 'required|date'
        ]);

        try {
            $costoVal = floatval($request->costo);
            $userRef  = auth()->id() ?? auth()->user()->user_id_legacy ?? 'Sistema';

            DB::table('gastos')->insert([
                'id'          => (string) Str::uuid(),
                'descripcion' => $request->descripcion,
                'costo'       => $costoVal,
                'monto'       => $costoVal,
                'fecha'       => $request->fecha,
                'tipo'        => 'Gasto',
                'user_id'     => $userRef,
                'created_at'  => now(),
                'updated_at'  => now()
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'Gasto registrado correctamente.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Error en base de datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consultar Gastos por rango de fechas (Soporta registros Legacy y Nuevos)
     */
    public function consultarGastosAjax(Request $request)
    {
        // Si no envían fecha1, tomamos por defecto 2020-01-01 para incluir el histórico migrado
        $fecha1 = $request->input('fecha1', '2020-01-01');
        $fecha2 = $request->input('fecha2', date('Y-m-d'));

        $gastos = DB::table('gastos')
            ->select(
                'id',
                'gasto_id_legacy',
                'descripcion',
                DB::raw("COALESCE(costo, monto, 0) as costo_real"),
                'fecha',
                'created_at'
            )
            ->where(function($q) {
                $q->where('tipo', 'Gasto')
                ->orWhereNull('tipo')
                ->orWhere('tipo', '');
            })
            ->whereBetween('fecha', [$fecha1, $fecha2])
            ->orderBy('fecha', 'desc')
            ->get();

        // Mapeo dinámico del folio a mostrar (#3, #4...)
        $gastosFormatted = $gastos->map(function($gasto) {
            $gasto->folio_mostrar = $gasto->gasto_id_legacy ?? substr($gasto->id, 0, 8);
            return $gasto;
        });

        $total = $gastos->sum('costo_real');

        return response()->json([
            'data'  => $gastosFormatted,
            'total' => number_format($total, 2)
        ]);
    }

    /**
     * Eliminar Gasto o Préstamo
     */
    public function eliminarRegistro(Request $request)
    {
        $id = $request->id;
        $tipo = $request->tipo;

        if ($tipo === 'gasto') {
            DB::table('gastos')
                ->where('id', $id)
                ->orWhere('gasto_id_legacy', $id)
                ->delete();
        } else {
            DB::table('prestamo_abonos')
                ->where('prestamo_id', $id)
                ->orWhere('prestamo_id_legacy', $id)
                ->delete();

            DB::table('prestamos')
                ->where('id', $id)
                ->orWhere('prestamo_id_legacy', $id)
                ->delete();
        }

        return response()->json(['success' => true, 'message' => 'Registro eliminado correctamente.']);
    }

    /**
     * Guardar un nuevo Préstamo asignado a un usuario
     */
    public function storePrestamo(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|string|max:255',
            'monto'       => 'required|numeric|min:0.01',
            'fecha'       => 'required|date',
            'user_id'     => 'required'
        ]);

        try {
            $montoVal = floatval($request->monto);

            DB::table('prestamos')->insert([
                'id'             => (string) Str::uuid(),
                'descripcion'    => $request->descripcion,
                'monto_total'    => $montoVal,
                'costo'          => $montoVal,
                'saldo_restante' => $montoVal,
                'fecha'          => $request->fecha,
                'user_id'        => $request->user_id,
                'status'         => 'Activo',
                'created_at'     => now(),
                'updated_at'     => now()
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'Préstamo registrado exitosamente.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Error al guardar préstamo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener Préstamos por sub-pestaña (Activos, Historial Pagados o Mis Préstamos)
     */
    public function obtenerPrestamosAjax(Request $request)
    {
        try {
            $seccion = $request->input('seccion', 'activos'); // activos, pagados, mis_prestamos

            $query = DB::table('prestamos as p')
                ->select(
                    'p.id',
                    'p.prestamo_id_legacy',
                    'p.descripcion',
                    'p.fecha',
                    'p.saldo_restante',
                    'p.user_id',
                    'p.status',
                    DB::raw("COALESCE(p.costo, p.monto_total, 0) as costo_real"),
                    DB::raw("COALESCE(u.username, 'Cobrador') as nombre_usuario") // Corregido: solo usa u.username
                )
                ->leftJoin('users as u', 'p.user_id', '=', 'u.id');

            if ($seccion === 'activos') {
                $query->where('p.saldo_restante', '>', 0);
            } elseif ($seccion === 'pagados') {
                $query->where('p.saldo_restante', '<=', 0);
            } elseif ($seccion === 'mis_prestamos') {
                $query->where('p.user_id', auth()->id());
            }

            $prestamos = $query->orderBy('p.fecha', 'desc')->get();

            // Mapear folio visible (#32A08F23 o #11)
            $prestamosFormatted = $prestamos->map(function($item) {
                $item->folio_mostrar = $item->prestamo_id_legacy ?? strtoupper(substr($item->id, 0, 8));
                return $item;
            });

            return response()->json([
                'success' => true,
                'data'    => $prestamosFormatted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error SQL: ' . $e->getMessage(),
                'data'    => []
            ], 500);
        }
    }

    /**
     * Registrar un Abono a un Préstamo Activo (Soporta UUID e ID Legacy)
     */
    public function abonarPrestamo(Request $request)
    {
        $request->validate([
            'prestamo_id' => 'required',
            'monto_abono' => 'required|numeric|min:0.01',
            'fecha_abono' => 'required|date'
        ]);

        // Buscar por id (UUID) o por prestamo_id_legacy
        $prestamo = DB::table('prestamos')
            ->where('id', $request->prestamo_id)
            ->orWhere('prestamo_id_legacy', $request->prestamo_id)
            ->first();

        if (!$prestamo) {
            return response()->json(['success' => false, 'message' => 'Préstamo no encontrado.'], 404);
        }

        $montoAbono = floatval($request->monto_abono);
        $nuevoSaldo = max(0, floatval($prestamo->saldo_restante) - $montoAbono);
        $nuevoStatus = ($nuevoSaldo <= 0) ? 'Pagado' : 'Activo';

        DB::beginTransaction();
        try {
            $userRef = auth()->id() ?? 'Sistema';

            // Insertar en la tabla de abonos relacionando ambas llaves
            DB::table('prestamo_abonos')->insert([
                'id'                 => (string) Str::uuid(),
                'prestamo_id'        => $prestamo->id,
                'prestamo_id_legacy' => $prestamo->prestamo_id_legacy,
                'monto'              => $montoAbono,
                'fecha'              => $request->fecha_abono,
                'user_id'            => $userRef,
                'created_at'         => now(),
                'updated_at'         => now()
            ]);

            // Actualizar el saldo restante del préstamo
            DB::table('prestamos')
                ->where('id', $prestamo->id)
                ->update([
                    'saldo_restante' => $nuevoSaldo,
                    'status'         => $nuevoStatus,
                    'updated_at'     => now()
                ]);

            DB::commit();
            return response()->json([
                'success' => true, 
                'message' => 'Abono registrado correctamente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 
                'message' => 'Error al guardar el abono: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Historial de Abonos de un Préstamo (Modal)
     */
    public function historialAbonosPrestamo($id)
    {
        try {
            $abonos = DB::table('prestamo_abonos as pa')
                ->select(
                    'pa.id',
                    'pa.monto',
                    'pa.fecha',
                    DB::raw("COALESCE(u.username, 'Sistema') as cobrador")
                )
                ->leftJoin('users as u', 'pa.user_id', '=', 'u.id')
                ->where(function($q) use ($id) {
                    $q->where('pa.prestamo_id', $id)
                    ->orWhere('pa.prestamo_id_legacy', $id);
                })
                ->orderBy('pa.fecha', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $abonos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar historial: ' . $e->getMessage(),
                'data'    => []
            ], 500);
        }
    }

}