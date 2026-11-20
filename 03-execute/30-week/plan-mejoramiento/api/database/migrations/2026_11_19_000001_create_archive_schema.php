<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-02 · Migración inicial del esquema `archive` (docs/data-model.md §3 a §5 y §9).
 * Se escribe en SQL para que coincida exactamente con el documento: ninguna columna con DEFAULT,
 * CHECK de rango, índices parciales y FK con su política. Todo el DDL vive aquí (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE SCHEMA IF NOT EXISTS archive;

            CREATE TABLE archive.category (
                id   uuid         NOT NULL,
                name varchar(120) NOT NULL,
                CONSTRAINT category_pkey PRIMARY KEY (id),
                CONSTRAINT category_name_unique UNIQUE (name)
            );

            CREATE TABLE archive.dossier (
                id               uuid         NOT NULL,
                title            varchar(250) NOT NULL,
                producing_office varchar(200) NOT NULL,
                filing_number    char(11)     NOT NULL,
                category_id      uuid         NOT NULL,
                total_units      integer      NOT NULL,
                available_units  integer      NOT NULL,
                version          integer      NOT NULL,
                cover_key        varchar(512) NULL,
                deleted_at       timestamptz  NULL,
                CONSTRAINT dossier_pkey PRIMARY KEY (id),
                CONSTRAINT dossier_category_id_fkey FOREIGN KEY (category_id)
                    REFERENCES archive.category (id) ON DELETE RESTRICT,
                CONSTRAINT ck_dossier_available_range CHECK (available_units >= 0 AND available_units <= total_units),
                CONSTRAINT ck_dossier_filing_number_format CHECK (filing_number ~ '^[0-9]{4}-[0-9]{6}$')
            );
            CREATE UNIQUE INDEX dossier_filing_number_active_unique
                ON archive.dossier (filing_number) WHERE deleted_at IS NULL;
            CREATE INDEX dossier_category_title_active_idx
                ON archive.dossier (category_id, title) WHERE deleted_at IS NULL;

            CREATE TABLE archive.official (
                id              uuid         NOT NULL,
                full_name       varchar(200) NOT NULL,
                document_number varchar(30)  NOT NULL,
                CONSTRAINT official_pkey PRIMARY KEY (id),
                CONSTRAINT official_document_number_unique UNIQUE (document_number)
            );

            CREATE TABLE archive."user" (
                id            uuid         NOT NULL,
                username      varchar(120) NOT NULL,
                password_hash varchar(512) NOT NULL,
                role          varchar(40)  NOT NULL,
                CONSTRAINT user_pkey PRIMARY KEY (id),
                CONSTRAINT user_username_unique UNIQUE (username)
            );

            CREATE TABLE archive.loan (
                id                uuid        NOT NULL,
                official_id       uuid        NOT NULL,
                loaned_by_user_id uuid        NOT NULL,
                loaned_at         timestamptz NOT NULL,
                CONSTRAINT loan_pkey PRIMARY KEY (id),
                CONSTRAINT loan_official_id_fkey FOREIGN KEY (official_id)
                    REFERENCES archive.official (id) ON DELETE RESTRICT,
                CONSTRAINT loan_loaned_by_user_id_fkey FOREIGN KEY (loaned_by_user_id)
                    REFERENCES archive."user" (id) ON DELETE RESTRICT
            );
            CREATE INDEX loan_loaned_at_idx ON archive.loan (loaned_at);
            CREATE INDEX loan_official_id_idx ON archive.loan (official_id);

            CREATE TABLE archive.loan_item (
                id            uuid         NOT NULL,
                loan_id       uuid         NOT NULL,
                dossier_id    uuid         NOT NULL,
                dossier_title varchar(250) NOT NULL,
                filing_number char(11)     NOT NULL,
                category_name varchar(120) NOT NULL,
                due_at        timestamptz  NOT NULL,
                returned_at   timestamptz  NULL,
                CONSTRAINT loan_item_pkey PRIMARY KEY (id),
                CONSTRAINT loan_item_loan_id_fkey FOREIGN KEY (loan_id)
                    REFERENCES archive.loan (id) ON DELETE CASCADE,
                CONSTRAINT loan_item_dossier_id_fkey FOREIGN KEY (dossier_id)
                    REFERENCES archive.dossier (id) ON DELETE RESTRICT,
                CONSTRAINT loan_item_loan_dossier_unique UNIQUE (loan_id, dossier_id)
            );
            CREATE INDEX loan_item_dossier_id_idx ON archive.loan_item (dossier_id);
            CREATE INDEX loan_item_open_due_at_idx ON archive.loan_item (due_at) WHERE returned_at IS NULL;

            INSERT INTO archive.category (id, name) VALUES
                ('11111111-1111-4111-8111-111111111111', 'Contratos'),
                ('22222222-2222-4222-8222-222222222222', 'Personal'),
                ('33333333-3333-4333-8333-333333333333', 'Predial'),
                ('44444444-4444-4444-8444-444444444444', 'Actas'),
                ('55555555-5555-4555-8555-555555555555', 'Jurídico');
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP SCHEMA IF EXISTS archive CASCADE;');
    }
};
