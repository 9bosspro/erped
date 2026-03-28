<?php

namespace Core\Base\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait CacheTrait
{
    //

    protected function cacheQuery($key, $sql, $timeout = 60)
    {
        return Cache::remember($key, $timeout, function () use ($sql) {
            return DB::select(DB::raw($sql));
        });
    }

    //   $cache = $this->cacheQuery('usersTable', 'SOME COMPLEX JOINS ETC..', 30);
    protected function set(Route $route, Request $request, Response $response)
    {
        $key = $this->keygen($request->url());

        if (! Cache::has($key)) {
            Cache::put($key, $response->getContent(), 1);
        }
    }

    protected function grab(Route $route, Request $request)
    {
        $key = $this->keygen($request->url());

        if (Cache::has($key)) {
            return Cache::get($key);
        }
    }

    protected function keygen($url)
    {
        return 'route_'.Str::slug($url);
    }
}
