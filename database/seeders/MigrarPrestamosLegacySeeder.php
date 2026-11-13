<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrarPrestamosLegacySeeder extends Seeder
{
    public function run(): void
    {
        // Nombres de tus tablas (ajusta si en tu BD tienen otro nombre)
        $tablaPrestamosOld     = 'prestamos_old';
        $tablaPagoPrestamosOld = 'pago_prestamo_old'; // o 'pago_prestamos_old'
        $tablaPrestamosNew     = 'prestamos';
        $tablaAbonosNew        = 'prestamo_abonos';

        // 1. Mapa de usuarios legacy -> UUID nuevo
        $usuariosMap = DB::table('users')
            ->whereNotNull('user_id_legacy')
            ->pluck('id', 'user_id_legacy')
            ->toArray();

        // 2. Precargar préstamos ya migrados para evitar duplicados
        $prestamosMap = DB::table($tablaPrestamosNew)
            ->whereNotNull('prestamo_id_legacy')
            ->pluck('id', 'prestamo_id_legacy')
            ->toArray();

        $prestamosOld = DB::table($tablaPrestamosOld)->orderBy('pres_id', 'asc')->get();
        $pagosOld     = DB::table($tablaPagoPrestamosOld)->orderBy('idPP', 'asc')->get();

        $prestamosInsertados = 0;
        $abonosInsertados    = 0;

        DB::transaction(function () use (
            $prestamosOld,
            $pagosOld,
            $usuariosMap,
            &$prestamosMap,
            $tablaPrestamosNew,
            $tablaAbonosNew,
            &$prestamosInsertados,
            &$abonosInsertados
        ) {
            // PASO A: MIGRAR PRÉSTAMOS
            foreach ($prestamosOld as $old) {
                $fechaValida = $this->formatearDate($old->pres_fecha);
                $userUuid    = $usuariosMap[$old->pres_usuario] ?? null;

                $costo    = round((float) $old->pres_costo, 2);
                $restante = round((float) $old->pres_restante, 2);
                $status   = ($restante <= 0) ? 'Liquidado' : 'Activo';

                if (!isset($prestamosMap[$old->pres_id])) {
                    $nuevoUuid = (string) Str::uuid();

                    DB::table($tablaPrestamosNew)->insert([
                        'id'                 => $nuevoUuid,
                        'prestamo_id_legacy' => $old->pres_id,
                        'descripcion'        => trim((string) $old->pres_descripcion),
                        'costo'              => $costo,
                        'monto_total'        => $costo, // En legacy pres_costo es el total del préstamo
                        'saldo_restante'     => max(0, $restante),
                        'fecha'              => $fechaValida,
                        'user_id'            => $userUuid,
                        'status'             => $status,
                        'created_at'         => $fechaValida . ' 00:00:00',
                        'updated_at'         => now(),
                    ]);

                    $prestamosMap[$old->pres_id] = $nuevoUuid;
                    $prestamosInsertados++;
                }
            }

            // PASO B: MIGRAR PAGOS / ABONOS DE PRÉSTAMOS
            foreach ($pagosOld as $pago) {
                // Solo migramos el abono si su préstamo padre existe en el mapa
                if (isset($prestamosMap[$pago->idPrestamo])) {
                    $prestamoUuid = $prestamosMap[$pago->idPrestamo];
                    $fechaAbono   = $this->formatearDate($pago->fechaPretamo); // Ojo: en tu tabla vieja dice 'fechaPretamo' sin 's'
                    $montoAbono   = round((float) $pago->monto, 2);

                    // Evitar duplicados si se vuelve a ejecutar el seeder
                    $yaExiste = DB::table($tablaAbonosNew)
                        ->where('prestamo_id', $prestamoUuid)
                        ->where('monto', $montoAbono)
                        ->where('fecha', $fechaAbono)
                        ->exists();

                    if (!$yaExiste && $montoAbono > 0) {
                        DB::table($tablaAbonosNew)->insert([
                            'id'          => (string) Str::uuid(),
                            'prestamo_id' => $prestamoUuid,
                            'monto'       => $montoAbono,
                            'fecha'       => $fechaAbono,
                            'created_at'  => $fechaAbono . ' 00:00:00',
                            'updated_at'  => now(),
                        ]);
                        $abonosInsertados++;
                    }
                }
            }
        });

        $this->command->info("Migración finalizada: {$prestamosInsertados} préstamos y {$abonosInsertados} abonos de préstamos migrados.");
    }

    private function formatearDate(?string $fecha): string
    {
        if (empty($fecha) || str_starts_with(trim($fecha), '0000-00-00')) {
            return date('Y-m-d');
        }

        $timestamp = strtotime($fecha);
        if ($timestamp === false || $timestamp <= 0) {
            return date('Y-m-d');
        }

        return date('Y-m-d', $timestamp);
    }
}