<?php

namespace App\Infrastructure\Http;

use App\Domain\Exception\BusinessRuleViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Único lugar donde un error se traduce a HTTP (architecture.md §5, RNF-4). Todo error sale con el mismo
 * cuerpo { code, message, details } y nunca con trazas, SQL ni rutas del servidor.
 */
final class ErrorRenderer
{
    /** Estado HTTP de cada código de negocio (api-contract §2). */
    private const STATUS = [
        'INVALID_CREDENTIALS' => 401,
        'UNAUTHENTICATED' => 401,
        'FORBIDDEN_ROLE' => 403,
        'NOT_FOUND' => 404,
        'USERNAME_TAKEN' => 409,
        'FILING_NUMBER_TAKEN' => 409,
        'DOCUMENT_TAKEN' => 409,
        'DOSSIER_HAS_OPEN_LOANS' => 409,
        'DOSSIER_NOT_AVAILABLE' => 409,
        'OFFICIAL_HAS_OVERDUE' => 409,
        'ITEM_ALREADY_RETURNED' => 409,
        'OFFICIAL_LIMIT_EXCEEDED' => 422,
        'DUPLICATE_DOSSIER_IN_LOAN' => 422,
        'UNITS_BELOW_LOANED' => 422,
        'VALIDATION_ERROR' => 422,
        'INVALID_DATE_RANGE' => 422,
    ];

    public static function render(Throwable $e): JsonResponse
    {
        if ($e instanceof BusinessRuleViolation) {
            return self::body(self::STATUS[$e->errorCode] ?? 422, $e->errorCode, $e->getMessage(), $e->details);
        }
        if ($e instanceof NotFoundHttpException) {
            return self::body(404, 'NOT_FOUND', 'El recurso solicitado no existe.');
        }
        if ($e instanceof MethodNotAllowedHttpException) {
            return self::body(405, 'METHOD_NOT_ALLOWED', 'El método HTTP no está permitido en esta ruta.');
        }
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return self::body($e->getStatusCode(), 'VALIDATION_ERROR', 'La petición no es válida.');
        }

        Log::error('Error no controlado', ['exception' => $e::class, 'message' => $e->getMessage()]);

        return self::body(500, 'INTERNAL_ERROR', 'Ocurrió un error inesperado. Intente de nuevo.');
    }

    /** @param list<array{field: string, issue: string}> $details */
    public static function body(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return new JsonResponse(
            ['code' => $code, 'message' => $message, 'details' => $details],
            $status,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }
}
