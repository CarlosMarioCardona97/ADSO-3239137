<?php

namespace App\Infrastructure\Persistence;

use App\Application\Port\ReportReader;
use App\Domain\ValueObject\DateRange;
use Illuminate\Database\ConnectionInterface;

/**
 * Q9 · Reporte de más solicitados (ADR-004): UNA consulta agregada en el motor, agrupada por los valores
 * CONGELADOS de la línea. No une con el catálogo vivo ni expone funcionarios ni archivistas.
 */
final class DbReportReader implements ReportReader
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function mostRequested(DateRange $range): array
    {
        $rows = $this->db->select(
            'SELECT i.dossier_id, i.dossier_title, i.filing_number, i.category_name, COUNT(*) AS times_requested
               FROM loan_item i
               JOIN loan l ON l.id = i.loan_id
              WHERE l.loaned_at >= ? AND l.loaned_at < ?
              GROUP BY i.dossier_id, i.dossier_title, i.filing_number, i.category_name
              ORDER BY times_requested DESC, i.dossier_title',
            [Timestamps::toDb($range->from), Timestamps::toDb($range->toExclusive())],
        );

        return array_map(fn ($r) => [
            'dossier_id' => $r->dossier_id,
            'dossier_title' => $r->dossier_title,
            'filing_number' => $r->filing_number,
            'category_name' => $r->category_name,
            'times_requested' => (int) $r->times_requested,
        ], $rows);
    }
}
