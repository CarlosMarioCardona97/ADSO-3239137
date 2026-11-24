<?php

use App\Application\Port\DossierRepository;
use App\Application\Port\LoanRepository;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Loan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// T-13 · RNF-2 · Dos archivistas entregan la ÚLTIMA unidad a la vez: uno gana y el otro recibe 409 (ADR-002).

it('deja pasar solo un préstamo cuando dos operadores leen la misma última unidad', function () {
    $admin = headersFor('admin');
    $dossier = createDossier($admin, '2025-000100', 1);
    $first = createOfficial($admin, '1075000101', 'Operador uno pide');
    $second = createOfficial($admin, '1075000102', 'Operador dos pide');
    $userId = DB::table('user')->value('id');

    $dossiers = app(DossierRepository::class);
    $loans = app(LoanRepository::class);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    // Los dos operadores LEEN el expediente antes de que cualquiera guarde: ambos ven 1 unidad, versión 1.
    $readByA = $dossiers->findActive($dossier['id']);
    $readByB = $dossiers->findActive($dossier['id']);

    $loanA = Loan::open((string) Str::uuid(), $first['id'], $userId, $now);
    $loanA->addItem((string) Str::uuid(), $readByA);
    $loanB = Loan::open((string) Str::uuid(), $second['id'], $userId, $now);
    $loanB->addItem((string) Str::uuid(), $readByB);

    $loans->addWithDossiers($loanA, [$readByA]);                 // gana A

    try {
        $loans->addWithDossiers($loanB, [$readByB]);             // B llega tarde: versión vieja
        $this->fail('El segundo préstamo debía rechazarse');
    } catch (BusinessRuleViolation $e) {
        expect($e->errorCode)->toBe('DOSSIER_NOT_AVAILABLE');
    }

    expect(DB::table('loan')->count())->toBe(1)
        ->and(DB::table('loan_item')->count())->toBe(1)
        ->and(DB::table('dossier')->where('id', $dossier['id'])->value('available_units'))->toBe(0);
});

it('responde 409 por HTTP al pedir un expediente sin unidades y no registra nada', function () {
    $admin = headersFor('admin');
    $dossier = createDossier($admin, '2025-000101', 1);
    $a = createOfficial($admin, '1075000103');
    $b = createOfficial($admin, '1075000104');

    $this->postJson('/api/v1/loans', ['official_id' => $a['id'], 'items' => [['dossier_id' => $dossier['id']]]], $admin)->assertCreated();
    $this->postJson('/api/v1/loans', ['official_id' => $b['id'], 'items' => [['dossier_id' => $dossier['id']]]], $admin)
        ->assertStatus(409)->assertJsonPath('code', 'DOSSIER_NOT_AVAILABLE');

    expect(DB::table('loan')->count())->toBe(1);
});

it('la base rechaza unidades fuera de rango aunque se escriba por fuera del sistema (última barrera)', function () {
    $admin = headersFor('admin');
    $dossier = createDossier($admin, '2025-000102', 1);

    expect(fn () => DB::table('dossier')->where('id', $dossier['id'])->update(['available_units' => -1]))
        ->toThrow(Illuminate\Database\QueryException::class, 'ck_dossier_available_range');
});
