<?php

namespace App\Application\Service;

use App\Application\Port\Clock;
use App\Application\Port\DossierRepository;
use App\Application\Port\IdGenerator;
use App\Application\Port\LoanRepository;
use App\Application\Port\OfficialRepository;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Loan;
use App\Domain\Official;

/** HU-06 · Registrar un préstamo. Un servicio, un caso de uso (SRP). */
final class LendDossiersService
{
    public function __construct(
        private readonly OfficialRepository $officials,
        private readonly DossierRepository $dossiers,
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
        private readonly IdGenerator $ids,
    ) {}

    /** @param list<string> $dossierIds */
    public function lend(string $officialId, array $dossierIds, string $userId): Loan
    {
        // CA-06.1: de 1 a 3 líneas.
        if (count($dossierIds) < 1 || count($dossierIds) > Loan::MAX_ITEMS) {
            throw BusinessRuleViolation::validation('items', 'un préstamo admite de 1 a 3 expedientes');
        }
        // CA-06.2: sin expedientes repetidos.
        if (count(array_unique($dossierIds)) !== count($dossierIds)) {
            throw new BusinessRuleViolation('DUPLICATE_DOSSIER_IN_LOAN', 'Hay un expediente repetido en el préstamo.');
        }

        if ($this->officials->find($officialId) === null) {
            throw BusinessRuleViolation::notFound('el funcionario');
        }

        $now = $this->clock->now();
        // RN-3 y CA-06.4: sin vencidos y máximo 3 sin devolver.
        Official::assertCanBorrow(
            $this->officials->openItemsCount($officialId),
            $this->officials->hasOverdue($officialId, $now),
            count($dossierIds),
        );

        $found = $this->dossiers->findActiveMany($dossierIds);
        $loan = Loan::open($this->ids->next(), $officialId, $userId, $now);
        $touched = [];
        foreach (array_values($dossierIds) as $index => $dossierId) {
            $dossier = $found[$dossierId] ?? throw new BusinessRuleViolation(
                'NOT_FOUND',
                'No se encontró el expediente.',
                [['field' => "items[{$index}].dossier_id", 'issue' => 'no existe o está dado de baja']],
            );
            // CA-06.3: descuenta 1 unidad; si no hay, 409 y no se registra nada.
            $loan->addItem($this->ids->next(), $dossier);
            $touched[] = $dossier;
        }

        // Una sola transacción con testigo de versión (ADR-002).
        $this->loans->addWithDossiers($loan, $touched);

        return $loan;
    }
}
