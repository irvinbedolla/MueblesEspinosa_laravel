<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kardex', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users'); // Usuario que realizó la acción
            
            // Origen y Destino del movimiento
            $table->foreignUuid('origen_user_id')->nullable()->constrained('users'); // NULL = Almacén
            $table->foreignUuid('destino_user_id')->nullable()->constrained('users'); // NULL = Almacén

            // Tipo: 'Entrada_Inicial', 'Traspaso_Vendedor', 'Devolucion_Almacen', 'Venta', 'Ajuste_Stock'
            $table->string('tipo_movimiento', 40);
            $table->integer('cantidad');
            $table->integer('stock_anterior');
            $table->integer('stock_nuevo');
            $table->string('observaciones', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('kardex');
    }
};