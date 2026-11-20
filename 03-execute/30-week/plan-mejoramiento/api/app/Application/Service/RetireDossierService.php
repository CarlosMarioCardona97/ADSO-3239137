<?php

namespace App\Application\Service;

use App\Application\Port\Clock;
use App\Application\Port\DossierRepository;
use App\Domain\Exception\BusinessRuleViolation;

/** HU-03 · DELETE /dossiers/{id}: baja lógica (CA-03.5, ADR-003). */
final class RetireDossierService
{
    public function __construct(
        private readonly DossierRepository $dossiers,
        private readonly Clock $clock,
    ) {}

    public function retire(string $id): void
    {
        $dossier = $this->dossiers->findActive($id) ?? throw BusinessRuleViolation::notFound('el expediente');
        if ($this->dossiers->hasOpenLoans($id)) {
            throw new BusinessRuleViolation(
                'DOSSIER_HAS_OPEN_LOANS',
                'El expediente tiene préstamos sin devolver y no se puede dar de baja.',
            );
        }
        $dossier->retire($this->clock->now());
        $this->dossiers->saveRetirement($dossier);
    }
}
