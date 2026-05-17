<?php

declare(strict_types=1);

namespace Engine\Modules\Demo\Http\Controllers;

use App\Http\Controllers\BaseInertiaController;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Core\Base\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Response as InertiaResponse;
use Slave\Contracts\Master\TokenFlow;
use Throwable;

class LabController extends BaseInertiaController
{
    use ApiResponseTrait;

    protected string $pagePrefix = 'demo';

    public function __construct(
        private readonly \Engine\Modules\Demo\Services\KeyLabService $keyLabService,
        private readonly SodiumHelper $sodium,
        private readonly JwtHelper $jwtHelper,
    ) {}

    /**
     * แสดงหน้า Lab ผ่าน Inertia
     */
    public function index(): InertiaResponse
    {
        return $this->inertia('lab/index', [
            'message' => 'Demo Module — Inertia integration สำเร็จ',
            'items' => ['item-1', 'item-2', 'item-3'],
        ]);
    }

    public function resetpasswordfromforgetpass(): JsonResponse
    {
        $masterClient = app('slave.master');
    }

    /**
     * ทดสอบ reset password ผ่าน Master (lab endpoint)
     *
     * @return array<string, mixed> ข้อมูล response ที่ decode จาก JSON แล้ว
     *                              หรือ payload error สำหรับ debug หาก Master ตอบ 5xx
     */


