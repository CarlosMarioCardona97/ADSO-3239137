<?php

namespace App\Application\Service;

use App\Application\Port\CategoryReader;
use App\Application\Port\DossierRepository;
use App\Application\Port\IdGenerator;
use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\ValueObject\FilingNumber;
use App\Domain\ValueObject\Quantity;

/** HU-03 · Crear expediente (CA-03.1, CA-03.2). */
final class CreateDossierService
{
    public function __construct(
        private readonly DossierRepository $dossiers,
        private readonly CategoryReader $categories,
        private readonly IdGenerator $ids,
    ) {}

    public function create(string $title, string $producingOffice, string $filingNumber, string $categoryId, int $totalUnits): Dossier
    {
        $categoryName = $this->categories->nameOf($categoryId)
            ?? throw BusinessRuleViolation::validation('category_id', 'no corresponde a ninguna categoría');

        $dossier = Dossier::register(
            $this->ids->next(), $title, $producingOffice, FilingNumber::fromString($filingNumber),
            $categoryId, $categoryName, Quantity::of($totalUnits),
        );

        if ($this->dossiers->filingNumberInUse($dossier->filingNumber->value)) {
            throw new BusinessRuleViolation(
                'FILING_NUMBER_TAKEN',
                "Ya existe un expediente activo con el radicado {$dossier->filingNumber->value}.",
            );
        }
        $this->dossiers->add($dossier);

        return $dossier;
    }
}
