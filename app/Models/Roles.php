<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'name', 'display_name', 'description'];

    /**
     * Relación con Permisos especificando la tabla pivote y las claves foráneas exactas
     */
    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class, 
            'role_has_permissions', // Tabla pivote
            'role_id',              // Clave foránea del modelo actual (Role)
            'permission_id'         // Clave foránea del modelo relacionado (Permission)
        );
    }

    /**
     * Relación con Usuarios especificando la tabla pivote y las claves foráneas exactas
     */
    public function users()
    {
        return $this->belongsToMany(
            User::class, 
            'user_has_roles', // Tabla pivote
            'role_id',        // Clave foránea del modelo actual (Role)
            'user_id'         // Clave foránea del modelo relacionado (User)
        );
    }
}