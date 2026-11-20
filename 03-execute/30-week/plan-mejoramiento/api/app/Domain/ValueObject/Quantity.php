<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\BusinessRuleViolation;

/** Cantidad entera mayor que cero (total de unidades, CA-03.3). */
final class Quantity
{
    private function __construct(public readonly int $value) {}

    public static function of(int $value, string $field = 'total_units'): self
    {
        if ($value < 1) {
            throw BusinessRuleViolation::validation($field, 'debe ser un número entero mayor o igual a 1');
        }

        return new self($value);
    }
}
