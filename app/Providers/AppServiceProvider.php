<?php

namespace App\Providers;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;
use App\Services\BackendApi\BackendApiClient;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //  $this->app->register(BaseServiceProvider::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->singleton(BackendApiClient::class);
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

        $threshold = config('backend.slow_query_threshold_ms', 100);

        DB::listen(function (QueryExecuted $query) use ($threshold) {
            if ($query->time > $threshold) {
                Log::channel('daily')->warning('Slow query detected', [
                    'sql'       => $query->sql,
                    'bindings'  => $query->bindings,
                    'time_ms'   => $query->time,
                    'threshold' => $threshold,
                ]);
            }
        });
    }
}
