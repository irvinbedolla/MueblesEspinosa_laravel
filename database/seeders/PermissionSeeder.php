<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Módulo POS
            ['name' => 'pos.access', 'module' => 'POS / Ventas', 'display_name' => 'Acceder al Punto de Venta'],
            ['name' => 'pos.discount', 'module' => 'POS / Ventas', 'display_name' => 'Aplicar Descuentos en POS'],
            ['name' => 'pos.change_date', 'module' => 'POS / Ventas', 'display_name' => 'Cambiar Fecha de Venta'],

            // Módulo Cobranza
            ['name' => 'cobranza.access', 'module' => 'Cobranza', 'display_name' => 'Acceder a Módulo de Cobros'],
            ['name' => 'cobranza.register', 'module' => 'Cobranza', 'display_name' => 'Registrar Abonos en Campo'],
            
            // Módulo Inventario
            ['name' => 'inventory.view', 'module' => 'Inventario', 'display_name' => 'Ver Existencias e Inventario'],
            ['name' => 'inventory.transfer', 'module' => 'Inventario', 'display_name' => 'Realizar Traspasos de Almacén'],

            // Módulo Usuarios y Roles
            ['name' => 'users.manage', 'module' => 'Administración', 'display_name' => 'Gestionar Usuarios'],
            ['name' => 'roles.manage', 'module' => 'Administración', 'display_name' => 'Gestionar Roles y Permisos'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(
                ['name' => $p['name']],
                [
                    'id'           => Str::uuid()->toString(),
                    'module'       => $p['module'],
                    'display_name' => $p['display_name']
                ]
            );
        }
    }
}