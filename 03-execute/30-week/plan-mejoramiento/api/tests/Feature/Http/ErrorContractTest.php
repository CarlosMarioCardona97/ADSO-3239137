<?php

// T-16 · RNF-4: todo error HTTP usa el mismo cuerpo { code, message, details } y nunca filtra detalles internos.

dataset('error cases', [
    'sin token' => fn () => [test()->getJson('/api/v1/dossiers'), 401],
    'rol insuficiente' => fn () => [test()->getJson('/api/v1/reports/most-requested?from=2026-01-01&to=2026-01-02', headersFor('archivist')), 403],
    'id inexistente' => fn () => [test()->getJson('/api/v1/loans/00000000-0000-4000-8000-000000000000', headersFor('admin')), 404],
    'id que no es uuid' => fn () => [test()->getJson('/api/v1/officials/abc', headersFor('admin')), 404],
    'ruta inexistente' => fn () => [test()->getJson('/api/v1/no-existe', headersFor('admin')), 404],
    'validación' => fn () => [test()->postJson('/api/v1/officials', ['full_name' => ''], headersFor('admin')), 422],
]);

it('usa el cuerpo único de error', function (array $case) {
    [$response, $status] = $case;

    $response->assertStatus($status)->assertJsonStructure(['code', 'message', 'details']);
    expect(array_keys($response->json()))->toBe(['code', 'message', 'details'])
        ->and($response->getContent())
        ->not->toContain('SQLSTATE')
        ->not->toContain('vendor/')
        ->not->toContain('trace')
        ->not->toContain('password');
})->with('error cases');

it('lista cada campo inválido en details', function () {
    $this->postJson('/api/v1/dossiers', ['title' => 'Solo título'], headersFor('admin'))
        ->assertStatus(422)
        ->assertJsonPath('details.0.field', 'producing_office')
        ->assertJsonCount(4, 'details');
});
