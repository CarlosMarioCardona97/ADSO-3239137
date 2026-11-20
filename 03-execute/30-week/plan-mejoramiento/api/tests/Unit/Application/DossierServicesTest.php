<?php

use App\Application\Service\CreateDossierService;
use App\Application\Service\DossierCoverService;
use App\Application\Service\RetireDossierService;
use App\Application\Service\SearchDossiersService;
use App\Application\Service\UpdateDossierService;
use App\Domain\Exception\BusinessRuleViolation;
use Tests\Fakes\FakeSecurity;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryCategories;
use Tests\Fakes\InMemoryCovers;
use Tests\Fakes\InMemoryDossiers;
use Tests\Fakes\InMemoryStore;

// T-08 · HU-03 y HU-04 contra fakes.

const CONTRACTS = '11111111-1111-4111-8111-111111111111';
const PROPERTY = '33333333-3333-4333-8333-333333333333';

beforeEach(function () {
    $this->store = new InMemoryStore;
    $dossiers = new InMemoryDossiers($this->store);
    $categories = new InMemoryCategories($this->store);
    $this->create = new CreateDossierService($dossiers, $categories, new FakeSecurity);
    $this->update = new UpdateDossierService($dossiers, $categories);
    $this->search = new SearchDossiersService($dossiers);
    $this->retire = new RetireDossierService($dossiers, new FixedClock);
    $this->covers = new DossierCoverService($dossiers, new InMemoryCovers($this->store));
});

it('crea el expediente con la categoría y las disponibles iguales al total (CA-03.1)', function () {
    $dossier = $this->create->create('Contrato 045', 'Infraestructura', '2024-000153', CONTRACTS, 2);
    expect($dossier->categoryName())->toBe('Contratos')->and($dossier->availableUnits())->toBe(2);
});

it('rechaza un radicado repetido entre activos y lo libera tras la baja (CA-03.2, ADR-003)', function () {
    $first = $this->create->create('Contrato 045', 'Infraestructura', '2024-000153', CONTRACTS, 1);
    expect(fn () => $this->create->create('Otro', 'Hacienda', '2024-000153', CONTRACTS, 1))
        ->toThrow(BusinessRuleViolation::class, 'Ya existe');

    $this->retire->retire($first->id);
    expect($this->create->create('Otro', 'Hacienda', '2024-000153', CONTRACTS, 1)->filingNumber->value)->toBe('2024-000153');
});

it('rechaza una categoría inexistente', function () {
    $this->create->create('Contrato', 'Oficina', '2024-000001', 'no-existe', 1);
})->throws(BusinessRuleViolation::class);

it('actualiza datos y total (CA-03.3)', function () {
    $dossier = $this->create->create('Contrato 045', 'Infraestructura', '2024-000153', CONTRACTS, 2);
    $updated = $this->update->update($dossier->id, 'Avalúo predial', 'Hacienda', PROPERTY, 4);
    expect($updated->title())->toBe('Avalúo predial')
        ->and($updated->categoryName())->toBe('Predial')
        ->and($updated->availableUnits())->toBe(4);
});

it('busca por texto parcial, filtra por categoría y pagina con total (CA-04.1, CA-04.2)', function () {
    $this->create->create('Contrato de obra', 'Infraestructura', '2024-000001', CONTRACTS, 1);
    $this->create->create('Acta de concejo', 'Secretaría General', '2024-000002', '44444444-4444-4444-8444-444444444444', 1);
    $this->create->create('Contrato de aseo', 'Servicios', '2024-000003', CONTRACTS, 1);

    $page = $this->search->search('contrato', CONTRACTS, 1, 1);
    expect($page->total)->toBe(2)
        ->and($page->items)->toHaveCount(1)
        ->and($page->items[0]->title())->toBe('Contrato de aseo');
    expect($this->search->search('2024-000002', null, 1, 20)->total)->toBe(1);
});

it('responde 404 para un expediente dado de baja (CA-04.3)', function () {
    $dossier = $this->create->create('Contrato', 'Oficina', '2024-000001', CONTRACTS, 1);
    $this->retire->retire($dossier->id);
    $this->search->get($dossier->id);
})->throws(BusinessRuleViolation::class, 'No se encontró');

it('guarda una clave opaca para la carátula y la borra en orden (CA-03.4)', function () {
    $dossier = $this->create->create('Contrato', 'Oficina', '2024-000001', CONTRACTS, 1);
    $png = "\x89PNG".str_repeat('a', 100);

    $withCover = $this->covers->upload($dossier->id, $png, 'image/png');
    expect($withCover->coverKey())->toEndWith('.png')
        ->and($this->covers->get($dossier->id)['mime'])->toBe('image/png');

    $this->covers->remove($dossier->id);
    expect($this->search->get($dossier->id)->coverKey())->toBeNull()
        ->and($this->store->covers)->toBe([]);
});

it('rechaza carátulas que no son JPG o PNG o pesan más de 2 MB', function () {
    $dossier = $this->create->create('Contrato', 'Oficina', '2024-000001', CONTRACTS, 1);
    expect(fn () => $this->covers->upload($dossier->id, 'GIF89a', 'image/gif'))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $this->covers->upload($dossier->id, str_repeat('a', 3 * 1024 * 1024), 'image/png'))->toThrow(BusinessRuleViolation::class);
});
