<?php

namespace App\Application\Service;

use App\Application\Port\CoverStorage;
use App\Application\Port\DossierRepository;
use App\Domain\Dossier;
use App\Domain\Exception\BusinessRuleViolation;

/** HU-03 · Carátula digitalizada (CA-03.4): JPG o PNG de máximo 2 MB, guardada con una clave opaca. */
final class DossierCoverService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct(
        private readonly DossierRepository $dossiers,
        private readonly CoverStorage $storage,
    ) {}

    /** `$detectedMime` lo detecta el adaptador HTTP por el CONTENIDO del archivo, no por la extensión. */
    public function upload(string $id, string $bytes, string $detectedMime): Dossier
    {
        $dossier = $this->dossiers->findActive($id) ?? throw BusinessRuleViolation::notFound('el expediente');
        if (! isset(self::EXTENSIONS[$detectedMime])) {
            throw BusinessRuleViolation::validation('file', 'solo se aceptan imágenes JPG o PNG');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw BusinessRuleViolation::validation('file', 'la carátula no puede pesar más de 2 MB');
        }

        $oldKey = $dossier->coverKey();
        $dossier->setCoverKey($this->storage->put($bytes, self::EXTENSIONS[$detectedMime]));
        $this->dossiers->saveCoverKey($dossier);
        if ($oldKey !== null) {
            $this->storage->delete($oldKey);
        }

        return $dossier;
    }

    /** @return array{bytes: string, mime: string} */
    public function get(string $id): array
    {
        $dossier = $this->dossiers->findActive($id) ?? throw BusinessRuleViolation::notFound('el expediente');
        $key = $dossier->coverKey();
        $bytes = $key !== null ? $this->storage->get($key) : null;
        if ($key === null || $bytes === null) {
            throw BusinessRuleViolation::notFound('la carátula');
        }

        return ['bytes' => $bytes, 'mime' => str_ends_with($key, '.png') ? 'image/png' : 'image/jpeg'];
    }

    /** Orden obligatorio (data-model §7): primero se anula cover_key y se guarda; después se borra el binario. */
    public function remove(string $id): void
    {
        $dossier = $this->dossiers->findActive($id) ?? throw BusinessRuleViolation::notFound('el expediente');
        $oldKey = $dossier->coverKey();
        if ($oldKey === null) {
            return;
        }
        $dossier->setCoverKey(null);
        $this->dossiers->saveCoverKey($dossier);
        $this->storage->delete($oldKey);
    }
}
