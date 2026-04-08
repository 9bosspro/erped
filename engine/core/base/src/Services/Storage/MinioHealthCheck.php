<?php

declare(strict_types=1);

namespace Core\Base\Services\Storage;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * MinioHealthCheck — ตรวจสุขภาพและวินิจฉัย MinIO/S3 storage
 *
 * คุณสมบัติ:
 *  - ตรวจ config ครบถ้วน (driver, key, secret, bucket, endpoint)
 *  - ทดสอบ connection ไปยัง MinIO server
 *  - ตรวจว่า bucket มีอยู่และเข้าถึงได้
 *  - ตรวจ read/write/delete permissions ด้วย test file
 *  - Quick ping พร้อม cache (1 นาที)
 *  - ดูสถิติ bucket (จำนวน objects, ขนาดรวม)
 *  - สร้าง bucket อัตโนมัติถ้ายังไม่มี
 *
 * การใช้งาน:
 *  - $health = new MinioHealthCheck('minio');
 *  - $health->check();   // ตรวจครบทุกด้าน
 *  - $health->ping();    // quick check พร้อม cache
 */
class MinioHealthCheck
{
    /** @var string ชื่อ disk ที่ต้องการตรวจสอบ */
    protected string $disk = 'minio';

    protected ?S3Client $s3Client = null;

    /** @var array<string, mixed> config ของ disk จาก filesystems.php */
    protected array $config;

