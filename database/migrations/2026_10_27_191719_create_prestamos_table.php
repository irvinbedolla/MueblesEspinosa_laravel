<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('prestamo_id_legacy')->nullable()->index(); // Mantiene 11, 13, 17... (Imagen 2)
            $table->string('descripcion');                                         // Corresponde a pres_descripcion
            $table->decimal('costo', 10, 2)->default(0.00);                        // Corresponde a pres_costo
            $table->decimal('monto_total', 10, 2)->default(0.00);
            $table->decimal('saldo_restante', 10, 2)->default(0.00);                   // Corresponde a pres_restante
            $table->date('fecha');                                                 // Corresponde a pres_fecha
            $table->string('user_id')->nullable()->index();                        // Corresponde a pres_usuario (ID 6, 8...)
            $table->string('status')->default('Activo');                           // 'Activo' o 'Pagado'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};