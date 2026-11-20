<?php

namespace App\Application\Port;

use App\Application\Page;
use App\Domain\Dossier;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

interface LoanRepository
{
    /**
     * Guarda el préstamo y las unidades de sus expedientes en UNA transacción, con el testigo de versión
     * (ADR-002). Si otro operador cambió un expediente en medio, lanza DOSSIER_NOT_AVAILABLE y no guarda nada.
     *
     * @param  list<Dossier>  $dossiers
     */
    public function addWithDossiers(Loan $loan, array $dossiers): void;

    public function find(string $id): ?Loan;

    public function search(LoanFilter $filter, DateTimeImmutable $now): Page;

    /** Guarda la devolución y suma la unidad en UNA transacción. Si ya estaba devuelta: ITEM_ALREADY_RETURNED. */
    public function recordReturn(LoanItem $item, Dossier $dossier): void;

    /**
     * Q8: líneas sin devolver con due_at anterior a ahora.
     *
     * @return list<array{loan_id: string, item_id: string, dossier_title: string, filing_number: string, official_name: string, due_at: DateTimeImmutable}>
     */
    public function overdueItems(DateTimeImmutable $now): array;
}
