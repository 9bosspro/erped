<?php

namespace Engine\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function index(): View
    {
        return view('auth::index');
    }

    public function create(): View
    {
        return view('auth::create'); // @phpstan-ignore argument.type
    }

    public function store(Request $_request): void {}

    public function show(string $id): View
    {
        return view('auth::show'); // @phpstan-ignore argument.type
    }

    public function edit(string $id): View
    {
        return view('auth::edit'); // @phpstan-ignore argument.type
    }

    public function update(Request $_request, string $_id): void {}

    public function destroy(string $_id): void {}
}
