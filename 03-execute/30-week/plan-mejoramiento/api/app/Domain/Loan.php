<?php

namespace App\Domain;

use App\Domain\Exception\BusinessRuleViolation;
use DateTimeImmutable;

/**
 * Préstamo: hecho consumado, inmutable salvo la devolución de sus líneas (CA-06.7).
 * Defiende: de 1 a 3 líneas, sin expediente repetido y vencimiento a 5 días (RN-2).
 */
final class Loan
{
    public const LOAN_DAYS = 5;

    public const MAX_ITEMS = 3;

    /** @param list<LoanItem> $items */
    private function __construct(
        public readonly string $id,
        public readonly string $officialId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
        private array $items,
    ) {}

    public static function open(string $id, string $officialId, string $loanedByUserId, DateTimeImmutable $loanedAt): self
    {
        return new self($id, $officialId, $loanedByUserId, $loanedAt, []);
    }

    /** @param list<LoanItem> $items */
    public static function restore(string $id, string $officialId, string $loanedByUserId, DateTimeImmutable $loanedAt, array $items): self
    {
        return new self($id, $officialId, $loanedByUserId, $loanedAt, $items);
    }

    /**
     * Agrega una línea. Descontar la unidad y crear la línea son UNA operación (architecture.md §3):
     * primero Dossier::withdraw(), que falla si no hay unidades; luego se congela la línea (CA-06.6).
     */
    public function addItem(string $itemId, Dossier $dossier): LoanItem
    {
        if (count($this->items) >= self::MAX_ITEMS) {
            throw BusinessRuleViolation::validation('items', 'un préstamo admite de 1 a 3 expedientes');
        }
        foreach ($this->items as $existing) {
            if ($existing->dossierId === $dossier->id) {
                throw BusinessRuleViolation::duplicateDossierInLoan($dossier->filingNumber->value);
            }
        }
        if (! $dossier->isActive()) {
            throw BusinessRuleViolation::notFound('el expediente');
        }

        $dossier->withdraw();

        $item = new LoanItem(
            $itemId,
            $dossier->id,
            $dossier->title(),
            $dossier->filingNumber->value,
            $dossier->categoryName(),
            $this->loanedAt->modify('+'.self::LOAN_DAYS.' days'),
        );
        $this->items[] = $item;

        return $item;
    }

    /** Devuelve UNA línea (CA-07.1) y suma la unidad al expediente una sola vez (CA-07.2). */
    public function returnItem(string $itemId, Dossier $dossier, DateTimeImmutable $at): LoanItem
    {
        $item = $this->item($itemId);
        if ($item->dossierId !== $dossier->id) {
            throw new \LogicException('El expediente no corresponde a la línea.');
        }
        $item->markReturned($at);
        $dossier->restock();

        return $item;
    }

    public function item(string $itemId): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }
        throw BusinessRuleViolation::notFound('la línea del préstamo');
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** CA-08.1 y CA-08.3: el estado se calcula con las líneas y el instante actual; no se almacena. */
    public function status(DateTimeImmutable $now): LoanStatus
    {
        $pending = array_filter($this->items, fn (LoanItem $item) => ! $item->isReturned());
        if ($pending === []) {
            return LoanStatus::Closed;
        }
        foreach ($pending as $item) {
            if ($item->isOverdue($now)) {
                return LoanStatus::Overdue;
            }
        }

        return LoanStatus::Open;
    }
}
