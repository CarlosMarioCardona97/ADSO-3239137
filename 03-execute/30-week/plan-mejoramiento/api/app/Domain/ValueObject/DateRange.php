<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\BusinessRuleViolation;
use DateTimeImmutable;
use DateTimeZone;

/** Rango de fechas inclusivo en UTC (CA-10.2): el fin no puede ser anterior al inicio. */
final class DateRange
{
    private function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
    ) {}

    public static function fromDates(string $from, string $to): self
    {
        $utc = new DateTimeZone('UTC');
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $utc);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $utc);
        if ($start === false || $start->format('Y-m-d') !== $from) {
            throw BusinessRuleViolation::validation('from', 'debe ser una fecha AAAA-MM-DD');
        }
        if ($end === false || $end->format('Y-m-d') !== $to) {
            throw BusinessRuleViolation::validation('to', 'debe ser una fecha AAAA-MM-DD');
        }
        if ($end < $start) {
            throw new BusinessRuleViolation(
                'INVALID_DATE_RANGE',
                'La fecha final no puede ser anterior a la fecha inicial.',
                [['field' => 'to', 'issue' => 'anterior a from']],
            );
        }

        return new self($start, $end);
    }

    /** Primer instante después del rango: el fin es inclusivo, se compara con "< toExclusive". */
    public function toExclusive(): DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }
}
