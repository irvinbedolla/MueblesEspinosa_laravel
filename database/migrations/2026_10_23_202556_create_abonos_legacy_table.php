<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            // Campos para soportar la importación del sistema legacy
            $table->integer('a_id')->nullable()->after('id'); // ID entero original
            $table->integer('a_npago')->nullable()->default(1)->after('a_fecha'); // Número de pago
            $table->string('a_cobrador', 100)->nullable()->default('0')->after('a_npago'); // Cobrador/Usuario
            $table->string('a_tipopago', 50)->nullable()->default('Efectivo')->after('a_cobrador'); // Forma de pago
            $table->string('a_tipo', 50)->nullable()->default('Abono')->after('a_tipopago'); // Tipo de movimiento
            $table->dateTime('a_hora')->nullable()->after('a_tipo'); // Hora original del abono
        });
    }

    public function down(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            $table->dropColumn(['a_id', 'a_npago', 'a_cobrador', 'a_tipopago', 'a_tipo', 'a_hora']);
        });
    }
};