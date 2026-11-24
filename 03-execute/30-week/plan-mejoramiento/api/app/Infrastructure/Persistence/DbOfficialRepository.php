<?php

namespace App\Infrastructure\Persistence;

use App\Application\Page;
use App\Application\Port\OfficialRepository;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Official;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/** Funcionarios en PostgreSQL. No existe operación de borrado (CA-05.3). */
final class DbOfficialRepository implements OfficialRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function search(?string $text, int $page, int $size): Page
    {
        $query = $this->db->table('official');
        if ($text !== null) {
            $like = '%'.addcslashes($text, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('full_name', 'ilike', $like)->orWhere('document_number', 'ilike', $like));
        }
        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page(
            $rows->map(fn ($r) => Official::restore($r->id, $r->full_name, $r->document_number))->all(),
            $page, $size, $total,
        );
    }

    public function find(string $id): ?Official
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = $this->db->table('official')->where('id', $id)->first();

        return $row === null ? null : Official::restore($row->id, $row->full_name, $row->document_number);
    }

    public function documentInUse(string $documentNumber): bool
    {
        return $this->db->table('official')->where('document_number', $documentNumber)->exists();
    }

    public function add(Official $official): void
    {
        try {
            $this->db->table('official')->insert([
                'id' => $official->id,
                'full_name' => $official->fullName,
                'document_number' => $official->documentNumber,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new BusinessRuleViolation('DOCUMENT_TAKEN', "Ya existe un funcionario con el documento {$official->documentNumber}.");
            }
            throw $e;
        }
    }

    public function openItemsCount(string $officialId): int
    {
        return $this->openItems($officialId)->count();
    }

    public function hasOverdue(string $officialId, DateTimeImmutable $now): bool
    {
        return $this->openItems($officialId)->where('i.due_at', '<', Timestamps::toDb($now))->exists();
    }

    private function openItems(string $officialId): Builder
    {
        return $this->db->table('loan_item as i')
            ->join('loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.official_id', $officialId)
            ->whereNull('i.returned_at');
    }
}
