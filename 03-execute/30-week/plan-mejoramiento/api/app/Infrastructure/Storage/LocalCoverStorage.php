<?php

namespace App\Infrastructure\Storage;

use App\Application\Port\CoverStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Carátulas en disco (plan D-08). La clave es opaca: un UUID con extensión, nunca la ruta real.
 * Cambiar a S3 sería otro adaptador y una línea en la composición (OCP).
 */
final class LocalCoverStorage implements CoverStorage
{
    public function __construct(private readonly Filesystem $disk) {}

    public function put(string $bytes, string $extension): string
    {
        $key = Str::uuid().'.'.$extension;
        $this->disk->put('covers/'.$key, $bytes);

        return $key;
    }

    public function get(string $key): ?string
    {
        $path = 'covers/'.basename($key);

        return $this->disk->exists($path) ? $this->disk->get($path) : null;
    }

    public function delete(string $key): void
    {
        $this->disk->delete('covers/'.basename($key));
    }
}
