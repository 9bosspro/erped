<?php

namespace Core\Base\Console\Commands;

use Illuminate\Console\Command;
use ReflectionClass;

class ListProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string providers:list  app:list-providers
     */
    protected $signature = 'providers:lists';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $app = $this->laravel; // หรือ app()
        $reflection = new ReflectionClass($app);
        $providersProperty = $reflection->getProperty('serviceProviders');
        $providersProperty->setAccessible(true);
        $providers = $providersProperty->getValue($app);

        if (empty($providers)) {
            $this->info('No providers registered.');

            return;
        }

        $this->info('Registered Service Providers:');
        foreach ($providers as $index => $provider) {
            $this->info('==> '.$index);
            //  $this->line(sprintf('%d. %s', $index + 1, get_class($provider)));

            // $this->line(sprintf('%d. %s', $index + 1, get_class($provider)));
        }
    }
}
