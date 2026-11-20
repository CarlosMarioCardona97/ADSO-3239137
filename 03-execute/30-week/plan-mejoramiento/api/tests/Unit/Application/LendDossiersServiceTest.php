<?php

use App\Application\Service\LendDossiersService;
use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Official;
use App\Domain\ValueObject\FilingNumber;
use App\Domain\ValueObject\Quantity;
use Tests\Fakes\FakeSecurity;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryDossiers;
use Tests\Fakes\InMemoryLoans;
use Tests\Fakes\InMemoryOfficials;
use Tests\Fakes\InMemoryStore;

// T-09 · HU-06 completa contra fakes: límites, vencidos y sin unidades.

function seedDossier(InMemoryStore $store, string $id, string $filing, int $units = 1): void
{
    $store->dossiers[$id] = Dossier::register($id, "Expediente {$filing}", 'Oficina', FilingNumber::fromString($filing), 'c', 'Actas', Quantity::of($units));
}

function lendError(callable $action): string
{
    try {
        $action();
    } catch (BusinessRuleViolation $e) {
        return $e->errorCode;
    }

    return 'SIN_ERROR';
}

beforeEach(function () {
    $this->store = new InMemoryStore;
    $this->clock = new FixedClock;
    $this->service = new LendDossiersService(
        new InMemoryOfficials($this->store), new InMemoryDossiers($this->store), new InMemoryLoans($this->store),
        $this->clock, new FakeSecurity,
    );
    $this->store->officials['o-1'] = Official::register('o-1', 'María Pérez', '1075000001');
    foreach (range(1, 5) as $n) {
        seedDossier($this->store, "d-{$n}", sprintf('2024-%06d', $n));
    }
});

it('registra el préstamo, descuenta unidades y vence a 5 días (CA-06.1, CA-06.3, CA-06.6)', function () {
    $loan = $this->service->lend('o-1', ['d-1', 'd-2'], 'u-1');

    expect($loan->items())->toHaveCount(2)
        ->and($loan->items()[0]->dueAt->format('Y-m-d'))->toBe('2026-11-15')
        ->and($this->store->dossiers['d-1']->availableUnits())->toBe(0);
});

it('exige de 1 a 3 líneas y sin repetidos (CA-06.1, CA-06.2)', function () {
    expect(lendError(fn () => $this->service->lend('o-1', [], 'u-1')))->toBe('VALIDATION_ERROR')
        ->and(lendError(fn () => $this->service->lend('o-1', ['d-1', 'd-2', 'd-3', 'd-4'], 'u-1')))->toBe('VALIDATION_ERROR')
        ->and(lendError(fn () => $this->service->lend('o-1', ['d-1', 'd-1'], 'u-1')))->toBe('DUPLICATE_DOSSIER_IN_LOAN');
});

it('responde 409 sin unidades y no registra nada (CA-06.3)', function () {
    $this->service->lend('o-1', ['d-1'], 'u-1');
    $this->store->officials['o-2'] = Official::register('o-2', 'Juan Gómez', '1075000002');

    expect(lendError(fn () => $this->service->lend('o-2', ['d-2', 'd-1'], 'u-1')))->toBe('DOSSIER_NOT_AVAILABLE')
        ->and($this->store->loans)->toHaveCount(1)
        ->and($this->store->dossiers['d-2']->availableUnits())->toBe(1);
});

it('no deja pasar de 3 expedientes sin devolver por funcionario (CA-06.4)', function () {
    $this->service->lend('o-1', ['d-1', 'd-2'], 'u-1');
    expect(lendError(fn () => $this->service->lend('o-1', ['d-3', 'd-4'], 'u-1')))->toBe('OFFICIAL_LIMIT_EXCEEDED');
    $this->service->lend('o-1', ['d-3'], 'u-1');
    expect(lendError(fn () => $this->service->lend('o-1', ['d-4'], 'u-1')))->toBe('OFFICIAL_LIMIT_EXCEEDED');
});

it('bloquea al funcionario con vencidos (CA-06.5)', function () {
    $this->service->lend('o-1', ['d-1'], 'u-1');
    $this->clock->advanceDays(6);
    expect(lendError(fn () => $this->service->lend('o-1', ['d-2'], 'u-1')))->toBe('OFFICIAL_HAS_OVERDUE');
});

it('responde 404 para funcionarios o expedientes inexistentes', function () {
    expect(lendError(fn () => $this->service->lend('nadie', ['d-1'], 'u-1')))->toBe('NOT_FOUND')
        ->and(lendError(fn () => $this->service->lend('o-1', ['no-existe'], 'u-1')))->toBe('NOT_FOUND');
});
