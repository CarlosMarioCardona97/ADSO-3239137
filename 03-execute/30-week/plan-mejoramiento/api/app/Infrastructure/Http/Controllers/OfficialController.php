<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Service\CreateOfficialService;
use App\Application\Service\SearchOfficialsService;
use App\Infrastructure\Http\Input;
use App\Infrastructure\Http\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /officials — HU-05 (admin y archivista). */
final class OfficialController
{
    public function __construct(
        private readonly SearchOfficialsService $search,
        private readonly CreateOfficialService $create,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $input = new Input($request);
        [$page, $size] = $input->pagination();
        $input->validate();

        return new JsonResponse(
            Presenter::page($this->search->search($input->optionalQuery('q'), $page, $size), Presenter::official(...)),
            200, [], JSON_UNESCAPED_UNICODE,
        );
    }

    public function show(string $id): JsonResponse
    {
        $found = $this->search->get($id);

        return new JsonResponse(
            Presenter::official($found['official']) + ['active_loans' => $found['active_loans']],
            200, [], JSON_UNESCAPED_UNICODE,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $input = new Input($request);
        $name = $input->string('full_name', 200);
        $document = $input->string('document_number', 30);
        $input->validate();

        return new JsonResponse(Presenter::official($this->create->create($name, $document)), 201, [], JSON_UNESCAPED_UNICODE);
    }
}
