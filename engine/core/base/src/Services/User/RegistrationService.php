<?php

declare(strict_types=1);

namespace Core\Base\Services\User;

use App\Models\AnonymousPeople;
use Core\Base\Contracts\User\RegistrationServiceInterface;
use Core\Base\DTO\ServiceResult;
use Core\Base\DTO\User\RegistrationDTO;
use Core\Base\Repositories\Auth\RegisterTokenInterface;
use Core\Base\Repositories\User\UserInterface;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RegistrationService — จัดการกระบวนการลงทะเบียนผู้ใช้ใหม่แบบครบวงจร
 *
 * Flow การทำงาน:
 * 1. Client ขอ RegisterToken → ส่งเมล → user คลิก link (ได้ JWT)
 * 2. `checkToken()`         → decode JWT → ตรวจสอบ RegisterToken ใน DB
 * 3. `executeRegistration()` → สร้าง People + User + revoke token (ใน transaction)
 *
 * Dependency ทั้งหมด inject ผ่าน constructor (DI Container ของ Laravel จัดการ)
 * ไม่มี setter/getter — ใช้ `readonly` property ป้องกัน state mutation
 *
 * @see RegisterTokenInterface  สำหรับ token validation + lifecycle
 * @see UserInterface           สำหรับ user/people data access
 */
class RegistrationService implements RegistrationServiceInterface
{
    /**
     * @param  UserInterface  $userRepository  Repository สำหรับ User และ People
     * @param  RegisterTokenInterface  $registerTokenRepository  Repository สำหรับ RegisterToken
     * @param  JwtHelper  $jwtService  Service สำหรับจัดการ JWT
     */
    public function __construct(
        private readonly UserInterface $userRepository,
        private readonly RegisterTokenInterface $registerTokenRepository,
        private readonly JwtHelper $jwtService,
    ) {}

    // =========================================================================
    // Token Validation
    // =========================================================================

    /**
     * ตรวจสอบและ decode JWT token ของการลงทะเบียน
     *
     * ลำดับการตรวจสอบ:
     * 1. Decode JWT → ได้ payload ที่มี `data` (= RegisterToken ID)
     * 2. ค้นหา RegisterToken จาก ID → ตรวจสอบ revoked, expires_at, email
     * 3. คืนค่า payload พร้อม email สำหรับขั้นตอนถัดไป
     *
     * @param  string|null  $token  JWT Bearer token (null → คืน 401 ทันที)
     */
    public function checkToken(?string $token): ServiceResult
    {
        if (empty($token)) {
            return $this->fail('คุณไม่มีสิทธิ์ดำเนินการลงทะเบียน ต้องไปขอโทเคนก่อนค่ะ', 401);
        }

        $parsed = $this->jwtService->parseSafe($token);
        if ($parsed === null) {
            return $this->fail('โทเคนไม่ถูกต้องหรือหมดอายุแล้ว', 401);
        }

        $tokenDataId = $parsed->claims()->get('data');
        if (empty($tokenDataId)) {
            return $this->fail('รูปแบบโทเคนไม่ถูกต้อง', 401);
        }

        $tokenCheck = $this->checkRegisterToken((string) $tokenDataId);
        if (! $tokenCheck->success) {
            return $tokenCheck;
        }

        // แนบ email จาก RegisterToken และจำลอง payload ให้โค้ดเก่า
        $payload = (object) [
            'data' => $tokenDataId,
            'email' => $tokenCheck->data->email,
        ];

        return $this->success('ตรวจสอบโทเคนสำเร็จ', $payload, 200);
    }

    /**
     * ตรวจสอบว่า RegisterToken ID ยังใช้งานได้
     *
     * ตรวจสอบ: ไม่ revoked, ไม่หมดอายุ, email ยังไม่มีในระบบ
     *
     * @param  string  $registerTokenId  UUID ของ RegisterToken
     */
    public function checkRegisterToken(string $registerTokenId): ServiceResult
    {
        $token = $this->registerTokenRepository->findValidToken($registerTokenId);

        if (! $token) {
            return $this->fail(
                'กรุณาตรวจสอบอีเมล หรือหมดเวลา หรือมีการคอนเฟิร์มแล้ว หรือไปลงทะเบียนก่อนจะหมดเวลา',
                422,
            );
        }

        if (empty($token->email)) {
            return $this->fail('ไม่มีอีเมลในระบบ กรุณาขอโทเคนใหม่', 422);
        }

        // ตรวจสอบ email ซ้ำ
        if ($this->userRepository->emailExists($token->email)) {
            return $this->fail('มีผู้ใช้งานอีเมลนี้อยู่แล้ว กรุณาใช้อีเมลอื่น', 422);
        }

        return $this->success('คุณมีสิทธิ์ดำเนินการลงทะเบียน', $token, 200);
    }

    // =========================================================================
    // Token Request
    // =========================================================================

