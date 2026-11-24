<?php

namespace App\Infrastructure\Persistence;

use App\Application\Page;
use App\Application\Port\LoanFilter;
use App\Application\Port\LoanRepository;
use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\LoanStatus;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/** Préstamos en PostgreSQL con concurrencia optimista sobre el expediente (ADR-002). */
final class DbLoanRepository implements LoanRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function addWithDossiers(Loan $loan, array $dossiers): void
    {
        try {
            $this->db->transaction(function () use ($loan, $dossiers) {
                foreach ($dossiers as $dossier) {
                    // Testigo de versión: si otro operador cambió el expediente desde que lo leímos,
                    // el UPDATE no encuentra la fila (0 filas) y se revierte TODO el préstamo.
                    $affected = $this->db->update(
                        'UPDATE dossier SET available_units = ?, version = version + 1
                         WHERE id = ? AND version = ? AND deleted_at IS NULL',
                        [$dossier->availableUnits(), $dossier->id, $dossier->version()],
                    );
                    if ($affected !== 1) {
                        throw BusinessRuleViolation::dossierNotAvailable($dossier->filingNumber->value);
                    }
                }

                $this->db->table('loan')->insert([
                    'id' => $loan->id,
                    'official_id' => $loan->officialId,
                    'loaned_by_user_id' => $loan->loanedByUserId,
                    'loaned_at' => Timestamps::toDb($loan->loanedAt),
                ]);
                $this->db->table('loan_item')->insert(array_map(fn (LoanItem $item) => [
                    'id' => $item->id,
                    'loan_id' => $loan->id,
                    'dossier_id' => $item->dossierId,
                    'dossier_title' => $item->dossierTitle,
                    'filing_number' => $item->filingNumber,
                    'category_name' => $item->categoryName,
                    'due_at' => Timestamps::toDb($item->dueAt),
                    'returned_at' => null,
                ], $loan->items()));
            });
        } catch (QueryException $e) {
            // Última barrera: el CHECK ck_dossier_available_range (23514).
            if ($e->getCode() === '23514') {
                throw new BusinessRuleViolation('DOSSIER_NOT_AVAILABLE', 'No quedan unidades disponibles del expediente.');
            }
            throw $e;
        }
    }

    public function find(string $id): ?Loan
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = $this->db->table('loan')->where('id', $id)->first();
        if ($row === null) {
            return null;
        }

        return $this->hydrate([$row])[0];
    }

    public function search(LoanFilter $filter, DateTimeImmutable $now): Page
    {
        $query = $this->db->table('loan as l');
        if ($filter->from !== null) {
            $query->where('l.loaned_at', '>=', Timestamps::toDb($filter->from));
        }
        if ($filter->toExclusive !== null) {
            $query->where('l.loaned_at', '<', Timestamps::toDb($filter->toExclusive));
        }
        if ($filter->officialId !== null) {
            if (! Uuid::isValid($filter->officialId)) {
                return new Page([], $filter->page, $filter->size, 0);
            }
            $query->where('l.official_id', $filter->officialId);
        }
        if ($filter->status !== null) {
            $this->whereStatus($query, $filter->status, Timestamps::toDb($now));
        }

        $total = (clone $query)->count();
        $rows = $query->select('l.*')->orderByDesc('l.loaned_at')->orderBy('l.id')
            ->offset(($filter->page - 1) * $filter->size)->limit($filter->size)->get()->all();

        return new Page($this->hydrate($rows), $filter->page, $filter->size, $total);
    }

    public function recordReturn(LoanItem $item, Dossier $dossier): void
    {
        $this->db->transaction(function () use ($item, $dossier) {
            // Solo marca la línea si seguía sin devolver: dos devoluciones simultáneas no suman dos veces.
            $affected = $this->db->update(
                'UPDATE loan_item SET returned_at = ? WHERE id = ? AND returned_at IS NULL',
                [Timestamps::toDb($item->returnedAt()), $item->id],
            );
            if ($affected !== 1) {
                throw BusinessRuleViolation::itemAlreadyReturned();
            }
            $this->db->update(
                'UPDATE dossier SET available_units = available_units + 1, version = version + 1 WHERE id = ?',
                [$dossier->id],
            );
        });
    }

    public function overdueItems(DateTimeImmutable $now): array
    {
        return $this->db->table('loan_item as i')
            ->join('loan as l', 'l.id', '=', 'i.loan_id')
            ->join('official as o', 'o.id', '=', 'l.official_id')
            ->whereNull('i.returned_at')
            ->where('i.due_at', '<', Timestamps::toDb($now))
            ->orderBy('i.due_at')
            ->get(['l.id as loan_id', 'i.id as item_id', 'i.dossier_title', 'i.filing_number', 'o.full_name', 'i.due_at'])
            ->map(fn ($r) => [
                'loan_id' => $r->loan_id,
                'item_id' => $r->item_id,
                'dossier_title' => $r->dossier_title,
                'filing_number' => $r->filing_number,
                'official_name' => $r->full_name,
                'due_at' => Timestamps::fromDb($r->due_at),
            ])->all();
    }

    /** El estado no está en la base (artículo VII): se filtra con la misma regla que Loan::status(). */
    private function whereStatus(Builder $query, LoanStatus $status, string $now): void
    {
        $pending = fn (Builder $q) => $q->from('loan_item as p')->whereColumn('p.loan_id', 'l.id')->whereNull('p.returned_at');
        $overdue = fn (Builder $q) => $pending($q)->where('p.due_at', '<', $now);

        match ($status) {
            LoanStatus::Closed => $query->whereNotExists($pending),
            LoanStatus::Overdue => $query->whereExists($overdue),
            LoanStatus::Open => $query->whereExists($pending)->whereNotExists($overdue),
        };
    }

    /**
     * @param  list<object>  $rows
     * @return list<Loan>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $items = $this->db->table('loan_item')
            ->whereIn('loan_id', array_map(fn ($r) => $r->id, $rows))
            ->orderBy('filing_number')
            ->get()
            ->groupBy('loan_id');

        return array_map(fn ($row) => Loan::restore(
            $row->id,
            $row->official_id,
            $row->loaned_by_user_id,
            Timestamps::fromDb($row->loaned_at),
            ($items[$row->id] ?? collect())->map(fn ($i) => new LoanItem(
                $i->id, $i->dossier_id, $i->dossier_title, $i->filing_number, $i->category_name,
                Timestamps::fromDb($i->due_at), Timestamps::fromDb($i->returned_at),
            ))->values()->all(),
        ), $rows);
    }
}
