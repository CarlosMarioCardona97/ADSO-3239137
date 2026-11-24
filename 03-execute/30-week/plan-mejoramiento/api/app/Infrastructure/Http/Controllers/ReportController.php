<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Service\MostRequestedReportService;
use App\Infrastructure\Http\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /reports/most-requested — solo admin (HU-10). */
final class ReportController
{
    public function __construct(private readonly MostRequestedReportService $reports) {}

    public function mostRequested(Request $request): JsonResponse
    {
        $input = new Input($request);
        $from = $input->optionalQuery('from');
        $to = $input->optionalQuery('to');
        if ($from === null) {
            $input->addError('from', 'es obligatorio (AAAA-MM-DD)');
        }
        if ($to === null) {
            $input->addError('to', 'es obligatorio (AAAA-MM-DD)');
        }
        $input->validate();

        return new JsonResponse($this->reports->report((string) $from, (string) $to), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
