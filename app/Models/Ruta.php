<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ruta extends Model
{
    use HasFactory;

    protected $table = 'rutas';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'nombre',
        'cobrador_id',
        'fecha_ruta',
        'status'
    ];

    // Asignación automática de UUID al crear
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    // Relación con el Cobrador (Usuario)
    public function cobrador()
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }

    // Relación con los detalles de la ruta
    public function detalles()
    {
        return $this->hasMany(RutaDetalle::class, 'ruta_id')->orderBy('orden', 'asc');
    }
}