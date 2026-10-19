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
        // Consulta los clientes de la base de datos previa
        $clientesViejos = DB::connection('mysql')->table('clientes_old')->get();

        foreach ($clientesViejos as $c) {
            Cliente::create([
                'id'             => Str::uuid()->toString(),
                'cliente_id'     => $c->cliente_id,
                'nombre'         => $c->cliente_nombre,
                'telefono'       => $c->cliente_telefono,
                'email'          => $c->cliente_email,
                'ine'            => $c->cliente_ine,
                'calle'          => $c->cliente_calle,
                'colonia'        => $c->cliente_colonia,
                'ciudad'         => $c->cliente_ciudad,
                'estado'         => $c->cliente_estado,
                'descripcion'    => $c->cliente_descripcion,
                'ubicacion'      => $c->cliente_ubicacion,
                'fecha_registro' => $this->limpiarFecha($c->cliente_fecha),
                'ultima_compra'  => $this->limpiarFecha($c->cliente_compra),
                'status'         => $c->cliente_status ?: 'Activo',
            ]);
        }
    }

    /**
     * Helper para validar y sanitizar fechas heredadas de bases de datos antiguas
     */
    private function limpiarFecha($fecha): ?string
    {
        if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00' || str_starts_with((string)$fecha, '-')) {
            return null;
        }

        try {
            $parsed = Carbon::parse($fecha);
            return $parsed->year > 1900 ? $parsed->format('Y-m-d') : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}