<?php

// T-16 · HU-10 por HTTP y ADR-004: el reporte usa los valores congelados y no cambia al renombrar.

beforeEach(function () {
    $this->admin = headersFor('admin');
    $this->official = createOfficial($this->admin, '1075000500');
    $this->range = '?from='.now('UTC')->subDay()->format('Y-m-d').'&to='.now('UTC')->addDay()->format('Y-m-d');
});

it('conserva el título viejo aunque se renombre el expediente (CA-10.4)', function () {
    $dossier = createDossier($this->admin, '2025-000500', 2, 'Título original');
    $this->postJson('/api/v1/loans', ['official_id' => $this->official['id'], 'items' => [['dossier_id' => $dossier['id']]]], $this->admin)->assertCreated();

    $this->putJson("/api/v1/dossiers/{$dossier['id']}", [
        'title' => 'Título nuevo', 'producing_office' => 'Hacienda', 'category_id' => CATEGORY_RECORDS, 'total_units' => 2,
    ], $this->admin)->assertOk();

    $this->getJson('/api/v1/reports/most-requested'.$this->range, $this->admin)
        ->assertOk()
        ->assertJsonPath('rows.0.dossier_title', 'Título original')
        ->assertJsonPath('rows.0.category_name', 'Contratos')
        ->assertJsonPath('rows.0.times_requested', 1);
});

it('ordena de más a menos solicitados y no expone funcionarios (CA-10.1, CA-10.3)', function () {
    $popular = createDossier($this->admin, '2025-000510', 3, 'Muy pedido');
    $rare = createDossier($this->admin, '2025-000511', 3, 'Poco pedido');
    $other = createOfficial($this->admin, '1075000501', 'Juan Gómez');
    $this->postJson('/api/v1/loans', ['official_id' => $this->official['id'], 'items' => [['dossier_id' => $popular['id']], ['dossier_id' => $rare['id']]]], $this->admin);
    $this->postJson('/api/v1/loans', ['official_id' => $other['id'], 'items' => [['dossier_id' => $popular['id']]]], $this->admin);

    $response = $this->getJson('/api/v1/reports/most-requested'.$this->range, $this->admin)
        ->assertJsonPath('rows.0.dossier_title', 'Muy pedido')
        ->assertJsonPath('rows.0.times_requested', 2)
        ->assertJsonPath('rows.1.times_requested', 1);
    expect($response->getContent())->not->toContain('María')->not->toContain('Juan');
});

it('valida el rango y responde lista vacía si no hay préstamos (CA-10.2)', function () {
    $this->getJson('/api/v1/reports/most-requested?from=2020-01-01&to=2020-01-31', $this->admin)->assertOk()->assertJsonPath('rows', []);
    $this->getJson('/api/v1/reports/most-requested?from=2026-11-30&to=2026-11-01', $this->admin)
        ->assertStatus(422)->assertJsonPath('code', 'INVALID_DATE_RANGE');
    $this->getJson('/api/v1/reports/most-requested', $this->admin)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('es solo para el admin (CA-10.5)', function () {
    $this->getJson('/api/v1/reports/most-requested'.$this->range, headersFor('archivist'))->assertForbidden();
});
