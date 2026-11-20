<?php

namespace App\Domain;

use App\Domain\Exception\BusinessRuleViolation;

/** Funcionario de la alcaldía que pide expedientes. No inicia sesión y no se elimina (CA-05.3). */
final class Official
{
    private function __construct(
        public readonly string $id,
        public readonly string $fullName,
        public readonly string $documentNumber,
    ) {}

    public static function register(string $id, string $fullName, string $documentNumber): self
    {
        $name = trim($fullName);
        $document = trim($documentNumber);
        if ($name === '' || mb_strlen($name) > 200) {
            throw BusinessRuleViolation::validation('full_name', 'es obligatorio y admite máximo 200 caracteres');
        }
        if (preg_match('/^[0-9A-Za-z-]{3,30}$/', $document) !== 1) {
            throw BusinessRuleViolation::validation('document_number', 'debe tener entre 3 y 30 letras, dígitos o guiones');
        }

        return new self($id, $name, $document);
    }

    public static function restore(string $id, string $fullName, string $documentNumber): self
    {
        return new self($id, $fullName, $documentNumber);
    }

    /**
     * RN-2 y RN-3: ¿puede el funcionario recibir `$requested` expedientes más?
     * El caso de uso trae del repositorio cuántos tiene sin devolver y si tiene vencidos.
     */
    public static function assertCanBorrow(int $openItems, bool $hasOverdue, int $requested): void
    {
        if ($hasOverdue) {
            throw BusinessRuleViolation::officialHasOverdue();
        }
        if ($openItems + $requested > Loan::MAX_ITEMS) {
            throw BusinessRuleViolation::officialLimitExceeded($openItems);
        }
    }
}
