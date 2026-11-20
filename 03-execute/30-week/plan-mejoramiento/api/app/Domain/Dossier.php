<?php

namespace App\Domain;

use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\ValueObject\FilingNumber;
use App\Domain\ValueObject\Quantity;
use DateTimeImmutable;

/**
 * Expediente del archivo. Defiende RN-1: unidades disponibles entre 0 y el total, siempre.
 * `version` es el testigo de concurrencia optimista (ADR-002).
 */
final class Dossier
{
    private function __construct(
        public readonly string $id,
        private string $title,
        private string $producingOffice,
        public readonly FilingNumber $filingNumber,
        private string $categoryId,
        private string $categoryName,
        private int $totalUnits,
        private int $availableUnits,
        private int $version,
        private ?string $coverKey,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /** CA-03.1: las disponibles arrancan iguales al total. */
    public static function register(
        string $id,
        string $title,
        string $producingOffice,
        FilingNumber $filingNumber,
        string $categoryId,
        string $categoryName,
        Quantity $totalUnits,
    ): self {
        return new self(
            $id,
            self::requireText($title, 'title', 250),
            self::requireText($producingOffice, 'producing_office', 200),
            $filingNumber,
            $categoryId,
            $categoryName,
            $totalUnits->value,
            $totalUnits->value,
            1,
            null,
            null,
        );
    }

    /** Reconstruye un expediente desde la persistencia. */
    public static function restore(
        string $id,
        string $title,
        string $producingOffice,
        string $filingNumber,
        string $categoryId,
        string $categoryName,
        int $totalUnits,
        int $availableUnits,
        int $version,
        ?string $coverKey,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            $id, $title, $producingOffice, FilingNumber::fromString($filingNumber), $categoryId, $categoryName,
            $totalUnits, $availableUnits, $version, $coverKey, $deletedAt,
        );
    }

    /** Entrega una unidad (CA-06.3). Sin unidades: 409 DOSSIER_NOT_AVAILABLE. */
    public function withdraw(): void
    {
        if ($this->availableUnits < 1) {
            throw BusinessRuleViolation::dossierNotAvailable($this->filingNumber->value);
        }
        $this->availableUnits--;
    }

    /** Recibe una unidad devuelta (CA-07.1). Nunca supera el total (CA-07.3). */
    public function restock(): void
    {
        if ($this->availableUnits >= $this->totalUnits) {
            throw new \LogicException('Las unidades disponibles no pueden superar el total.');
        }
        $this->availableUnits++;
    }

    /** PUT /dossiers/{id}. Cambiar el total ajusta las disponibles en la misma diferencia y nunca deja < 0 (CA-03.3). */
    public function update(string $title, string $producingOffice, string $categoryId, string $categoryName, Quantity $totalUnits): void
    {
        $newAvailable = $this->availableUnits + ($totalUnits->value - $this->totalUnits);
        if ($newAvailable < 0) {
            throw BusinessRuleViolation::unitsBelowLoaned($this->totalUnits - $this->availableUnits);
        }
        $this->title = self::requireText($title, 'title', 250);
        $this->producingOffice = self::requireText($producingOffice, 'producing_office', 200);
        $this->categoryId = $categoryId;
        $this->categoryName = $categoryName;
        $this->totalUnits = $totalUnits->value;
        $this->availableUnits = $newAvailable;
    }

    /** Baja lógica (CA-03.5, ADR-003). Que no tenga préstamos abiertos lo comprueba el caso de uso. */
    public function retire(DateTimeImmutable $at): void
    {
        $this->deletedAt = $at;
    }

    /** CA-03.4: sin carátula es null, nunca cadena vacía. */
    public function setCoverKey(?string $coverKey): void
    {
        if ($coverKey === '') {
            throw new \LogicException('La clave de la carátula nunca es cadena vacía.');
        }
        $this->coverKey = $coverKey;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function producingOffice(): string
    {
        return $this->producingOffice;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function categoryName(): string
    {
        return $this->categoryName;
    }

    public function totalUnits(): int
    {
        return $this->totalUnits;
    }

    public function availableUnits(): int
    {
        return $this->availableUnits;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    private static function requireText(string $value, string $field, int $max): string
    {
        $clean = trim($value);
        if ($clean === '') {
            throw BusinessRuleViolation::validation($field, 'es obligatorio');
        }
        if (mb_strlen($clean) > $max) {
            throw BusinessRuleViolation::validation($field, "admite máximo {$max} caracteres");
        }

        return $clean;
    }
}
