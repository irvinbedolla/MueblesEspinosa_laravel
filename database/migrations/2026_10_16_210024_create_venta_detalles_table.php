<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignUuid('articulo_id')->constrained('articulos');

            $table->integer('cantidad');
            $table->string('nombre_articulo', 100);
            $table->string('numero_serie', 50)->nullable(); // 'va_nserie'
            $table->decimal('precio_unitario', 12, 2); // 'va_precio'
            $table->decimal('descuento', 12, 2)->default(0); // 'va_descuento'
            $table->decimal('pago_cuota_articulo', 12, 2)->default(0); // 'va_pago'
            $table->decimal('subtotal', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('venta_detalles');
    }
};