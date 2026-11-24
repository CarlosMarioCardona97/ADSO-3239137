<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Service\LoginService;
use App\Infrastructure\Http\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /auth/login — público (HU-01). */
final class AuthController
{
    public function __construct(private readonly LoginService $login) {}

    public function login(Request $request): JsonResponse
    {
        $input = new Input($request);
        $username = $input->string('username', 120);
        $password = $input->string('password', 200);
        $input->validate();

        return new JsonResponse($this->login->login($username, $password));
    }
}
