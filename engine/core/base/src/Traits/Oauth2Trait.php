<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Oauth2Trait — OAuth2 Authorization Code + Client Credentials Flow
 *
 * ใช้กับ Controller ที่ต้องการ SSO / OAuth2 login
 * ต้องการ config:
 *   auth.client_id, auth.client_secret, auth.callback, auth.scopes, auth.sso_host
 *   myconfig.serverservice.service.ssooauth2.url / .clientid / .clientsecret
 */
trait Oauth2Trait
{
    /**
     * เริ่ม OAuth2 Authorization Code Flow — redirect ไปยัง SSO server
     */
    public function getLogin(Request $request): RedirectResponse
    {
        $request->session()->put('state', $state = (new \Core\Base\Support\Helpers\Crypto\HashHelper)->randomString(40));

        $query = http_build_query([
            'client_id' => config('auth.client_id'),
            'redirect_uri' => config('auth.callback'),
            'response_type' => 'code',
            'scope' => config('auth.scopes'),
            'state' => $state,
        ]);

        return redirect(config('auth.sso_host').'/oauth/authorize?'.$query);
    }

    /**
     * รับ callback จาก SSO server — ตรวจ state แล้วแลก code เป็น token
     */
    public function getCallback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('state');

        throw_unless(
            \strlen((string) $state) > 0 && $state === $request->input('state'),
            InvalidArgumentException::class,
            'OAuth2 state mismatch — อาจเป็น CSRF attack',
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

    /**
     * ขอ access token ด้วย Client Credentials Grant
     *
     * @throws Exception เมื่อ token request ล้มเหลว
     */
    protected function clientCredentials(): string
    {
        $baseUrl = (string) config('myconfig.serverservice.service.ssooauth2.url');
        $endpoint = rtrim($baseUrl, '/').'/oauth/token';

        $response = Http::withHeaders(['Accept' => 'application/json; charset=UTF-8'])
            ->asForm()
            ->post($endpoint, [
                'grant_type' => 'client_credentials',
                'client_id' => config('myconfig.serverservice.service.ssooauth2.clientid'),
                'client_secret' => config('myconfig.serverservice.service.ssooauth2.clientsecret'),
            ]);

        if (! $response->successful()) {
            throw new Exception(
                $response->json('message', 'OAuth2 client_credentials ล้มเหลว'),
                $response->status(),
            );
        }

        return (string) $response->json('access_token');
    }
}
