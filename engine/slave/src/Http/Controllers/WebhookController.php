<?php

declare(strict_types=1);

namespace Slave\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WebhookController — รับและประมวลผล event จาก Master Server
 *
 * ตรวจสอบ HMAC-SHA256 signature ก่อนประมวลผลทุกครั้ง
 * ลำดับการทำงาน:
 *  1. ตรวจสอบ signature (ป้องกัน spoofed requests)
 *  2. แยก event type จาก payload
 *  3. dispatch ไปยัง Laravel event system (slave.webhook.{event})
 */
class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event   = $request->string('event')->value();
        $raw     = $request->input('data', []);
        $payload = \is_array($raw) ? $raw : [];

        $this->dispatch($event, $payload);

        return response()->json(['received' => true, 'event' => $event]);
    }

    /**
     * ตรวจสอบ HMAC-SHA256 signature จาก Master
     *
     * Master ส่ง signature ใน header X-Slave-Signature
     * คำนวณจาก HMAC-SHA256(raw_body, client_secret)
     */
    private function verifySignature(Request $request): bool
    {
        $headerName = \defined('SLAVE_WEBHOOK_SIGNATURE_HEADER')
            ? SLAVE_WEBHOOK_SIGNATURE_HEADER
            : 'X-Slave-Signature';

        $headerVal = $request->header($headerName);
        $signature = \is_string($headerVal) ? $headerVal : '';

        return slave_verify_webhook_signature($request->getContent(), $signature);
    }

    /**
     * ส่ง event ไปยัง Laravel event system
     * Module อื่นๆ รับฟังผ่าน Event::listen('slave.webhook.*')
     *
     * @param array<string, mixed> $payload
     */
    private function dispatch(string $event, array $payload): void
    {
        if ($event === '') {
            return;
        }

        event("slave.webhook.{$event}", $payload);
    }
}
