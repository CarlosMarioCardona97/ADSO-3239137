<?php

/*
| Implementaciones EN MEMORIA de los puertos de persistencia, para probar los casos de uso sin base de
| datos (plan §3, artículo II). Todas comparten un InMemoryStore. Guardan copias clonadas para comportarse
| como un almacén real: lo que un caso de uso cambia en un objeto no se ve aquí hasta que lo guarda.
| Se cargan desde tests/Pest.php.
*/

namespace Tests\Fakes;

use App\Application\Page;
use App\Application\Port\CategoryReader;
use App\Application\Port\CoverStorage;
use App\Application\Port\DossierRepository;
use App\Application\Port\LoanFilter;
use App\Application\Port\LoanRepository;
use App\Application\Port\OfficialRepository;
use App\Application\Port\ReportReader;
use App\Application\Port\UserRepository;
use App\Domain\Dossier;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\Official;
use App\Domain\User;
use App\Domain\ValueObject\DateRange;
use DateTimeImmutable;

final class InMemoryStore
{
    /** @var array<string, string> */
    public array $categories = [
        '11111111-1111-4111-8111-111111111111' => 'Contratos',
        '22222222-2222-4222-8222-222222222222' => 'Personal',
        '33333333-3333-4333-8333-333333333333' => 'Predial',
        '44444444-4444-4444-8444-444444444444' => 'Actas',
        '55555555-5555-4555-8555-555555555555' => 'Jurídico',
    ];

    /** @var array<string, Dossier> */
    public array $dossiers = [];

    /** @var array<string, Official> */
    public array $officials = [];

    /** @var array<string, Loan> */
    public array $loans = [];

    /** @var array<string, User> */
    public array $users = [];

    /** @var array<string, string> */
    public array $covers = [];

    /** @return list<LoanItem> */
    public function itemsOf(string $officialId): array
    {
        $items = [];
        foreach ($this->loans as $loan) {
            if ($loan->officialId === $officialId) {
                array_push($items, ...$loan->items());
            }
        }

        return $items;
    }
}

final class InMemoryCategories implements CategoryReader
{
    public function __construct(private InMemoryStore $store) {}

    public function all(): array
    {
        $rows = [];
        foreach ($this->store->categories as $id => $name) {
            $rows[] = ['id' => $id, 'name' => $name];
        }
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }

    public function nameOf(string $id): ?string
    {
        return $this->store->categories[$id] ?? null;
    }
}

final class InMemoryCovers implements CoverStorage
{
    public function __construct(private InMemoryStore $store) {}

    public function put(string $bytes, string $extension): string
    {
        $key = 'cover-'.(count($this->store->covers) + 1).'.'.$extension;
        $this->store->covers[$key] = $bytes;

        return $key;
    }

    public function get(string $key): ?string
    {
        return $this->store->covers[$key] ?? null;
    }

    public function delete(string $key): void
    {
        unset($this->store->covers[$key]);
    }
}

final class InMemoryDossiers implements DossierRepository
{
    public function __construct(private InMemoryStore $store) {}

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $rows = array_values(array_filter($this->store->dossiers, function (Dossier $d) use ($text, $categoryId) {
            $haystack = $d->title().' '.$d->producingOffice().' '.$d->filingNumber->value;

            return $d->isActive()
                && ($text === null || stripos($haystack, $text) !== false)
                && ($categoryId === null || $d->categoryId() === $categoryId);
        }));
        usort($rows, fn (Dossier $a, Dossier $b) => strcmp($a->title(), $b->title()));

