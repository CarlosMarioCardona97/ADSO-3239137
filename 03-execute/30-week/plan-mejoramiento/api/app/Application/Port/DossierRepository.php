<?php

namespace App\Application\Port;

use App\Application\Page;
use App\Domain\Dossier;

interface DossierRepository
{
    /** Q1: solo activos, texto parcial en título, dependencia o radicado, orden por título. */
    public function search(?string $text, ?string $categoryId, int $page, int $size): Page;

    /** Q2: null si no existe o está dado de baja. */
    public function findActive(string $id): ?Dossier;

    /** Incluye los dados de baja (para devoluciones de préstamos viejos). */
    public function find(string $id): ?Dossier;

    /**
     * Q3: expedientes activos por lote.
     *
     * @param  list<string>  $ids
     * @return array<string, Dossier> indexados por id
     */
    public function findActiveMany(array $ids): array;

    public function filingNumberInUse(string $filingNumber): bool;

    public function hasOpenLoans(string $id): bool;

    public function add(Dossier $dossier): void;

    /** Guarda título, dependencia, categoría y total; ajusta las disponibles en el motor. */
    public function saveDetails(Dossier $dossier): void;

    public function saveCoverKey(Dossier $dossier): void;

    public function saveRetirement(Dossier $dossier): void;
}
