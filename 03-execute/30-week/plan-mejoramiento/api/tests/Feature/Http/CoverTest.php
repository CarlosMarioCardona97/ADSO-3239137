<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// T-15 · CA-03.4: carátula JPG/PNG de máximo 2 MB, clave opaca, null sin carátula, tipo validado por contenido.

/** PNG válido de 1x1 relleno hasta el tamaño pedido (los bytes de más no cambian el tipo detectado). */
function pngOfSize(int $bytes): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent('caratula.png', str_pad($png, $bytes, "\0"));
}

beforeEach(function () {
    Storage::fake('local');
    $this->admin = headersFor('admin');
    $this->dossier = createDossier($this->admin, '2025-000200');
});

it('arranca sin carátula: cover_key es null, nunca cadena vacía', function () {
    expect($this->dossier['cover_key'])->toBeNull();
    $this->getJson("/api/v1/dossiers/{$this->dossier['id']}/cover", $this->admin)->assertNotFound();
});

it('acepta un PNG de 2 MB y guarda una clave opaca', function () {
    $response = $this->put("/api/v1/dossiers/{$this->dossier['id']}/cover", ['file' => pngOfSize(2 * 1024 * 1024)], $this->admin)
        ->assertOk();

    $key = $response->json('cover_key');
    expect($key)->toMatch('/^[0-9a-f-]{36}\.png$/')->and($key)->not->toContain('/');

    $this->get("/api/v1/dossiers/{$this->dossier['id']}/cover", $this->admin)
        ->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('rechaza más de 2 MB y archivos que no son imagen aunque digan .png', function () {
    $this->put("/api/v1/dossiers/{$this->dossier['id']}/cover", ['file' => pngOfSize(3 * 1024 * 1024)], $this->admin)
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');

    $fake = UploadedFile::fake()->createWithContent('falsa.png', 'esto es texto, no una imagen');
    $this->put("/api/v1/dossiers/{$this->dossier['id']}/cover", ['file' => $fake], $this->admin)
        ->assertStatus(422)->assertJsonPath('details.0.field', 'file');
});

it('quita la carátula: primero anula la clave y luego borra el archivo', function () {
    $key = $this->put("/api/v1/dossiers/{$this->dossier['id']}/cover", ['file' => pngOfSize(1024)], $this->admin)->json('cover_key');
    Storage::disk('local')->assertExists("covers/{$key}");

    $this->deleteJson("/api/v1/dossiers/{$this->dossier['id']}/cover", [], $this->admin)->assertNoContent();

    $this->getJson("/api/v1/dossiers/{$this->dossier['id']}", $this->admin)->assertJsonPath('cover_key', null);
    Storage::disk('local')->assertMissing("covers/{$key}");
});
