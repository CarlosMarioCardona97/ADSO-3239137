<?php

namespace App\Application\Service;

use App\Application\Port\ReportReader;
use App\Domain\ValueObject\DateRange;

/** HU-10 · Reporte de expedientes más solicitados (ADR-004). */
final class MostRequestedReportService
{
    public function __construct(private readonly ReportReader $reports) {}

    /** @return array{from: string, to: string, rows: list<array<string, mixed>>} */
    public function report(string $from, string $to): array
    {
        $range = DateRange::fromDates($from, $to);   // CA-10.2: fin anterior al inicio → 422

        return ['from' => $from, 'to' => $to, 'rows' => $this->reports->mostRequested($range)];
    }
}
