<?php

namespace App\Infrastructure\Security;

use App\Application\Port\IdGenerator;
use Illuminate\Support\Str;

/** Identificadores UUID v4 como texto (api-contract §1). */
final class UuidGenerator implements IdGenerator
{
    public function next(): string
    {
        return (string) Str::uuid();
    }
}
