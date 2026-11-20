<?php

use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Official;
use App\Domain\Role;
use App\Domain\User;

// T-06 · User y Official: normalización del nombre y rol válido.

it('guarda el nombre de usuario en minúsculas y recortado (CA-02.2)', function () {
    $user = User::createArchivist('u-1', ' Ana ', 'hash', 'archivist');
    expect($user->username)->toBe('ana')->and($user->role)->toBe(Role::Archivist);
});

it('rechaza crear un admin por la API (CA-02.3)', function () {
    User::createArchivist('u-1', 'mallory', 'hash', 'admin');
})->throws(BusinessRuleViolation::class, 'Hay datos inválidos');

it('crea el admin inicial solo desde el arranque', function () {
    expect(User::bootstrapAdmin('u-0', 'Admin', 'hash')->role)->toBe(Role::Admin);
});

it('valida el nombre y el documento del funcionario', function () {
    $official = Official::register('o-1', ' María Pérez ', '1075123456');
    expect($official->fullName)->toBe('María Pérez');

    Official::register('o-2', 'Sin documento', '');
})->throws(BusinessRuleViolation::class);
