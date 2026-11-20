<?php

namespace App\Application\Port;

use DateTimeImmutable;

/** Reloj (plan D-05). Permite probar vencimientos sin esperar 5 días. Siempre devuelve UTC. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
