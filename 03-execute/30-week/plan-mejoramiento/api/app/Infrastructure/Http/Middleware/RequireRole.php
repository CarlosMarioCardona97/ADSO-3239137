<?php

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Exception\BusinessRuleViolation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CA-01.3 · Autorización por rol en cada endpoint: `role:admin` o `role:admin,archivist`.
 * Rol insuficiente: 403, sin revelar si el recurso existe (api-contract §4).
 */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->attributes->get('role'), $roles, true)) {
            throw new BusinessRuleViolation('FORBIDDEN_ROLE', 'Su rol no tiene permiso para esta operación.');
        }

        return $next($request);
    }
}
