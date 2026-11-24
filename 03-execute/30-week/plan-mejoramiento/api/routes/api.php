<?php

use App\Infrastructure\Http\Controllers\AuthController;
use App\Infrastructure\Http\Controllers\CategoryController;
use App\Infrastructure\Http\Controllers\DossierController;
use App\Infrastructure\Http\Controllers\DossierCoverController;
use App\Infrastructure\Http\Controllers\HealthController;
use App\Infrastructure\Http\Controllers\LoanController;
use App\Infrastructure\Http\Controllers\OfficialController;
use App\Infrastructure\Http\Controllers\ReportController;
use App\Infrastructure\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Prefijo /api/v1 (bootstrap/app.php). Contrato: docs/api-contract.md §3.

// 3.1 Públicos
Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('token')->group(function () {
    // 3.3 Categorías y 3.4 consulta de expedientes: cualquier usuario autenticado
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/dossiers', [DossierController::class, 'index']);
    Route::get('/dossiers/{id}', [DossierController::class, 'show']);
    Route::get('/dossiers/{id}/cover', [DossierCoverController::class, 'show']);

    // Solo admin
    Route::middleware('role:admin')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::post('/dossiers', [DossierController::class, 'store']);
        Route::put('/dossiers/{id}', [DossierController::class, 'update']);
        Route::delete('/dossiers/{id}', [DossierController::class, 'destroy']);
        Route::put('/dossiers/{id}/cover', [DossierCoverController::class, 'update']);
        Route::delete('/dossiers/{id}/cover', [DossierCoverController::class, 'destroy']);
        Route::get('/reports/most-requested', [ReportController::class, 'mostRequested']);
    });

    // Admin y archivista
    Route::middleware('role:admin,archivist')->group(function () {
        Route::get('/officials', [OfficialController::class, 'index']);
        Route::get('/officials/{id}', [OfficialController::class, 'show']);
        Route::post('/officials', [OfficialController::class, 'store']);

        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);   // antes de /loans/{id}
        Route::get('/loans/{id}', [LoanController::class, 'show']);
        Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem']);
    });
});
