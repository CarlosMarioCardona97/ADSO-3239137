<?php

namespace Tests\Fakes;

use App\Application\Port\IdGenerator;
use App\Application\Port\PasswordHasher;

/** Fakes de identificadores y hash: deterministas y sin criptografía. */
final class FakeSecurity implements IdGenerator, PasswordHasher
{
    private int $sequence = 0;

    public function next(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', ++$this->sequence);
    }

    public function hash(string $plain): string
    {
        return 'hashed:'.strrev($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        return $hash === $this->hash($plain);
    }
}
