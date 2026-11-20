<?php

namespace App\Domain\Exception;

use RuntimeException;

/**
 * Regla de negocio rota. Lleva el código del contrato (docs/api-contract.md §2) y un mensaje en español.
 * El dominio no sabe de HTTP: el adaptador HTTP traduce el código a un estado (artículo I).
 */
final class BusinessRuleViolation extends RuntimeException
{
    /** @param list<array{field: string, issue: string}> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function validation(string $field, string $issue): self
    {
        return new self('VALIDATION_ERROR', 'Hay datos inválidos en la petición.', [['field' => $field, 'issue' => $issue]]);
    }

    public static function notFound(string $what): self
    {
        return new self('NOT_FOUND', "No se encontró {$what}.");
    }

    public static function dossierNotAvailable(string $filingNumber): self
    {
        return new self('DOSSIER_NOT_AVAILABLE', "No quedan unidades disponibles del expediente {$filingNumber}.");
    }

    public static function officialLimitExceeded(int $current): self
    {
        return new self(
            'OFFICIAL_LIMIT_EXCEEDED',
            "El funcionario ya tiene {$current} expedientes sin devolver; el máximo es 3.",
            [['field' => 'items', 'issue' => "sin devolver actualmente: {$current}"]],
        );
    }

    public static function officialHasOverdue(): self
    {
        return new self('OFFICIAL_HAS_OVERDUE', 'El funcionario tiene expedientes vencidos; debe devolverlos antes de pedir otro.');
    }

    public static function duplicateDossierInLoan(string $filingNumber): self
    {
        return new self('DUPLICATE_DOSSIER_IN_LOAN', "El expediente {$filingNumber} está repetido en el préstamo.");
    }

    public static function itemAlreadyReturned(): self
    {
        return new self('ITEM_ALREADY_RETURNED', 'Esta línea del préstamo ya fue devuelta.');
    }

    public static function unitsBelowLoaned(int $loaned): self
    {
        return new self(
            'UNITS_BELOW_LOANED',
            "El total de unidades no puede quedar por debajo de las {$loaned} unidades prestadas.",
            [['field' => 'total_units', 'issue' => "unidades prestadas: {$loaned}"]],
        );
    }
}
