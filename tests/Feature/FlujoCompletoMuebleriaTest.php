<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\InventarioStock;
use App\Models\Roles;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlujoCompletoMuebleriaTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $vendedor;
    protected User $cobrador;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear roles base incluyendo display_name
        $rolAdmin = Roles::firstOrCreate(
            ['name' => 'administrador'],
            ['id' => Str::uuid()->toString(), 'display_name' => 'Administrador']
        );
        $rolVendedor = Roles::firstOrCreate(
            ['name' => 'vendedor'],
            ['id' => Str::uuid()->toString(), 'display_name' => 'Vendedor']
        );
        $rolCobrador = Roles::firstOrCreate(
            ['name' => 'cobrador'],
            ['id' => Str::uuid()->toString(), 'display_name' => 'Cobrador']
        );

        $this->admin = User::create([
            'id'        => Str::uuid()->toString(),
            'username'  => 'admin_test',
            'email'     => 'admin_test@mueblesespinosa.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($rolAdmin->id);

        $this->vendedor = User::create([
            'id'        => Str::uuid()->toString(),
            'username'  => 'vendedor_test',
            'email'     => 'vendedor_test@mueblesespinosa.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->vendedor->roles()->attach($rolVendedor->id);

        $this->cobrador = User::create([
            'id'        => Str::uuid()->toString(),
            'username'  => 'cobrador_test',
            'email'     => 'cobrador_test@mueblesespinosa.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->cobrador->roles()->attach($rolCobrador->id);
    }

    public function test_1_ciclo_completo_inventario_traspaso_venta_y_abonos(): void
    {
        // 1. Alta de artículo en Almacén General
        $this->actingAs($this->admin)->post(route('articulos.store'), [
            'nombre'        => 'REFRIGERADOR PRUEBA 14P',
            'marca'         => 'MABE',
            'modelo'        => 'RMA14',
            'barras'        => '7501234567890',
            'pcompra'       => 5000,
            'pcontado'      => 7500,
            'pcredito'      => 9000,
            'stock_inicial' => 10,
            'stock_min'     => 2,
        ])->assertRedirect(route('articulos.index'));

        $articulo = Articulo::where('barras', '7501234567890')->firstOrFail();
        $this->assertEquals(10, InventarioStock::where('articulo_id', $articulo->id)->whereNull('user_id')->value('cantidad'));

        // 2. Bloqueo de artículo duplicado
        $this->actingAs($this->admin)->post(route('articulos.store'), [
            'nombre'        => 'REFRIGERADOR PRUEBA 14P',
            'marca'         => 'MABE',
            'modelo'        => 'RMA14',
            'pcompra'       => 5000,
            'pcontado'      => 7500,
            'pcredito'      => 9000,
            'stock_inicial' => 5,
            'stock_min'     => 2,
        ])->assertSessionHas('error');

        // 3. Traspaso de 4 piezas de Almacén a Vendedor
        $this->actingAs($this->admin)->post(route('articulos.traspaso.store'), [
            'articulo_id' => $articulo->id,
            'origen'      => 'almacen',
            'destino'     => $this->vendedor->id,
            'cantidad'    => 4,
        ])->assertRedirect(route('articulos.index'));

        $this->assertEquals(6, InventarioStock::where('articulo_id', $articulo->id)->whereNull('user_id')->value('cantidad'));
        $this->assertEquals(4, InventarioStock::where('articulo_id', $articulo->id)->where('user_id', $this->vendedor->id)->value('cantidad'));

        // 4. Venta a crédito en POS (1 pieza con $1,000 de anticipo)
        $clienteId = Str::uuid()->toString();
        DB::table('clientes')->insert([
            'id' => $clienteId, 'nombre' => 'JUAN PEREZ PRUEBA', 'status' => 'Activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $payloadVenta = [
            'cliente_id'        => $clienteId,
            'vendedor_id'       => $this->vendedor->id,
            'cobrador_id'       => $this->cobrador->id,
            'tipo_venta'        => 'Credito',
            'fecha_venta'       => date('Y-m-d'),
            'forma_pago'        => 'Efectivo',
            'subtotal'          => 9000,
            'total'             => 9000,
            'anticipo'          => 1000,
            'frecuencia_nombre' => 'Semanal',
            'num_pagos'         => 16,
            'cuota_pago'        => 500,
            'articulos'         => [[
                'id'              => $articulo->id,
                'cantidad'        => 1,
                'precio_unitario' => 9000,
                'subtotal'        => 9000,
            ]],
        ];

        $resVenta = $this->actingAs($this->vendedor)->postJson(route('ventas.store'), $payloadVenta);
        $resVenta->assertOk()->assertJson(['status' => 'success']);

        $venta = Venta::findOrFail($resVenta->json('venta_id'));
        $this->assertEquals(8000, (float) $venta->saldo_restante);
        $this->assertEquals(3, InventarioStock::where('articulo_id', $articulo->id)->where('user_id', $this->vendedor->id)->value('cantidad'));

        // 5. Bloqueo de venta duplicada mismo día/cliente/artículos
        $this->actingAs($this->vendedor)
            ->postJson(route('ventas.store'), $payloadVenta)
            ->assertStatus(422);

        // 6. Abono parcial de $500 y liquidación
        $this->actingAs($this->cobrador)->postJson(route('abonos.store'), [
            'id'    => $venta->id,
            'abono' => 500,
            'fecha' => date('Y-m-d'),
            'fpago' => 'Efectivo',
            'tipo'  => 1,
        ])->assertOk();

        $venta->refresh();
        $this->assertEquals(7500, (float) $venta->saldo_restante);

        $this->actingAs($this->cobrador)->postJson(route('abonos.store'), [
            'id'    => $venta->id,
            'abono' => 7500,
            'fecha' => date('Y-m-d'),
            'fpago' => 'Efectivo',
            'tipo'  => 2,
        ])->assertOk();

        $venta->refresh();
        $this->assertEquals(0, (float) $venta->saldo_restante);
        $this->assertEquals('Pagado', $venta->status);
    }

    public function test_2_validacion_de_limites_stock_y_abonos_excedidos(): void
    {
        $articulo = Articulo::create([
            'id'         => Str::uuid()->toString(),
            'nombre'     => 'ESTUFA PRUEBA LIMITES',
            'pcompra'    => 2000,
            'pcontado'   => 3000,
            'pcredito'   => 3800,
            'porcentaje' => 90,
            'stock_min'  => 1,
        ]);

        InventarioStock::create([
            'id'          => Str::uuid()->toString(),
            'articulo_id' => $articulo->id,
            'user_id'     => null,
            'cantidad'    => 2,
        ]);

        // Intentar traspasar 10 piezas cuando solo hay 2 (debe regresar con error de sesión, NO error 500)
        $resTraspaso = $this->actingAs($this->admin)->post(route('articulos.traspaso.store'), [
            'articulo_id' => $articulo->id,
            'origen'      => 'almacen',
            'destino'     => $this->vendedor->id,
            'cantidad'    => 10,
        ]);
        $resTraspaso->assertSessionHas('error');
        $this->assertEquals(2, InventarioStock::where('articulo_id', $articulo->id)->whereNull('user_id')->value('cantidad'));

        // Intentar registrar un abono mayor al saldo restante de una venta
        $clienteId = Str::uuid()->toString();
        DB::table('clientes')->insert([
            'id' => $clienteId, 'nombre' => 'CLIENTE LIMITE ABONO', 'status' => 'Activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $ventaId = Str::uuid()->toString();
        DB::table('ventas')->insert([
            'id'             => $ventaId,
            'cliente_id'     => $clienteId,
            'vendedor_id'    => $this->vendedor->id,
            'cobrador_id'    => $this->cobrador->id,
            'tipo_venta'     => 'Credito',
            'fecha_venta'    => date('Y-m-d'),
            'total'          => 1500,
            'saldo_restante' => 1500,
            'status'         => 'Pendiente',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->actingAs($this->cobrador)->postJson(route('abonos.store'), [
            'id'    => $ventaId,
            'abono' => 2000, // Mayor al saldo de $1,500
            'fecha' => date('Y-m-d'),
            'fpago' => 'Efectivo',
            'tipo'  => 1,
        ])->assertStatus(422);
    }

    public function test_3_todas_las_vistas_principales_cargan_sin_error_500_para_los_3_roles(): void
    {
        $rutasNombres = [
            'dashboard',
            'articulos.index',
            'articulos.create',
            'articulos.traspaso',
            'articulos.kardex',
            'articulos.bajo_stock',
            'ventas.index',
            'ventas.pos',
            'abonos.index',
            'clientes.index',
        ];

        $usuarios = [
            'administrador' => $this->admin,
            'vendedor'      => $this->vendedor,
            'cobrador'      => $this->cobrador,
        ];

        foreach ($usuarios as $rolNombre => $usuario) {
            foreach ($rutasNombres as $ruta) {
                if (!Route::has($ruta)) {
                    continue;
                }

                $response = $this->actingAs($usuario)->get(route($ruta));

                if ($response->getStatusCode() === 500) {
                    $errorReal = $response->exception ? $response->exception->getMessage() . ' en ' . $response->exception->getFile() . ':' . $response->exception->getLine() : 'Error 500 desconocido';
                    $this->fail("La ruta [{$ruta}] lanzó error 500 como [{$rolNombre}]: {$errorReal}");
                }

                $this->assertNotEquals(500, $response->getStatusCode());
            }
        }
    }
}