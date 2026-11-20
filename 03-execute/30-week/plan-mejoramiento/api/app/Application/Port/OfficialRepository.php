<?php

namespace App\Application\Port;

use App\Application\Page;
use App\Domain\Official;
use DateTimeImmutable;

interface OfficialRepository
{
    public function search(?string $text, int $page, int $size): Page;

    public function find(string $id): ?Official;

    public function documentInUse(string $documentNumber): bool;

    public function add(Official $official): void;

    /** Q7: expedientes sin devolver del funcionario. */
    public function openItemsCount(string $officialId): int;

    /** Q7: ¿tiene alguna línea sin devolver y vencida? */
    public function hasOverdue(string $officialId, DateTimeImmutable $now): bool;
}
