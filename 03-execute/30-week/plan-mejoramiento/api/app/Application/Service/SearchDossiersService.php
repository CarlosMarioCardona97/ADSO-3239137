<?php

namespace App\Application\Service;

use App\Application\Page;
use App\Application\Port\DossierRepository;
use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;

/** HU-04 · Buscar y consultar expedientes activos. */
final class SearchDossiersService
{
    public function __construct(private readonly DossierRepository $dossiers) {}

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $text = $text !== null && trim($text) !== '' ? trim($text) : null;

        return $this->dossiers->search($text, $categoryId, $page, $size);
    }

    /** CA-04.3: inexistente o dado de baja → 404. */
    public function get(string $id): Dossier
    {
        return $this->dossiers->findActive($id) ?? throw BusinessRuleViolation::notFound('el expediente');
    }
}
