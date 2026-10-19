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

});