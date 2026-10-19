<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class LegacyUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Opcional: Leer directamente de la BD vieja 'muebles_espinosa'
        $usuariosViejos = DB::connection('mysql') // Asegúrate de tener acceso a los datos
            ->table('usuarios')
            ->get();

        foreach ($usuariosViejos as $u) {
            User::updateOrCreate(
                ['us_id' => $u->us_id],
                [
                    'id'        => Str::uuid()->toString(),
                    'name'      => $u->us_usuario,
                    'username'  => strtolower(trim($u->us_usuario)),
                    'password'  => Hash::make($u->us_pass), // Encripta la contraseña de texto plano a Bcrypt
                    'us_tipo'   => $u->us_tipo,
                    'us_status' => $u->us_status,
                    'sucursal'  => 'MATRIZ',
                ]
            );
        }
    }
}