    public function lab1(): JsonResponse
    {
        $payload = [
            'backend_user_id' => '019e3081-a54f-7374-9bf5-b04210bd7604',
            'password'        => '9999999999',
        ];

        //  $data = $this->resetpassword($payload, '/api/v1/clients/reset-password-forget');

        $data = callRestApiHybrid($payload, '/api/v1/clients/reset-password-forget', 'post');
        dd($data);


        $masterClient = app('slave.master');
        //   $masterClient->clearAllWithSessionAndRedis();

        $client_secret_code = config('slave::client.client_secret');
        $client_secret = decodeKey($client_secret_code);
        // encodeKey
        //  dd(encodeb64UrlSafe($client_secret));

        //  $masterClient->clearAllTokens();
        /*  $token = $masterClient->getToken();
        dd($token);
 */
        // ping ไม่ต้อง token
        if ($masterClient->ping() === false) {
            return $this->sendError('Master is not ping available');
        }


        dd($masterClient->debugCachedTokens(), $masterClient->withTokenStore('session')->debugCachedTokens());
        $payloadLogin = [
            'email' => 'ampolbo@gmail.com',
            'nonce' => Str::random(16),
            'name_th' => 'ทดสอบ login',
            'name_en' => 'Test',
            'password' => '0812345678',
            'metadata' => [],
        ];
        //

        /*  $response = Http::withHeaders([
            'X-For-Debug' => 'true',
        ])->asForm()->post(config('slave::client.master_url') . '/api/v1/auth/personal/login-social', $payloadLogin);
        dd($response->body()); */
        //

        /*  $ok = $masterClient->withFlow(TokenFlow::Personal)->withTokenStore('session')->withBody($payloadLogin);
        $tokens = $ok->sendRequest('POST', '/api/v1/auth/user/me');

        dd($tokens->body()); */
        /*  $securitykey = config('core.base::security.cast_key');
        dd($securitykey); */

        //  $token = $masterClient->getTokenFromTokensStore();
        // dd($tokens->body());
        //   dd('Master ping OK');

        /*   $payloadLogin = [
            'email' => 'ampolbo@gmail.com',
            'nonce' => Str::random(16),
            'name_th' => 'นายสมชาย ใจดี',
            'name_en' => 'Mr. Somchai Heart',
            'mobile' => '0812345678',
            'password' => '0812345678',
            'metadata' => [],
        ]; */
        /*   $payloadๅ = [
            'grant_type' => 'client_credentials',
            'client_id' => config('slave::client.client_id') ?? env('CLIENT_ID'),
            'client_secret' => config('slave::client.client_secret') ?? env('CLIENT_SECRET'),
            'scope' => '',
        ]; */
        $payload = [
            'grant_type' => 'password',
            'client_id' => config('slave::client.client_id') ?? env('CLIENT_ID'),
            'client_secret' => $client_secret,
            'username' => '9edampol@gmail.com',
            'password' => '9edampol@gmail.com',
            'scope' => '',
        ];

        /*  $response = Http::withHeaders([
            'X-For-Debug' => 'true',
        ])->asForm()->post(config('slave::client.master_url') . '/oauth/token', $payload);

        dd($response->json());
        if (! $response->successful()) {
            throw new Exception('Failed to obtain token: ' . $response->body());
        } */

        //  dd($response->json());
        //   $clients = $masterClient->withFlow(TokenFlow::Password)->withoutToken()->sendRequest('POST', '/oauth/token', $payload);
        //   $clients = $masterClient->withFlow(TokenFlow::Password)->withoutToken()->client();

        // dd($client);
        //   $client = app('slave.master');
        //   $personalClient = $masterClient->withFlow(TokenFlow::Personal)->withTokenStore('session');
        //  $ok = $personalClient->getToken();

        // dd($ok);
        //  $masterClientJwt = $masterClient->withFlow(TokenFlow::Jwt)->withScope('social_login:all');
        //  $test = $masterClientJwt->ping();
        $payloadLogin = [
            'email' => 'ampolbo@gmail.com',
            'nonce' => Str::random(16),
            'name_th' => 'นายสมชาย ใจดี',
            'name_en' => 'Mr. Somchai Heart',
            'password' => '0812345678',
            'metadata' => [],
        ];
        //
        //  $response = $masterClientJwt->post('api/v1/auth/login-social', $payloadLogin);
        //  $masterClient->clearAllTokens();
        //   $masterClient->withTokenStore('session')->clearAllTokens();

        //   dd($personalClient->getToken());
        /*  $personalClient = $masterClient->withFlow(TokenFlow::Personal)
            ->withTokenStore('session');
        $response = $personalClient->post('api/v1/auth/user/logout');
        dd($response); */

        // ยิง api ไป logout ที่ Master Backend
        /*  $token = $personalClient->getToken();
        $response = $personalClient->withToken($token)->post('api/v1/auth/user/logout');
        dd($response);
 */

        //  $response = $masterClientJwt->sendRequest('POST', '/api/v1/auth/login-social', $payloadLogin);
        // sendRequest
        //  $client = app('slave.master');
        //  $masterClient->clearToken(TokenFlow::Jwt, 'social_login:all');

        // 🧪 ดึงข้อมูลข้างในออกมาโชว์ทั้งหมด! >withTokenStore('session')->
        try {
            $masterClient = $masterClient->withFlow(TokenFlow::Password)->withUserPassword('9edampol@gmail.com', '9edampol@gmail.com');
            //  $tokens = $masterClient->sendRequest('POST', '/api/v1/auth/user/me');
            $response = $masterClient->post('api/v1/auth/user/me');
            dd($response);
            /*    $data = $response->json();
            $data = $data['data']; */
            // dd($data);
            // return $this->sendResponse($data);

            $personalClient = $masterClient->withFlow(TokenFlow::Personal)->withTokenStore('session');
            // storeToken
            // นำ token ยิง  useribfo  หรือ  me
            $userResp = $personalClient->post('/api/v1/auth/user/me');
            //  return $this->sendResponse($userResp);
            // dd($userResp);

            /*    $keys = $keyLabService->generateKeys();

            return $this->sendResponse($keys, 'generate keys สำเร็จ'); */

            /*   $masterClientjwt = $masterClient->withFlow(TokenFlow::Jwt)->withScope('social_login:all');
            $masterClient->clearToken(TokenFlow::Jwt, 'social_login:all');

            $response = $masterClientjwt->post('/api/v1/auth/login-social', $payloadLogin); */

            //

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation Error', 401, $e->errors());
        } catch (Throwable  $e) {
            return $this->sendError('Social Login Error', 403, ['error' => $e->getMessage()]);
        }
    }
}
