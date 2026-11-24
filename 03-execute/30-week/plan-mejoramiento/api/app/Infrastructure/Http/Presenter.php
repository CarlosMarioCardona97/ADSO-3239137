<?php

namespace App\Infrastructure\Http;

use App\Application\Page;
use App\Domain\Dossier;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\Official;
use App\Domain\User;
use App\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;

/** Convierte objetos del dominio en el JSON del contrato (snake_case, fechas UTC con Z). */
final class Presenter
{
    /** @return array<string, mixed> */
    public static function dossier(Dossier $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title(),
            'producing_office' => $d->producingOffice(),
            'filing_number' => $d->filingNumber->value,
            'category' => ['id' => $d->categoryId(), 'name' => $d->categoryName()],
            'total_units' => $d->totalUnits(),
            'available_units' => $d->availableUnits(),
            'cover_key' => $d->coverKey(),
        ];
    }

    /** @return array<string, mixed> */
    public static function official(Official $o): array
    {
        return ['id' => $o->id, 'full_name' => $o->fullName, 'document_number' => $o->documentNumber];
    }

    /** Sin password ni password_hash (CA-02.3, RNF-3). */
    public static function user(User $u): array
    {
        return ['id' => $u->id, 'username' => $u->username, 'role' => $u->role->value];
    }

    /** @return array<string, mixed> */
    public static function loan(Loan $loan, DateTimeImmutable $now): array
    {
        return [
            'id' => $loan->id,
            'official_id' => $loan->officialId,
            'loaned_at' => Timestamps::toApi($loan->loanedAt),
            'status' => $loan->status($now)->value,
            'items' => array_map(fn (LoanItem $i) => self::item($i), $loan->items()),
        ];
    }

    /** @return array<string, mixed> */
    public static function item(LoanItem $i): array
    {
        return [
            'id' => $i->id,
            'dossier_id' => $i->dossierId,
            'dossier_title' => $i->dossierTitle,
            'filing_number' => $i->filingNumber,
            'category_name' => $i->categoryName,
            'due_at' => Timestamps::toApi($i->dueAt),
            'returned_at' => Timestamps::toApi($i->returnedAt()),
        ];
    }

    /** @return array{items: list<mixed>, page: int, size: int, total: int} */
    public static function page(Page $page, callable $map): array
    {
        return [
            'items' => array_map($map, $page->items),
            'page' => $page->page,
            'size' => $page->size,
            'total' => $page->total,
        ];
    }
}
