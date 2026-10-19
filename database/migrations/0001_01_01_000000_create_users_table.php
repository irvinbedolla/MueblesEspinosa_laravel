<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            // Llave primaria única en formato UUID para soporte Offline
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id_legacy')->nullable()->index();
            // Credenciales de acceso
            $table->string('username')->unique();        // Reemplaza a 'us_usuario' y 'name'
            $table->string('password');                  // Encriptado Bcrypt
            
            // Roles: 0 = Administrador, 1 = Vendedor, 2 = Cobrador
            $table->tinyInteger('role')->default(1); 
            
            // Estado de la cuenta: 1 = Activo, 0 = Inactivo
            $table->boolean('is_active')->default(true); 

            $table->rememberToken();                     // Mantiene la sesión activa offline
            $table->timestamps();                        // created_at y updated_at
            $table->softDeletes();                      // deleted_at (Baja lógica)
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};