<?php

declare(strict_types=1);

namespace Engine\Modules\Demo\Http\Controllers;

use App\Http\Controllers\BaseInertiaController;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Core\Base\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Response as InertiaResponse;
use Slave\Contracts\Master\TokenFlow;

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

    /**
     * JSON endpoint: ทดสอบ social login ผ่าน master
     */
    public function lab1(): JsonResponse
    {
        $masterClient = app('slave.master');

        /*   if ($masterClient->ping() === false) {
            return $this->sendError('Master is not ping available');
        } */

        $payloadLogin = [
            'email' => 'ampolbo@gmail.com',
            'nonce' => Str::random(16),
            'name_th' => 'นายสมชาย ใจดี',
            'name_en' => 'Mr. Somchai Heart',
            'mobile' => '0812345678',
            'password' => '0812345678',
            'metadata' => [],
        ];
        //
        //   $client = app('slave.master');
        //   $personalClient = $masterClient->withFlow(TokenFlow::Personal)->withTokenStore('session');
        //  $ok = $personalClient->getToken();

        // dd($ok);
        $masterClientJwt = $masterClient->withFlow(TokenFlow::Jwt)->withScope('social_login:all');
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
        $personalClient = $masterClient->withFlow(TokenFlow::Personal)
            ->withTokenStore('session');
        $response = $personalClient->post('api/v1/auth/user/logout');
        dd($response);

        // ยิง api ไป logout ที่ Master Backend
        /*  $token = $personalClient->getToken();
        $response = $personalClient->withToken($token)->post('api/v1/auth/user/logout');
        dd($response);
 */
        $personalClient->sendRequest('POST', '/api/v1/auth/user/logout', [
            'token' => $personalClient->getToken(), // 🌟 ดึง Token มาใส่แบบถูกต้องแล้วครับ!
        ]);


        //  $response = $masterClientJwt->sendRequest('POST', '/api/v1/auth/login-social', $payloadLogin);
        //sendRequest
        //  $client = app('slave.master');
        // 🧪 ดึงข้อมูลข้างในออกมาโชว์ทั้งหมด! >withTokenStore('session')->
        dd($masterClient->debugCachedTokens(), $masterClient->withTokenStore('session')->debugCachedTokens());


        $personalClient = $masterClient->withFlow(TokenFlow::Personal)->withTokenStore('session');
        //storeToken
        // นำ token ยิง  useribfo  หรือ  me
        $userResp = $personalClient->post('/api/v1/auth/user/me');
        dd($userResp);


        try {
            $keys = $keyLabService->generateKeys();

            return $this->sendResponse($keys, 'generate keys สำเร็จ');

            /*   $masterClientjwt = $masterClient->withFlow(TokenFlow::Jwt)->withScope('social_login:all');
            $masterClient->clearToken(TokenFlow::Jwt, 'social_login:all');

            $response = $masterClientjwt->post('/api/v1/auth/login-social', $payloadLogin); */

            //
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation Error', 401, $e->errors());
        } catch (Exception $e) {
            return $this->sendError('Social Login Error', 403, ['error' => $e->getMessage()]);
        }
    }
}
