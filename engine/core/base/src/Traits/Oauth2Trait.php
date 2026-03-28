<?php

namespace Core\Base\Traits;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait Oauth2Trait
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

    protected function client_credentials()
    {
        /*
        $url_endpoint =
            config('myconfig.serverservice.service.ssooauth2.url') .
            '/oauth/token'; */
        $url_endpoint = 'http://serversso.test/oauth/token';

        $hesders = [];
        $hesders['Accept'] = 'application/json; charset=UTF-8';
        $data = [];
        $data['grant_type'] = 'client_credentials';
        $data['client_id'] = config(
            'myconfig.serverservice.service.ssooauth2.clientid',
        );
        $data['client_secret'] = config(
            'myconfig.serverservice.service.ssooauth2.clientsecret',
        );
        //   return $data;

        $response = Http::withHeaders($hesders)
            ->asForm()
            ->post($url_endpoint, $data);
        $_data = $response->json(); // ['access_token']
        $status = $response->status(); // โคด
        // tt($_data);
        if ($status != 200) {
            throw new Exception($_data['message'], $status);
        }

        return $_data['access_token'];
    }

    protected function loins(Request $request) {}
}
