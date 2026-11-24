<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Service\ListCategoriesService;
use Illuminate\Http\JsonResponse;

/** GET /categories — autenticado. */
final class CategoryController
{
    public function __construct(private readonly ListCategoriesService $categories) {}

    public function index(): JsonResponse
    {
        return new JsonResponse($this->categories->list(), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
