<?php

namespace Database\Seeders;

use App\Models\Articulo;
use App\Models\ArticuloPrecio;
use App\Models\InventarioStock;
use App\Models\Kardex;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArticuloSeeder extends Seeder
{
    public function run(): void
    {
        $articulosViejos = DB::connection('mysql')->table('articulos_old')->get();

        foreach ($articulosViejos as $a) {
            
            // 1. Obtener o crear el artículo maestro
            $articulo = Articulo::where('nombre', $a->nombre)
                ->where('marca', $a->marca)
                ->where('modelo', $a->modelo)
                ->first();

            if (!$articulo) {
                $pcompra = floatval($a->pcompra);
                $porcentaje = floatval($a->porcentaje) ?: 0;
                $pcredito = floatval($a->pcredito) ?: ($pcompra + ($pcompra * ($porcentaje / 100)));

                $articulo = Articulo::create([
                    'id'          => Str::uuid()->toString(),
                    'nombre'      => mb_strtoupper(trim($a->nombre)),
                    'marca'       => $a->marca,
                    'modelo'      => $a->modelo,
                    'barras'      => $a->barras,
                    'n_serie'     => $a->n_serie,
                    'descripcion' => $a->descripcion,
                    'categoria'   => (string) $a->categoria,
                    'pcompra'     => $pcompra,
                    'pcontado'    => floatval($a->pcontado),
                    'pcredito'    => $pcredito,
                    'porcentaje'  => $porcentaje,
                    'stock_min'   => intval($a->stock_min) ?: 5,
                ]);

                // Generar tabla de precios a meses
                $this->generarPreciosCredito($articulo->id, $pcredito, $a);
            }

            // 2. Resolver la ubicación del stock (Almacén o Vendedor específico)
            $vendedorUuid = null;

            if (intval($a->inventario) > 0) {
                // Buscar el UUID del usuario mediante su ID entero legado
                $vendedorUuid = User::where('user_id_legacy', $a->inventario)->value('id');
            }

            $cantidadItem = intval($a->stock) ?: 1;

            // 3. Crear o incrementar el registro de stock para esa ubicación específica
            $stockRegistro = InventarioStock::where('articulo_id', $articulo->id)
                ->where('user_id', $vendedorUuid)
                ->first();

            if ($stockRegistro) {
                $stockRegistro->increment('cantidad', $cantidadItem);
            } else {
                InventarioStock::create([
                    'id'          => Str::uuid()->toString(),
                    'articulo_id' => $articulo->id,
                    'user_id'     => $vendedorUuid,
                    'cantidad'    => $cantidadItem,
                ]);
            }

            // 4. Registrar en Kardex
            Kardex::create([
                'id'              => Str::uuid()->toString(),
                'articulo_id'     => $articulo->id,
                'user_id'         => auth()->id(),
                'origen_user_id'  => null,
                'destino_user_id' => $vendedorUuid,
                'tipo_movimiento' => $vendedorUuid ? 'Asignacion_Vendedor' : 'Entrada_Inicial',
                'cantidad'        => $cantidadItem,
                'stock_anterior'  => 0,
                'stock_nuevo'     => $cantidadItem,
                'observaciones'   => $vendedorUuid ? 'Stock migrado asignado a vendedor' : 'Stock en almacén general',
            ]);
        }
    }

    private function generarPreciosCredito(string $articuloId, float $pre1, $articuloViejo)
    {
        $porcentajesDescuento = [
            1 => 40, 2 => 35, 3 => 30, 4 => 25, 
            5 => 20, 6 => 15, 7 => 10, 8 => 5,
            9 => 0,  10 => 0, 11 => 0, 12 => 0
        ];

        for ($m = 1; $m <= 12; $m++) {
            $columnaPrecioViejo = 'precio' . $m;
            $precioPrevio = isset($articuloViejo->$columnaPrecioViejo) ? floatval($articuloViejo->$columnaPrecioViejo) : 0;

            if ($precioPrevio > 0) {
                $precioTotal = $precioPrevio;
            } else {
                $descuentoPct = $porcentajesDescuento[$m] ?? 0;
                $montoDescuento = $pre1 * ($descuentoPct / 100);
                $precioTotal = $pre1 - $montoDescuento;
            }

            ArticuloPrecio::create([
                'id'           => Str::uuid()->toString(),
                'articulo_id'  => $articuloId,
                'meses'        => $m,
                'precio_total' => round($precioTotal, 2),
                'pago_mensual' => round($precioTotal / $m, 2),
            ]);
        }
    }
}