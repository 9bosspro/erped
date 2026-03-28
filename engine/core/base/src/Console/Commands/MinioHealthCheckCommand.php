<?php

namespace Core\Base\Console\Commands;

use Core\Base\Services\Storage\MinioHealthCheck;
use Illuminate\Console\Command;

class MinioHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'minio:health
                            {--disk=minio : Storage disk to check}
                            {--full : Run full diagnostic check}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Check MinIO/S3 storage connection health';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = $this->option('disk');
        $full = $this->option('full');
        $json = $this->option('json');

        $healthCheck = new MinioHealthCheck($disk);

        $this->info("🔍 Checking MinIO storage: {$disk}");
        $this->newLine();

        if ($full) {
            return $this->runFullCheck($healthCheck, $json);
        }

        return $this->runQuickCheck($healthCheck, $json);
    }

    /**
     * Run quick ping check
     */
    protected function runQuickCheck(MinioHealthCheck $healthCheck, bool $json): int
    {
        $result = $healthCheck->ping(useCache: false);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $result['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        if ($result['healthy']) {
            $this->info('✅ MinIO is healthy');
            $this->line("   Latency: {$result['latency_ms']}ms");
        } else {
            $this->error('❌ MinIO is unhealthy');
            $this->line("   Error: {$result['message']}");
        }

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Run full diagnostic check
     */
    protected function runFullCheck(MinioHealthCheck $healthCheck, bool $json): int
    {
        $result = $healthCheck->check();

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $result['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        // Configuration Check
        $this->line('<fg=yellow>📋 Configuration</>');
        $config = $result['checks']['config'];
        $this->displayCheck('Config Valid', $config['passed']);

        if (! empty($config['missing_keys'])) {
            $this->warn('   Missing: '.implode(', ', $config['missing_keys']));
        }

        if (! empty($config['warnings'])) {
            foreach ($config['warnings'] as $warning) {
                $this->warn("   ⚠ {$warning}");
            }
        }

        $this->line('   Endpoint: '.($config['endpoint'] ?? 'N/A'));
        $this->line('   Bucket: '.($config['bucket'] ?? 'N/A'));
        $this->newLine();

        // Connection Check
        $this->line('<fg=yellow>🔌 Connection</>');
        $conn = $result['checks']['connection'];
        $this->displayCheck('Connected', $conn['passed']);
        $this->line("   Latency: {$conn['latency_ms']}ms");

        if (! $conn['passed']) {
            $this->error("   Error: {$conn['message']}");
        }
        $this->newLine();

        // Bucket Check
        $this->line('<fg=yellow>🪣 Bucket</>');
        $bucket = $result['checks']['bucket'];
        $this->displayCheck("Bucket '{$bucket['bucket']}'", $bucket['passed']);

        if (! $bucket['passed']) {
            $this->warn("   {$bucket['message']}");
        }
        $this->newLine();

        // Permissions Check
        $this->line('<fg=yellow>🔐 Permissions</>');
        $perms = $result['checks']['permissions'];
        $this->displayCheck('Read', $perms['can_read']);
        $this->displayCheck('Write', $perms['can_write']);
        $this->displayCheck('Delete', $perms['can_delete']);
        $this->newLine();

        // Summary
        $this->line('<fg=yellow>📊 Summary</>');
        $this->line("   Total Latency: {$result['latency_ms']}ms");

        if ($result['healthy']) {
            $this->info('   ✅ Overall Status: HEALTHY');
        } else {
            $this->error('   ❌ Overall Status: UNHEALTHY');
        }

        // Optional: Show bucket stats
        if ($result['healthy']) {
            $this->newLine();
            $this->line('<fg=yellow>📈 Bucket Statistics</>');
            $stats = $healthCheck->getBucketStats();
            $this->line("   Objects: {$stats['total_objects']}");
            $this->line("   Size: {$stats['total_size_human']}");
        }

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Display a check result
     */
    protected function displayCheck(string $label, bool $passed): void
    {
        $icon = $passed ? '✅' : '❌';
        $status = $passed ? '<fg=green>OK</>' : '<fg=red>FAIL</>';
        $this->line("   {$icon} {$label}: {$status}");
    }
}
