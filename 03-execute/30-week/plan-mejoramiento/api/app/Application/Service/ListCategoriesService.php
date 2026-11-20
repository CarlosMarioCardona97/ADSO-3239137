<?php

namespace App\Application\Service;

use App\Application\Port\CategoryReader;

/** GET /categories · Las cinco categorías en orden por nombre. */
final class ListCategoriesService
{
    public function __construct(private readonly CategoryReader $categories) {}

    /** @return list<array{id: string, name: string}> */
    public function list(): array
    {
        return $this->categories->all();
    }
}
