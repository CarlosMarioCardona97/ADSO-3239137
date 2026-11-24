<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Service\CreateArchivistService;
use App\Infrastructure\Http\Input;
use App\Infrastructure\Http\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /users — admin (HU-02). */
final class UserController
{
    public function __construct(private readonly CreateArchivistService $create) {}

    public function store(Request $request): JsonResponse
    {
        $input = new Input($request);
        $username = $input->string('username', 120);
        $password = $input->string('password', 200);
        $role = $input->string('role', 40);
        $input->validate();

        return new JsonResponse(Presenter::user($this->create->create($username, $password, $role)), 201);
    }
}
