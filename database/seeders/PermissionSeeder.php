<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            // Dashboard
            ['module' => 'Dashboard', 'name' => 'dashboard.view', 'display_name' => 'Ver Panel de Inicio y Estadísticas'],

            // POS / Ventas (conservando tus 3 existentes)
            ['module' => 'POS / Ventas', 'name' => 'pos.access', 'display_name' => 'Acceder al Punto de Venta'],
            ['module' => 'POS / Ventas', 'name' => 'pos.discount', 'display_name' => 'Aplicar Descuentos en POS'],
            ['module' => 'POS / Ventas', 'name' => 'pos.change_date', 'display_name' => 'Cambiar Fecha de Venta'],
            ['module' => 'POS / Ventas', 'name' => 'ventas.view', 'display_name' => 'Consultar Historial de Ventas'],
            ['module' => 'POS / Ventas', 'name' => 'ventas.edit', 'display_name' => 'Editar Ventas / Cambiar Artículo o Estatus'],
            ['module' => 'POS / Ventas', 'name' => 'ventas.delete', 'display_name' => 'Eliminar o Cancelar Ventas'],

            // Clientes
            ['module' => 'Clientes', 'name' => 'clientes.view', 'display_name' => 'Ver Directorio de Clientes'],
            ['module' => 'Clientes', 'name' => 'clientes.create', 'display_name' => 'Registrar Nuevos Clientes'],
            ['module' => 'Clientes', 'name' => 'clientes.edit', 'display_name' => 'Editar Datos y GPS de Clientes'],
            ['module' => 'Clientes', 'name' => 'clientes.delete', 'display_name' => 'Eliminar Clientes'],

            // Cobranza y Rutas (conservando tus 2 existentes)
            ['module' => 'Cobranza', 'name' => 'cobranza.access', 'display_name' => 'Acceder a Módulo de Cobros'],
            ['module' => 'Cobranza', 'name' => 'cobranza.register', 'display_name' => 'Registrar Abonos en Campo'],
            ['module' => 'Cobranza', 'name' => 'abonos.delete', 'display_name' => 'Eliminar o Cancelar Abonos'],
            ['module' => 'Cobranza', 'name' => 'rutas.view', 'display_name' => 'Ver Agenda y Rutas de Cobro'],
            ['module' => 'Cobranza', 'name' => 'rutas.reagendar', 'display_name' => 'Reagendar Fechas de Cobro en Ruta'],
            ['module' => 'Cobranza', 'name' => 'rutas.asignar', 'display_name' => 'Asignar o Reasignar Cobrador a Cuentas'],
            ['module' => 'Cobranza', 'name' => 'cobranza.movil', 'display_name' => 'Acceder a Vista Móvil de Cobranza'],

            // Inventario (conservando tus 2 existentes)
            ['module' => 'Inventario', 'name' => 'inventory.view', 'display_name' => 'Ver Existencias e Inventario'],
            ['module' => 'Inventario', 'name' => 'inventory.transfer', 'display_name' => 'Realizar Traspasos de Almacén'],
            ['module' => 'Inventario', 'name' => 'inventory.create', 'display_name' => 'Crear Artículos y Surtir Almacén'],
            ['module' => 'Inventario', 'name' => 'inventory.edit', 'display_name' => 'Editar Artículos y Precios'],
            ['module' => 'Inventario', 'name' => 'inventory.delete', 'display_name' => 'Eliminar Artículos'],
            ['module' => 'Inventario', 'name' => 'inventory.kardex', 'display_name' => 'Ver Kardex y Bajo Stock'],

            // Gastos y Préstamos
            ['module' => 'Gastos', 'name' => 'gastos.view', 'display_name' => 'Ver Módulo de Gastos y Préstamos'],
            ['module' => 'Gastos', 'name' => 'gastos.create', 'display_name' => 'Registrar Gastos y Préstamos'],
            ['module' => 'Gastos', 'name' => 'gastos.edit', 'display_name' => 'Editar Gastos / Abonar a Préstamos'],
            ['module' => 'Gastos', 'name' => 'gastos.delete', 'display_name' => 'Eliminar Gastos o Préstamos'],

            // Cierres y Reportes
            ['module' => 'Cierres y Reportes', 'name' => 'cierres.view', 'display_name' => 'Acceder a Cierres de Caja'],
            ['module' => 'Cierres y Reportes', 'name' => 'cierres.all', 'display_name' => 'Generar Cierres de Otros Usuarios'],
            ['module' => 'Cierres y Reportes', 'name' => 'reportes.view', 'display_name' => 'Ver y Exportar Reportes Generales'],

            // Administración (conservando tus 2 existentes)
            ['module' => 'Administración', 'name' => 'users.manage', 'display_name' => 'Gestionar Usuarios (Ver/Crear/Editar/Borrar)'],
            ['module' => 'Administración', 'name' => 'roles.manage', 'display_name' => 'Gestionar Roles y Permisos'],
        ];

        $creados = 0;
        $actualizados = 0;

        foreach ($permisos as $p) {
            $existe = DB::table('permissions')->where('name', $p['name'])->first();

            if ($existe) {
                DB::table('permissions')->where('id', $existe->id)->update([
                    'module'       => $p['module'],
                    'display_name' => $p['display_name'],
                    'updated_at'   => now(),
                ]);
                $actualizados++;
            } else {
                DB::table('permissions')->insert([
                    'id'           => (string) Str::uuid(),
                    'name'         => $p['name'],
                    'module'       => $p['module'],
                    'display_name' => $p['display_name'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $creados++;
            }
        }

        $this->command->info("Permisos listos: {$creados} nuevos creados y {$actualizados} existentes conservados.");
    }
}