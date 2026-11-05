<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('gasto_id_legacy')->nullable()->index(); // Mantiene 3, 4, 5... (Imagen 1)
            $table->string('descripcion');                                      // Corresponde a gasto_descripcion
            $table->decimal('costo', 10, 2)->default(0.00);                     // Corresponde a gasto_costo
            $table->decimal('monto', 10, 2)->default(0.00);                     // Duplicado de respaldo para consultas
            $table->date('fecha');                                              // Corresponde a gasto_fecha
            $table->string('tipo')->default('Gasto');                           // Distingue entre 'Gasto' y 'Prestamo'
            $table->uuid('user_id')->nullable()->index();                       // Usuario que registró el gasto
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};