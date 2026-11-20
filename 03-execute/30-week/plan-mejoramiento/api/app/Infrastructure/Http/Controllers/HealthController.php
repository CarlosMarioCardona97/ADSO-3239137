<?php

namespace App\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/health — público. Responde {"status":"ok"} (T-01).
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
