<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('articulos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 100);
            $table->string('marca', 50)->nullable();
            $table->string('modelo', 50)->nullable();
            $table->string('barras', 50)->nullable()->index();
            $table->string('n_serie', 50)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('categoria', 50)->nullable();

            // Costos y Precios Base
            $table->decimal('pcompra', 12, 2)->default(0);
            $table->decimal('pcontado', 12, 2)->default(0);
            $table->decimal('pcredito', 12, 2)->default(0); // Precio base de crédito ($pre1)
            $table->decimal('porcentaje', 5, 2)->default(0); // % Utilidad asignado

            // Control de Alerta
            $table->integer('stock_min')->default(5);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('articulos');
    }
};