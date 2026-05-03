<?php

declare(strict_types=1);

namespace Core\Base\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * EmailSpam — ตรวจสอบ email ผ่าน Mailboxlayer API
 *
 * ตรวจจับ disposable email และ format ที่ไม่ถูกต้อง
 *
 * กลยุทธ์ fail-open: ผ่านการตรวจสอบเสมอเมื่อ:
 *  - อยู่ใน environment 'local'
 *  - ไม่มี API key ถูก configure
 *  - External service ไม่ตอบสนองหรือ error
 * เพื่อไม่ให้ block production เมื่อ external service ล่ม
 */
class EmailSpam implements ValidationRule
{
    /**
     * ตรวจสอบความถูกต้องของ email
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ข้ามการตรวจสอบใน local environment
        if (app()->environment('local')) {
            return;
        }

        $apiKeyVal = config('services.mailboxlayer.key', '');
        $apiKey = is_scalar($apiKeyVal) ? (string) $apiKeyVal : '';

        // ข้ามถ้าไม่มี API key — fail open
        if ($apiKey === '') {
            return;
        }

        if (! is_scalar($value) || ! $this->check((string) $value)) {
            $fail('ที่อยู่อีเมลไม่ถูกต้องหรือเป็น disposable email');
        }
    }

    /**
     * เรียก Mailboxlayer API เพื่อตรวจสอบ email
     *
     * ใช้ Laravel Http facade แทน file_get_contents เพื่อ:
     *  - ควบคุม timeout ได้
     *  - mock ได้ใน test (Http::fake())
     *  - error handling ที่ดีกว่า
     *
     * @return bool true = email ถูกต้อง, false = ไม่ผ่าน
     */
    protected function check(string $email): bool
    {
        try {
            $apiKeyVal = config('services.mailboxlayer.key', '');
            $apiKey = is_scalar($apiKeyVal) ? (string) $apiKeyVal : '';

            $response = Http::timeout(5)->get('https://apilayer.net/api/check', [
                'access_key' => $apiKey,
                'email' => $email,
                'smtp' => 1,
            ]);

            // fail open เมื่อ service ไม่ตอบสนอง
            if (! $response->successful()) {
                return true;
            }

            /** @var array{format_valid?: bool, disposable?: bool} $data */
            $data = $response->json();

            return ($data['format_valid'] ?? false) && ! ($data['disposable'] ?? false);
        } catch (Throwable $e) {
            report($e);

            return true; // fail open
        }
    }
}
