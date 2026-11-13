<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ClienteSeeder extends Seeder
{

    public function run(): void
    {
        // Precargar IDs legacy ya migrados para evitar duplicados
        $clientesExistentes = DB::table('clientes')
            ->whereNotNull('cliente_id')
            ->pluck('id', 'cliente_id')
            ->toArray();

        $clientesOld = DB::table('clientes_old')
            ->orderBy('cliente_id', 'asc')
            ->get();

        $insertados = 0;
        $actualizados = 0;

        DB::transaction(function () use (
            $clientesOld,
            &$clientesExistentes,
            &$insertados,
            &$actualizados
        ) {
            foreach ($clientesOld as $old) {
                $fechaRegistro = $this->formatearDate($old->cliente_fecha);
                $ultimaCompra  = $this->formatearDate($old->cliente_compra);

                // Extraer latitud y longitud si cliente_ubicacion trae formato "lat, lng"
                $latitud = null;
                $longitud = null;
                $ubicacionRaw = trim((string) ($old->cliente_ubicacion ?? ''));

                if ($ubicacionRaw !== '' && str_contains($ubicacionRaw, ',')) {
                    $partes = array_map('trim', explode(',', $ubicacionRaw));
                    if (count($partes) === 2 && is_numeric($partes[0]) && is_numeric($partes[1])) {
                        $latitud = round((float) $partes[0], 8);
                        $longitud = round((float) $partes[1], 8);
                    }
                }

                $datosCliente = [
                    'cliente_id'    => $old->cliente_id,
                    'nombre'        => mb_strtoupper(trim((string) $old->cliente_nombre)),
                    'telefono'      => $this->limpiarTexto($old->cliente_telefono),
                    'email'         => $this->limpiarTexto($old->cliente_email),
                    'ine'           => $this->limpiarTexto($old->cliente_ine),
                    'calle'         => $this->limpiarTexto($old->cliente_calle),
                    'colonia'       => $this->limpiarTexto($old->cliente_colonia),
                    'ciudad'        => $this->limpiarTexto($old->cliente_ciudad),
                    'estado'        => $this->limpiarTexto($old->cliente_estado),
                    'descripcion'   => $this->limpiarTexto($old->cliente_descripcion),
                    'ubicacion'     => $ubicacionRaw !== '' ? $ubicacionRaw : null,
                    'latitud'       => $latitud,
                    'longitud'      => $longitud,
                    'fecha_registro'=> $fechaRegistro,
                    'ultima_compra' => $ultimaCompra,
                    'status'        => !empty(trim((string) $old->cliente_status)) ? trim((string) $old->cliente_status) : 'Activo',
                    'updated_at'    => now(),
                ];

                if (isset($clientesExistentes[$old->cliente_id])) {
                    // Si ya existe en clientes, actualizamos sus datos conservando su UUID
                    DB::table('clientes')
                        ->where('id', $clientesExistentes[$old->cliente_id])
                        ->update($datosCliente);
                    $actualizados++;
                } else {
                    $nuevoUuid = (string) Str::uuid();
                    $datosCliente['id'] = $nuevoUuid;
                    $datosCliente['created_at'] = $fechaRegistro ? $fechaRegistro . ' 00:00:00' : now();

                    DB::table('clientes')->insert($datosCliente);
                    $clientesExistentes[$old->cliente_id] = $nuevoUuid;
                    $insertados++;
                }
            }
        });

        $this->command->info("Migración de clientes completada: {$insertados} insertados y {$actualizados} actualizados.");
    }

    private function limpiarTexto(?string $valor): ?string
    {
        $limpio = trim((string) $valor);
        return ($limpio === '' || $limpio === '0' || strtolower($limpio) === 'null') ? null : $limpio;
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