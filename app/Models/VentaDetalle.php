<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'venta_detalles';

    protected $fillable = [
        'id',
        'venta_id',
        'articulo_id',
        'cantidad',
        'nombre_articulo',
        'numero_serie',
        'precio_unitario',
        'descuento',
        'pago_cuota_articulo',
        'subtotal',
    ];

    // Relación con la Venta principal
    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    // Relación con el Artículo del catálogo
    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}