<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clientes', function (Blueprint $table) {
            // Llave primaria UUID (Requerida por Laravel / Offline-First)
            $table->uuid('id')->primary();
            
            // Campo auxiliar para conservar el ID histórico (100, 1000, 1001...)
            $table->unsignedBigInteger('cliente_id')->nullable()->index();

            // Datos generales
            $table->string('nombre', 100);
            $table->string('telefono', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('ine', 30)->nullable();
            
            // Dirección y notas
            $table->string('calle', 100)->nullable();
            $table->string('colonia', 100)->nullable();
            $table->string('ciudad', 50)->nullable();
            $table->string('estado', 50)->nullable();
            $table->text('descripcion')->nullable();
            
            // Geolocalización y Maps
            $table->string('ubicacion', 255)->nullable();
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            
            // Fechas e Historial
            $table->date('fecha_registro')->nullable();
            $table->date('ultima_compra')->nullable();
            
            // Estatus: 'Activo', 'Lista_Negra', 'Inactivo'
            $table->string('status', 20)->default('Activo');
            
            // Cobrador / Vendedor asignado a la cuenta
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('clientes');
    }
};