<?php

namespace Engine\Modules\Demo\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function index(): View
    {
        return view('demo::index');
    }

    public function create(): View
    {
        return view('demo::create'); // @phpstan-ignore argument.type
    }

    public function store(Request $_request): void {}

    public function show(string $id): View
    {
        return view('demo::show'); // @phpstan-ignore argument.type
    }

    public function edit(string $id): View
    {
        return view('demo::edit'); // @phpstan-ignore argument.type
    }

    public function update(Request $_request, string $_id): void {}

    public function destroy(string $_id): void {}
}
