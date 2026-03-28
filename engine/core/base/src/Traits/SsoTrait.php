<?php

namespace Core\Base\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait SsoTrait
{
    public function getLogin(Request $request)
    {
        // dd($request);

        //  Auth::logout();
        $request->session()->put('state', $state = Str::random(40));
        $query = http_build_query([
            'client_id' => config('auth.client_id'),
            'redirect_uri' => config('auth.callback'),
            'response_type' => 'code',
            'scope' => config('auth.scopes'),
            'state' => $state,
        ]);

        // dd(config("auth.client_id"));

        //  Auth::logout();

        return redirect(config('auth.sso_host').'/oauth/authorize?'.$query);
    }

    public function getCallback(Request $request)
    {
        // dd($request->code);
        //  exit;
        $datas = [
            'grant_type' => 'authorization_code',
            'client_id' => config('auth.client_id'),
            'client_secret' => config('auth.client_secret'),
            'redirect_uri' => config('auth.callback'),
            'code' => $request->code,
        ];

        $state = $request->session()->pull('state');

        throw_unless(
            strlen($state) > 0 && $state == $request->state,
            InvalidArgumentException::class,
        );

        $response = Http::asForm()->post(
            config('auth.sso_host').'/oauth/token',
            $datas,
        );

        // dd($response->json());
        //  exit;
        $request->session()->put($response->json());

        return redirect(route('sso.connect'));
    }
}
