<?php

use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use App\Domain\Official;
use App\Domain\ValueObject\FilingNumber;
use App\Domain\ValueObject\Quantity;

// T-05 · Loan: estado calculado, vencimiento a 5 días, máximo 3 líneas, sin repetidos, valores congelados.

function dossierFor(string $id, string $filing, int $units = 2): Dossier
{
    return Dossier::register($id, "Expediente {$id}", 'Oficina Jurídica', FilingNumber::fromString($filing), 'c', 'Jurídico', Quantity::of($units));
}

beforeEach(function () {
    $this->loanedAt = new DateTimeImmutable('2026-11-10T14:00:00Z');
    $this->loan = Loan::open('l-1', 'o-1', 'u-1', $this->loanedAt);
});

it('vence a los 5 días y congela título, radicado y categoría (CA-06.6)', function () {
    $dossier = dossierFor('d-1', '2024-000001');
    $item = $this->loan->addItem('i-1', $dossier);

    expect($item->dueAt->format(DATE_ATOM))->toBe('2026-11-15T14:00:00+00:00')
        ->and($item->dossierTitle)->toBe('Expediente d-1')
        ->and($item->filingNumber)->toBe('2024-000001')
        ->and($item->categoryName)->toBe('Jurídico')
        ->and($dossier->availableUnits())->toBe(1);

    // El catálogo cambia después; la línea no (RN-4).
    $dossier->update('Título nuevo', 'Otra oficina', 'c2', 'Actas', Quantity::of(2));
    expect($item->dossierTitle)->toBe('Expediente d-1')->and($item->categoryName)->toBe('Jurídico');
});

it('calcula open, overdue y closed con un reloj fijo (CA-08.1)', function () {
    $dossier = dossierFor('d-1', '2024-000001');
    $this->loan->addItem('i-1', $dossier);

    expect($this->loan->status(new DateTimeImmutable('2026-11-12T00:00:00Z')))->toBe(LoanStatus::Open)
        ->and($this->loan->status(new DateTimeImmutable('2026-11-16T00:00:00Z')))->toBe(LoanStatus::Overdue);

    $this->loan->returnItem('i-1', $dossier, new DateTimeImmutable('2026-11-16T00:00:00Z'));
    expect($this->loan->status(new DateTimeImmutable('2026-11-20T00:00:00Z')))->toBe(LoanStatus::Closed)
        ->and($dossier->availableUnits())->toBe(2);
});

it('cuenta los días de retraso', function () {
    $this->loan->addItem('i-1', dossierFor('d-1', '2024-000001'));
    $item = $this->loan->item('i-1');
    expect($item->daysLate(new DateTimeImmutable('2026-11-18T14:00:00Z')))->toBe(3)
        ->and($item->daysLate(new DateTimeImmutable('2026-11-14T14:00:00Z')))->toBe(0);
});

it('no admite el mismo expediente dos veces (CA-06.2)', function () {
    $dossier = dossierFor('d-1', '2024-000001');
    $this->loan->addItem('i-1', $dossier);
    try {
        $this->loan->addItem('i-2', $dossier);
        $this->fail('Debía lanzar DUPLICATE_DOSSIER_IN_LOAN');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('DUPLICATE_DOSSIER_IN_LOAN');
    }
});

it('admite máximo 3 líneas (CA-06.1)', function () {
    foreach ([1, 2, 3] as $n) {
        $this->loan->addItem("i-{$n}", dossierFor("d-{$n}", "2024-00000{$n}"));
    }
    $this->loan->addItem('i-4', dossierFor('d-4', '2024-000004'));
})->throws(BusinessRuleViolation::class);

it('no suma dos veces al devolver la misma línea (CA-07.2)', function () {
    $dossier = dossierFor('d-1', '2024-000001');
    $this->loan->addItem('i-1', $dossier);
    $at = new DateTimeImmutable('2026-11-11T00:00:00Z');
    $this->loan->returnItem('i-1', $dossier, $at);
    try {
        $this->loan->returnItem('i-1', $dossier, $at);
        $this->fail('Debía lanzar ITEM_ALREADY_RETURNED');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('ITEM_ALREADY_RETURNED')->and($dossier->availableUnits())->toBe(2);
    }
});

it('bloquea al funcionario con vencidos o que superaría 3 sin devolver (RN-2, RN-3)', function () {
    expect(fn () => Official::assertCanBorrow(0, true, 1))->toThrow(BusinessRuleViolation::class, 'vencidos')
        ->and(fn () => Official::assertCanBorrow(2, false, 2))->toThrow(BusinessRuleViolation::class, '3');
    Official::assertCanBorrow(2, false, 1);
});
