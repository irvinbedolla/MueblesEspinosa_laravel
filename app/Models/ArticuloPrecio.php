<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ArticuloPrecio extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'articulo_id', 'meses', 'precio_total', 'pago_mensual'];

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }
}