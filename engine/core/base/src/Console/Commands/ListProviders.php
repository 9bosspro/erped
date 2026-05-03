<?php

declare(strict_types=1);

namespace Core\Base\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

class ListProviders extends Command
{
    protected $signature = 'providers:list';

    protected $description = 'List all registered service providers';

    public function handle(): int
    {
        $app = $this->laravel;
        $reflection = new ReflectionClass($app);
        $property = $reflection->getProperty('serviceProviders');
        $property->setAccessible(true);

        /** @var ServiceProvider[] $providers */
        $providers = $property->getValue($app);

        if (empty($providers)) {
            $this->info('No providers registered.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($providers as $index => $provider) {
            $rows[] = [(int) $index + 1, $provider::class];
        }

        $this->table(['#', 'Provider'], $rows);
        $this->line(sprintf('Total: %d providers', count($providers)));

        return self::SUCCESS;
    }
}
