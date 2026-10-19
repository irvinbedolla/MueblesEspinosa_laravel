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
        $ventasViejas = DB::connection('mysql')->table('ventas_old')->get();

        foreach ($ventasViejas as $v) {
            
            // Buscar UUIDs correspondientes
            $clienteUuid  = Cliente::where('cliente_id', $v->venta_cliente)->value('id');
            $vendedorUuid = User::where('user_id_legacy', $v->venta_vendedor)->value('id');
            $cobradorUuid = User::where('user_id_legacy', $v->venta_cobrador)->value('id');

            // Omitir si no existe el cliente
            if (!$clienteUuid) continue;

            $frecuenciaNombre = match(intval($v->venta_frecuencia)) {
                15      => 'Quincenal',
                30      => 'Mensual',
                default => 'Semanal'
            };

            $venta = Venta::create([
                'id'                 => Str::uuid()->toString(),
                'folio'              => $v->venta_id,
                'venta_id_legacy'    => $v->venta_id,
                'tipo_venta'         => $v->venta_tipo ?: 'Credito',
                'fecha_venta'        => $v->venta_fecha ?: now()->format('Y-m-d'),
                'hora_venta'         => $v->venta_hora ?: '12:00:00',
                'forma_pago'         => $v->venta_pago ?: 'Efectivo',
                'cliente_id'         => $clienteUuid,
                'vendedor_id'        => $vendedorUuid ?: auth()->id(),
                'cobrador_id'        => $cobradorUuid,
                'num_pagos'          => intval($v->venta_pagos) ?: 1,
                'frecuencia_dias'    => intval($v->venta_frecuencia) ?: 7,
                'frecuencia_nombre'  => $frecuenciaNombre,
                'fecha_primer_pago'  => ($v->venta_fecha_pago && $v->venta_fecha_pago !== '0000-00-00') ? $v->venta_fecha_pago : null,
                'fecha_final_pago'   => ($v->venta_pagof && $v->venta_pagof !== '0000-00-00') ? $v->venta_pagof : null,
                'dia_semana'         => $v->venta_dia_semana,
                'subtotal'           => floatval($v->venta_total) + floatval($v->venta_descuento),
                'credito_monto'      => floatval($v->venta_credito),
                'anticipo'           => floatval($v->venta_anticipo),
                'descuento'          => floatval($v->venta_descuento),
                'total'              => floatval($v->venta_total),
                'saldo_restante'     => floatval($v->venta_restante),
                'comentario'         => $v->venta_comentario,
                'status'             => $v->status ?: 'Pendiente',
            ]);

            // Migrar Artículos de la Venta
            $articulosVenta = DB::connection('mysql')->table('venta_articulos_old')
                ->where('va_venta', $v->venta_id)
                ->get();

            foreach ($articulosVenta as $va) {
                // Buscar UUID del artículo si existe en el nuevo catálogo
                $articuloUuid = Articulo::where('nombre', $va->va_nombre)->value('id');

                if ($articuloUuid) {
                    VentaDetalle::create([
                        'id'                 => Str::uuid()->toString(),
                        'venta_id'           => $venta->id,
                        'articulo_id'        => $articuloUuid,
                        'cantidad'           => intval($va->va_cantidad) ?: 1,
                        'nombre_articulo'    => $va->va_nombre,
                        'numero_serie'       => $va->va_nserie,
                        'precio_unitario'    => floatval($va->va_precio),
                        'descuento'          => floatval($va->va_descuento),
                        'pago_cuota_articulo'=> floatval($va->va_pago),
                        'subtotal'           => floatval($va->va_precio) * (intval($va->va_cantidad) ?: 1),
                    ]);
                }
            }
        }
    }
}