<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        
        // 1. Tabla de Roles (UUID)
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();          // Nombre interno: 'admin', 'vendedor', 'cobrador'
            $table->string('display_name');           // Nombre visible: 'Administrador', 'Vendedor'
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Tabla de Permisos por Módulo (UUID)
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();          // Clave única: 'pos.access', 'inventory.transfer'
            $table->string('module');                  // Módulo: 'POS', 'Inventario', 'Cobranza'
            $table->string('display_name');           // Nombre visible: 'Acceder al POS'
            $table->timestamps();
        });

        // 3. Pivote: Rol tiene Permisos
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignUuid('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->primary(['role_id', 'permission_id']);
        });

        // 4. Pivote: Usuario tiene Roles
        Schema::create('user_has_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('role_id')->constrained('roles')->onDelete('cascade');
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};