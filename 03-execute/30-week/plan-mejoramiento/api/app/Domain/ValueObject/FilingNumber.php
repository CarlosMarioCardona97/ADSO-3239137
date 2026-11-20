<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\BusinessRuleViolation;

/** Número de radicado con formato AAAA-NNNNNN (CA-03.2). */
final class FilingNumber
{
    private function __construct(public readonly string $value) {}

    public static function fromString(string $raw): self
    {
        $value = trim($raw);
        if (preg_match('/^\d{4}-\d{6}$/', $value) !== 1) {
            throw BusinessRuleViolation::validation('filing_number', 'debe tener el formato AAAA-NNNNNN, por ejemplo 2024-000153');
        }

        return new self($value);
    }
}
