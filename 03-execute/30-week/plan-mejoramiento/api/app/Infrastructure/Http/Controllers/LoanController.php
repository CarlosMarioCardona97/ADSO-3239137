<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Port\LoanFilter;
use App\Application\Service\LendDossiersService;
use App\Application\Service\LoanQueryService;
use App\Application\Service\OverdueItemsService;
use App\Application\Service\ReturnItemService;
use App\Domain\LoanStatus;
use App\Domain\ValueObject\DateRange;
use App\Infrastructure\Http\Input;
use App\Infrastructure\Http\Presenter;
use App\Infrastructure\Persistence\Timestamps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /loans — HU-06 a HU-09 (admin y archivista). */
final class LoanController
{
    public function __construct(
        private readonly LendDossiersService $lend,
        private readonly ReturnItemService $return,
        private readonly LoanQueryService $query,
        private readonly OverdueItemsService $overdue,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $input = new Input($request);
        $officialId = $input->uuid('official_id');
        $items = $request->input('items');
        $dossierIds = [];
        if (! is_array($items) || ! array_is_list($items)) {
            $input->addError('items', 'debe ser una lista de 1 a 3 expedientes');
        } else {
            foreach ($items as $index => $item) {
                $id = is_array($item) ? ($item['dossier_id'] ?? null) : null;
                if (! is_string($id) || $id === '') {
                    $input->addError("items[{$index}].dossier_id", 'es obligatorio');
                } else {
                    $dossierIds[] = $id;
                }
            }
        }
        $input->validate();

        $loan = $this->lend->lend($officialId, $dossierIds, (string) $request->attributes->get('user_id'));

        return new JsonResponse(Presenter::loan($loan, $this->query->now()), 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function index(Request $request): JsonResponse
    {
        $input = new Input($request);
        [$page, $size] = $input->pagination();
        $from = $input->optionalQuery('from');
        $to = $input->optionalQuery('to');
        $status = $input->optionalQuery('status');
        $statusEnum = $status !== null ? LoanStatus::tryFrom($status) : null;
        if ($status !== null && $statusEnum === null) {
            $input->addError('status', 'debe ser open, overdue o closed');
        }
        $input->validate();

        if ($from !== null && $to !== null) {
            DateRange::fromDates($from, $to);   // fin anterior al inicio → 422 INVALID_DATE_RANGE
        }
        $filter = new LoanFilter(
            $from !== null ? DateRange::fromDates($from, $from)->from : null,
            $to !== null ? DateRange::fromDates($to, $to)->toExclusive() : null,
            $statusEnum,
            $input->optionalQuery('official_id'),
            $page,
            $size,
        );
        $now = $this->query->now();
        $result = $this->query->search($filter);

        return new JsonResponse(
            Presenter::page($result, fn ($loan) => Presenter::loan($loan, $now)),
            200, [], JSON_UNESCAPED_UNICODE,
        );
    }

    public function show(string $id): JsonResponse
    {
        return new JsonResponse(Presenter::loan($this->query->get($id), $this->query->now()), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function returnItem(string $loanId, string $itemId): JsonResponse
    {
        return new JsonResponse(Presenter::item($this->return->returnItem($loanId, $itemId)), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function overdue(): JsonResponse
    {
        $items = array_map(fn (array $row) => [
            'loan_id' => $row['loan_id'],
            'item_id' => $row['item_id'],
            'dossier_title' => $row['dossier_title'],
            'filing_number' => $row['filing_number'],
            'official_name' => $row['official_name'],
            'due_at' => Timestamps::toApi($row['due_at']),
            'days_late' => $row['days_late'],
        ], $this->overdue->list());

        return new JsonResponse(['items' => $items], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
