<?php

namespace App\Application\Service;

use App\Application\Port\Clock;
use App\Application\Port\DossierRepository;
use App\Application\Port\LoanRepository;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\LoanItem;

/** HU-07 · Devolver una línea a la vez (CA-07.1, CA-07.2). */
final class ReturnItemService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly DossierRepository $dossiers,
        private readonly Clock $clock,
    ) {}

    public function returnItem(string $loanId, string $itemId): LoanItem
    {
        $loan = $this->loans->find($loanId) ?? throw BusinessRuleViolation::notFound('el préstamo');
        $item = $loan->item($itemId);
        $dossier = $this->dossiers->find($item->dossierId) ?? throw BusinessRuleViolation::notFound('el expediente');

        $loan->returnItem($itemId, $dossier, $this->clock->now());
        $this->loans->recordReturn($item, $dossier);

        return $item;
    }
}
