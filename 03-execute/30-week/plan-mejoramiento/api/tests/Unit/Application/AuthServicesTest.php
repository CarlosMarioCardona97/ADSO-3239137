<?php

use App\Application\Service\CreateArchivistService;
use App\Application\Service\LoginService;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\User;
use Tests\Fakes\FakeSecurity;
use Tests\Fakes\FakeTokens;
use Tests\Fakes\InMemoryStore;
use Tests\Fakes\InMemoryUsers;

// T-07 · HU-01 y HU-02 contra fakes.

beforeEach(function () {
    $this->store = new InMemoryStore;
    $this->users = new InMemoryUsers($this->store);
    $this->security = new FakeSecurity;
    $this->login = new LoginService($this->users, $this->security, new FakeTokens);
    $this->create = new CreateArchivistService($this->users, $this->security, $this->security);
    $this->users->add(User::bootstrapAdmin('admin-id', 'admin', $this->security->hash('Admin-2026')));
});

it('entrega un token de 8 horas con credenciales válidas (CA-01.1)', function () {
    $result = $this->login->login(' ADMIN ', 'Admin-2026');
    expect($result['token_type'])->toBe('Bearer')
        ->and($result['expires_in'])->toBe(28800)
        ->and($result['role'])->toBe('admin');
});

it('responde lo mismo si falla el usuario o la clave (CA-01.2)', function (string $user, string $pass) {
    try {
        $this->login->login($user, $pass);
        $this->fail('Debía fallar');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('INVALID_CREDENTIALS')
            ->and($e->getMessage())->toBe('Usuario o contraseña incorrectos.');
    }
})->with([['nadie', 'Admin-2026'], ['admin', 'otra-clave']]);

it('crea archivistas normalizados y sin devolver la clave (CA-02.1, CA-02.2)', function () {
    $user = $this->create->create('Ana ', 'clave-segura', 'archivist');
    expect($user->username)->toBe('ana')
        ->and($user->passwordHash)->not->toContain('clave-segura');
});

it('rechaza nombres repetidos con USERNAME_TAKEN (CA-02.2)', function () {
    $this->create->create('ana', 'clave-segura', 'archivist');
    try {
        $this->create->create(' ANA', 'otra-clave-1', 'archivist');
        $this->fail('Debía fallar');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('USERNAME_TAKEN');
    }
});

it('rechaza pedir el rol admin (CA-02.3)', function () {
    $this->create->create('mallory', 'clave-segura', 'admin');
})->throws(BusinessRuleViolation::class);
