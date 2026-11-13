<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ArticuloController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\VentaPdfController;
use App\Http\Controllers\RutaCobroController;
use App\Http\Controllers\AbonoController;
use App\Http\Controllers\CierreController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\GastoController;



// Rutas Públicas (Login)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Ruta para actualizar contraseña desde el modal
    Route::put('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');

    // Ruta de roles
    Route::resource('roles', RoleController::class)->except(['show']);
    // Rutas de Usuarios
    Route::resource('users', UserController::class)->except(['show']);
    //Rutas de Clientes
    Route::resource('clientes', ClienteController::class)->except(['show']);
    // CRUD Principal de Artículos
    Route::resource('articulos', ArticuloController::class)->except(['show']);

    // Movimientos y Traspasos de Inventario
    Route::get('/inventario/traspaso', [ArticuloController::class, 'traspasoForm'])->name('articulos.traspaso');
    Route::post('/inventario/traspaso', [ArticuloController::class, 'procesarTraspaso'])->name('articulos.traspaso.store');

    // Kardex de Auditoría
    Route::get('/inventario/kardex', [ArticuloController::class, 'kardex'])->name('articulos.kardex');
    Route::post('/inventario/surtir-almacen', [ArticuloController::class, 'surtirAlmacen'])->name('articulos.surtir');
    Route::get('/inventario/bajo-stock', [ArticuloController::class, 'bajoStock'])->name('articulos.bajo_stock');

    // Rutas de ventas
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/pos', [VentaController::class, 'pos'])->name('ventas.pos');
    Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
    Route::put('/ventas/{venta}/status', [VentaController::class, 'updateStatus'])->name('ventas.update_status');

    Route::post('/ventas/store', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/{id}/pdf-ticket', [VentaPdfController::class, 'ticket'])->name('ventas.pdf.ticket');
    Route::get('/ventas/{id}/pdf-boleta/{detalleId}', [VentaPdfController::class, 'boleta'])->name('ventas.pdf.boleta');
    Route::post('ventas/clientes/ajax-store', [VentaController::class, 'storeClienteAjax'])->name('ventas.clientes.storeAjax');    

    Route::get('/ventas/{id}/continuar', [VentaController::class, 'continuarPOS'])->name('ventas.continuar');
    Route::patch('/ventas/{id}/pendiente', [VentaController::class, 'pasarAPendiente'])->name('ventas.pendiente');
    Route::delete('/ventas/{id}/descartar', [VentaController::class, 'descartar'])->name('ventas.descartar');

    // Obtener detalles e historial de abonos vía AJAX
    Route::get('/ventas/{id}/detalles-ajax', [VentaController::class, 'getDetallesAjax'])->name('ventas.detalles.ajax');
    Route::get('/ventas/{id}/historial-ajax', [VentaController::class, 'getHistorialAjax'])->name('ventas.historial.ajax');

    // Actualizar número de serie inline
    Route::post('/ventas/detalles/{id}/update-serie', [VentaController::class, 'updateNumeroSerie'])->name('ventas.detalles.update_serie');

    // Rutas de Boleta en formato PC y Móvil
    Route::get('/ventas/{id}/pdf-boleta-pc/{detalleId}', [VentaPdfController::class, 'boletaPC'])->name('ventas.pdf.boleta_pc');
    Route::get('/ventas/{id}/pdf-boleta-movil/{detalleId}', [VentaPdfController::class, 'boletaMovil'])->name('ventas.pdf.boleta_movil');
    Route::post('/ventas/{id}/cambiar-estatus', [VentaController::class, 'cambiarEstatusStatus'])->name('ventas.cambiar_estatus');
    
    // Vista móvil del Cobrador (Optimizada para Offline)
    Route::get('/cobrador/mi-ruta', [RutaCobroController::class, 'miRutaCobrador'])->name('cobrador.mi_ruta');
    
    // API/Endpoint de Sincronización masiva desde Offline (IndexedDB)
    Route::post('/api/cobrador/sincronizar-abonos', [RutaCobroController::class, 'sincronizarAbonosOffline'])->name('cobrador.sincronizar');

    // Rutas de Cobranza Dinámica (Agenda)
    Route::get('/rutas', [RutaCobroController::class, 'index'])->name('rutas.index');
    Route::post('/rutas/{venta}/reagendar', [RutaCobroController::class, 'reagendar'])->name('rutas.reagendar');
    Route::post('/rutas/{venta}/asignar-cobrador', [RutaCobroController::class, 'asignarCobrador'])->name('rutas.asignar_cobrador');

    // Vista móvil del Cobrador (Optimizada para Offline)
    Route::get('/cobrador/mi-ruta', [RutaCobroController::class, 'miRutaCobrador'])->name('cobrador.mi_ruta');
    Route::post('/api/cobrador/sincronizar-abonos', [RutaCobroController::class, 'sincronizarAbonosOffline'])->name('cobrador.sincronizar');
    // Lista general de ventas con abonos pendientes (abono_cliente1.php)
    Route::get('/abonos', [AbonoController::class, 'index'])->name('abonos.index');
    
    // Vista individual de la cuenta / Formulario de cobro (abono_cliente2.php)
    Route::get('/abonos/{id}', [AbonoController::class, 'show'])->name('abonos.show');
    Route::post('/abonos/store', [AbonoController::class, 'store'])->name('abonos.store');

    // Endpoints para Modales AJAX
    Route::post('/abonos/cambiar-cobrador', [AbonoController::class, 'cambiarCobrador'])->name('abonos.cambiarCobrador');
    Route::post('/abonos/guardar-notas', [AbonoController::class, 'guardarNotas'])->name('abonos.guardarNotas');
    Route::get('/abonos-buscar-ajax', [AbonoController::class, 'buscarVentasAjax'])->name('abonos.buscar.ajax');
    Route::post('/abonos/actualizar-telefono', [AbonoController::class, 'actualizarTelefono'])->name('abonos.telefono.update');
    Route::post('/abonos/fijar-gps', [AbonoController::class, 'fijarGps'])->name('abonos.gps.store');
    Route::post('/abonos/subir-evidencia', [AbonoController::class, 'subirEvidencia'])->name('abonos.evidencia.store');
    Route::post('/abonos/notas', [AbonoController::class, 'guardarNotas'])->name('abonos.notas.store');
    Route::post('/abonos/reagendar', [AbonoController::class, 'reagendarVisita'])->name('abonos.reagendar.store');
    Route::post('/abonos/descuento', [AbonoController::class, 'aplicarDescuento'])->name('abonos.descuento.store');
    Route::post('/abonos/reasignar-cobrador', [AbonoController::class, 'reasignarCobrador'])->name('abonos.cobrador.update');

    //Rutas de cierres
    Route::get('/cierres', [CierreController::class, 'index'])->name('cierres.index');
    Route::get('/cierres/reporte-pdf', [CierreController::class, 'generarPdf'])->name('cierres.pdf');

    //Rutas de reportes
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/buscar-cliente-ajax', [ReporteController::class, 'buscarClienteAjax'])->name('reportes.cliente.ajax');
    Route::get('/reportes/obtener-datos', [ReporteController::class, 'obtenerDatosReporte'])->name('reportes.datos');
    Route::get('/reportes/exportar-pdf', [ReporteController::class, 'exportarPdf'])->name('reportes.exportar.pdf');

    // 1. RUTA PRINCIPAL (La que arrojó el error por no tener ->name('gastos.index'))
    Route::get('/gastos', [GastoController::class, 'index'])->name('gastos.index');

    // 2. Rutas para Gastos Operativos
    Route::post('/gastos/store', [GastoController::class, 'storeGasto'])->name('gastos.store');
    Route::get('/gastos/consultar-ajax', [GastoController::class, 'consultarGastosAjax'])->name('gastos.consultar.ajax');

    // 3. Rutas para Préstamos
    Route::get('/gastos/prestamos-ajax', [GastoController::class, 'obtenerPrestamosAjax'])->name('gastos.prestamos.ajax');
    Route::post('/gastos/prestamos/store', [GastoController::class, 'storePrestamo'])->name('gastos.prestamos.store');
    Route::post('/gastos/prestamos/abonar', [GastoController::class, 'abonarPrestamo'])->name('gastos.prestamos.abonar');
    Route::get('/gastos/prestamos/historial/{id}', [GastoController::class, 'historialAbonosPrestamo'])->name('gastos.prestamos.historial');

    // 4. Ruta para Eliminar
    Route::delete('/gastos/eliminar', [GastoController::class, 'eliminarRegistro'])->name('gastos.eliminar');

    // Cambiar artículo de una venta y consultar inventario del vendedor
    Route::get('/ventas/{venta}/inventario-vendedor', [VentaController::class, 'obtenerInventarioVendedorAjax'])->name('ventas.inventario_vendedor');
    Route::post('/ventas/{venta}/cambiar-articulo', [VentaController::class, 'cambiarArticuloVenta'])->name('ventas.cambiar_articulo');
    Route::post('/ventas/{id}/cambiar-estatus', [VentaController::class, 'cambiarEstatusStatus'])->name('ventas.cambiar_estatus');

   
});
