<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRutasTable extends Migration
{
    public function up(): void
    {
        Schema::create('rutas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre'); // Ej: Ruta Centro, Ruta Norte
            $table->uuid('cobrador_id'); // Relación con el usuario cobrador
            $table->date('fecha_ruta');
            $table->enum('status', ['Pendiente', 'En Proceso', 'Completada'])->default('Pendiente');
            $table->timestamps();

            $table->foreign('cobrador_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('ruta_detalles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ruta_id');
            $table->unsignedBigInteger('venta_id'); // Venta asociada (Asegurado como unsignedBigInteger)
            $table->integer('orden')->default(0); // Orden de visita
            $table->decimal('monto_sugerido', 10, 2)->default(0);
            $table->decimal('monto_cobrado', 10, 2)->nullable();
            $table->enum('status', ['Pendiente', 'Cobrado', 'No_Encontrado', 'Promesa_Pago'])->default('Pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_cobro')->nullable();
            $table->timestamps();

            $table->foreign('ruta_id')->references('id')->on('rutas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_detalles');
        Schema::dropIfExists('rutas');
    }
}