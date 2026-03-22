<?php

namespace App\Providers;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot(): void
    {
        if (config('myapp.force_ssl')) {
            URL::forceScheme('https');
        }

        $this->configureSlowQueryLogging();
    }

    private function configureSlowQueryLogging(): void
    {
        if (! $this->app->environment(['local', 'staging'])) {
            return;
        }

        DB::listen(function (QueryExecuted $query) {
            if ($query->time > 100) { // > 100ms
                Log::channel('daily')->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                ]);
            }
        });
    }
}
