<?php

declare(strict_types=1);

namespace Core\Base\Services\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * SsoClient — HTTP client สำหรับเชื่อมต่อกับ SSO Master Server
 *
 * ความรับผิดชอบ:
 * - ดึงข้อมูลผู้ใช้จาก SSO (user info, access token)
 * - ตรวจสอบ, ดึง, เพิ่มผู้ใช้ผ่าน SSO API
 * - จัดการ OAuth2 callback flow
 *
 * การใช้งาน:
 * ```php
 * $sso = app(Httpclients::class);
 * $userInfo = $sso->getUserInfo($accessToken);
 * ```
 *
 * @deprecated ใช้ชื่อ class เดิม Httpclients เพื่อ backward compatibility
 *             ในอนาคตควรเปลี่ยนเป็น SsoClient
 */
class Httpclients
{
    /**
     * ดึง API key สำหรับ SSO Master API
     */
    public function getAccessToken(): string
    {
        return (string) config('services.sso_master_api.api_key', '');
    }

    /**
     * ดึงข้อมูลผู้ใช้จาก SSO ด้วย access token
     *
     * @param  string  $accessToken  Bearer token ของผู้ใช้
     */
    public function getUserInfo(string $accessToken): Response
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$accessToken}",
        ])->post(config('auth.auth_sso_host').'/api/v1/me');
    }

    /**
     * ตรวจสอบ access token ปัจจุบัน
     *
     * @param  string  $accessToken  Bearer token
     * @return array<string, mixed>|null ข้อมูล token
     */
    public function currentAccessTokens(string $accessToken): ?array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$accessToken}",
        ])->post(config('auth.auth_sso_host').'/api/v1/currentAccessToken');

        return $response->json();
    }

    /**
     * ตรวจสอบผู้ใช้ใน SSO Master Server
     *
     * @param  array<string, mixed>  $data  ข้อมูลผู้ใช้ที่ต้องการตรวจสอบ
     * @param  string  $signature  hash signature สำหรับยืนยัน request
     */
    public function checkUser(array $data, string $signature): Response
    {
        return $this->sendSsoRequest('/api/v1/chkuser', $data, $signature);
    }

    /**
     * ดึงข้อมูลผู้ใช้จาก SSO Master Server
     *
     * @param  array<string, mixed>  $data  ข้อมูลสำหรับค้นหาผู้ใช้
     * @param  string  $signature  hash signature สำหรับยืนยัน request
     */
    public function getUser(array $data, string $signature): Response
    {
        return $this->sendSsoRequest('/api/v1/getuser', $data, $signature);
    }

    /**
     * เพิ่มผู้ใช้ใหม่ใน SSO Master Server
     *
     * @param  array<string, mixed>  $data  ข้อมูลผู้ใช้ที่ต้องการเพิ่ม
     * @param  string  $signature  hash signature สำหรับยืนยัน request
     */
    public function addUser(array $data, string $signature): Response
    {
        return $this->sendSsoRequest('/api/v1/adduser', $data, $signature);
    }

    /**
     * จัดการ OAuth2 callback — แลก code เป็น token
     *
     * @param  \Illuminate\Http\Request  $request  HTTP request จาก SSO callback
     * @return \Illuminate\Http\RedirectResponse redirect ไปหน้า SSO connect
     *
     * @throws InvalidArgumentException เมื่อ state ไม่ตรงกัน (CSRF protection)
     */
    public function getCallback(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $state = $request->session()->pull('state');

        throw_unless(
            is_string($state) && strlen($state) > 0 && $state === $request->input('state'),
            InvalidArgumentException::class,
            'Invalid OAuth state — possible CSRF attack.',
        );

        $response = Http::asForm()->post(
            config('auth.sso_host').'/oauth/token',
            [
                'grant_type' => 'authorization_code',
                'client_id' => config('auth.client_id'),
                'client_secret' => config('auth.client_secret'),
                'redirect_uri' => config('auth.callback'),
                'code' => $request->input('code'),
            ],
        );

        $request->session()->put($response->json());

        return redirect(route('sso.connect'));
    }

    // ─── Backward Compatibility Aliases ──────────────────────────────

    /** @deprecated ใช้ getUserInfo() แทน */
    public function user_info_sso(string $accessToken): Response
    {
        return $this->getUserInfo($accessToken);
    }

    /** @deprecated ใช้ checkUser() แทน */
    public function chkuser(array $data, string $signature): Response
    {
        return $this->checkUser($data, $signature);
    }

    // ─── Private ─────────────────────────────────────────────────────

    /**
     * ส่ง request ไปยัง SSO Master Server พร้อม Bearer token และ Signature
     */
    private function sendSsoRequest(string $endpoint, array $data, string $signature): Response
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->getAccessToken()}",
            'Signature' => $signature,
        ])->post(
            config('services.sso_master_server.sso_host').$endpoint,
            $data,
        );
    }
}
