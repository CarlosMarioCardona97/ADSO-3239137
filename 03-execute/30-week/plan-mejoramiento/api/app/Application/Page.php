<?php

namespace App\Application;

/** Resultado paginado del contrato: { items, page, size, total }. */
final class Page
{
    /** @param list<mixed> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $size,
        public readonly int $total,
    ) {}
}
