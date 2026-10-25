<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('a_venta')->nullable();
            $table->uuid('venta_id');
            $table->decimal('a_pago', 10, 2);
            $table->dateTime('a_fecha');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Opcional: Clave foránea con la tabla ventas
            // $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos');
    }
};