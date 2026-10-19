<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventario_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('articulo_id')->constrained('articulos')->cascadeOnDelete();
            
            // user_id NULL = Almacén General | user_id = Inventario del Vendedor
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->integer('cantidad')->default(0);

            $table->timestamps();

            // Un vendedor (o almacén) solo puede tener un registro de stock por artículo
            $table->unique(['articulo_id', 'user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('inventario_stock');
    }
};