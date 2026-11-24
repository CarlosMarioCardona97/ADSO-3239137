<?php

namespace App\Infrastructure\Persistence;

use App\Application\Page;
use App\Application\Port\DossierRepository;
use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Expedientes en PostgreSQL. El filtro de baja lógica (`deleted_at IS NULL`) vive en un único lugar:
 * el método active() (ADR-003).
 */
final class DbDossierRepository implements DossierRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $query = $this->active();
        if ($text !== null) {
            $like = '%'.addcslashes($text, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('d.title', 'ilike', $like)
                ->orWhere('d.producing_office', 'ilike', $like)
                ->orWhere('d.filing_number', 'ilike', $like));
        }
        if ($categoryId !== null) {
            if (! Uuid::isValid($categoryId)) {
                return new Page([], $page, $size, 0);
            }
            $query->where('d.category_id', $categoryId);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('d.title')->orderBy('d.id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page($rows->map(fn ($row) => $this->toDomain($row))->all(), $page, $size, $total);
    }

    public function findActive(string $id): ?Dossier
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = $this->active()->where('d.id', $id)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function find(string $id): ?Dossier
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = $this->base()->where('d.id', $id)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function findActiveMany(array $ids): array
    {
        $valid = array_values(array_filter($ids, fn ($id) => Uuid::isValid($id)));
        $found = [];
        foreach ($this->active()->whereIn('d.id', $valid)->get() as $row) {
            $found[$row->id] = $this->toDomain($row);
        }

        return $found;
    }

    public function filingNumberInUse(string $filingNumber): bool
    {
        return $this->db->table('dossier')->where('filing_number', $filingNumber)->whereNull('deleted_at')->exists();
    }

    public function hasOpenLoans(string $id): bool
    {
        return $this->db->table('loan_item')->where('dossier_id', $id)->whereNull('returned_at')->exists();
    }

    public function add(Dossier $dossier): void
    {
        try {
            $this->db->table('dossier')->insert([
                'id' => $dossier->id,
                'title' => $dossier->title(),
                'producing_office' => $dossier->producingOffice(),
                'filing_number' => $dossier->filingNumber->value,
                'category_id' => $dossier->categoryId(),
                'total_units' => $dossier->totalUnits(),
                'available_units' => $dossier->availableUnits(),
                'version' => $dossier->version(),
                'cover_key' => $dossier->coverKey(),
                'deleted_at' => null,
            ]);
        } catch (QueryException $e) {
            // Barrera del motor: índice único parcial del radicado (carrera entre dos altas iguales).
            if ($e->getCode() === '23505') {
                throw new BusinessRuleViolation('FILING_NUMBER_TAKEN', "Ya existe un expediente activo con el radicado {$dossier->filingNumber->value}.");
            }
            throw $e;
        }
    }

    /**
     * Las disponibles se ajustan EN EL MOTOR con la diferencia del total, así no se pisa un préstamo
     * que ocurra al mismo tiempo. Si quedaran negativas, el CHECK ck_dossier_available_range lo impide.
     */
    public function saveDetails(Dossier $dossier): void
    {
        try {
            $this->db->update(
                'UPDATE dossier SET title = ?, producing_office = ?, category_id = ?,
                        available_units = available_units + (? - total_units), total_units = ?, version = version + 1
                 WHERE id = ?',
                [$dossier->title(), $dossier->producingOffice(), $dossier->categoryId(),
                    $dossier->totalUnits(), $dossier->totalUnits(), $dossier->id],
            );
        } catch (QueryException $e) {
            if ($e->getCode() === '23514') {
                throw BusinessRuleViolation::unitsBelowLoaned($dossier->totalUnits() - $dossier->availableUnits());
            }
            throw $e;
        }
    }

    public function saveCoverKey(Dossier $dossier): void
    {
        $this->db->table('dossier')->where('id', $dossier->id)->update(['cover_key' => $dossier->coverKey()]);
    }

    public function saveRetirement(Dossier $dossier): void
    {
        $this->db->table('dossier')->where('id', $dossier->id)->whereNull('deleted_at')
            ->update(['deleted_at' => Timestamps::toDb($dossier->deletedAt())]);
    }

    private function base(): Builder
    {
        return $this->db->table('dossier as d')
            ->join('category as c', 'c.id', '=', 'd.category_id')
            ->select('d.*', 'c.name as category_name');
    }

    private function active(): Builder
    {
        return $this->base()->whereNull('d.deleted_at');
    }

    private function toDomain(object $row): Dossier
    {
        return Dossier::restore(
            $row->id, $row->title, $row->producing_office, $row->filing_number, $row->category_id,
            $row->category_name, (int) $row->total_units, (int) $row->available_units, (int) $row->version,
            $row->cover_key, Timestamps::fromDb($row->deleted_at),
        );
    }
}
