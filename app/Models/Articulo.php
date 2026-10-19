<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Articulo extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'id', 'nombre', 'marca', 'modelo', 'barras', 'n_serie', 
        'descripcion', 'categoria', 'pcompra', 'pcontado', 
        'pcredito', 'porcentaje', 'stock_min'
    ];

    // Relación con los precios calculados por meses
    public function precios()
    {
        return $this->hasMany(ArticuloPrecio::class)->orderBy('meses');
    }

    // Relación con los registros de stock en distintas ubicaciones/vendedores
    public function stocks()
    {
        return $this->hasMany(InventarioStock::class);
    }

    // Helper para obtener el stock total global
    public function getStockTotalAttribute(): int
    {
        return $this->stocks()->sum('cantidad');
    }

    // Helper para obtener el stock específico en Almacén General
    public function getStockAlmacenAttribute(): int
    {
        return $this->stocks()->whereNull('user_id')->value('cantidad') ?? 0;
    }
}