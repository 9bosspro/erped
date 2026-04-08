<?php

declare(strict_types=1);

namespace Core\Base\Services\Integration;

use Core\Base\Support\Helpers\Crypto\HashHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * CrossHostReceiver — ตัวรับข้อมูลข้ามเซิร์ฟเวอร์แบบปลอดภัย (Webhook/API Hook)
 *
 * ทำหน้าที่:
 * 1. ตรวจสอบ Signature (HMAC Validation) ป้องกันการสวมรอย
 * 2. ตรวจสอบ Timestamp + Nonce ป้องกัน Replay Attack
 * 3. Unseal ข้อมูล (กรณีแบบ Sealed Box) กู้กลับเป็น JSON
 * 4. แปลงคืนเข้าสู่ Array ทั่วไป ให้ Controller เรียกลง DTO ได้
 *
 * Signature Formula (ต้องตรงกับ CrossHostClient):
 *   HMAC = hash(timestamp . nonce . body . sharedSecret)
 */
class CrossHostReceiver
{
    protected HashHelper $hashHelper;

    protected SodiumHelper $sodium;

    public function __construct(
        protected string $sharedSecret,
        protected ?string $myPrivateKeyBase64 = null,
        protected int $timestampTtl = 300,
        ?HashHelper $hashHelper = null,
        ?SodiumHelper $sodium = null,
    ) {
        $this->hashHelper = $hashHelper ?? app(HashHelper::class);
        $this->sodium = $sodium ?? app(SodiumHelper::class);
    }

    // ─── Signed Payload ────────────────────────────────────────────────────

    /**
     * ดึง Payload จาก request ที่มีแค่ Signature (ไม่มีการ Seal)
     *
     * @return array<string, mixed>
     *
     * @throws AccessDeniedHttpException ถ้ายืนยัน Signature ไม่ผ่าน หรือตรวจพบ Replay
     */
    public function receiveSignedPayload(Request $request): array
    {
        [$timestamp, $nonce, $signatureHeader] = $this->extractReplayHeaders($request);

        $this->validateReplayProtection($request, $timestamp, $nonce);

        // ดึง raw JSON โดยตรง (ไม่ clone — getContent() ไม่แก้ไข Request object)
        $jsonPayload = $request->getContent();

        // ตรวจ Signature — สูตรต้องตรงกับ CrossHostClient::sendSignedPayload
        $expectedSignature = $this->hashHelper->hash($timestamp.$nonce.$jsonPayload.$this->sharedSecret);

        if (! hash_equals($expectedSignature, $signatureHeader)) {
            Log::warning('CrossHost signed payload: signature mismatch', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            throw new AccessDeniedHttpException('Access Denied.');
        }

        // บันทึก nonce หลังผ่านการตรวจแล้ว เพื่อป้องกัน replay ในอนาคต
        $this->consumeNonce($nonce);

        return json_decode($jsonPayload, true) ?? [];
    }

    // ─── Sealed Payload ────────────────────────────────────────────────────

    /**
     * กู้คืน Payload ลับสุดยอด (Sealed Box) กลับมาเป็น Array ข้อความ
     *
     * @return array<string, mixed>
     *
     * @throws AccessDeniedHttpException ถ้ายืนยัน Signature ไม่ผ่าน หรือตรวจพบ Replay
     * @throws BadRequestHttpException ถ้าโครงสร้างกล่องผิดพลาด
     * @throws RuntimeException ถ้า server config ผิด (ไม่มี private key)
     */
    public function receiveSealedPayload(Request $request): array
    {
        if (empty($this->myPrivateKeyBase64)) {
            throw new RuntimeException('Server configuration error: missing private key.');
        }

        [$timestamp, $nonce, $signatureHeader] = $this->extractReplayHeaders($request);

        $this->validateReplayProtection($request, $timestamp, $nonce);

        $sealedBox = $request->input('box');

        if (empty($sealedBox) || ! is_string($sealedBox)) {
            throw new BadRequestHttpException('Missing or invalid sealed box in payload.');
        }

        // ตรวจ Signature ของกล่อง — สูตรต้องตรงกับ CrossHostClient::sendSealedPayload
        $expectedSignature = $this->hashHelper->hash($timestamp.$nonce.$sealedBox.$this->sharedSecret);

        if (! hash_equals($expectedSignature, $signatureHeader)) {
            Log::warning('CrossHost sealed payload: signature mismatch', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            throw new AccessDeniedHttpException('Access Denied.');
        }

        // แกะกล่อง (Libsodium Unseal) — error ภายในถูก log ไม่รั่วออกไปผู้ใช้
        try {
            $unsealedJson = $this->sodium->sealOpenWithKeyPair($sealedBox, $this->myPrivateKeyBase64);
        } catch (Exception $e) {
            Log::error('CrossHost sealed payload: unseal failed', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'error' => $e->getMessage(),
            ]);
            throw new AccessDeniedHttpException('Access Denied.');
        }

        // บันทึก nonce หลังผ่านการตรวจแล้ว
        $this->consumeNonce($nonce);

        return json_decode($unsealedJson, true) ?? [];
    }

    // ─── Internal ──────────────────────────────────────────────────────────

    /**
     * ดึง X-Timestamp, X-Nonce, X-Signature จาก header พร้อมตรวจว่ามีครบ
     *
     * @return array{string, string, string} [timestamp, nonce, signature]
     */
    private function extractReplayHeaders(Request $request): array
    {
        $timestamp = $request->header('X-Timestamp', '');
        $nonce = $request->header('X-Nonce', '');
        $signature = $request->header('X-Signature', '');

        if (empty($timestamp) || empty($nonce) || empty($signature)) {
            throw new AccessDeniedHttpException('Access Denied.');
        }

        return [(string) $timestamp, (string) $nonce, (string) $signature];
    }

    /**
     * ตรวจสอบ Timestamp (อายุ request) และ Nonce (ไม่ซ้ำ)
     *
     * @throws AccessDeniedHttpException ถ้า request เก่าเกินไป หรือ nonce ถูกใช้ไปแล้ว
     */
    private function validateReplayProtection(Request $request, string $timestamp, string $nonce): void
    {
        $ts = (int) $timestamp;

        // Timestamp ต้องเป็นตัวเลขที่สมเหตุสมผล
        if ($ts <= 0 || abs(time() - $ts) > $this->timestampTtl) {
            Log::warning('CrossHost: request expired or clock skew too large', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'timestamp' => $timestamp,
                'server_ts' => time(),
            ]);
            throw new AccessDeniedHttpException('Access Denied.');
        }

        // Nonce ต้องยังไม่เคยถูกใช้ (ป้องกัน replay ภายใน TTL window)
        $cacheKey = 'crosshost:nonce:'.hash('sha256', $nonce);
        if (Cache::has($cacheKey)) {
            Log::warning('CrossHost: nonce replay detected', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            throw new AccessDeniedHttpException('Access Denied.');
        }
    }

    /**
     * บันทึก nonce ลง cache เพื่อป้องกัน replay ซ้ำ
     * TTL ตั้งให้เท่ากับ timestamp_ttl เพราะ request ที่เกิน TTL จะถูกปฏิเสธอยู่แล้ว
     */
    private function consumeNonce(string $nonce): void
    {
        $cacheKey = 'crosshost:nonce:'.hash('sha256', $nonce);
        Cache::put($cacheKey, true, $this->timestampTtl);
    }
}
