<?php

use App\Application\Service\LendDossiersService;
use App\Application\Service\MostRequestedReportService;
use App\Application\Service\ReturnItemService;
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

// T-11 · HU-10: reporte de más solicitados sobre valores congelados (ADR-004).

beforeEach(function () {
    $this->store = new InMemoryStore;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-11-03T10:00:00Z'));
    $dossiers = new InMemoryDossiers($this->store);
    $loans = new InMemoryLoans($this->store);
    $this->lend = new LendDossiersService(new InMemoryOfficials($this->store), $dossiers, $loans, $this->clock, new FakeSecurity);
    $this->return = new ReturnItemService($loans, $dossiers, $this->clock);
    $this->report = new MostRequestedReportService($loans);

    $this->store->officials['o-1'] = Official::register('o-1', 'María Pérez', '1075000001');
    $this->store->dossiers['d-1'] = Dossier::register('d-1', 'Contrato 045', 'Infraestructura', FilingNumber::fromString('2024-000001'), 'c', 'Contratos', Quantity::of(3));
    $this->store->dossiers['d-2'] = Dossier::register('d-2', 'Acta 12', 'Concejo', FilingNumber::fromString('2024-000002'), 'c', 'Actas', Quantity::of(3));
});

it('ordena de más a menos solicitados y no muestra funcionarios (CA-10.1, CA-10.3)', function () {
    $first = $this->lend->lend('o-1', ['d-1', 'd-2'], 'u-1');
    foreach ($first->items() as $item) {
        $this->return->returnItem($first->id, $item->id);
    }
    $this->lend->lend('o-1', ['d-1'], 'u-1');

    $report = $this->report->report('2026-11-01', '2026-11-30');
    expect($report['rows'])->toHaveCount(2)
        ->and($report['rows'][0]['dossier_title'])->toBe('Contrato 045')
        ->and($report['rows'][0]['times_requested'])->toBe(2)
        ->and(array_keys($report['rows'][0]))->not->toContain('official_name');
});

it('conserva el título viejo aunque se renombre el expediente (CA-10.4)', function () {
    $this->lend->lend('o-1', ['d-1'], 'u-1');
    $this->store->dossiers['d-1']->update('Contrato 045 renombrado', 'Infraestructura', 'c', 'Contratos', Quantity::of(3));

    expect($this->report->report('2026-11-01', '2026-11-30')['rows'][0]['dossier_title'])->toBe('Contrato 045');
});

it('devuelve lista vacía para un rango sin préstamos y 422 para un rango invertido (CA-10.2)', function () {
    expect($this->report->report('2026-01-01', '2026-01-31')['rows'])->toBe([]);
    $this->report->report('2026-11-30', '2026-11-01');
})->throws(BusinessRuleViolation::class, 'La fecha final');
