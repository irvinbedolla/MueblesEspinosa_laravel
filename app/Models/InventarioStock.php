<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InventarioStock extends Model
{
    use HasUuids;

    protected $table = 'inventario_stock';

    protected $fillable = ['id', 'articulo_id', 'user_id', 'cantidad'];

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}