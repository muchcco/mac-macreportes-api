<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Configuracion\TiempoAtencionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReporteDetalleAtencionController;

Route::get('/reportes/detalle-atenciones', [ReporteDetalleAtencionController::class, 'index']);
Route::get('/reportes/detalle-atenciones/export', [ReporteDetalleAtencionController::class, 'export']);
Route::get('/reportes/detalle-atenciones/details', [ReporteDetalleAtencionController::class, 'details']);
Route::get('/dashboard/ubicaciones', [DashboardController::class, 'ubicaciones']);
Route::get('/dashboard/mapa', [DashboardController::class, 'mapa']);
Route::prefix('configuracion/tiempos-atencion')->group(function () {
    Route::get('/', [TiempoAtencionController::class, 'index']);
    Route::get('/filtros', [TiempoAtencionController::class, 'filtros']);
    Route::get('/servicios', [TiempoAtencionController::class, 'servicios']);
    Route::get('/{id}', [TiempoAtencionController::class, 'show'])->whereNumber('id');
    Route::post('/', [TiempoAtencionController::class, 'store']);
    Route::put('/{id}', [TiempoAtencionController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [TiempoAtencionController::class, 'destroy'])->whereNumber('id');
});
