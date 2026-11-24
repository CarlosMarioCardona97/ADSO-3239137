<?php

namespace App\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;

/** Conversión entre `timestamptz` de PostgreSQL y DateTimeImmutable en UTC (RN-6). */
final class Timestamps
{
    public static function toDb(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function fromDb(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    /** Fecha y hora con Z para el contrato HTTP (api-contract §1). */
    public static function toApi(?DateTimeImmutable $instant): ?string
    {
        return $instant?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
