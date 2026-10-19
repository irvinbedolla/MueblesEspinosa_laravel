<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'name', 'module', 'display_name'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }
}