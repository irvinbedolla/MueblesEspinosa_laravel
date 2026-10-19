<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Consultar directamente la tabla vieja 'usuarios_old'
        $usuariosViejos = DB::connection('mysql')->table('usuarios_old')->get();

        foreach ($usuariosViejos as $u) {
            
            // Generar un correo electrónico único basado en su nombre de usuario
            $usernameLimpio = Str::slug($u->us_usuario, '');
            $emailFicticio = $usernameLimpio . $u->us_id . '@mueblesespinosa.com';

            // Evitar duplicados si ya existe el usuario
            $existe = User::where('user_id_legacy', $u->us_id)->first();

            if (!$existe) {
                User::create([
                    'id'             => Str::uuid()->toString(),
                    'user_id_legacy' => $u->us_id, // 👈 Se guarda el 'us_id' (1, 2, 3, 6, 7...)
                    'username'       => strtolower(trim($u->us_usuario)),
                    'email'          => $emailFicticio, // Asigna un correo válido
                    'password'       => Hash::make($u->us_pass ?: '123456'), // Hashea la contraseña para Laravel
                    'is_active'      => ($u->us_status == 0), // 0 es activo en la DB previa
                ]);
            }
        }
    }
}