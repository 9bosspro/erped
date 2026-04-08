<?php

declare(strict_types=1);

namespace Core\Base\Services\Integration;

use Core\Base\Support\DTO\BaseDTO;
use Core\Base\Support\Helpers\Crypto\HashHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * CrossHostClient — ส่งข้อมูลข้ามเซิร์ฟเวอร์แบบแนบการเข้ารหัสขั้นสูง (Enterprise Secure Node)
 *
 * ทำหน้าที่:
 * 1. แปลง DTO เป็น Array/JSON
 * 2. สร้าง HMAC Signature เพื่อป้องกัน Payload โดนแอบแก้ (Anti-Tampering)
 * 3. แนบ X-Timestamp + X-Nonce เพื่อป้องกัน Replay Attack
 * 4. [Option] เข้ารหัส Payload ทั้งก้อนด้วย Public Key ของปลายทาง (Sealed Box)
 * 5. ยิง HTTP Request (มี Retry & Timeout มาตรฐาน)
 *
 * Signature Formula:
 *   HMAC = hash(timestamp . nonce . body . sharedSecret)
 */
class CrossHostClient
{
    protected HashHelper $hashHelper;

    protected SodiumHelper $sodium;

    public function __construct(
        protected string $targetHostUrl,
        protected string $sharedSecret,
        protected ?string $targetPublicKeyBase64 = null,
        ?HashHelper $hashHelper = null,
        ?SodiumHelper $sodium = null,
    ) {
        if (empty($targetHostUrl) || empty($sharedSecret)) {
            throw new InvalidArgumentException('CrossHostClient requires target URL and shared secret.');
        }

        $this->hashHelper = $hashHelper ?? app(HashHelper::class);
        $this->sodium = $sodium ?? app(SodiumHelper::class);
    }

    // ─── Signed Payload ────────────────────────────────────────────────────

    /**
     * ส่งข้อมูลข้ามเซิร์ฟเวอร์ พร้อม Signature + Replay Attack Protection
     * (เหมาะสำหรับข้อมูลที่ต้องการความมั่นใจว่าไม่โดนแก้/replay กลางทาง)
     */
    public function sendSignedPayload(string $endpoint, BaseDTO|array $payload): Response
    {
        $data = $payload instanceof BaseDTO ? $payload->toArray() : $payload;
        $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = $this->generateNonce();

        // Signature ครอบทั้ง timestamp + nonce + body เพื่อผูก headers กับ payload ไว้ด้วยกัน
        $signature = $this->hashHelper->hash($timestamp.$nonce.$jsonPayload.$this->sharedSecret);

        return Http::timeout(10)
            ->retry(3, 100)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
            ])
            ->post(rtrim($this->targetHostUrl, '/').'/'.ltrim($endpoint, '/'), $data);
    }

    // ─── Sealed Payload ────────────────────────────────────────────────────

    /**
     * ส่งข้อมูลลับสุดยอดข้ามเซิร์ฟเวอร์ด้วยระบบ Sealed Box
     * - ข้อมูลถูก Seal ด้วย Public Key ของเซิร์ฟเวอร์ปลายทาง
     * - มีเฉพาะเซิร์ฟเวอร์ที่มี Private Key คู่กันเท่านั้นที่แกะได้
     * - Signature ครอบ sealed box + timestamp + nonce (ป้องกัน tampering + replay)
     */
    public function sendSealedPayload(string $endpoint, BaseDTO|array $payload): Response
    {
        if (empty($this->targetPublicKeyBase64)) {
            throw new InvalidArgumentException('Sealed Payload requires target Public Key.');
        }

        $data = $payload instanceof BaseDTO ? $payload->toArray() : $payload;
        $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = $this->generateNonce();

        // 1. Seal ด้วย Public Key (ผลลัพธ์เป็น base64)
        $sealedBody = $this->sodium->seal($jsonPayload, $this->targetPublicKeyBase64);

        // 2. Signature ครอบ sealed box + timestamp + nonce
        $signature = $this->hashHelper->hash($timestamp.$nonce.$sealedBody.$this->sharedSecret);

        return Http::timeout(15)
            ->retry(3, 200)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
                'X-Payload-Type' => 'sealed-box',
            ])
            ->post(rtrim($this->targetHostUrl, '/').'/'.ltrim($endpoint, '/'), [
                'box' => $sealedBody,
            ]);
    }

    // ─── Internal ──────────────────────────────────────────────────────────

    private function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
