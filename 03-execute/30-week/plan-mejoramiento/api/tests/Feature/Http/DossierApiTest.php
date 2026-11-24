<?php

// T-16 · HU-03 y HU-04 por HTTP: crear, buscar, editar y dar de baja expedientes.

beforeEach(function () {
    $this->admin = headersFor('admin');
});

it('crea con disponibles iguales al total y rechaza radicados repetidos o mal formados (CA-03.1, CA-03.2)', function () {
    $dossier = createDossier($this->admin, '2025-000300', 3);
    expect($dossier['available_units'])->toBe(3)
        ->and($dossier['category'])->toBe(['id' => CATEGORY_CONTRACTS, 'name' => 'Contratos']);

    $this->postJson('/api/v1/dossiers', [
        'title' => 'Otro', 'producing_office' => 'Hacienda', 'filing_number' => '2025-000300',
        'category_id' => CATEGORY_CONTRACTS, 'total_units' => 1,
    ], $this->admin)->assertStatus(409)->assertJsonPath('code', 'FILING_NUMBER_TAKEN');

    $this->postJson('/api/v1/dossiers', [
        'title' => 'Otro', 'producing_office' => 'Hacienda', 'filing_number' => '300',
        'category_id' => CATEGORY_CONTRACTS, 'total_units' => 0,
    ], $this->admin)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('ajusta las disponibles al cambiar el total y no deja negativas (CA-03.3)', function () {
    $dossier = createDossier($this->admin, '2025-000301', 2);
    $official = createOfficial($this->admin, '1075000301');
    $this->postJson('/api/v1/loans', ['official_id' => $official['id'], 'items' => [['dossier_id' => $dossier['id']]]], $this->admin)
        ->assertCreated();

    $body = ['title' => 'Contrato renombrado', 'producing_office' => 'Hacienda', 'category_id' => CATEGORY_RECORDS];
    $this->putJson("/api/v1/dossiers/{$dossier['id']}", $body + ['total_units' => 5], $this->admin)
        ->assertOk()->assertJsonPath('available_units', 4)->assertJsonPath('category.name', 'Actas');

    $this->putJson("/api/v1/dossiers/{$dossier['id']}", $body + ['total_units' => 1], $this->admin)->assertOk()
        ->assertJsonPath('available_units', 0);
});

it('busca por texto, filtra por categoría y pagina con total (CA-04.1, CA-04.2)', function () {
    createDossier($this->admin, '2025-000310', 1, 'Contrato de aseo');
    createDossier($this->admin, '2025-000311', 1, 'Contrato de obra');
    createDossier($this->admin, '2025-000312', 1, 'Licencia de construcción');

    $this->getJson('/api/v1/dossiers?q=contrato&size=1', $this->admin)
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('size', 1)
        ->assertJsonPath('items.0.title', 'Contrato de aseo');

    $this->getJson('/api/v1/dossiers?category_id='.CATEGORY_RECORDS, $this->admin)->assertJsonPath('total', 0);
    $this->getJson('/api/v1/dossiers?size=51', $this->admin)->assertStatus(422);
});

it('da de baja lógica: 409 con préstamos abiertos, luego 204 y 404 (CA-03.5, CA-04.3)', function () {
    $dossier = createDossier($this->admin, '2025-000320', 1);
    $official = createOfficial($this->admin, '1075000320');
    $loan = $this->postJson('/api/v1/loans', ['official_id' => $official['id'], 'items' => [['dossier_id' => $dossier['id']]]], $this->admin)->json();

    $this->deleteJson("/api/v1/dossiers/{$dossier['id']}", [], $this->admin)->assertStatus(409)->assertJsonPath('code', 'DOSSIER_HAS_OPEN_LOANS');

    $this->postJson("/api/v1/loans/{$loan['id']}/items/{$loan['items'][0]['id']}/return", [], $this->admin)->assertOk();
    $this->deleteJson("/api/v1/dossiers/{$dossier['id']}", [], $this->admin)->assertNoContent();
    $this->getJson("/api/v1/dossiers/{$dossier['id']}", $this->admin)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');

    // Sigue en el préstamo pasado (RN-5).
    $this->getJson("/api/v1/loans/{$loan['id']}", $this->admin)->assertOk()->assertJsonPath('items.0.filing_number', '2025-000320');
});

it('solo el admin crea, edita o da de baja expedientes', function () {
    $archivist = headersFor('archivist');
    $this->postJson('/api/v1/dossiers', [], $archivist)->assertForbidden();
    $this->getJson('/api/v1/dossiers', $archivist)->assertOk();
});
