<?php

namespace App\Application\Service;

use App\Application\Page;
use App\Application\Port\Clock;
use App\Application\Port\LoanFilter;
use App\Application\Port\LoanRepository;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Loan;
use DateTimeImmutable;

/** HU-08 · Ver un préstamo y listar préstamos con su estado calculado. */
final class LoanQueryService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {}

    public function get(string $id): Loan
    {
        return $this->loans->find($id) ?? throw BusinessRuleViolation::notFound('el préstamo');
    }

    public function search(LoanFilter $filter): Page
    {
        return $this->loans->search($filter, $this->clock->now());
    }

    /** Instante usado para calcular el estado (artículo VII). */
    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
