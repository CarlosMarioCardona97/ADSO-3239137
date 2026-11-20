<?php

use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\ValueObject\FilingNumber;
use App\Domain\ValueObject\Quantity;

// T-04 · Dossier: las unidades disponibles nunca bajan de 0 ni superan el total (RN-1).

function makeDossier(int $units = 2): Dossier
{
    return Dossier::register(
        'd-1', 'Contrato de obra 045', 'Secretaría de Infraestructura',
        FilingNumber::fromString('2024-000153'), 'cat-1', 'Contratos', Quantity::of($units),
    );
}

it('arranca con las disponibles iguales al total (CA-03.1)', function () {
    $dossier = makeDossier(3);
    expect($dossier->availableUnits())->toBe(3)
        ->and($dossier->totalUnits())->toBe(3)
        ->and($dossier->coverKey())->toBeNull();
});

it('no entrega unidades cuando no quedan (CA-06.3)', function () {
    $dossier = makeDossier(1);
    $dossier->withdraw();
    expect($dossier->availableUnits())->toBe(0);

    try {
        $dossier->withdraw();
        $this->fail('Debía lanzar DOSSIER_NOT_AVAILABLE');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('DOSSIER_NOT_AVAILABLE')
            ->and($dossier->availableUnits())->toBe(0);
    }
});

it('nunca supera el total al recibir devoluciones (CA-07.3)', function () {
    $dossier = makeDossier(1);
    $dossier->restock();
})->throws(LogicException::class);

it('ajusta las disponibles en la misma diferencia al cambiar el total (CA-03.3)', function () {
    $dossier = makeDossier(3);
    $dossier->withdraw();                 // 2 disponibles de 3
    $dossier->update('Nuevo asunto', 'Secretaría de Hacienda', 'cat-2', 'Predial', Quantity::of(5));
    expect($dossier->availableUnits())->toBe(4)->and($dossier->title())->toBe('Nuevo asunto');
});

it('no deja disponibles negativas al bajar el total (CA-03.3)', function () {
    $dossier = makeDossier(2);
    $dossier->withdraw();
    $dossier->withdraw();                 // 0 disponibles, 2 prestadas
    try {
        $dossier->update('Asunto', 'Oficina', 'cat-1', 'Contratos', Quantity::of(1));
        $this->fail('Debía lanzar UNITS_BELOW_LOANED');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('UNITS_BELOW_LOANED')->and($dossier->totalUnits())->toBe(2);
    }
});

it('queda inactivo tras la baja lógica (CA-03.5)', function () {
    $dossier = makeDossier();
    $dossier->retire(new DateTimeImmutable('2026-11-19T10:00:00Z'));
    expect($dossier->isActive())->toBeFalse();
});

it('exige título y dependencia productora', function () {
    Dossier::register('d-2', '   ', 'Oficina', FilingNumber::fromString('2024-000001'), 'c', 'Actas', Quantity::of(1));
})->throws(BusinessRuleViolation::class);
