<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Kardex extends Model
{
    use HasUuids;

    protected $table = 'kardex';

    protected $fillable = [
        'id', 'articulo_id', 'user_id', 'origen_user_id', 
        'destino_user_id', 'tipo_movimiento', 'cantidad', 
        'stock_anterior', 'stock_nuevo', 'observaciones'
    ];

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function origen()
    {
        return $this->belongsTo(User::class, 'origen_user_id');
    }

    public function destino()
    {
        return $this->belongsTo(User::class, 'destino_user_id');
    }
}