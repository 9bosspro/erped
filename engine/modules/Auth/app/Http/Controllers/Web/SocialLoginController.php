<?php

declare(strict_types=1);

namespace Engine\Modules\Auth\Http\Controllers\Web;

//use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Slave\Contracts\Master\TokenFlow;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class SocialLoginController extends Controller
{
    private const array SUPPORTED_PROVIDERS = ['google'];
    private const SESSION_ACCESS_TOKEN_JWT  = 'backend_access_token_jwt';

    /**
     * ส่งผู้ใช้ไปยังหน้า Login ของ Provider (เช่น Google)
     */
    public function redirect(string $provider, Request $request): SymfonyRedirect
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS, true), 400, 'Provider not supported');

        $redirectTo = $request->query('redirect_to', '/');

        // รับเฉพาะ relative path เพื่อป้องกัน Open Redirect
        if (! \is_string($redirectTo) || ! str_starts_with($redirectTo, '/')) {
            $redirectTo = '/';
        }

        $payload = [
            'redirect_to' => $redirectTo,
            'origin'      => $request->headers->get('origin'),
            'nonce'       => Str::random(16),
            'expires_at'  => now()->addMinutes(3)->timestamp,
        ];

        $payloadJson = canonicalize($payload);
        $signature   = hash_hmac('sha256', (string) $payloadJson, (string) get_app_key());

        $state = encodeb64UrlSafe((string) json_encode([
            'data' => $payload,
            'sig'  => $signature,
        ]));

        return Socialite::driver($provider)->stateless()
            ->with(['state' => $state])
            ->redirect();
    }

    /**
     * รับข้อมูลกลับจาก Provider หลัง user authorize แล้ว
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS, true), 400, 'Provider not supported');

        $stateRaw = $request->input('state');
        if (! $stateRaw) {
            abort(400, 'State missing');
        }

        $decodedState = decodeb64UrlSafe((string) $stateRaw);

        /** @var array{data?: mixed, sig?: mixed}|null $stateData */
        $stateData = json_decode((string) $decodedState, true);

        if (! is_array($stateData) || ! isset($stateData['data'], $stateData['sig'])) {
            abort(400, 'Invalid state data');
        }

        $payload     = $stateData['data'];
        $receivedSig = (string) ($stateData['sig'] ?? '');
        $payloadJson = canonicalize($payload);
        $expectedSig = hash_hmac('sha256', (string) $payloadJson, (string) get_app_key());

        if (! hash_equals($expectedSig, $receivedSig)) {
            abort(403, 'State ถูกดัดแปลงระหว่างทาง');
        }

        if (! is_array($payload)) {
            abort(400, 'Invalid payload');
        }

        $expiresAt = is_scalar($payload['expires_at'] ?? null) ? (int) $payload['expires_at'] : 0;
        if (now()->timestamp > $expiresAt) {
            abort(403, 'State expired');
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $email      = $socialUser->getEmail();

            if (empty($email)) {
                abort(403, 'Email is required');
            }
            //  dd($socialUser);
            $client = app('slave.master');


            $clients = $client->withFlow(TokenFlow::Jwt)->withScope('social_login:all');

            $metadata = [
                'social_users' => [
                    $provider => [
                        'id'     => $socialUser->getId(),
                        'email'  => $socialUser->getEmail(),
                        'name'   => $socialUser->getName(),
                        'avatar' => $socialUser->getAvatar(),
                    ],
                ],
            ];

            $payloadLogin = [
                'email'    => $socialUser->getEmail(),
                'nonce'    => Str::random(16),
                'name_th'  => $socialUser->getName(),
                'name_en'  => $socialUser->getName(),
                'password' => Str::random(64),
                'metadata' => $metadata,
            ];

            $response = $clients->post('/api/v1/auth/login-social', $payloadLogin);
            //sendRequest

            if ($response['success'] === false) {
                return redirect()->route('login')
                    ->withErrors(['email' => $response['message']]);
            }
            //
            $data = $response['data'];
            //  dd($data);
            //ลบ token jwt social_login:all ที่ใช้ไปแล้ว
            $client->clearToken(TokenFlow::Jwt, 'social_login:all');
            //
            //  ใช้  $data['token'] เรียก api  /me   มาดึงข้อมูลส่วนตัวของ user เพื่อเทราบรายละเอียด  user
            //

            $user = User::firstOrCreate(
                ['email' => $socialUser->getEmail()],
                [
                    'username' => $data['user']['username'] ?? $socialUser->getEmail(),
                    'name'     => $socialUser->getName(),
                    'name_th'  => $socialUser->getName(),
                    'name_en'  => $socialUser->getName(),
                    'password' => Str::random(64),
                    'metadata' => $metadata,
                    'backend_user_id' => $data['user']['id'],
                ]
            );

            if (! $user->wasRecentlyCreated) {
                $user->update([
                    'name'    => $socialUser->getName(),
                    'name_th' => $socialUser->getName(),
                    'name_en' => $socialUser->getName(),
                    'metadata' => $metadata,
                    'backend_user_id' => $data['user']['id'],
                ]);
            }

            //
            $personalClient = $client->withFlow(TokenFlow::Personal)->withTokenStore('session')->withToken($data['token']);
            //storeToken
            // นำ token ยิง  useribfo  หรือ  me
            $userResp = $personalClient->post('/api/v1/auth/user/me');
            if ($userResp['success'] == false) {
                return redirect()->route('login')->withErrors(['email' => $userResp['message']]);
            }

            //   $userRespData = $userResp['data'];
            //  dd($userRespData);

            Auth::login($user);
            //
            $personalClient->storeToken([
                'access_token' => $data['token'],
                'token_type'   => $data['token_type'] ?? 'Bearer',
                'expires_in'   => (int) ($data['expires_in'] ?? 86400),
                // 'refresh_token' => $data['refresh_token'] ?? null,
            ]);




            // session([self::SESSION_ACCESS_TOKEN_JWT => $data['token']]);

            $redirectTo = (string) ($payload['redirect_to'] ?? '/');

            if (! str_starts_with($redirectTo, '/')) {
                $redirectTo = '/';
            }

            return redirect($redirectTo);
        } catch (Exception $e) {
            return redirect()->route('login')
                ->withErrors(['email' => $e->getMessage()]);
        }
    }
}
