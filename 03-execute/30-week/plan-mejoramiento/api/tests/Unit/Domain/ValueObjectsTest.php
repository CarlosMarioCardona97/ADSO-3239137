<?php

use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\ValueObject\DateRange;
use App\Domain\ValueObject\FilingNumber;
use App\Domain\ValueObject\Quantity;

// T-03 · Objetos de valor: radicado AAAA-NNNNNN, cantidad > 0, fin >= inicio.

it('acepta un radicado con formato AAAA-NNNNNN y lo recorta', function () {
    expect(FilingNumber::fromString(' 2024-000153 ')->value)->toBe('2024-000153');
});

it('rechaza radicados con formato inválido', function (string $raw) {
    FilingNumber::fromString($raw);
})->with(['2024-153', '24-000153', '2024000153', 'ABCD-000153', ''])
    ->throws(BusinessRuleViolation::class);

it('rechaza cantidades menores que 1', function (int $value) {
    Quantity::of($value);
})->with([0, -3])->throws(BusinessRuleViolation::class);

it('acepta cantidades desde 1', function () {
    expect(Quantity::of(1)->value)->toBe(1);
});

it('rechaza un rango con fin anterior al inicio con INVALID_DATE_RANGE', function () {
    try {
        DateRange::fromDates('2026-11-10', '2026-11-01');
        $this->fail('Debía lanzar la excepción');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('INVALID_DATE_RANGE');
    }
});

it('trata el fin del rango como inclusivo', function () {
    $range = DateRange::fromDates('2026-11-01', '2026-11-01');
    expect($range->toExclusive()->format('Y-m-d'))->toBe('2026-11-02');
});

it('rechaza fechas que no son AAAA-MM-DD', function () {
    DateRange::fromDates('2026-13-01', '2026-11-30');
})->throws(BusinessRuleViolation::class);
