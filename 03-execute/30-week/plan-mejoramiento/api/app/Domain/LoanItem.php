<?php

namespace App\Domain;

use App\Domain\Exception\BusinessRuleViolation;
use DateTimeImmutable;

/**
 * Línea de préstamo: un expediente dentro de un préstamo. Guarda CONGELADOS el título, el radicado y la
 * categoría del instante del préstamo (RN-4, artículo VIII). Lo único que cambia es `returnedAt`.
 */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $dossierId,
        public readonly string $dossierTitle,
        public readonly string $filingNumber,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null,
    ) {}

    /** CA-07.2: devolver una línea ya devuelta es 409 y nunca suma dos veces. */
    public function markReturned(DateTimeImmutable $at): void
    {
        if ($this->returnedAt !== null) {
            throw BusinessRuleViolation::itemAlreadyReturned();
        }
        $this->returnedAt = $at;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return $this->returnedAt === null && $this->dueAt < $now;
    }

    /** Días de retraso (HU-09): cuenta cada día empezado después del vencimiento. 0 si no está vencida. */
    public function daysLate(DateTimeImmutable $now): int
    {
        if (! $this->isOverdue($now)) {
            return 0;
        }

        return (int) ceil(($now->getTimestamp() - $this->dueAt->getTimestamp()) / 86400);
    }
}
