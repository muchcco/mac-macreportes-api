<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReporteDetalleAtencionController;

Route::get('/reportes/detalle-atenciones', [ReporteDetalleAtencionController::class, 'index']);
Route::get('/reportes/detalle-atenciones/export', [ReporteDetalleAtencionController::class, 'export']);
Route::get('/reportes/detalle-atenciones/details', [ReporteDetalleAtencionController::class, 'details']);
Route::get('/dashboard/ubicaciones', [DashboardController::class, 'ubicaciones']);
Route::get('/dashboard/mapa', [DashboardController::class, 'mapa']);
