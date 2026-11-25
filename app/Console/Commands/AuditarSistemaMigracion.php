<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditarSistemaMigracion extends Command
{
    protected $signature = 'sistema:auditar';
    protected $description = 'Verifica integridad de saldos, inventarios, clientes y roles antes de apagar el sistema anterior';

    public function handle(): int
    {
        $this->info('=== AUDITORÍA DE INTEGRIDAD DEL SISTEMA ===');

        // 1. Ventas activas con saldo descuadrado respecto a sus abonos
        $ventasDescuadradas = DB::select("
            SELECT v.id, v.folio, v.total, COALESCE(v.descuento, 0) AS descuento,
                   v.saldo_restante, COALESCE(SUM(a.a_pago), 0) AS total_abonado,
                   ROUND(v.total - COALESCE(v.descuento, 0) - COALESCE(SUM(a.a_pago), 0), 2) AS saldo_calculado
            FROM ventas v
            LEFT JOIN abonos a ON (a.venta_id = v.id OR a.a_venta = CAST(v.folio AS CHAR))
            WHERE v.status NOT IN ('Eliminar', 'Devolucion', 'Recogido')
            GROUP BY v.id, v.folio, v.total, v.descuento, v.saldo_restante
            HAVING ABS(v.saldo_restante - saldo_calculado) > 0.50
        ");
        $this->line('1. Ventas con diferencia entre saldo_restante y suma de abonos: <fg=yellow>' . count($ventasDescuadradas) . '</>');

        if (count($ventasDescuadradas) > 0) {
            $this->table(
                ['Folio', 'Total', 'Descuento', 'Abonado', 'Saldo BD', 'Saldo Calculado'],
                array_map(fn($r) => [$r->folio, $r->total, $r->descuento, $r->total_abonado, $r->saldo_restante, $r->saldo_calculado], array_slice($ventasDescuadradas, 0, 15))
            );
        }

        // 2. Ventas sin cliente válido
        $ventasSinCliente = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->whereNull('c.id')
            ->count();
        $this->line('2. Ventas cuyo cliente_id no existe en tabla clientes: <fg=yellow>' . $ventasSinCliente . '</>');

        // 3. Ventas pendientes sin cobrador asignado válido
        $ventasSinCobrador = DB::table('ventas as v')
            ->leftJoin('users as u', 'v.cobrador_id', '=', 'u.id')
            ->whereNotIn('v.status', ['Pagado', 'Eliminar', 'Devolucion', 'Recogido'])
            ->whereNull('u.id')
            ->count();
        $this->line('3. Ventas activas sin cobrador válido (UUID): <fg=yellow>' . $ventasSinCobrador . '</>');

        // 4. Stocks negativos en inventario
        $stocksNegativos = DB::table('inventario_stock')->where('cantidad', '<', 0)->count();
        $this->line('4. Registros de inventario con cantidad negativa: <fg=yellow>' . $stocksNegativos . '</>');

        // 5. Usuarios activos sin rol asignado
        $usuariosSinRol = DB::table('users as u')
            ->leftJoin('user_has_roles as uhr', 'u.id', '=', 'uhr.user_id')
            ->where('u.is_active', 1)
            ->whereNull('uhr.role_id')
            ->count();
        $this->line('5. Usuarios activos sin rol en user_has_roles: <fg=yellow>' . $usuariosSinRol . '</>');

        // 6. Artículos duplicados (Nombre + Marca + Modelo)
        $articulosDuplicados = DB::select("
            SELECT UPPER(TRIM(nombre)) AS n, UPPER(TRIM(COALESCE(marca, ''))) AS m, UPPER(TRIM(COALESCE(modelo, ''))) AS mo, COUNT(*) AS total
            FROM articulos
            GROUP BY n, m, mo
            HAVING COUNT(*) > 1
        ");
        $this->line('6. Grupos de artículos duplicados restantes: <fg=yellow>' . count($articulosDuplicados) . '</>');

        $this->info('=== AUDITORÍA FINALIZADA ===');
        return self::SUCCESS;
    }
}