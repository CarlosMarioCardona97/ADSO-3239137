<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-18 · Baja al motor dos invariantes que eran "solo dominio" (artículo VI):
 * el total de unidades es al menos 1 y el rol es admin o archivist.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE archive.dossier
                ADD CONSTRAINT ck_dossier_total_positive CHECK (total_units >= 1);
            ALTER TABLE archive."user"
                ADD CONSTRAINT ck_user_role CHECK (role IN ('admin', 'archivist'));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE archive.dossier DROP CONSTRAINT IF EXISTS ck_dossier_total_positive;
            ALTER TABLE archive."user" DROP CONSTRAINT IF EXISTS ck_user_role;
        SQL);
    }
};
