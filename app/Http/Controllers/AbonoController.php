<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class AbonoController extends Controller
{
    /**
     * Vista principal de Abonos
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        // $tipoSeccion = 0 para Administrador (acceso total), 1 para Cobrador (solo asignadas)
        $tipoSeccion = $user->isAdmin() ? 0 : 1;

        $tipoAbono      = $request->input('tipo', 1);
        $dia            = $request->input('Dia', '');
        $filtroCobrador = $request->input('cobrador', 0);

        // Consulta de ventas pendientes de cobro
        $query = DB::table('ventas as v')
            ->select(
                'v.id',
                'v.folio',
                'v.venta_id_legacy',
                'v.fecha_venta',
                'v.fecha_final_pago',
                'v.saldo_restante',
                'v.total',
                'v.num_pagos',
                'v.status',
                'v.dia_semana',
                DB::raw("DATE_FORMAT(v.fecha_venta, '%d / %m / %Y') as fecha_formato"),
                DB::raw("DATE_FORMAT(v.fecha_final_pago, '%d / %m / %Y') as fpago_formato"),
                'c.nombre as cliente_nombre', 
                'c.telefono as cliente_telefono', 
                'c.ciudad as cliente_ciudad',
                'c.calle as cliente_calle',
                'c.colonia as cliente_colonia',
                'u_ven.username as vendedor_nombre',
                'u_cob.username as cobrador_nombre'
            )
            // Left Join Clientes (Soporta UUID e ID Legacy)
            ->leftJoin('clientes as c', function($join) {
                $join->on('v.cliente_id', '=', 'c.id');
                if (Schema::hasColumn('clientes', 'cliente_id')) {
                    $join->orOn('v.cliente_id', '=', 'c.cliente_id');
                }
            })
            // Left Join Vendedor
            ->leftJoin('users as u_ven', function($join) {
                $join->on('v.vendedor_id', '=', 'u_ven.id');
                if (Schema::hasColumn('users', 'user_id_legacy')) {
                    $join->orOn('v.vendedor_id', '=', 'u_ven.user_id_legacy');
                }
            })
            // Left Join Cobrador
            ->leftJoin('users as u_cob', function($join) {
                $join->on('v.cobrador_id', '=', 'u_cob.id');
                if (Schema::hasColumn('users', 'user_id_legacy')) {
                    $join->orOn('v.cobrador_id', '=', 'u_cob.user_id_legacy');
                }
            })
            // Filtro estricto: Solo ventas pendientes con saldo pendiente mayor a $0
            ->whereNotIn('v.status', ['Pagado', 'Eliminar', 'Devolucion', 'Recogido'])
            ->where('v.saldo_restante', '>', 0);

        // 1. Filtro por Rol (Si no es Admin, solo ve sus cobros asignados)
        if ($tipoSeccion != 0) {
            $query->where(function($q) use ($user) {
                $q->where('v.cobrador_id', $user->id);
                if (!empty($user->user_id_legacy)) {
                    $q->orWhere('v.cobrador_id', $user->user_id_legacy);
                }
            });
        } 
        // 2. Filtro por Cobrador Seleccionado (Si es Admin y aplicó el filtro)
        else if (!empty($filtroCobrador) && $filtroCobrador != '0') {
            $query->where(function($q) use ($filtroCobrador) {
                $q->where('v.cobrador_id', $filtroCobrador);
                // Si el ID enviado es entero/legacy, también buscar por la columna legacy
                if (is_numeric($filtroCobrador)) {
                    $q->orWhere('v.cobrador_id', $filtroCobrador);
                }
            });
        }

        // 3. Filtro por Día de la Semana
        if (!empty($dia) && $dia != 'Todos') {
            $query->where('v.dia_semana', $dia);
        }

        // Ordenamiento por fecha de venta (las más antiguas primero para priorizar atrasos)
        $ventas = $query->orderBy('v.fecha_venta', 'asc')->get();

        // Obtener lista de cobradores activos para el selector/filtro
        $cobradores = DB::table('users')
            ->select('id', DB::raw("COALESCE(user_id_legacy, id) as user_id_legacy"), 'username')
            ->where('is_active', 1)
            ->get();

        return view('abonos.index', compact(
            'ventas', 
            'cobradores', 
            'tipoAbono', 
            'dia', 
            'filtroCobrador', 
            'tipoSeccion'
        ));
    }

    /**
     * Vista individual de cobro
     */
    public function cobrar(Request $request, $id)
    {
        $user = auth()->user();
        $tipoSeccion = $user->isAdmin() ? 0 : 1;
        $tipoAbono = $request->input('tipo', 1);

        // Obtener Venta
        $venta = DB::table('ventas as v')
            ->select(
                'v.*', 
                DB::raw("DATE_FORMAT(v.fecha_venta, '%d / %m / %Y') as fecha_formato"),
                DB::raw("DATE_FORMAT(v.fecha_final_pago, '%d / %m / %Y') as proximo_pago_formateado")
            )
            ->where('v.id', $id)
            ->orWhere('v.venta_id_legacy', $id)
            ->first();

        if (!$venta) {
            return redirect()->route('abonos.index')->with('error', 'La venta especificada no existe.');
        }

        // Datos del Cliente adaptado a los campos 'nombre', 'telefono', 'calle', 'colonia', 'ciudad'
        $cliente = DB::table('clientes')
            ->where('id', $venta->cliente_id)
            ->orWhere('cliente_id', $venta->cliente_id)
            ->first();

        // Mapear alias de propiedades para mantener compatibilidad con las vistas Blade
        if ($cliente) {
            $cliente->cliente_nombre = $cliente->nombre ?? 'Sin Nombre';
            $cliente->cliente_telefono = $cliente->telefono ?? '';
            $cliente->cliente_colonia = $cliente->colonia ?? '';
            $cliente->cliente_ciudad = $cliente->ciudad ?? '';
            $cliente->cliente_calle = $cliente->calle ?? '';
            $cliente->cliente_ubicacion = $cliente->ubicacion ?? '';
            $cliente->cliente_id = $cliente->cliente_id ?? $cliente->id;
        }

        // Vendedor y Cobrador
        $vendedor = DB::table('users')
            ->where('id', $venta->vendedor_id)
            ->orWhere('user_id_legacy', $venta->vendedor_id)
            ->value('username') ?? 'N/A';

        $cobrador = DB::table('users')
            ->where('id', $venta->cobrador_id)
            ->orWhere('user_id_legacy', $venta->cobrador_id)
            ->value('username') ?? 'N/A';

        // Suma de Abonos
        $sumAbonos = DB::table('abonos')
            ->where('a_venta', $venta->id)
            ->orWhere('venta_id', $venta->id)
            ->orWhere('a_venta', $venta->venta_id_legacy)
            ->select(DB::raw('SUM(a_pago) as suma'), DB::raw('MAX(a_npago) as ultimo'))
            ->first();

        $suma = $sumAbonos->suma ?? 0;
        $ultimoNPago = ($sumAbonos->ultimo ?? 0) + 1;

        // Descuentos
        $ventaDescuento = DB::table('venta_articulos')
            ->where('va_venta', $venta->id)
            ->orWhere('va_venta', $venta->venta_id_legacy)
            ->select(DB::raw('SUM(va_descuento * va_cantidad) as total_desc'))
            ->value('total_desc') ?? 0;

        // Último Abono
        $ultimoAbono = DB::table('abonos')
            ->where('a_venta', $venta->id)
            ->orWhere('venta_id', $venta->id)
            ->orWhere('a_venta', $venta->venta_id_legacy)
            ->orderBy('created_at', 'desc')
            ->first();

        // Saldo Restante
        $aux4 = $venta->total - $suma - $ventaDescuento;

        // Artículos de la venta
        $articulos = DB::table('venta_articulos as va')
            ->leftJoin('articulos as a', 'va.va_articulo', '=', 'a.art_id')
            ->select('va.*', 'a.modelo')
            ->where('va.va_venta', $venta->id)
            ->orWhere('va.va_venta', $venta->venta_id_legacy)
            ->get();

        // Usuarios
        $usuariosCobrador = DB::table('users')->select('id', 'user_id_legacy', 'username')->get();

        // Historial de Abonos
        $historialAbonos = DB::table('abonos as a')
            ->leftJoin('users as u', function($join) {
                $join->on('a.a_cobrador', '=', 'u.id')
                     ->orOn('a.a_cobrador', '=', 'u.user_id_legacy');
            })
            ->select('a.*', 'u.username as nombre_cobrador')
            ->where('a.a_venta', $venta->id)
            ->orWhere('a.venta_id', $venta->id)
            ->orWhere('a.a_venta', $venta->venta_id_legacy)
            ->orderBy('a.created_at', 'asc')
            ->get();

        return view('abonos.cobrar', compact(
            'venta', 'cliente', 'vendedor', 'cobrador', 'suma', 'ultimoNPago',
            'ventaDescuento', 'ultimoAbono', 'aux4', 'articulos',
            'usuariosCobrador', 'historialAbonos', 'tipoAbono', 'tipoSeccion'
        ));
    }

    /**
     * Muestra el detalle de la venta, historial de abonos y el formulario para abonar.
     */
    public function show(Request $request, $id)
    {
        $user = auth()->user();
        $tipoSeccion = $user->isAdmin() ? 0 : 1;

        // 1. Obtener la venta específica por UUID o ID Legacy
        $venta = DB::table('ventas as v')
            ->select(
                'v.*',
                'c.id as cliente_id_real',
                'c.nombre as cliente_nombre',
                'c.colonia as cliente_colonia',
                'c.ciudad as cliente_ciudad',
                'c.calle as cliente_calle',
                'c.telefono as cliente_telefono'
            )
            ->leftJoin('clientes as c', function($join) {
                $join->on('v.cliente_id', '=', 'c.id');
                if (Schema::hasColumn('clientes', 'cliente_id')) {
                    $join->orOn('v.cliente_id', '=', 'c.cliente_id');
                }
            })
            ->where('v.id', $id)
            ->orWhere('v.venta_id_legacy', $id)
            ->first();

        if (!$venta) {
            return redirect()->route('abonos.index')->with('error', 'La venta consultada no existe.');
        }

        // 2. Estructura de Cliente
        $cliente = (object) [
            'nombre'  => $venta->cliente_nombre ?? 'Cliente No Asignado',
            'colonia' => $venta->cliente_colonia ?? '',
            'ciudad'  => $venta->cliente_ciudad ?? ''
        ];

        // 3. Suma estricta de abonos PERTENECIENTES ÚNICAMENTE A ESTA VENTA
        $suma = DB::table('abonos')
            ->where(function($q) use ($venta) {
                $q->where('venta_id', $venta->id);
                if (!empty($venta->venta_id_legacy)) {
                    $q->orWhere('a_venta', $venta->venta_id_legacy);
                }
                $q->orWhere('a_venta', $venta->id);
            })
            ->sum('a_pago') ?? 0;

        // 4. Último abono correspondiente A ESTA VENTA
        $ultimoAbono = DB::table('abonos')
            ->where(function($q) use ($venta) {
                $q->where('venta_id', $venta->id);
                if (!empty($venta->venta_id_legacy)) {
                    $q->orWhere('a_venta', $venta->venta_id_legacy);
                }
                $q->orWhere('a_venta', $venta->id);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('a_hora', 'desc')
            ->first();

        // 5. Consecutivo del siguiente número de pago
        $totalAbonosCount = DB::table('abonos')
            ->where(function($q) use ($venta) {
                $q->where('venta_id', $venta->id);
                if (!empty($venta->venta_id_legacy)) {
                    $q->orWhere('a_venta', $venta->venta_id_legacy);
                }
                $q->orWhere('a_venta', $venta->id);
            })
            ->count();

        $ultimoNPago = $totalAbonosCount + 1;

        // 6. Cálculo del descuento para los productos de ESTA VENTA
        $ventaDescuento = DB::table('venta_detalles')
            ->where('venta_id', $venta->id)
            ->selectRaw('SUM(COALESCE(descuento, 0) * COALESCE(cantidad, 1)) as total_desc')
            ->value('total_desc') ?? 0;

        // 7. Saldo restante dinámico para la venta
        $aux4 = max(0, floatval($venta->saldo_restante));

        return view('abonos.show', compact(
            'venta',
            'cliente',
            'suma',
            'ultimoAbono',
            'ultimoNPago',
            'ventaDescuento',
            'aux4',
            'tipoSeccion'
        ));
    }

    /**
     * Procesa y guarda un nuevo abono para la venta.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id'    => 'required',
            'abono' => 'required|numeric|min:0.01',
            'fecha' => 'required|date',
            'fpago' => 'required|string',
            'tipo'  => 'required'
        ]);

        $ventaId = $request->input('id');
        $montoAbono = floatval($request->input('abono'));
        $fechaAbono = $request->input('fecha');
        $formaPago  = $request->input('fpago');
        $tipoAccion = $request->input('tipo'); // 1: Abono Normal, 2: Liquidar Venta
        $nPagoInput = $request->input('npago');

        $venta = DB::table('ventas')->where('id', $ventaId)->first();

        if (!$venta) {
            return back()->with('error', 'No se encontró la venta para registrar el abono.');
        }

        DB::beginTransaction();
        try {
            // Si elige liquidar y es administrador, el abono se ajusta al saldo total restante
            if ($tipoAccion == '2') {
                $montoAbono = floatval($venta->saldo_restante);
            }

            // Validar que el abono no supere el saldo pendiente
            if ($montoAbono > floatval($venta->saldo_restante)) {
                return back()->with('error', 'El monto ingresado ($' . number_format($montoAbono, 2) . ') supera el saldo restante ($' . number_format($venta->saldo_restante, 2) . ').');
            }

            $fechaHora = Carbon::parse($fechaAbono)->format('Y-m-d') . ' ' . now()->format('H:i:s');

            // 1. Insertar el abono en la base de datos
            DB::table('abonos')->insert([
                'id'            => (string) Str::uuid(),
                'a_id'          => null,
                'a_venta'       => $venta->venta_id_legacy ?? $venta->folio ?? $venta->id,
                'venta_id'      => $venta->id,
                'a_pago'        => $montoAbono,
                'a_fecha'       => $fechaAbono,
                'a_npago'       => $nPagoInput ?? 1,
                'a_cobrador'    => auth()->user()->username ?? 'Cobrador',
                'a_tipopago'    => $formaPago,
                'a_tipo'        => ($tipoAccion == '2' || ($venta->saldo_restante - $montoAbono) <= 0) ? 'Liquidar' : 'Abono',
                'a_hora'        => $fechaHora,
                'observaciones' => 'Abono registrado desde el sistema',
                'created_at'    => $fechaHora,
                'updated_at'    => now(),
            ]);

            // 2. Descontar el saldo restante y actualizar el estado
            $nuevoSaldo = max(0, floatval($venta->saldo_restante) - $montoAbono);
            $nuevoStatus = ($nuevoSaldo <= 0) ? 'Pagado' : $venta->status;

            DB::table('ventas')->where('id', $venta->id)->update([
                'saldo_restante' => $nuevoSaldo,
                'status'         => $nuevoStatus,
                'updated_at'     => now()
            ]);

            DB::commit();

            return redirect()->route('abonos.show', $venta->id)->with('success', '¡El abono de $' . number_format($montoAbono, 2) . ' se ha guardado correctamente!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Ocurrió un error al guardar el abono: ' . $e->getMessage());
        }
    }

    /**
     * Reasignar Cobrador
     */
    public function cambiarCobrador(Request $request)
    {
        try {
            DB::table('ventas')
                ->where('id', $request->id_venta)
                ->orWhere('venta_id_legacy', $request->id_venta)
                ->update(['cobrador_id' => $request->id_cobrador]);

            return response('success');
        } catch (\Exception $e) {
            return response('error: ' . $e->getMessage(), 500);
        }
    }
}