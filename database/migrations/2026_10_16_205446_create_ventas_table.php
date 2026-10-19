<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ventas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Folio y Referencia Legacy
            $table->unsignedBigInteger('folio')->nullable()->index();
            $table->unsignedBigInteger('venta_id_legacy')->nullable()->index();

            // Tipos y Fechas
            $table->string('tipo_venta', 30)->default('Credito'); // 'Contado', 'Credito'
            $table->date('fecha_venta');
            $table->time('hora_venta')->nullable();
            $table->string('forma_pago', 50)->default('Efectivo'); // 'Efectivo', 'tcredito', 'tdebito', 'Transferencia'

            // Relaciones
            $table->foreignUuid('cliente_id')->constrained('clientes');
            $table->foreignUuid('vendedor_id')->constrained('users');
            $table->foreignUuid('cobrador_id')->nullable()->constrained('users');

            // Parámetros de Crédito
            $table->integer('num_pagos')->default(1); // Equivalente a 'venta_pagos'
            $table->integer('frecuencia_dias')->default(7); // 7, 15, 30 días
            $table->string('frecuencia_nombre', 20)->default('Semanal'); // 'Semanal', 'Quincenal', 'Mensual'
            $table->date('fecha_primer_pago')->nullable(); // 'venta_fecha_pago'
            $table->date('fecha_final_pago')->nullable(); // 'venta_pagof'
            $table->string('dia_semana', 20)->nullable(); // 'lunes', 'martes', 'miércoles', etc.

            // Montos y Saldos
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('credito_monto', 12, 2)->default(0); // 'venta_credito'
            $table->decimal('anticipo', 12, 2)->default(0); // 'venta_anticipo'
            $table->decimal('descuento', 12, 2)->default(0); // 'venta_descuento'
            $table->decimal('total', 12, 2)->default(0); // 'venta_total'
            $table->decimal('saldo_restante', 12, 2)->default(0); // 'venta_restante'
            $table->decimal('pago_cuota', 12, 2)->default(0); // Pago periódico (semanal/quincenal/mensual)

            // Comentarios y Estatus Legacy Completo
            $table->text('comentario')->nullable();
            $table->enum('status', [
                'Pendiente', 'Pagado', 'Validar', 'Eliminar', 
                'Devolucion', 'Recogido', 'Fuga', 'Problema', 
                'Descompuesto', 'Abierta'
            ])->default('Pendiente');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ventas');
    }
};