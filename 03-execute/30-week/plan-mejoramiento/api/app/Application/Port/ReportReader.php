<?php

namespace App\Application\Port;

use App\Domain\ValueObject\DateRange;

/** Puerto de lectura del reporte (ADR-004): una consulta agregada sobre los valores congelados. */
interface ReportReader
{
    /** @return list<array{dossier_id: string, dossier_title: string, filing_number: string, category_name: string, times_requested: int}> */
    public function mostRequested(DateRange $range): array;
}
