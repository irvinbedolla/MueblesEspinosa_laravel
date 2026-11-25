<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AbonoController extends Controller
{
    public function index(Request $request)
    {
        // Temporal: habilitar vista global para todos los usuarios
        $tipoSeccion = 0;

        $tipoAbono      = $request->input('tipo', 1);
        $dia            = $request->input('Dia', '');
        $filtroCobrador = $request->input('cobrador', 0);
        $buscar         = trim((string) $request->input('buscar', ''));

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
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('users as u_ven', 'v.vendedor_id', '=', 'u_ven.id')
            ->leftJoin('users as u_cob', 'v.cobrador_id', '=', 'u_cob.id')
            ->whereNotIn('v.status', ['Pagado', 'Eliminar', 'Devolucion', 'Recogido'])
            ->where('v.saldo_restante', '>', 0);

        // Filtro opcional por Cobrador Seleccionado (sin restricción por usuario logueado)
        if (!empty($filtroCobrador) && $filtroCobrador != '0') {
            $query->where('v.cobrador_id', (string) $filtroCobrador);
        }

        // Filtro por Día de la Semana
        if (!empty($dia) && $dia != 'Todos') {
            $query->where('v.dia_semana', $dia);
        }

        // Búsqueda rápida en servidor (si existe input 'buscar' en la vista)
        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $q->where('c.nombre', 'like', "%{$buscar}%")
                  ->orWhere('v.folio', 'like', "%{$buscar}%")
                  ->orWhere('v.venta_id_legacy', 'like', "%{$buscar}%");
            });
        }

        // Obtener resultados en una sola consulta optimizada
        $ventas = $query->orderBy('v.fecha_venta', 'asc')->get();

        // Cachear cobradores 10 minutos para evitar consultarlo en cada recarga
        $cobradores = cache()->remember('cobradores_activos_abonos', 600, function () {
            return DB::table('users')
                ->select('id', DB::raw("COALESCE(user_id_legacy, id) as user_id_legacy"), 'username')
                ->where('is_active', 1)
                ->orderBy('username')
                ->get();
        });

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
     * Muestra el detalle de la venta, historial de abonos y el formulario para abonar.
     */
    public function show(Request $request, $id)
    {
        $user = auth()->user();
        $tipoSeccion = $user->isAdmin() ? 0 : 1;

        // 1. Obtener la venta (evitando coerción numérica de UUID a 0 en MySQL)
        $venta = DB::table('ventas as v')
            ->select('v.*')
            ->where('v.id', $id)
            ->first();

        if (!$venta && ctype_digit((string) $id) && (int) $id > 0) {
            $venta = DB::table('ventas as v')
                ->select('v.*')
                ->where('v.venta_id_legacy', (int) $id)
                ->orWhere('v.folio', $id)
                ->first();
        }

        if (!$venta) {
            return redirect()->route('abonos.index')->with('error', 'No se encontró la venta especificada.');
        }

        // Obtener el cliente específico vinculado a la venta (sin colisión UUID vs INT = 0)
        $clienteDB = null;
        if (!empty($venta->cliente_id)) {
            $clienteDB = DB::table('clientes')
                ->where('id', $venta->cliente_id)
                ->first();

            if (!$clienteDB && ctype_digit((string) $venta->cliente_id) && (int) $venta->cliente_id > 0 && Schema::hasColumn('clientes', 'cliente_id')) {
                $clienteDB = DB::table('clientes')
                    ->where('cliente_id', (int) $venta->cliente_id)
                    ->first();
            }
        }

        $cliente = (object) [
            'id'       => $clienteDB->id ?? $venta->cliente_id ?? null,
            'nombre'   => $clienteDB->nombre ?? 'Cliente General',
            'colonia'  => $clienteDB->colonia ?? '',
            'ciudad'   => $clienteDB->ciudad ?? '',
            'telefono' => $clienteDB->telefono ?? ''
        ];

        // 2. Historial de abonos (sin JOIN a users para evitar duplicados por coerción de tipos)
        $historialRaw = DB::table('abonos as a')
            ->where(function ($q) use ($venta) {
                $q->where('a.venta_id', (string) $venta->id);
                if (!empty($venta->venta_id_legacy) && (int) $venta->venta_id_legacy > 0) {
                    $q->orWhere('a.a_venta', (string) $venta->venta_id_legacy);
                }
                if (!empty($venta->folio) && (int) $venta->folio > 0) {
                    $q->orWhere('a.a_venta', (string) $venta->folio);
                }
                $q->orWhere('a.a_venta', (string) $venta->id);
            })
            ->orderBy('a.a_npago', 'asc')
            ->orderBy('a.a_fecha', 'asc')
            ->orderBy('a.created_at', 'asc')
            ->get();

        // Mapear nombres de cobradores en memoria sin duplicar filas
        $usuariosAll = DB::table('users')->select('id', 'user_id_legacy', 'username')->get();
        $mapPorId       = $usuariosAll->keyBy(fn($u) => (string) $u->id);
        $mapPorLegacy   = $usuariosAll->filter(fn($u) => !empty($u->user_id_legacy))->keyBy(fn($u) => (string) $u->user_id_legacy);
        $mapPorUsername = $usuariosAll->keyBy(fn($u) => mb_strtolower(trim((string) $u->username)));

        $historialAbonos = $historialRaw->map(function ($ab) use ($mapPorId, $mapPorLegacy, $mapPorUsername) {
            $key = trim((string) ($ab->a_cobrador ?? ''));
            $keyLower = mb_strtolower($key);

            if ($key !== '' && isset($mapPorId[$key])) {
                $ab->nombre_cobrador = $mapPorId[$key]->username;
            } elseif ($key !== '' && ctype_digit($key) && isset($mapPorLegacy[$key])) {
                $ab->nombre_cobrador = $mapPorLegacy[$key]->username;
            } elseif ($keyLower !== '' && isset($mapPorUsername[$keyLower])) {
                $ab->nombre_cobrador = $mapPorUsername[$keyLower]->username;
            } else {
                $ab->nombre_cobrador = $key !== '' ? $key : 'Cobrador';
            }

            return $ab;
        });

        $suma = $historialAbonos->sum('a_pago') ?? 0;
        $ultimoAbono = $historialAbonos->last();
        $ultimoNPago = $historialAbonos->count() + 1;

        // 3. Descuento asignado a la Venta
        $ventaDescuento = floatval($venta->descuento ?? 0);

        // 4. Saldo restante real
        $aux4 = max(0, floatval($venta->saldo_restante));

        // 5. Cobrador y Vendedor
        $cobradorAsignado = DB::table('users')
            ->where('id', $venta->cobrador_id ?? '')
            ->orWhere('username', $venta->cobrador_id ?? '')
            ->orWhere('user_id_legacy', $venta->cobrador_id ?? '')
            ->value('username') ?? 'Sin Asignar';

        $vendedorAsignado = DB::table('users')
            ->where('id', $venta->vendedor_id ?? '')
            ->orWhere('username', $venta->vendedor_id ?? '')
            ->orWhere('user_id_legacy', $venta->vendedor_id ?? '')
            ->value('username') ?? 'Sin Asignar';

        // Lista de usuarios activos para el modal
        $cobradoresDisponibles = DB::table('users')
            ->select('id', 'user_id_legacy', 'username')
            ->where('is_active', 1)
            ->get();

        // 6. Artículos de la venta sin duplicados (distinct)
        $detallesArticulos = DB::table('venta_detalles as vd')
            ->select('vd.id', 'vd.nombre_articulo', 'vd.numero_serie', 'vd.precio_unitario', 'vd.cantidad', 'a.modelo')
            ->leftJoin('articulos as a', 'vd.articulo_id', '=', 'a.id')
            ->where('vd.venta_id', $venta->id)
            ->distinct()
            ->get();

        // 7. Cálculo del Mes de Amortización limitado al rango de la escala (máx. 8 meses)
        $fechaVenta = Carbon::parse($venta->fecha_venta ?? $venta->created_at);
        $mesesCalculados = (int) $fechaVenta->diffInMonths(now()) + 1;
        $mesesAmortizacion = min(8, max(1, $mesesCalculados));

        $diaCobro = $venta->dia_cobro ?? $venta->dia_semana ?? null;
        $proximoPago = $venta->fecha_proximo_pago ?? $venta->fecha_primer_pago ?? $venta->fecha_siguiente_pago ?? null;

        // 8. Lógica de Comprobante / Ticket de WhatsApp
        $fechaHoyStr   = now()->format('Y-m-d');
        $fechaUltimoAb = $ultimoAbono ? Carbon::parse($ultimoAbono->a_fecha)->format('Y-m-d') : null;
        $abonoHoy      = ($fechaUltimoAb === $fechaHoyStr);
        $urlWhatsapp   = '#';

        $telWa = preg_replace('/[^0-9]/', '', (string) ($cliente->telefono ?? ''));
        if (strlen($telWa) === 10) {
            $telWa = '52' . $telWa;
        }

        $folioCuenta   = $venta->folio ?? $venta->venta_id_legacy ?? $venta->id;
        $nombreCliente = $cliente->nombre ?? 'Cliente';
        $ultimoMonto   = floatval($ultimoAbono->a_pago ?? 0);
        $horaAbonoStr  = $ultimoAbono->a_hora ?? ($ultimoAbono->a_fecha ?? now()->format('Y-m-d H:i:s'));
        $quienAtendio  = $ultimoAbono->nombre_cobrador ?? $cobradorAsignado;

        if ($aux4 <= 0 || $venta->status === 'Pagado') {
            $fechaLiq = $fechaUltimoAb ?? $fechaHoyStr;
            $textoTicket = "✨ VENTA LIQUIDADA ✨\n"
                . "MUEBLES ESPINOSA\n"
                . "---------------------------\n"
                . "Cuenta: #{$folioCuenta}\n"
                . "Cliente: {$nombreCliente}\n"
                . "Fecha: {$fechaLiq}\n"
                . "Atendio: {$quienAtendio}\n"
                . "---------------------------\n"
                . "¡Su cuenta ha sido pagada en su totalidad!\n"
                . "Último Pago: $" . number_format($ultimoMonto, 2) . "\n"
                . "Total Pagado: $" . number_format($suma, 2) . "\n"
                . "SALDO RESTANTE: $0.00\n"
                . "---------------------------\n"
                . "Agradecemos profundamente su preferencia y puntualidad. ¡Esperamos verle pronto para su próxima compra! 🤝";

            $urlWhatsapp = "https://api.whatsapp.com/send?phone={$telWa}&text=" . rawurlencode($textoTicket);
        } elseif ($abonoHoy) {
            $textoTicket = "MUEBLES ESPINOSA\n"
                . "Venta: {$folioCuenta}\n"
                . "Calle Revolución N°104\n"
                . "Col. Heberto Castillo\n"
                . "Cd. Altamirano Gro.\n"
                . "Fecha y Hora: {$horaAbonoStr}\n"
                . "Atendio: {$quienAtendio}\n"
                . "Cliente: {$nombreCliente}\n"
                . "Folio: {$folioCuenta}\n"
                . "Total: $" . number_format($venta->total, 2) . "\n"
                . "Usted Abono: la cantidad de $" . number_format($ultimoMonto, 2) . ".\n"
                . "Gracias por su abono\n"
                . "Total Abonado: $" . number_format($suma, 2) . "\n"
                . "Restante: $" . number_format($aux4, 2) . ".";

            $urlWhatsapp = "https://api.whatsapp.com/send?phone={$telWa}&text=" . rawurlencode($textoTicket);
        }

        return view('abonos.show', compact(
            'venta',
            'cliente',
            'suma',
            'historialAbonos',
            'ultimoAbono',
            'ultimoNPago',
            'ventaDescuento',
            'diaCobro',
            'proximoPago',
            'aux4',
            'tipoSeccion',
            'cobradorAsignado',
            'vendedorAsignado',
            'cobradoresDisponibles',
            'detallesArticulos',
            'mesesAmortizacion',
            'urlWhatsapp',
            'abonoHoy'
        ));
    }

    /**
     * Procesa y guarda un nuevo abono para la venta.
     */
    public function store(Request $request)
    {
        try {
            $ventaId         = $request->input('id');
            $montoAbono      = floatval($request->input('abono'));
            $fechaAbonoInput = $request->input('fecha');
            $fechaAbono      = !empty($fechaAbonoInput) ? $fechaAbonoInput : date('Y-m-d');
            $formaPago       = $request->input('fpago', 'Efectivo');
            $tipoAccion      = $request->input('tipo', 1); // 1: Normal, 2: Liquidar
            $tipoReagendar   = $request->input('tipo_reagendar', 'auto');
            $nPagoInput      = $request->input('npago', 1);

            DB::beginTransaction();

            // 1. Búsqueda con BLOQUEO DE FILA (lockForUpdate) para evitar condiciones de carrera
            $ventaQuery = DB::table('ventas')->where('id', $ventaId);
            if (ctype_digit((string) $ventaId) && (int) $ventaId > 0) {
                $ventaQuery->orWhere('venta_id_legacy', (int) $ventaId)
                           ->orWhere('folio', $ventaId);
            }
            $venta = $ventaQuery->lockForUpdate()->first();

            if (!$venta) {
                DB::rollBack();
                $msg = 'Error: No se encontró la venta especificada.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg], 404);
                }
                return back()->with('error', $msg);
            }

            $saldoRestante = floatval($venta->saldo_restante ?? $venta->venta_restante ?? 0);
            $montoRealCobrar = ($tipoAccion == '2') ? $saldoRestante : $montoAbono;

            // 2. CANDADO ANTI-DOBLE CLIC: Verificar si ya se registró este mismo abono hace menos de 15 segundos
            $abonoReciente = DB::table('abonos')
                ->where('venta_id', $venta->id)
                ->where('a_cobrador', auth()->id())
                ->where('a_pago', $montoRealCobrar)
                ->where('created_at', '>=', now()->subSeconds(15))
                ->exists();

            if ($abonoReciente) {
                DB::rollBack();
                $msg = 'El abono ya fue registrado hace unos segundos (se evitó duplicado).';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success'  => true,
                        'message'  => $msg,
                        'redirect' => route('abonos.show', $venta->id)
                    ]);
                }
                return redirect()->route('abonos.show', $venta->id)->with('success', $msg);
            }

            // 3. Validación de saldo y cuenta ya liquidada
            if ($saldoRestante <= 0 || $venta->status === 'Pagado') {
                DB::rollBack();
                $msg = 'Esta cuenta ya se encuentra liquidada.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
                return redirect()->route('abonos.show', $venta->id)->with('info', $msg);
            }

            if ($tipoAccion != '2' && ($montoAbono <= 0 || $montoAbono > $saldoRestante)) {
                DB::rollBack();
                $msg = '¡ERROR! El abono ($' . number_format($montoAbono, 2) . ') debe ser mayor a $0 y no superar el saldo restante ($' . number_format($saldoRestante, 2) . ').';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
                return back()->withInput()->with('error', $msg);
            }

            $hoy = now()->format('Y-m-d H:i:s');
            $fechaActual = date('Y-m-d');
            $usuarioActual = auth()->id();
            $aVentaRef = $venta->venta_id_legacy ?? $venta->folio ?? $venta->id;

            // Calcular el correlativo real en servidor para evitar números de pago repetidos
            $correlativoReal = DB::table('abonos')
                ->where('venta_id', $venta->id)
                ->count() + 1;

            // 4. Insertar el Registro de Abono
            $abonoData = [
                'id'            => (string) Str::uuid(),
                'a_venta'       => $aVentaRef,
                'venta_id'      => $venta->id,
                'a_pago'        => $montoRealCobrar,
                'a_fecha'       => $fechaAbono,
                'a_npago'       => $nPagoInput ?: $correlativoReal,
                'a_cobrador'    => $usuarioActual,
                'a_tipopago'    => $formaPago,
                'a_tipo'        => ($tipoAccion == '2') ? 'Liquidado' : 'Abono',
                'a_hora'        => $hoy,
                'observaciones' => ($tipoAccion == '2') ? 'Liquidación de cuenta' : 'Abono regular',
                'created_at'    => $hoy,
                'updated_at'    => $hoy
            ];

            if (Schema::hasColumn('abonos', 'a_id')) {
                $abonoData['a_id'] = null;
            }

            DB::table('abonos')->insert($abonoData);

            // 5. Actualización de la Venta (protegida con Schema::hasColumn)
            if ($tipoAccion == '2') {
                $dataUpdate = [
                    'status'     => 'Pagado',
                    'updated_at' => $hoy,
                ];

                if (Schema::hasColumn('ventas', 'venta_fecha_pago'))   $dataUpdate['venta_fecha_pago'] = $fechaActual;
                if (Schema::hasColumn('ventas', 'fecha_pago'))         $dataUpdate['fecha_pago'] = $fechaActual;
                if (Schema::hasColumn('ventas', 'fecha_liquidacion'))  $dataUpdate['fecha_liquidacion'] = $fechaActual;
                if (Schema::hasColumn('ventas', 'saldo_restante'))     $dataUpdate['saldo_restante'] = 0;
                if (Schema::hasColumn('ventas', 'venta_restante'))     $dataUpdate['venta_restante'] = 0;
                if (Schema::hasColumn('ventas', 'fecha_proximo_pago')) $dataUpdate['fecha_proximo_pago'] = null;
                if (Schema::hasColumn('ventas', 'nota_cobranza'))      $dataUpdate['nota_cobranza'] = null;

                DB::table('ventas')->where('id', $venta->id)->update($dataUpdate);
                $mensaje = '¡Cuenta totalmente liquidada con éxito!';
            } else {
                $frecuenciaDias = intval($venta->frecuencia_dias ?? $venta->frecuencia ?? $venta->venta_frecuencia ?? 7);
                if ($frecuenciaDias <= 0) $frecuenciaDias = 7;

                if ($tipoReagendar == 'auto') {
                    $nuevaFechaPago = Carbon::parse($fechaAbono)->addDays($frecuenciaDias)->format('Y-m-d');
                } else {
                    $nuevaFechaPago = $request->input('fecha_reagendar', $fechaAbono);
                }

                $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                $nuevoDiaCobro = $diasSemana[Carbon::parse($nuevaFechaPago)->dayOfWeek];

                $nuevoSaldo = max(0, $saldoRestante - $montoAbono);
                $nuevoStatus = ($nuevoSaldo <= 0) ? 'Pagado' : $venta->status;

                $dataUpdate = [
                    'status'     => $nuevoStatus,
                    'updated_at' => $hoy,
                ];

                if ($nuevoSaldo <= 0) {
                    if (Schema::hasColumn('ventas', 'venta_fecha_pago'))  $dataUpdate['venta_fecha_pago'] = $fechaActual;
                    if (Schema::hasColumn('ventas', 'fecha_pago'))        $dataUpdate['fecha_pago'] = $fechaActual;
                    if (Schema::hasColumn('ventas', 'fecha_liquidacion')) $dataUpdate['fecha_liquidacion'] = $fechaActual;
                }

                if (Schema::hasColumn('ventas', 'saldo_restante'))       $dataUpdate['saldo_restante'] = $nuevoSaldo;
                if (Schema::hasColumn('ventas', 'venta_restante'))       $dataUpdate['venta_restante'] = $nuevoSaldo;
                if (Schema::hasColumn('ventas', 'fecha_proximo_pago'))   $dataUpdate['fecha_proximo_pago'] = ($nuevoSaldo <= 0) ? null : $nuevaFechaPago;
                if (Schema::hasColumn('ventas', 'nota_cobranza'))        $dataUpdate['nota_cobranza'] = null;
                if (Schema::hasColumn('ventas', 'proximo_pago'))         $dataUpdate['proximo_pago'] = ($nuevoSaldo <= 0) ? null : $nuevaFechaPago;
                if (Schema::hasColumn('ventas', 'fecha_siguiente_pago')) $dataUpdate['fecha_siguiente_pago'] = ($nuevoSaldo <= 0) ? null : $nuevaFechaPago;
                if (Schema::hasColumn('ventas', 'venta_pagof'))          $dataUpdate['venta_pagof'] = ($nuevoSaldo <= 0) ? null : $nuevaFechaPago;
                if (Schema::hasColumn('ventas', 'dia_cobro'))            $dataUpdate['dia_cobro'] = $nuevoDiaCobro;
                if (Schema::hasColumn('ventas', 'dia_semana'))           $dataUpdate['dia_semana'] = $nuevoDiaCobro;

                DB::table('ventas')->where('id', $venta->id)->update($dataUpdate);

                $mensaje = ($nuevoSaldo <= 0)
                    ? '¡Cuenta liquidada por completo!'
                    : 'Se registró el abono correctamente. Próximo cobro: ' . Carbon::parse($nuevaFechaPago)->format('d/m/Y');
            }

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'  => true,
                    'message'  => $mensaje,
                    'redirect' => route('abonos.show', $venta->id)
                ]);
            }

            return redirect()->route('abonos.show', $venta->id)->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();
            $errorMsg = 'Error en base de datos: ' . $e->getMessage();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMsg], 500);
            }

            return back()->withInput()->with('error', $errorMsg);
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

    public function buscarVentasAjax(Request $request)
    {
        $term = trim($request->input('q', ''));

        if (empty($term)) {
            return response()->json([]);
        }

        $resultados = DB::table('ventas as v')
            ->select(
                'v.id as venta_uuid',             // UUID primario obligatorio para la URL
                'v.folio',
                'v.venta_id_legacy',
                'v.saldo_restante',
                'v.status',
                'c.nombre as cliente_nombre',
                'c.ciudad as cliente_ciudad',
                'c.colonia as cliente_colonia'
            )
            ->leftJoin('clientes as c', function($join) {
                $join->on('v.cliente_id', '=', 'c.id');
                if (Schema::hasColumn('clientes', 'cliente_id')) {
                    $join->orOn('v.cliente_id', '=', 'c.cliente_id');
                }
            })
            ->whereNotIn('v.status', ['Eliminar', 'Devolucion'])
            ->where(function($q) use ($term) {
                $q->where('c.nombre', 'LIKE', "%{$term}%")
                ->orWhere('v.folio', 'LIKE', "%{$term}%")
                ->orWhere('v.venta_id_legacy', 'LIKE', "%{$term}%");
            })
            ->limit(10)
            ->get();

        return response()->json($resultados);
    }

    /**
     * Guarda las coordenadas GPS capturadas en campo.
     */
    public function fijarGps(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required',
            'latitud'    => 'required',
            'longitud'   => 'required'
        ]);

        $coords = $request->latitud . ',' . $request->longitud;

        DB::table('clientes')
            ->where('id', $request->cliente_id)
            ->orWhere('cliente_id', $request->cliente_id)
            ->update(['cliente_ubicacion' => $coords, 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Ubicación GPS fijada correctamente.', 'ubicacion' => $coords]);
    }

    /**
     * Actualiza el teléfono del cliente buscando tanto por UUID como por ID Legacy.
     */
    public function actualizarTelefono(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required',
            'telefono'   => 'required|string|max:20'
        ]);

        $clienteId = $request->input('cliente_id');
        $telefono  = trim($request->input('telefono'));

        // Intentar actualizar por ID de UUID o por el cliente_id legacy
        $affected = DB::table('clientes')
            ->where('id', $clienteId)
            ->orWhere('cliente_id', $clienteId)
            ->update([
                'telefono'   => $telefono,
                'updated_at' => now()
            ]);

        // Si por alguna razón la venta tiene cliente_id desvinculado direct en ventas
        if ($affected === 0) {
            // Buscar la venta para obtener el cliente asignado real
            $venta = DB::table('ventas')->where('id', $clienteId)->orWhere('venta_id_legacy', $clienteId)->first();
            if ($venta && !empty($venta->cliente_id)) {
                DB::table('clientes')
                    ->where('id', $venta->cliente_id)
                    ->orWhere('cliente_id', $venta->cliente_id)
                    ->update([
                        'telefono'   => $telefono,
                        'updated_at' => now()
                    ]);
            }
        }

        return response()->json([
            'success'  => true, 
            'message'  => '¡Teléfono actualizado correctamente!',
            'telefono' => $telefono
        ]);
    }

    /**
     * Guarda y almacena la evidencia fotográfica de la venta de forma accesible.
     */
    public function subirEvidencia(Request $request)
    {
        $request->validate([
            'venta_id'  => 'required',
            'evidencia' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120'
        ]);

        if ($request->hasFile('evidencia')) {
            $file = $request->file('evidencia');
            
            // Asignar un nombre único con extensión correcta
            $filename = 'evidencia_' . time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            
            // Guardar físicamente en storage/app/public/evidencias
            $path = $file->storeAs('evidencias', $filename, 'public');

            // Guardar la referencia en la base de datos
            DB::table('venta_evidencias')->insert([
                'id'          => (string) Str::uuid(),
                'venta_id'    => $request->input('venta_id'),
                'ruta_imagen' => $path, // Se guarda como: evidencias/evidencia_123456.jpg
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return back()->with('success', 'Evidencia fotográfica subida correctamente.');
        }

        return back()->with('error', 'No se pudo subir la imagen.');
    }

    /**
     * 1. Guardar Notas / Comentarios de la venta (Imagen 2)
     */
    public function guardarNotas(Request $request)
    {
        $request->validate([
            'venta_id' => 'required',
            'notas'    => 'nullable|string'
        ]);

        DB::table('ventas')
            ->where('id', $request->venta_id)
            ->orWhere('venta_id_legacy', $request->venta_id)
            ->update([
                'comentario' => $request->notas,
                'updated_at' => now()
            ]);

        return back()->with('success', 'Notas actualizadas correctamente.');
    }

    /**
     * Reagenda la próxima fecha de cobro sin alterar el historial financiero de abonos.
     */
    public function reagendarVisita(Request $request)
    {
        // Aceptar 'nueva_fecha' (o 'proximo_pago' como respaldo)
        $fechaInput = $request->input('nueva_fecha') ?? $request->input('proximo_pago');
        $request->merge(['nueva_fecha' => $fechaInput]);

        $request->validate([
            'venta_id'    => 'required',
            'nueva_fecha' => 'required|date',
        ]);

        $ventaId    = $request->input('venta_id');
        $nuevaFecha = Carbon::parse($request->input('nueva_fecha'))->format('Y-m-d');

        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $nuevoDia   = $diasSemana[Carbon::parse($nuevaFecha)->dayOfWeek];

        $updateData = [
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('ventas', 'fecha_proximo_pago')) {
            $updateData['fecha_proximo_pago'] = $nuevaFecha;
        }
        if (Schema::hasColumn('ventas', 'fecha_final_pago')) {
            $updateData['fecha_final_pago'] = $nuevaFecha;
        }
        if (Schema::hasColumn('ventas', 'proximo_pago')) {
            $updateData['proximo_pago'] = $nuevaFecha;
        }
        if (Schema::hasColumn('ventas', 'fecha_siguiente_pago')) {
            $updateData['fecha_siguiente_pago'] = $nuevaFecha;
        }
        if (Schema::hasColumn('ventas', 'venta_pagof')) {
            $updateData['venta_pagof'] = $nuevaFecha;
        }
        if (Schema::hasColumn('ventas', 'dia_semana')) {
            $updateData['dia_semana'] = $nuevoDia;
        }
        if (Schema::hasColumn('ventas', 'dia_cobro')) {
            $updateData['dia_cobro'] = $nuevoDia;
        }

        // Evita colisión UUID vs INT en venta_id_legacy
        $query = DB::table('ventas')->where('id', $ventaId);
        if (ctype_digit((string) $ventaId) && (int) $ventaId > 0) {
            $query->orWhere('venta_id_legacy', (int) $ventaId);
        }

        $query->update($updateData);

        return back()->with('success', 'Visita reagendada correctamente para el ' . Carbon::parse($nuevaFecha)->format('d/m/Y'));
    }

    /**
     * 3. Aplicar Bono / Descuento a la Venta (Imagen 6)
     */
    public function aplicarDescuento(Request $request)
    {
        $request->validate([
            'venta_id'  => 'required',
            'descuento' => 'required|numeric|min:0.01'
        ]);

        $venta = DB::table('ventas')
            ->where('id', $request->venta_id)
            ->orWhere('venta_id_legacy', $request->venta_id)
            ->first();

        if (!$venta) {
            return back()->with('error', 'No se encontró la venta.');
        }

        $montoDescuento = floatval($request->descuento);
        
        // El descuento se acumula en la columna descuento y se resta del saldo_restante
        $nuevoDescuento = floatval($venta->descuento ?? 0) + $montoDescuento;
        $nuevoSaldo = max(0, floatval($venta->saldo_restante) - $montoDescuento);
        $nuevoStatus = ($nuevoSaldo <= 0) ? 'Pagado' : $venta->status;

        DB::table('ventas')
            ->where('id', $venta->id)
            ->update([
                'descuento'      => $nuevoDescuento,
                'saldo_restante' => $nuevoSaldo,
                'status'         => $nuevoStatus,
                'updated_at'     => now()
                // NOTA: NO se modifica la columna 'total' de la venta para conservar los $7,900.00 originales
            ]);

        return back()->with('success', 'Bono de descuento de $' . number_format($montoDescuento, 2) . ' aplicado correctamente.');
    }

    /**
     * Reasigna el cobrador de la venta.
     */
    public function reasignarCobrador(Request $request)
    {
        $request->validate([
            'venta_id' => 'required',
            'cobrador_user_id' => 'required'
        ]);

        // Verificar que el ID entregado corresponda a un usuario existente
        $user = DB::table('users')->where('id', $request->cobrador_user_id)->first();

        if (!$user) {
            return back()->with('error', 'El cobrador seleccionado no es válido.');
        }

        DB::table('ventas')
            ->where('id', $request->venta_id)
            ->orWhere('venta_id_legacy', $request->venta_id)
            ->update([
                'cobrador_id' => $user->id, // Debe pasarse el UUID para respetar la Foreign Key
                'updated_at'  => now()
            ]);

        return back()->with('success', '¡Cobrador reasignado a ' . ($user->username ?? $user->name) . ' correctamente!');
    }
}