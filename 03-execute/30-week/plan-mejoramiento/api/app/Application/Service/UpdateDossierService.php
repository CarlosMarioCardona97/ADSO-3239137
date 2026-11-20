<?php

namespace App\Application\Service;

use App\Application\Port\CategoryReader;
use App\Application\Port\DossierRepository;
use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\ValueObject\Quantity;

/** HU-03 · PUT /dossiers/{id}: reemplaza título, dependencia, categoría y total (CA-03.3). */
final class UpdateDossierService
{
    public function __construct(
        private readonly DossierRepository $dossiers,
        private readonly CategoryReader $categories,
    ) {}

    public function update(string $id, string $title, string $producingOffice, string $categoryId, int $totalUnits): Dossier
    {
        $dossier = $this->dossiers->findActive($id) ?? throw BusinessRuleViolation::notFound('el expediente');
        $categoryName = $this->categories->nameOf($categoryId)
            ?? throw BusinessRuleViolation::validation('category_id', 'no corresponde a ninguna categoría');

        $dossier->update($title, $producingOffice, $categoryId, $categoryName, Quantity::of($totalUnits));
        $this->dossiers->saveDetails($dossier);

        return $this->dossiers->findActive($id) ?? $dossier;
    }
}
