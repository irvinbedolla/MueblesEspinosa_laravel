<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'ventas';

    protected $fillable = [
        'id',
        'folio',
        'venta_id_legacy',
        'tipo_venta',
        'fecha_venta',
        'hora_venta',
        'forma_pago',
        'cliente_id',
        'vendedor_id',
        'cobrador_id',
        'num_pagos',
        'frecuencia_dias',
        'frecuencia_nombre',
        'fecha_primer_pago',
        'fecha_final_pago',
        'dia_semana',
        'subtotal',
        'credito_monto',
        'anticipo',
        'descuento',
        'total',
        'saldo_restante',
        'pago_cuota',
        'comentario',
        'status',
    ];

    // Relación con el Cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    // Relación con el Vendedor
    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    // Relación con el Cobrador asignado
    public function cobrador()
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }

    // Relación con los artículos detallados en la venta
    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }
}