    /**
     * สร้าง instance พร้อมเชื่อมต่อ S3 client
     *
     * @param  string|null  $disk  ชื่อ disk (default: 'minio')
     */
    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? 'minio';
        $this->config = config("filesystems.disks.{$this->disk}", []);
        $this->initializeS3Client();
    }

    /**
     * ตรวจสุขภาพครบทุกด้าน (config, connection, bucket, permissions)
     *
     * @return array{disk: string, timestamp: string, checks: array, latency_ms: float|null, healthy: bool}
     */
    public function check(): array
    {
        $startTime = microtime(true);

        $results = [
            'disk' => $this->disk,
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'config' => $this->checkConfig(),
                'connection' => $this->checkConnection(),
                'bucket' => $this->checkBucket(),
                'permissions' => $this->checkPermissions(),
            ],
            'latency_ms' => null,
            'healthy' => false,
        ];

        $results['latency_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        $results['healthy'] = $this->isHealthy($results['checks']);

        return $results;
    }

    /**
     * Quick ping — ทดสอบ connection อย่างรวดเร็ว (พร้อม cache 1 นาที)
     *
     * @param  bool  $useCache  true = ใช้ cached result ถ้ามี
     * @return array{healthy: bool, latency_ms: float|null, message: string, timestamp: string}
     */
    public function ping(bool $useCache = true): array
    {
        $cacheKey = "minio_health:{$this->disk}";

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startTime = microtime(true);
        $result = [
            'healthy' => false,
            'latency_ms' => null,
            'message' => '',
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            if (! $this->s3Client) {
                throw new Exception('S3 client not initialized');
            }

            $bucket = $this->config['bucket'] ?? '';
            $this->s3Client->headBucket(['Bucket' => $bucket]);

            $result['healthy'] = true;
            $result['message'] = 'Connection successful';
            $result['latency_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            Cache::put($cacheKey, $result, now()->addMinute());

        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['latency_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        }

        return $result;
    }

    /**
     * ตรวจ config ว่าครบถ้วนหรือไม่
     *
     * ตรวจ: driver, key, secret, bucket, endpoint
     * แจ้งเตือน: endpoint format, HTTPS ใน local, path_style_endpoint
     *
     * @return array{passed: bool, missing_keys: string[], warnings: string[], endpoint: string|null, bucket: string|null, region: string}
     */
    public function checkConfig(): array
    {
        $required = ['driver', 'key', 'secret', 'bucket', 'endpoint'];
        $missing = [];
        $warnings = [];

        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                $missing[] = $key;
            }
        }

        if (! empty($this->config['endpoint'])) {
            $endpoint = $this->config['endpoint'];

            if (! filter_var($endpoint, FILTER_VALIDATE_URL)) {
                $warnings[] = "Invalid endpoint URL format: {$endpoint}";
            }

            if (strpos($endpoint, 'https://') === 0 && app()->environment('local')) {
                $warnings[] = 'Using HTTPS endpoint in local environment - ensure SSL certificates are valid';
            }
        }

        if (($this->config['use_path_style_endpoint'] ?? false) !== true) {
            $warnings[] = "MinIO typically requires 'use_path_style_endpoint' => true";
        }

        return [
            'passed' => empty($missing),
            'missing_keys' => $missing,
            'warnings' => $warnings,
            'endpoint' => $this->config['endpoint'] ?? null,
            'bucket' => $this->config['bucket'] ?? null,
            'region' => $this->config['region'] ?? 'us-east-1',
        ];
    }

    /**
     * ตรวจ connection ไปยัง MinIO/S3 server
     *
     * ใช้ listBuckets() เพื่อทดสอบว่าเชื่อมต่อได้
     *
     * @return array{passed: bool, message: string, latency_ms: float|null}
     */
    public function checkConnection(): array
    {
        $result = [
            'passed' => false,
            'message' => '',
            'latency_ms' => null,
        ];

        if (! $this->s3Client) {
            $result['message'] = 'S3 client not initialized - check configuration';

            return $result;
        }

        $startTime = microtime(true);

        try {
            $this->s3Client->listBuckets();
            $result['passed'] = true;
            $result['message'] = 'Connected successfully';
        } catch (AwsException $e) {
            $result['message'] = 'AWS Error: '.$e->getAwsErrorMessage();
            $result['error_code'] = $e->getAwsErrorCode();
        } catch (Exception $e) {
            $result['message'] = 'Connection failed: '.$e->getMessage();
        }

        $result['latency_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $result;
    }

    /**
     * ตรวจว่า bucket มีอยู่และเข้าถึงได้หรือไม่
     *
     * @return array{passed: bool, bucket: string, exists: bool, message: string}
     */
    public function checkBucket(): array
    {
        $bucket = $this->config['bucket'] ?? '';
        $result = [
            'passed' => false,
            'bucket' => $bucket,
            'exists' => false,
            'message' => '',
        ];

        if (empty($bucket)) {
            $result['message'] = 'Bucket name not configured';

            return $result;
        }

        if (! $this->s3Client) {
            $result['message'] = 'S3 client not initialized';

            return $result;
        }

        try {
            $this->s3Client->headBucket(['Bucket' => $bucket]);
            $result['passed'] = true;
            $result['exists'] = true;
            $result['message'] = 'Bucket accessible';
        } catch (AwsException $e) {
            $errorCode = $e->getAwsErrorCode();

            if ($errorCode === 'NotFound' || $errorCode === 'NoSuchBucket') {
                $result['message'] = "Bucket '{$bucket}' does not exist";
            } elseif ($errorCode === 'Forbidden' || $errorCode === 'AccessDenied') {
                $result['exists'] = true;
                $result['message'] = 'Bucket exists but access denied - check credentials';
            } else {
                $result['message'] = 'Bucket check failed: '.$e->getAwsErrorMessage();
            }
        } catch (Exception $e) {
            $result['message'] = 'Error: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * ตรวจ read/write/delete permissions ด้วย test file
     *
     * สร้างไฟล์ทดสอบ → อ่าน → ลบ → รายงานผล
     *
     * @return array{passed: bool, can_read: bool, can_write: bool, can_delete: bool, message: string}
     */
    public function checkPermissions(): array
    {
        $result = [
            'passed' => false,
            'can_read' => false,
            'can_write' => false,
            'can_delete' => false,
            'message' => '',
        ];

        $testKey = '.health-check-'.uniqid();

        try {
            Storage::disk($this->disk)->put($testKey, 'health-check-test');
            $result['can_write'] = true;

            $content = Storage::disk($this->disk)->get($testKey);
            $result['can_read'] = ($content === 'health-check-test');

            Storage::disk($this->disk)->delete($testKey);
            $result['can_delete'] = ! Storage::disk($this->disk)->exists($testKey);

            $result['passed'] = $result['can_read'] && $result['can_write'] && $result['can_delete'];
            $result['message'] = $result['passed'] ? 'All permissions OK' : 'Some permissions failed';

        } catch (Exception $e) {
            $result['message'] = 'Permission test failed: '.$e->getMessage();

            try {
                Storage::disk($this->disk)->delete($testKey);
            } catch (Exception $cleanupEx) {
                // ไม่สนใจ error ตอน cleanup
            }
        }

        return $result;
    }

    /**
     * ดึงสถิติของ bucket (จำนวน objects, ขนาดรวม)
     *
     * @return array{bucket: string, total_objects: int, total_size_bytes: int, total_size_human: string}
     */
    public function getBucketStats(): array
    {
        $bucket = $this->config['bucket'] ?? '';
        $stats = [
            'bucket' => $bucket,
            'total_objects' => 0,
            'total_size_bytes' => 0,
            'total_size_human' => '0 B',
        ];

        if (! $this->s3Client || empty($bucket)) {
            return $stats;
        }

        try {
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $bucket,
            ]);

            $totalSize = 0;
            $count = 0;

            foreach ($objects['Contents'] ?? [] as $object) {
                $totalSize += $object['Size'] ?? 0;
                $count++;
            }

            $stats['total_objects'] = $count;
            $stats['total_size_bytes'] = $totalSize;
            $stats['total_size_human'] = $this->formatBytes($totalSize);

        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * ดึงรายการ buckets ทั้งหมดบน server
     *
     * @return array{buckets?: array<int, array{name: string, created: string|null}>, count?: int, error?: string}
     */
    public function listBuckets(): array
    {
        if (! $this->s3Client) {
            return ['error' => 'S3 client not initialized'];
        }

        try {
            $result = $this->s3Client->listBuckets();
            $buckets = [];

            foreach ($result['Buckets'] ?? [] as $bucket) {
                $buckets[] = [
                    'name' => $bucket['Name'],
                    'created' => $bucket['CreationDate']?->format('Y-m-d H:i:s'),
                ];
            }

            return [
                'buckets' => $buckets,
                'count' => count($buckets),
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * สร้าง bucket ถ้ายังไม่มี (idempotent)
     *
     * @param  string|null  $bucketName  ชื่อ bucket (null = ใช้จาก config)
     * @return array{success: bool, message: string, created?: bool}
     */
    public function ensureBucketExists(?string $bucketName = null): array
    {
        $bucket = $bucketName ?? ($this->config['bucket'] ?? '');

        if (empty($bucket)) {
            return ['success' => false, 'message' => 'Bucket name required'];
        }

        if (! $this->s3Client) {
            return ['success' => false, 'message' => 'S3 client not initialized'];
        }

        try {
            $this->s3Client->headBucket(['Bucket' => $bucket]);

            return ['success' => true, 'message' => 'Bucket already exists', 'created' => false];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NotFound' || $e->getAwsErrorCode() === 'NoSuchBucket') {
                try {
                    $this->s3Client->createBucket(['Bucket' => $bucket]);

                    return ['success' => true, 'message' => 'Bucket created', 'created' => true];
                } catch (Exception $createEx) {
                    return ['success' => false, 'message' => 'Failed to create bucket: '.$createEx->getMessage()];
                }
            }

            return ['success' => false, 'message' => $e->getAwsErrorMessage()];
        }
    }

    /**
     * สร้าง S3 client จาก config ของ disk
     */
    protected function initializeS3Client(): void
    {
        if (! empty($this->config) && ($this->config['driver'] ?? '') === 's3') {
            try {
                $httpOptions = $this->config['http'] ?? [];
                $verify = $httpOptions['verify'] ?? true;

                $this->s3Client = new S3Client([
                    'version' => 'latest',
                    'region' => $this->config['region'] ?? 'us-east-1',
                    'endpoint' => $this->config['endpoint'] ?? null,
                    'use_path_style_endpoint' => $this->config['use_path_style_endpoint'] ?? true,
                    'credentials' => [
                        'key' => $this->config['key'] ?? '',
                        'secret' => $this->config['secret'] ?? '',
                    ],
                    'http' => [
                        'verify' => $verify,
                    ],
                ]);
            } catch (Exception $e) {
                $this->s3Client = null;
            }
        }
    }

    /**
     * ตรวจว่า storage healthy หรือไม่จากผลตรวจทั้งหมด
     *
     * @param  array<string, array{passed: bool}>  $checks  ผลตรวจจาก check()
     */
    protected function isHealthy(array $checks): bool
    {
        return ($checks['config']['passed'] ?? false)
            && ($checks['connection']['passed'] ?? false)
            && ($checks['bucket']['passed'] ?? false)
            && ($checks['permissions']['passed'] ?? false);
    }

    /**
     * แปลงขนาด bytes เป็นรูปแบบที่อ่านง่าย (เช่น 1.5 GB)
     *
     * @param  int  $bytes  ขนาดเป็น bytes
     * @return string ขนาดที่อ่านง่าย เช่น '1.5 GB'
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
