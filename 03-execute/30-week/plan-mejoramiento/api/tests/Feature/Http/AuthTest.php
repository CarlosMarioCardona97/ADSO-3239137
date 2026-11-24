<?php

use App\Domain\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// T-14 · HU-01, HU-02 y RNF-3 por HTTP: token de 8 horas, 401/403 y la clave nunca sale ni se registra.

it('crea el admin inicial desde el entorno y permite iniciar sesión (T-17)', function () {
    // ADMIN_USERNAME=Admin y ADMIN_PASSWORD=Admin-de-pruebas-1 vienen de phpunit.xml.
    expect(Artisan::call('archive:bootstrap-admin'))->toBe(0)
        ->and(Artisan::call('archive:bootstrap-admin'))->toBe(0);   // la segunda vez no duplica

    $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'Admin-de-pruebas-1'])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 28800)
        ->assertJsonPath('role', Role::Admin->value);
});

it('responde 401 igual si falla el usuario o la clave (CA-01.2)', function () {
    headersFor('archivist', 'ana');

    $wrongUser = $this->postJson('/api/v1/auth/login', ['username' => 'nadie', 'password' => 'Clave-segura-1']);
    $wrongPass = $this->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => 'otra']);

    $wrongUser->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    expect($wrongPass->status())->toBe(401)->and($wrongPass->json())->toBe($wrongUser->json());
});

it('exige token y rol (CA-01.3)', function () {
    $this->getJson('/api/v1/categories')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    $this->getJson('/api/v1/categories', ['Authorization' => 'Bearer no-es-un-jwt'])->assertStatus(401);

    $archivist = headersFor('archivist');
    $this->getJson('/api/v1/categories', $archivist)->assertOk()->assertJsonCount(5);
    $this->postJson('/api/v1/users', ['username' => 'x', 'password' => 'Clave-segura-1', 'role' => 'archivist'], $archivist)
        ->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_ROLE');
});

it('crea archivistas sin devolver la clave ni el hash y rechaza el rol admin (CA-02)', function () {
    $admin = headersFor('admin');

    $response = $this->postJson('/api/v1/users', ['username' => ' Luis ', 'password' => 'Clave-secreta-99', 'role' => 'archivist'], $admin)
        ->assertCreated()
        ->assertJsonPath('username', 'luis')
        ->assertJsonMissingPath('password')
        ->assertJsonMissingPath('password_hash');
    expect($response->getContent())->not->toContain('Clave-secreta-99');

    $this->postJson('/api/v1/users', ['username' => 'LUIS', 'password' => 'Clave-secreta-99', 'role' => 'archivist'], $admin)
        ->assertStatus(409)->assertJsonPath('code', 'USERNAME_TAKEN');
    $this->postJson('/api/v1/users', ['username' => 'mallory', 'password' => 'Clave-secreta-99', 'role' => 'admin'], $admin)
        ->assertStatus(422);
    expect(DB::table('user')->where('password_hash', 'like', '%Clave-secreta%')->exists())->toBeFalse();
});

it('nunca escribe la contraseña en los logs (RNF-3)', function () {
    $logged = [];
    Log::listen(function ($message) use (&$logged) {
        $logged[] = json_encode([$message->message, $message->context]);
    });

    $this->postJson('/api/v1/auth/login', ['username' => 'nadie', 'password' => 'Secreto-que-no-debe-salir']);

    expect(implode("\n", $logged))->not->toContain('Secreto-que-no-debe-salir');
});
