<?php

namespace App\Infrastructure\Persistence;

use App\Application\Port\CategoryReader;
use Illuminate\Database\ConnectionInterface;

/** Q4 · Las cinco categorías sembradas por la migración. */
final class DbCategoryReader implements CategoryReader
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function all(): array
    {
        return $this->db->table('category')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])->all();
    }

    public function nameOf(string $id): ?string
    {
        if (! Uuid::isValid($id)) {
            return null;
        }

        return $this->db->table('category')->where('id', $id)->value('name');
    }
}
