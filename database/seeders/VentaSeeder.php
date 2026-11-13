<?php

namespace Database\Seeders;

use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VentaSeeder extends Seeder
{

    public function run(): void
    {
        // 1. Mapas de referencia (ID legacy -> UUID nuevo)
        $clientesMap = DB::table('clientes')
            ->whereNotNull('cliente_id')
            ->pluck('id', 'cliente_id')
            ->toArray();

        $usuariosMap = DB::table('users')
            ->whereNotNull('user_id_legacy')
            ->pluck('id', 'user_id_legacy')
            ->toArray();

        // Respaldos por si una venta vieja apunta a un usuario/cliente eliminado en el sistema anterior
        $usuarioFallbackId = DB::table('users')->value('id');
        $clienteFallbackId = DB::table('clientes')->value('id');

        // 2. Mapas de artículos (por ID legacy y por Nombre normalizado para los deduplicados)
        $articulosPorIdLegacy = [];
        $articulosPorNombre   = [];
        foreach (DB::table('articulos')->get() as $art) {
            if ($art->articulo_id_legacy) {
                $articulosPorIdLegacy[$art->articulo_id_legacy] = $art->id;
            }
            $nombreNorm = preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $art->nombre)));
            $articulosPorNombre[$nombreNorm] = $art->id;
        }
        $articuloFallbackId = DB::table('articulos')->value('id');

        // 3. Precargar ventas ya migradas para evitar duplicados
        $ventasMap = DB::table('ventas')
            ->whereNotNull('venta_id_legacy')
            ->pluck('id', 'venta_id_legacy')
            ->toArray();

        $ventasOld   = DB::table('ventas_old')->orderBy('venta_id', 'asc')->get();
        $detallesOld = DB::table('venta_articulos_old')->orderBy('va_id', 'asc')->get();

        // Agrupar detalles en memoria por va_venta para acelerar el cruce
        $detallesPorVenta = [];
        foreach ($detallesOld as $det) {
            $detallesPorVenta[$det->va_venta][] = $det;
        }

        $ventasInsertadas   = 0;
        $detallesInsertados = 0;

        DB::transaction(function () use (
            $ventasOld,
            $detallesPorVenta,
            $clientesMap,
            $usuariosMap,
            $usuarioFallbackId,
            $clienteFallbackId,
            $articulosPorIdLegacy,
            $articulosPorNombre,
            $articuloFallbackId,
            &$ventasMap,
            &$ventasInsertadas,
            &$detallesInsertados
        ) {
            foreach ($ventasOld as $old) {
                // Si ya existe en ventas, saltamos
                if (isset($ventasMap[$old->venta_id])) {
                    continue;
                }

                $ventaUuid  = (string) Str::uuid();
                $fechaVenta = $this->formatearDate($old->venta_fecha) ?? date('Y-m-d');
                $horaVenta  = !empty($old->venta_hora) ? $old->venta_hora : '00:00:00';

                // Relaciones obligatorias
                $clienteUuid  = $clientesMap[$old->venta_cliente] ?? $clienteFallbackId;
                $vendedorUuid = $usuariosMap[$old->venta_vendedor] ?? $usuarioFallbackId;
                $cobradorUuid = ((int) $old->venta_cobrador > 0 && isset($usuariosMap[$old->venta_cobrador]))
                    ? $usuariosMap[$old->venta_cobrador]
                    : null;

                // Frecuencia
                $frecDias = (int) ($old->venta_frecuencia ?: 7);
                $frecNombre = match ($frecDias) {
                    15      => 'Quincenal',
                    30      => 'Mensual',
                    default => 'Semanal',
                };

                // Montos y saldos
                $numPagos      = max(1, (int) ($old->venta_pagos ?: 1));
                $total         = round((float) $old->venta_total, 2);
                $anticipo      = round((float) ($old->venta_anticipo ?? 0), 2);
                $descuento     = round((float) ($old->venta_descuento ?? 0), 2);
                $creditoMonto  = round((float) ($old->venta_credito ?? $total), 2);
                $saldoRestante = is_null($old->venta_restante) ? max(0, $total - $anticipo) : round((float) $old->venta_restante, 2);
                $subtotal      = max(0, $total - $anticipo);
                $pagoCuota     = ($numPagos > 0) ? round($saldoRestante / $numPagos, 2) : $saldoRestante;

                // Día de semana limpio
                $diaSemana = trim((string) $old->venta_dia_semana);
                if ($diaSemana === '' || strtolower($diaSemana) === 'seleccionar') {
                    $diaSemana = null;
                }

                // Tipo de venta normalizado
                $tipoVenta = str_contains(strtolower((string) $old->venta_tipo), 'contado') ? 'Contado' : 'Credito';

                DB::table('ventas')->insert([
                    'id'                => $ventaUuid,
                    'folio'             => $old->venta_id,
                    'venta_id_legacy'   => $old->venta_id,
                    'tipo_venta'        => $tipoVenta,
                    'fecha_venta'       => $fechaVenta,
                    'hora_venta'        => $horaVenta,
                    'forma_pago'        => !empty(trim((string) $old->venta_pago)) ? trim((string) $old->venta_pago) : 'Efectivo',
                    'cliente_id'        => $clienteUuid,
                    'vendedor_id'       => $vendedorUuid,
                    'cobrador_id'       => $cobradorUuid,
                    'num_pagos'         => $numPagos,
                    'frecuencia_dias'   => $frecDias,
                    'frecuencia_nombre' => $frecNombre,
                    'fecha_primer_pago' => $this->formatearDate($old->venta_fecha_pago),
                    'fecha_final_pago'  => $this->formatearDate($old->venta_pagof),
                    'dia_semana'        => $diaSemana,
                    'subtotal'          => $subtotal,
                    'credito_monto'     => $creditoMonto,
                    'anticipo'          => $anticipo,
                    'descuento'         => $descuento,
                    'total'             => $total,
                    'saldo_restante'    => $saldoRestante,
                    'pago_cuota'        => $pagoCuota,
                    'comentario'        => !empty(trim((string) $old->venta_comentario)) ? trim((string) $old->venta_comentario) : null,
                    'status'            => $old->status ?: 'Pendiente',
                    'created_at'        => $fechaVenta . ' ' . $horaVenta,
                    'updated_at'        => now(),
                ]);

                $ventasMap[$old->venta_id] = $ventaUuid;
                $ventasInsertadas++;

                // INSERTAR DETALLES DE LA VENTA (venta_detalles)
                $articulosVenta = $detallesPorVenta[$old->venta_id] ?? [];
                foreach ($articulosVenta as $det) {
                    $nombreNorm = preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $det->va_nombre)));
                    $articuloUuid = $articulosPorIdLegacy[$det->va_articulo]
                        ?? $articulosPorNombre[$nombreNorm]
                        ?? $articuloFallbackId;

                    if (!$articuloUuid) {
                        continue; // Solo ocurriría si la tabla articulos estuviera completamente vacía
                    }

                    $cantidad       = max(1, (int) $det->va_cantidad);
                    $precioUnitario = round((float) $det->va_precio, 2);
                    $descDetalle    = round((float) ($det->va_descuento ?? 0), 2);
                    $subDetalle     = round(($precioUnitario * $cantidad) - $descDetalle, 2);
                    $cuotaArticulo  = round((float) ($det->va_pago ?? 0), 2);

                    if ($cuotaArticulo <= 0 && $numPagos > 0) {
                        $cuotaArticulo = round($subDetalle / $numPagos, 2);
                    }

                    DB::table('venta_detalles')->insert([
                        'id'                  => (string) Str::uuid(),
                        'venta_id'            => $ventaUuid,
                        'articulo_id'         => $articuloUuid,
                        'cantidad'            => $cantidad,
                        'nombre_articulo'     => mb_strtoupper(trim((string) $det->va_nombre)),
                        'numero_serie'        => !empty(trim((string) $det->va_nserie)) ? trim((string) $det->va_nserie) : null,
                        'precio_unitario'     => $precioUnitario,
                        'descuento'           => $descDetalle,
                        'pago_cuota_articulo' => $cuotaArticulo,
                        'subtotal'            => $subDetalle,
                        'created_at'          => $fechaVenta . ' ' . $horaVenta,
                        'updated_at'          => now(),
                    ]);

                    $detallesInsertados++;
                }
            }
        });

        $this->command->info("Migración de ventas completada: {$ventasInsertadas} ventas y {$detallesInsertados} detalles insertados.");
    }

    private function formatearDate(?string $fecha): ?string
    {
        if (empty($fecha) || str_starts_with(trim($fecha), '0000-00-00')) {
            return null;
        }

        $timestamp = strtotime($fecha);
        if ($timestamp === false || $timestamp <= 0) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}