<?php

use App\Infrastructure\Http\ErrorRenderer;
use App\Infrastructure\Http\Middleware\AuthenticateToken;
use App\Infrastructure\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'token' => AuthenticateToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todo error de la API sale con el cuerpo único del contrato (RNF-4).
        $exceptions->render(fn (Throwable $e, Request $request) => ErrorRenderer::render($e));
        // Las reglas de negocio no son fallas del servidor: no se registran como error.
        $exceptions->dontReport(App\Domain\Exception\BusinessRuleViolation::class);
    })->create();
