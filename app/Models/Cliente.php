<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'id',
        'nombre',
        'telefono',
        'email',
        'ine',
        'calle',
        'colonia',
        'ciudad',
        'estado',
        'descripcion',
        'ubicacion',
        'fecha_registro',
        'ultima_compra',
        'status',
        'user_id',
        'latitud',
        'longitud',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
        'ultima_compra'  => 'date',
    ];

    // Relación con el Cobrador / Vendedor asignado
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}