<?php

use App\Application\Service\LendDossiersService;
use App\Application\Service\LoanQueryService;
use App\Application\Service\OverdueItemsService;
use App\Application\Service\ReturnItemService;
use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\LoanStatus;
use App\Domain\Official;
use App\Domain\ValueObject\FilingNumber;
use App\Domain\ValueObject\Quantity;
use Tests\Fakes\FakeSecurity;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryDossiers;
use Tests\Fakes\InMemoryLoans;
use Tests\Fakes\InMemoryOfficials;
use Tests\Fakes\InMemoryStore;

// T-10 · HU-07 (devolver) y HU-09 (vencidos) contra fakes.

beforeEach(function () {
    $this->store = new InMemoryStore;
    $this->clock = new FixedClock;
    $dossiers = new InMemoryDossiers($this->store);
    $loans = new InMemoryLoans($this->store);
    $this->lend = new LendDossiersService(new InMemoryOfficials($this->store), $dossiers, $loans, $this->clock, new FakeSecurity);
    $this->return = new ReturnItemService($loans, $dossiers, $this->clock);
    $this->overdue = new OverdueItemsService($loans, $this->clock);
    $this->query = new LoanQueryService($loans, $this->clock);

    $this->store->officials['o-1'] = Official::register('o-1', 'María Pérez', '1075000001');
    $this->store->officials['o-2'] = Official::register('o-2', 'Juan Gómez', '1075000002');
    foreach ([1, 2, 3] as $n) {
        $this->store->dossiers["d-{$n}"] = Dossier::register("d-{$n}", "Expediente {$n}", 'Oficina', FilingNumber::fromString("2024-00000{$n}"), 'c', 'Personal', Quantity::of(2));
    }
});

it('devuelve una línea, suma la unidad y cierra el préstamo al final (CA-07.1, CA-07.4)', function () {
    $loan = $this->lend->lend('o-1', ['d-1', 'd-2'], 'u-1');
    [$first, $second] = $loan->items();

    $returned = $this->return->returnItem($loan->id, $first->id);
    expect($returned->returnedAt())->not->toBeNull()
        ->and($this->store->dossiers['d-1']->availableUnits())->toBe(2)
        ->and($this->query->get($loan->id)->status($this->clock->now()))->toBe(LoanStatus::Open);

    $this->return->returnItem($loan->id, $second->id);
    expect($this->query->get($loan->id)->status($this->clock->now()))->toBe(LoanStatus::Closed);
});

it('responde ITEM_ALREADY_RETURNED y nunca suma dos veces (CA-07.2)', function () {
    $loan = $this->lend->lend('o-1', ['d-1'], 'u-1');
    $itemId = $loan->items()[0]->id;
    $this->return->returnItem($loan->id, $itemId);

    try {
        $this->return->returnItem($loan->id, $itemId);
        $this->fail('Debía fallar');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('ITEM_ALREADY_RETURNED')
            ->and($this->store->dossiers['d-1']->availableUnits())->toBe(2);
    }
});

it('lista los vencidos con días de retraso, de más a menos (CA-09.1, CA-09.2)', function () {
    $this->lend->lend('o-1', ['d-1'], 'u-1');
    $this->clock->advanceDays(2);
    $this->lend->lend('o-2', ['d-2'], 'u-1');
    $this->clock->advanceDays(6);

    $rows = $this->overdue->list();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]['official_name'])->toBe('María Pérez')
        ->and($rows[0]['days_late'])->toBe(3)
        ->and($rows[1]['days_late'])->toBe(1);
});
