<?php

use Illuminate\Support\Facades\DB;

// T-16 · HU-05 a HU-09 por HTTP: funcionarios, prestar, devolver, listar y vencidos.

beforeEach(function () {
    $this->archivist = headersFor('archivist');
    $this->admin = headersFor('admin');
    $this->official = createOfficial($this->archivist, '1075000400');
});

function lendVia(array $headers, string $officialId, array $dossierIds)
{
    return test()->postJson('/api/v1/loans', [
        'official_id' => $officialId,
        'items' => array_map(fn ($id) => ['dossier_id' => $id], $dossierIds),
    ], $headers);
}

it('crea, busca y consulta funcionarios con su conteo sin devolver (HU-05)', function () {
    $this->postJson('/api/v1/officials', ['full_name' => 'Otra', 'document_number' => '1075000400'], $this->archivist)
        ->assertStatus(409)->assertJsonPath('code', 'DOCUMENT_TAKEN');
    $this->getJson('/api/v1/officials?q=1075000400', $this->archivist)->assertJsonPath('total', 1);
    $this->getJson("/api/v1/officials/{$this->official['id']}", $this->archivist)->assertJsonPath('active_loans', 0);
});

it('registra un préstamo con vencimiento a 5 días y valores congelados (HU-06)', function () {
    $dossier = createDossier($this->admin, '2025-000400', 2);

    $loan = lendVia($this->archivist, $this->official['id'], [$dossier['id']])
        ->assertCreated()
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('items.0.filing_number', '2025-000400')
        ->assertJsonPath('items.0.category_name', 'Contratos')
        ->json();

    $loanedAt = new DateTimeImmutable($loan['loaned_at']);
    expect((new DateTimeImmutable($loan['items'][0]['due_at']))->getTimestamp() - $loanedAt->getTimestamp())->toBe(5 * 86400)
        ->and($loan['loaned_at'])->toEndWith('Z');
    $this->getJson("/api/v1/officials/{$this->official['id']}", $this->archivist)->assertJsonPath('active_loans', 1);
});

it('aplica los límites del préstamo (CA-06.1, CA-06.2, CA-06.4)', function () {
    $ids = array_map(fn ($n) => createDossier($this->admin, "2025-00041{$n}")['id'], [1, 2, 3, 4]);

    lendVia($this->archivist, $this->official['id'], [])->assertStatus(422);
    lendVia($this->archivist, $this->official['id'], $ids)->assertStatus(422);
    lendVia($this->archivist, $this->official['id'], [$ids[0], $ids[0]])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE_DOSSIER_IN_LOAN');

    lendVia($this->archivist, $this->official['id'], [$ids[0], $ids[1]])->assertCreated();
    lendVia($this->archivist, $this->official['id'], [$ids[2], $ids[3]])
        ->assertStatus(422)->assertJsonPath('code', 'OFFICIAL_LIMIT_EXCEEDED')->assertJsonPath('details.0.issue', 'sin devolver actualmente: 2');
});

it('bloquea al funcionario con vencidos y los lista con días de retraso (CA-06.5, HU-09)', function () {
    $first = createDossier($this->admin, '2025-000420');
    $second = createDossier($this->admin, '2025-000421');
    $loan = lendVia($this->archivist, $this->official['id'], [$first['id']])->json();

    // Se simula que pasaron 7 días moviendo las fechas del préstamo en la base.
    DB::table('loan')->where('id', $loan['id'])->update(['loaned_at' => now('UTC')->subDays(7)]);
    DB::table('loan_item')->where('loan_id', $loan['id'])->update(['due_at' => now('UTC')->subDays(2)]);

    lendVia($this->archivist, $this->official['id'], [$second['id']])->assertStatus(409)->assertJsonPath('code', 'OFFICIAL_HAS_OVERDUE');

    $this->getJson('/api/v1/loans/overdue', $this->archivist)
        ->assertOk()
        ->assertJsonPath('items.0.official_name', 'María Pérez')
        ->assertJsonPath('items.0.days_late', 2);
    $this->getJson("/api/v1/loans/{$loan['id']}", $this->archivist)->assertJsonPath('status', 'overdue');
});

it('devuelve una línea a la vez, nunca dos veces, y cierra el préstamo (HU-07)', function () {
    $a = createDossier($this->admin, '2025-000430');
    $b = createDossier($this->admin, '2025-000431');
    $loan = lendVia($this->archivist, $this->official['id'], [$a['id'], $b['id']])->json();
    $url = fn ($item) => "/api/v1/loans/{$loan['id']}/items/{$item['id']}/return";

    $this->postJson($url($loan['items'][0]), [], $this->archivist)->assertOk()->assertJsonPath('returned_at', fn ($v) => str_ends_with($v, 'Z'));
    $this->postJson($url($loan['items'][0]), [], $this->archivist)->assertStatus(409)->assertJsonPath('code', 'ITEM_ALREADY_RETURNED');
    $this->getJson("/api/v1/loans/{$loan['id']}", $this->archivist)->assertJsonPath('status', 'open');

    $this->postJson($url($loan['items'][1]), [], $this->archivist)->assertOk();
    $this->getJson("/api/v1/loans/{$loan['id']}", $this->archivist)->assertJsonPath('status', 'closed');
    expect(DB::table('dossier')->whereIn('id', [$a['id'], $b['id']])->sum('available_units'))->toBe(2);
});

it('lista préstamos por estado, funcionario y fecha, del más reciente al más antiguo (HU-08)', function () {
    $a = createDossier($this->admin, '2025-000440');
    $b = createDossier($this->admin, '2025-000441');
    $first = lendVia($this->archivist, $this->official['id'], [$a['id']])->json();
    DB::table('loan')->where('id', $first['id'])->update(['loaned_at' => now('UTC')->subDay()]);
    $second = lendVia($this->archivist, $this->official['id'], [$b['id']])->json();

    $this->getJson("/api/v1/loans?official_id={$this->official['id']}", $this->archivist)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('items.0.id', $second['id']);
    $this->getJson('/api/v1/loans?status=closed', $this->archivist)->assertJsonPath('total', 0);
    $this->getJson('/api/v1/loans?status=perdido', $this->archivist)->assertStatus(422);
    $this->getJson('/api/v1/loans?from=2026-12-31&to=2026-01-01', $this->archivist)->assertStatus(422);
});