    /**
     * ขอ RegisterToken สำหรับ email ที่ระบุ
     *
     * ถ้า token ที่ยังใช้งานได้มีอยู่แล้ว จะ reject พร้อม remaining time
     * เพื่อป้องกัน token flooding และ email spam
     *
     * Logic นี้ extract มาจาก RegisterController::signupregister()
     * เพื่อให้ Controller บางเบาและทดสอบได้ง่ายขึ้น
     *
     * @param  string  $email  อีเมลที่ต้องการลงทะเบียน
     */
    public function requestRegisterToken(string $email): ServiceResult
    {
        $delay = (int) config('core.base::crypto.jwt.delay', 86400);
        $key = (string) config('core.base::crypto.jwt.secret', '');

        if (empty($key)) {
            return $this->fail('ไม่มี key ในระบบ กรุณาขอโทเคนใหม่', 422);
        }
        // ตรวจสอบ token ที่ยังไม่หมดอายุ — ป้องกันการขอซ้ำ
        $existing = $this->registerTokenRepository->findActiveByEmail($email);

        if ($existing) {
            return $this->fail(
                'กรุณาตรวจสอบอีเมล หรือไปลงทะเบียนก่อนจะหมดเวลา หรือติดต่อผู้ดูแลระบบ',
                422,
                ['remaining_time' => remaining_time_text($existing->expires_at)],
            );
        }

        // สร้าง RegisterToken ใหม่
        $token = $this->registerTokenRepository->createToken($email, $delay);

        return $this->success('การขอใช้ระบบสำเร็จ', [
            'token' => $this->jwtService->buildCustomToken($token->id, $delay),
        ], 200);
    }

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * ดำเนินการลงทะเบียนผู้ใช้ใหม่ (ทั้งหมดทำใน DB transaction)
     *
     * ลำดับการทำงานใน transaction:
     * 1. สุ่ม citizen_id ชั่วคราว (สำหรับ AnonymousPeople)
     * 2. ตรวจสอบว่ามี People record อยู่แล้วหรือไม่
     *    - มี People + มี User → คืน user เดิม
     *    - มี People แต่ไม่มี User → สร้าง User ใหม่
     *    - ไม่มี People → สร้าง AnonymousPeople + People + User
     * 3. Revoke RegisterToken (ป้องกันใช้ซ้ำ)
     * 4. Log การลงทะเบียน (หลัง commit สำเร็จ)
     *
     * ⚠️ password จะถูก hash ด้วย bcrypt ก่อนบันทึก — ห้าม expose ใน response
     *
     * @param  RegistrationDTO  $data  ข้อมูลผู้สมัคร
     * @param  string  $registerTokenId  UUID ของ RegisterToken ที่ใช้ลงทะเบียน
     * @param  string  $email  อีเมลของผู้ลงทะเบียน (จาก RegisterToken)
     *
     * @throws Throwable เมื่อ transaction ล้มเหลว (rollback อัตโนมัติ)
     */
    public function executeRegistration(RegistrationDTO $data, string $registerTokenId, string $email): ServiceResult
    {
        return DB::transaction(function () use ($data, $registerTokenId, $email) {
            // 1. สุ่ม citizen_id ชั่วคราวสำหรับ AnonymousPeople
            $citizenId = random_citizen_id();

            // 2. สร้าง/ค้นหา People + User
            [$person, $people, $user] = $this->resolvePersonAndUser($citizenId, $data, $email);

            // 3. Revoke token — ป้องกันใช้ซ้ำ (idempotent: false ถ้า already revoked)
            $this->registerTokenRepository->revokeToken($registerTokenId);

            // 4. Log หลัง commit สำเร็จ (ไม่ block response)
            DB::afterCommit(function () use ($user, $email, $registerTokenId): void {
                Log::info('USER_REGISTRATION_SUCCESS', [
                    'user_id' => $user?->id,
                    'email' => $email,
                    'register_token_id' => $registerTokenId,
                    'ip' => request()->ip(),
                    'timestamp' => now()->toISOString(),
                ]);
            });

            return ServiceResult::success([
                'person' => $person,
                'user' => $user,
                'people' => $people,
                'registerTokenId' => $registerTokenId,
                'email' => $email,
                'citizen_id' => $citizenId,
                'username' => $citizenId,
                // ❌ ห้าม return $data โดยตรง เพราะมี password อยู่
            ], 'Registration completed successfully');
        });
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * ค้นหาหรือสร้าง People และ User จาก citizen_id
     *
     * คืน tuple [person, people, user] เสมอ
     * - person  = AnonymousPeople (หรือ null ถ้ามี People อยู่แล้ว)
     * - people  = People model
     * - user    = User model ที่สร้างหรือค้นพบ
     *
     * @return array{0: mixed, 1: mixed, 2: mixed}
     */
    private function resolvePersonAndUser(string $citizenId, RegistrationDTO $data, string $email): array
    {
        $nameTh = $data->name_th ?? '';
        $nameEn = $data->name_en ?? $nameTh; // fallback: ใช้ name_th ถ้าไม่มี name_en
        $password = Hash::make($data->password);

        /** @var \App\Models\People|null $existingPeople */
        $existingPeople = $this->userRepository->checkPeople($citizenId);

        if ($existingPeople) {
            // กรณี: มี People record อยู่แล้ว
            $user = $existingPeople->user ?? $existingPeople->user()->create([
                'username' => $citizenId,
                'name_th' => $nameTh,
                'name_en' => $nameEn,
                'email' => $email,
                'password' => $password,
                'metadata' => [],
            ]);

            return [null, $existingPeople, $user];
        }

        // กรณี: ไม่มี People record → สร้างใหม่ทั้งหมด
        /** @var AnonymousPeople $person */
        $person = AnonymousPeople::firstOrCreate(
            ['citizen_id' => $citizenId],
            ['name_th' => $nameTh, 'name_en' => $nameEn, 'metadata' => []],
        );

        /** @var \App\Models\People $people */
        $people = $person->people()->create(['metadata' => []]);

        $user = $people->user()->create([
            'username' => $citizenId,
            'name_th' => $nameTh,
            'name_en' => $nameEn,
            'email' => $email,
            'password' => $password,
            'metadata' => [],
        ]);

        return [$person, $people, $user];
    }

    private function success(string $message, mixed $data, int $code): ServiceResult
    {
        return ServiceResult::success($data, $message, $code);
    }

    /**
     * คืนค่า error ในรูปแบบ ServiceResult
     */
    private function fail(string $message, int $code, mixed $data = []): ServiceResult
    {
        return ServiceResult::error($message, $code, $data);
    }
}
