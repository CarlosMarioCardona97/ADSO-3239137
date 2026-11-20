<?php

namespace App\Application\Service;

use App\Application\Page;
use App\Application\Port\OfficialRepository;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\Official;

/** HU-05 · Listar, buscar y consultar funcionarios (CA-05.2). */
final class SearchOfficialsService
{
    public function __construct(private readonly OfficialRepository $officials) {}

    public function search(?string $text, int $page, int $size): Page
    {
        $text = $text !== null && trim($text) !== '' ? trim($text) : null;

        return $this->officials->search($text, $page, $size);
    }

    /** @return array{official: Official, active_loans: int} */
    public function get(string $id): array
    {
        $official = $this->officials->find($id) ?? throw BusinessRuleViolation::notFound('el funcionario');

        return ['official' => $official, 'active_loans' => $this->officials->openItemsCount($id)];
    }
}
