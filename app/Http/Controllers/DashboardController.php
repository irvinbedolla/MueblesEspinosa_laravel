<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Datos mock/estáticos según el rol para alimentar las gráficas
        $data = [];

        if ($user->hasRole('admin') || $user->isAdmin()) {
            $data = [
                'ventas_mes_monto'  => '$145,800.00',
                'ventas_mes_cant'   => 48,
                'abonos_mes_monto'  => '$92,300.00',
                'abonos_mes_cant'   => 124,
                'chart_ventas'      => [12000, 19000, 15000, 25000, 22000, 30000],
                'chart_labels'      => ['May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct'],
            ];
        } elseif ($user->hasRole('vendedor') || $user->isVendedor()) {
            $data = [
                'ventas_propias_cant'  => 18,
                'ventas_propias_monto' => '$54,200.00',
                'meta_mes_porcentaje'  => 75,
                'chart_rendimiento'    => [3, 5, 2, 4, 4],
                'chart_labels'         => ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Actual'],
            ];
        } else { // Cobrador
            $data = [
                'cobros_hoy_cant'     => 14,
                'cobros_hoy_monto'    => '$8,400.00',
                'cobros_completados'  => 6,
                'cobros_pendientes'   => 8,
                'chart_cobranza_hoy'  => [6, 8], // Completados vs Pendientes
            ];
        }

        return view('dashboard', compact('user', 'data'));
    }
}