        return new Page(array_map(fn ($d) => clone $d, array_slice($rows, ($page - 1) * $size, $size)), $page, $size, count($rows));
    }

    public function findActive(string $id): ?Dossier
    {
        $dossier = $this->find($id);

        return $dossier?->isActive() ? $dossier : null;
    }

    public function find(string $id): ?Dossier
    {
        return isset($this->store->dossiers[$id]) ? clone $this->store->dossiers[$id] : null;
    }

    public function findActiveMany(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if ($dossier = $this->findActive($id)) {
                $found[$id] = $dossier;
            }
        }

        return $found;
    }

    public function filingNumberInUse(string $filingNumber): bool
    {
        foreach ($this->store->dossiers as $d) {
            if ($d->isActive() && $d->filingNumber->value === $filingNumber) {
                return true;
            }
        }

        return false;
    }

    public function hasOpenLoans(string $id): bool
    {
        foreach ($this->store->loans as $loan) {
            foreach ($loan->items() as $item) {
                if ($item->dossierId === $id && ! $item->isReturned()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function add(Dossier $dossier): void
    {
        $this->store->dossiers[$dossier->id] = clone $dossier;
    }

    public function saveDetails(Dossier $dossier): void
    {
        $this->store->dossiers[$dossier->id] = clone $dossier;
    }

    public function saveCoverKey(Dossier $dossier): void
    {
        $this->store->dossiers[$dossier->id] = clone $dossier;
    }

    public function saveRetirement(Dossier $dossier): void
    {
        $this->store->dossiers[$dossier->id] = clone $dossier;
    }
}

final class InMemoryOfficials implements OfficialRepository
{
    public function __construct(private InMemoryStore $store) {}

    public function search(?string $text, int $page, int $size): Page
    {
        $rows = array_values(array_filter(
            $this->store->officials,
            fn (Official $o) => $text === null || stripos($o->fullName.' '.$o->documentNumber, $text) !== false,
        ));

        return new Page(array_slice($rows, ($page - 1) * $size, $size), $page, $size, count($rows));
    }

    public function find(string $id): ?Official
    {
        return $this->store->officials[$id] ?? null;
    }

    public function documentInUse(string $documentNumber): bool
    {
        foreach ($this->store->officials as $o) {
            if ($o->documentNumber === $documentNumber) {
                return true;
            }
        }

        return false;
    }

    public function add(Official $official): void
    {
        $this->store->officials[$official->id] = $official;
    }

    public function openItemsCount(string $officialId): int
    {
        return count(array_filter($this->store->itemsOf($officialId), fn (LoanItem $i) => ! $i->isReturned()));
    }

    public function hasOverdue(string $officialId, DateTimeImmutable $now): bool
    {
        return array_filter($this->store->itemsOf($officialId), fn (LoanItem $i) => $i->isOverdue($now)) !== [];
    }
}

final class InMemoryLoans implements LoanRepository, ReportReader
{
    public function __construct(private InMemoryStore $store) {}

    public function addWithDossiers(Loan $loan, array $dossiers): void
    {
        foreach ($dossiers as $dossier) {
            $this->store->dossiers[$dossier->id] = clone $dossier;
        }
        $this->store->loans[$loan->id] = $loan;
    }

    public function find(string $id): ?Loan
    {
        return $this->store->loans[$id] ?? null;
    }

    public function search(LoanFilter $filter, DateTimeImmutable $now): Page
    {
        $rows = array_values(array_filter($this->store->loans, fn (Loan $l) => ($filter->officialId === null || $l->officialId === $filter->officialId)
            && ($filter->status === null || $l->status($now) === $filter->status)));

        return new Page(array_slice($rows, ($filter->page - 1) * $filter->size, $filter->size), $filter->page, $filter->size, count($rows));
    }

    public function recordReturn(LoanItem $item, Dossier $dossier): void
    {
        $this->store->dossiers[$dossier->id] = clone $dossier;
    }

    public function overdueItems(DateTimeImmutable $now): array
    {
        $rows = [];
        foreach ($this->store->loans as $loan) {
            foreach ($loan->items() as $item) {
                if ($item->isOverdue($now)) {
                    $rows[] = [
                        'loan_id' => $loan->id,
                        'item_id' => $item->id,
                        'dossier_title' => $item->dossierTitle,
                        'filing_number' => $item->filingNumber,
                        'official_name' => $this->store->officials[$loan->officialId]->fullName,
                        'due_at' => $item->dueAt,
                    ];
                }
            }
        }

        return $rows;
    }

    public function mostRequested(DateRange $range): array
    {
        $rows = [];
        foreach ($this->store->loans as $loan) {
            if ($loan->loanedAt < $range->from || $loan->loanedAt >= $range->toExclusive()) {
                continue;
            }
            foreach ($loan->items() as $item) {
                $key = $item->dossierId.'|'.$item->dossierTitle.'|'.$item->categoryName;
                $rows[$key] ??= [
                    'dossier_id' => $item->dossierId,
                    'dossier_title' => $item->dossierTitle,
                    'filing_number' => $item->filingNumber,
                    'category_name' => $item->categoryName,
                    'times_requested' => 0,
                ];
                $rows[$key]['times_requested']++;
            }
        }
        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => [$b['times_requested'], $a['dossier_title']] <=> [$a['times_requested'], $b['dossier_title']]);

        return $rows;
    }
}

final class InMemoryUsers implements UserRepository
{
    public function __construct(private InMemoryStore $store) {}

    public function findByUsername(string $username): ?User
    {
        foreach ($this->store->users as $user) {
            if ($user->username === $username) {
                return $user;
            }
        }

        return null;
    }

    public function usernameExists(string $username): bool
    {
        return $this->findByUsername($username) !== null;
    }

    public function add(User $user): void
    {
        $this->store->users[$user->id] = $user;
    }
}
