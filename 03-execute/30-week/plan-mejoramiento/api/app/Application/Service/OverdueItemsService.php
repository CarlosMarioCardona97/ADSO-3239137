<?php

namespace App\Application\Service;

use App\Application\Port\Clock;
use App\Application\Port\LoanRepository;

/** HU-09 · Expedientes vencidos, de más a menos días de retraso (CA-09.2). */
final class OverdueItemsService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $now = $this->clock->now();
        $rows = array_map(function (array $row) use ($now) {
            $row['days_late'] = (int) ceil(($now->getTimestamp() - $row['due_at']->getTimestamp()) / 86400);

            return $row;
        }, $this->loans->overdueItems($now));

        usort($rows, fn (array $a, array $b) => $b['days_late'] <=> $a['days_late']);

        return $rows;
    }
}
