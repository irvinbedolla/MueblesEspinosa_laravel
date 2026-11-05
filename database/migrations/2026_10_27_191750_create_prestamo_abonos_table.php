<?php 


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamo_abonos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('abono_id_legacy')->nullable()->index();    // Mantiene idPP: 1, 2, 3... (Imagen 3)
            $table->uuid('prestamo_id')->nullable()->index();                      // Relación UUID
            $table->unsignedBigInteger('prestamo_id_legacy')->nullable()->index(); // Relación con idPrestamo (Imagen 3)
            $table->decimal('monto', 10, 2)->default(0.00);                        // Corresponde a monto
            $table->date('fecha');                                                 // Corresponde a fechaPretamo
            $table->string('user_id')->nullable();                                 // Registrado por
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_abonos');
    }
};