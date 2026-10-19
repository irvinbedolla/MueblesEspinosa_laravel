<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('articulo_precios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->integer('meses'); // 1 a 12
            $table->decimal('precio_total', 12, 2); // Precio con el ajuste asignado
            $table->decimal('pago_mensual', 12, 2); // Precio total / meses

            $table->timestamps();
            
            $table->unique(['articulo_id', 'meses']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('articulo_precios');
    }
};