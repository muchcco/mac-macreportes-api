<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReporteDetalleAtencionController;

// ... tus otras rutas (como /test-db)

Route::prefix('api')->group(function () {
    Route::get('/reportes/detalle-atenciones', [ReporteDetalleAtencionController::class, 'index']);
});
