<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// T-18 · Las invariantes que el motor puede imponer, las impone (artículo VI): un INSERT directo que
// las viole falla aunque no pase por el dominio.

it('rechaza un expediente con total de unidades 0 (ck_dossier_total_positive)', function () {
    expect(fn () => DB::table('dossier')->insert([
        'id' => (string) Str::uuid(), 'title' => 'x', 'producing_office' => 'y', 'filing_number' => '2025-000900',
        'category_id' => CATEGORY_CONTRACTS, 'total_units' => 0, 'available_units' => 0, 'version' => 1,
        'cover_key' => null, 'deleted_at' => null,
    ]))->toThrow(QueryException::class, 'ck_dossier_total_positive');
});

it('rechaza un rol distinto de admin o archivist (ck_user_role)', function () {
    expect(fn () => DB::table('user')->insert([
        'id' => (string) Str::uuid(), 'username' => 'root', 'password_hash' => 'x', 'role' => 'superuser',
    ]))->toThrow(QueryException::class, 'ck_user_role');
});

it('rechaza un radicado con formato inválido (ck_dossier_filing_number_format)', function () {
    expect(fn () => DB::table('dossier')->insert([
        'id' => (string) Str::uuid(), 'title' => 'x', 'producing_office' => 'y', 'filing_number' => 'ABCD-12345X',
        'category_id' => CATEGORY_CONTRACTS, 'total_units' => 1, 'available_units' => 1, 'version' => 1,
        'cover_key' => null, 'deleted_at' => null,
    ]))->toThrow(QueryException::class, 'ck_dossier_filing_number_format');
});

it('impide borrar un expediente que está en el histórico de préstamos (FK RESTRICT, ADR-003)', function () {
    $admin = headersFor('admin');
    $dossier = createDossier($admin, '2025-000901');
    $official = createOfficial($admin, '1075000901');
    $this->postJson('/api/v1/loans', ['official_id' => $official['id'], 'items' => [['dossier_id' => $dossier['id']]]], $admin)->assertCreated();

    expect(fn () => DB::table('dossier')->where('id', $dossier['id'])->delete())
        ->toThrow(QueryException::class, 'loan_item_dossier_id_fkey');
});
