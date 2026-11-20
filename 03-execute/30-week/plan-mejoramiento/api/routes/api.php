<?php

use App\Infrastructure\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Prefijo /api/v1 (bootstrap/app.php). Contrato: docs/api-contract.md
Route::get('/health', HealthController::class);
