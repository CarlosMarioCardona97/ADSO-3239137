<?php

namespace App\Infrastructure\Security;

use App\Application\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Reloj real en UTC. `CLOCK_OFFSET_DAYS` (por defecto 0) solo lo usa la instancia de siembra para crear
 * préstamos vencidos de demostración por la API (plan D-15). El dominio no se entera.
 */
final class SystemClock implements Clock
{
    public function __construct(private readonly int $offsetDays = 0) {}

    public function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->offsetDays === 0 ? $now : $now->modify("{$this->offsetDays} days");
    }
}
