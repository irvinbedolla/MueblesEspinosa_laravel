<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RutaDetalle extends Model
{
    use HasFactory;

    protected $table = 'ruta_detalles';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'ruta_id',
        'venta_id',
        'orden',
        'monto_sugerido',
        'monto_cobrado',
        'status',
        'observaciones',
        'fecha_cobro'
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

    // Relación con la Cabecera de la Ruta
    public function ruta()
    {
        return $this->belongsTo(Ruta::class, 'ruta_id');
    }

    // Relación con la Venta asociada
    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}