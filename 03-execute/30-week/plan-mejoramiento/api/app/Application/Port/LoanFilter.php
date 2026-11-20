<?php

namespace App\Application\Port;

use App\Domain\LoanStatus;
use DateTimeImmutable;

/** Filtros de GET /loans (CA-08.2). */
final class LoanFilter
{
    public function __construct(
        public readonly ?DateTimeImmutable $from,
        public readonly ?DateTimeImmutable $toExclusive,
        public readonly ?LoanStatus $status,
        public readonly ?string $officialId,
        public readonly int $page,
        public readonly int $size,
    ) {}
}
