<?php

namespace App\Infrastructure\Http\Middleware;

use App\Application\Port\TokenIssuer;
use App\Domain\Exception\BusinessRuleViolation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** CA-01.3 · Todo endpoint salvo login y health exige `Authorization: Bearer <token>`. Sin token o inválido: 401. */
final class AuthenticateToken
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token !== null ? $this->tokens->decode($token) : null;
        if ($claims === null) {
            throw new BusinessRuleViolation('UNAUTHENTICATED', 'Debe iniciar sesión: el token falta, no es válido o venció.');
        }

        $request->attributes->set('user_id', $claims['sub']);
        $request->attributes->set('role', $claims['role']);

        return $next($request);
    }
}
