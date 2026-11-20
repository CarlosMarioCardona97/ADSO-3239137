<?php

namespace App\Application\Port;

/** Genera identificadores UUID v4 como texto. */
interface IdGenerator
{
    public function next(): string;
}
