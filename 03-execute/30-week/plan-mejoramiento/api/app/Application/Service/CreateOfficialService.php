<?php

namespace App\Application\Service;

use App\Application\Port\IdGenerator;
use App\Application\Port\OfficialRepository;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Official;

/** HU-05 · Crear funcionario (CA-05.1): el documento es único. */
final class CreateOfficialService
{
    public function __construct(
        private readonly OfficialRepository $officials,
        private readonly IdGenerator $ids,
    ) {}

    public function create(string $fullName, string $documentNumber): Official
    {
        $official = Official::register($this->ids->next(), $fullName, $documentNumber);
        if ($this->officials->documentInUse($official->documentNumber)) {
            throw new BusinessRuleViolation(
                'DOCUMENT_TAKEN',
                "Ya existe un funcionario con el documento {$official->documentNumber}.",
            );
        }
        $this->officials->add($official);

        return $official;
    }
}
