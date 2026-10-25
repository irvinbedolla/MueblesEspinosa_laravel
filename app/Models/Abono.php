<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Abono extends Model
{
    use HasUuids;

    protected $table = 'abonos';

    protected $fillable = [
        'id',
        'a_id',
        'a_venta',
        'venta_id',
        'a_pago',
        'a_fecha',
        'a_npago',
        'a_cobrador',
        'a_tipopago',
        'a_tipo',
        'a_hora',
        'observaciones'
    ];
}