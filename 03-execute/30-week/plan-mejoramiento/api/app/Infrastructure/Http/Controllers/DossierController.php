<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Service\CreateDossierService;
use App\Application\Service\RetireDossierService;
use App\Application\Service\SearchDossiersService;
use App\Application\Service\UpdateDossierService;
use App\Infrastructure\Http\Input;
use App\Infrastructure\Http\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** /dossiers — HU-03 y HU-04. */
final class DossierController
{
    public function __construct(
        private readonly SearchDossiersService $search,
        private readonly CreateDossierService $create,
        private readonly UpdateDossierService $update,
        private readonly RetireDossierService $retire,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $input = new Input($request);
        [$page, $size] = $input->pagination();
        $input->validate();
        $result = $this->search->search($input->optionalQuery('q'), $input->optionalQuery('category_id'), $page, $size);

        return new JsonResponse(Presenter::page($result, Presenter::dossier(...)), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(string $id): JsonResponse
    {
        return new JsonResponse(Presenter::dossier($this->search->get($id)), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request): JsonResponse
    {
        $input = new Input($request);
        $title = $input->string('title', 250);
        $office = $input->string('producing_office', 200);
        $filing = $input->string('filing_number', 11);
        $category = $input->uuid('category_id');
        $total = $input->integer('total_units');
        $input->validate();

        $dossier = $this->create->create($title, $office, $filing, $category, $total);

        return new JsonResponse(Presenter::dossier($dossier), 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $input = new Input($request);
        $title = $input->string('title', 250);
        $office = $input->string('producing_office', 200);
        $category = $input->uuid('category_id');
        $total = $input->integer('total_units');
        $input->validate();

        $dossier = $this->update->update($id, $title, $office, $category, $total);

        return new JsonResponse(Presenter::dossier($dossier), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(string $id): Response
    {
        $this->retire->retire($id);

        return new Response(null, 204);
    }
}
