<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Http\Client\RequestException;

// ─────────────────────────────────────────────────────────────────────────────
//  Slave Client Helpers
//  ฟังก์ชันสะดวกสำหรับเข้าถึงค่าคอนฟิกของ Slave Client
// ─────────────────────────────────────────────────────────────────────────────

if (! function_exists('slave_is_installed')) {
    /**
     * ตรวจสอบว่า Slave ถูกติดตั้งและ activate แล้วหรือยัง
     */
    function slave_is_installed(): bool
    {
        return (bool) config('slave::client.installed', false);
    }
}

if (! function_exists('slave_master_url')) {
    /**
     * คืนค่า URL ของ Master Server
     */
    function slave_master_url(): string
    {
        return (string) config('slave::client.master_url', '');
    }
}

if (! function_exists('slave_client_id')) {
    /**
     * คืนค่า Client ID ที่ใช้ยืนยันตัวตนกับ Master
     */
    function slave_client_id(): string
    {
        return (string) config('slave::client.client_id', '');
    }
}

if (! function_exists('slave_version')) {
    /**
     * คืนค่า version ของ Slave package
     */
    function slave_version(): string
    {
        return defined('SLAVE_VERSION') ? SLAVE_VERSION : '0.0.0';
    }
}

if (! function_exists('csp_nonce')) {
    /**
     * คืนค่า CSP nonce สำหรับใช้ใน inline <script>/<style>
     *
     * SecurityHeaders middleware bind ค่า nonce ลง container เฉพาะ web request
     * - หาก binding ไม่มี (เช่น API request, console, test) → คืน '' เพื่อกัน BindingResolutionException
     * - ค่าที่ได้ปลอดภัยพร้อมใช้ใน HTML attribute (base64 ของ random_bytes)
     */
    function csp_nonce(): string
    {
        if (! app()->bound('csp-nonce')) {
            return '';
        }

        $nonce = app('csp-nonce');

        return \is_string($nonce) ? $nonce : '';
    }
}

if (! function_exists('slave_verify_webhook_signature')) {
    /**
     * ตรวจสอบ HMAC signature ของ webhook ที่มาจาก Master
     *
     * @param  string  $payload   raw request body
     * @param  string  $signature signature จาก X-Slave-Signature header
     */
    function slave_verify_webhook_signature(string $payload, string $signature): bool
    {
        $secret = (string) config('slave::client.client_secret', '');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $algo = defined('SLAVE_HMAC_ALGO') ? SLAVE_HMAC_ALGO : 'sha256';
        $expected = hash_hmac($algo, $payload, $secret);

        return hash_equals($expected, $signature);
    }
}


if (! function_exists('resetpassword')) {
    /**
     * ตรวจสอบ HMAC signature ของ webhook ที่มาจาก Master
     *
     * @param  string  $payload   raw request body
     * @param  string  $signature signature จาก X-Slave-Signature header
     */
    function callRestApiHybrid(array $payload, string $path, string $method): array
    {
        $masterClient = app('slave.master');
        $headers                = $masterClient->generateSignedHeaders($payload);
        $headers['X-For-slave'] = 'true';
        $encryptedPayload       = $masterClient->encryptedpayload($payload);

        try {
            $response = $masterClient->withHeaders($headers)
                ->sendRequest($method, $path, [
                    'encrypted_payload' => $encryptedPayload,
                ]);

            return $response->json() ?? ['raw_body' => $response->body()];
        } catch (RequestException $e) {
            Log::critical('Master Server API Unreachable', ['error' => $e->getMessage()]);
            return [
                'error'  => true,
                'status' => $e->response?->status(),
                'body'   => $e->response?->body(),
            ];
        }
    }
}
