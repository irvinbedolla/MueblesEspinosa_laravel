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
        // 1. Mapa de usuarios legacy -> UUID nuevo
        $usuariosMap = DB::table('users')
            ->whereNotNull('user_id_legacy')
            ->pluck('id', 'user_id_legacy')
            ->toArray();

        // 2. Precargar catálogo maestro existente para evitar duplicados
        $catalogoExistente = [];
        foreach (DB::table('articulos')->get() as $art) {
            $clave = $this->generarClaveUnica($art->nombre, $art->barras);
            $catalogoExistente[$clave] = $art->id;
        }

        // 3. Obtener registros válidos de articulos_old
        $registrosOld = DB::table('articulos_old')
            ->whereNotIn('art_status', ['Eliminado', 'Cancelado'])
            ->orderBy('art_id', 'asc')
            ->get();

        $articulosCreados = 0;
        $stocksInsertados = 0;
        $stocksSumados = 0;

        DB::transaction(function () use (
            $registrosOld,
            $usuariosMap,
            &$catalogoExistente,
            &$articulosCreados,
            &$stocksInsertados,
            &$stocksSumados
        ) {
            foreach ($registrosOld as $old) {
                $claveUnica = $this->generarClaveUnica($old->nombre, $old->barras);
                $fechaValida = $this->formatearFechaSegura($old->fecha_ingreso);

                // A) INSERTAR EN CATÁLOGO MAESTRO (SOLO SI NO EXISTE)
                if (!isset($catalogoExistente[$claveUnica])) {
                    $articuloUuid = (string) Str::uuid();
                    $nombreLimpio = mb_strtoupper(trim($old->nombre));
                    $barrasLimpia = !empty(trim((string) $old->barras)) ? trim((string) $old->barras) : null;

                    DB::table('articulos')->insert([
                        'id'                 => $articuloUuid,
                        'articulo_id_legacy' => $old->art_id,
                        'nombre'             => $nombreLimpio,
                        'marca'              => !empty(trim((string) $old->marca)) ? trim((string) $old->marca) : null,
                        'modelo'             => !empty(trim((string) $old->modelo)) ? trim((string) $old->modelo) : null,
                        'barras'             => $barrasLimpia,
                        'n_serie'            => !empty(trim((string) $old->n_serie)) ? trim((string) $old->n_serie) : null,
                        'descripcion'        => !empty(trim((string) $old->descripcion)) ? trim((string) $old->descripcion) : null,
                        'categoria'          => (string) $old->categoria,
                        'pcompra'            => round((float) $old->pcompra, 2),
                        'pcontado'           => round((float) $old->pcontado, 2),
                        'pcredito'           => round((float) $old->pcredito, 2),
                        'porcentaje'         => round((float) $old->porcentaje, 2),
                        'stock_min'          => $old->stock_min > 0 ? $old->stock_min : 5,
                        'created_at'         => $fechaValida,
                        'updated_at'         => now(),
                    ]);

                    // B) INSERTAR SUS 12 PRECIOS A CRÉDITO
                    for ($m = 1; $m <= 12; $m++) {
                        $campoPrecio = 'precio' . $m;
                        $precioTotal = round((float) ($old->$campoPrecio ?? 0), 2);

                        if ($precioTotal <= 0 && $old->pcompra > 0) {
                            $utilidades = [1 => 40, 2 => 35, 3 => 30, 4 => 25, 5 => 20, 6 => 15, 7 => 10, 8 => 5];
                            $pct = $utilidades[$m] ?? 0;
                            $precioTotal = round($old->pcompra + ($old->pcompra * ($pct / 100)), 2);
                        }

                        DB::table('articulo_precios')->insert([
                            'id'           => (string) Str::uuid(),
                            'articulo_id'  => $articuloUuid,
                            'meses'        => $m,
                            'precio_total' => $precioTotal,
                            'pago_mensual' => round($precioTotal / $m, 2),
                            'created_at'   => $fechaValida,
                            'updated_at'   => now(),
                        ]);
                    }

                    $catalogoExistente[$claveUnica] = $articuloUuid;
                    $articulosCreados++;
                } else {
                    $articuloUuid = $catalogoExistente[$claveUnica];
                }

                // C) INSERTAR O SUMAR EXISTENCIA EN INVENTARIO_STOCK
                $userUuid = null;
                if ((int) $old->inventario > 0 && isset($usuariosMap[$old->inventario])) {
                    $userUuid = $usuariosMap[$old->inventario];
                }

                $condicion = match ($old->art_status) {
                    'Disponible' => 'Nuevo',
                    'Devolucion' => 'Devolución',
                    'Recogido'   => 'Recogido',
                    'Vendido'    => 'Vendido',
                    default      => 'Nuevo',
                };

                $numeroVenta = ((int) $old->venta_id > 0) ? (int) $old->venta_id : null;

                // Buscamos respetando exactamente la llave única: (articulo_id, user_id, condicion)
                $queryStock = DB::table('inventario_stock')
                    ->where('articulo_id', $articuloUuid)
                    ->where('condicion', $condicion);

                if (is_null($userUuid)) {
                    $queryStock->whereNull('user_id');
                } else {
                    $queryStock->where('user_id', $userUuid);
                }

                $stockExistente = $queryStock->first();

                if ($stockExistente) {
                    // Si ya existe la fila para ese artículo + usuario + condición, acumulamos el stock
                    DB::table('inventario_stock')
                        ->where('id', $stockExistente->id)
                        ->update([
                            'cantidad'     => $stockExistente->cantidad + max(0, (int) $old->stock),
                            'numero_venta' => $numeroVenta ?? $stockExistente->numero_venta,
                            'updated_at'   => now(),
                        ]);
                    $stocksSumados++;
                } else {
                    if ((int) $old->stock > 0 || in_array($condicion, ['Vendido', 'Recogido', 'Devolución'])) {
                        DB::table('inventario_stock')->insert([
                            'id'           => (string) Str::uuid(),
                            'articulo_id'  => $articuloUuid,
                            'user_id'      => $userUuid,
                            'cantidad'     => max(0, (int) $old->stock),
                            'condicion'    => $condicion,
                            'numero_venta' => $numeroVenta,
                            'created_at'   => $fechaValida,
                            'updated_at'   => now(),
                        ]);
                        $stocksInsertados++;
                    }
                }
            }
        });

        $this->command->info("Migración completada: {$articulosCreados} artículos únicos, {$stocksInsertados} registros nuevos de stock y {$stocksSumados} cantidades agrupadas.");
    }

    private function formatearFechaSegura(?string $fecha)
    {
        if (empty($fecha) || str_starts_with(trim($fecha), '0000-00-00')) {
            return now();
        }

        $timestamp = strtotime($fecha);
        if ($timestamp === false || $timestamp <= 0) {
            return now();
        }

        return date('Y-m-d 00:00:00', $timestamp);
    }

    private function generarClaveUnica(?string $nombre, ?string $barras): string
    {
        $barrasClean = trim((string) $barras);
        if ($barrasClean !== '' && $barrasClean !== '0') {
            return 'BARRAS_' . mb_strtoupper($barrasClean);
        }

        $nombreClean = preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $nombre)));
        return 'NOMBRE_' . $nombreClean;
    }
}