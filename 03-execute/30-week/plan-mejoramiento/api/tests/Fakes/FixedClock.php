<?php

namespace Tests\Fakes;

use App\Application\Port\Clock;
use DateTimeImmutable;

/** Reloj de pruebas: devuelve siempre el instante que se le indique. */
final class FixedClock implements Clock
{
    public function __construct(public DateTimeImmutable $now = new DateTimeImmutable('2026-11-10T14:00:00Z')) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceDays(int $days): void
    {
        $this->now = $this->now->modify("+{$days} days");
    }
}
