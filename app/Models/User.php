<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Roles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id_legacy',
        'email',
        'username',
        'password',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Relación con Roles especificando la tabla pivote y las llaves foráneas exactas
     */
    public function roles()
    {
        return $this->belongsToMany(
            Roles::class,
            'user_has_roles', // Nombre de la tabla pivote
            'user_id',        // Clave foránea de esta tabla (users)
            'role_id'         // Clave foránea del modelo relacionado (roles)
        );
    }

    /**
     * Verificar si el usuario tiene un permiso específico consultando sus roles
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()->whereHas('permissions', function ($q) use ($permissionName) {
            $q->where('name', $permissionName);
        })->exists();
    }

    /**
     * Verificar si el usuario tiene un rol específico (insensible a mayúsculas/minúsculas)
     * Soporta también 'admin' como alias de 'administrador'
     */
    public function hasRole(string $roleName): bool
    {
        $buscado = strtolower(trim($roleName));
        $rolesUsuario = $this->roles->pluck('name')->map(fn ($n) => strtolower(trim((string) $n)));

        if ($buscado === 'admin' || $buscado === 'administrador') {
            return $rolesUsuario->contains('administrador') || $rolesUsuario->contains('admin');
        }

        return $rolesUsuario->contains($buscado);
    }

    /**
     * Verificar si el usuario es Administrador
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('administrador')
            || strtolower(trim((string) $this->username)) === 'admin';
    }

    public function isVendedor(): bool
    {
        return $this->hasRole('vendedor');
    }

    public function isCobrador(): bool
    {
        return $this->hasRole('cobrador');
    }

    public function tienePermiso(string $permiso): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permiso))
            ->exists();
    }
}