<?php

namespace Engine\Modules\Demo\Http\Controllers;

use App\Http\Controllers\Controller;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Core\Base\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;


class LabController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        //private readonly EncryptionServiceInterface $encryptionService,
        private readonly SodiumHelper $sodium,
        private readonly JwtHelper $jwtHelper,
    ) {}
    /* public function __invoke()
    {
        return 'Hello World';
    } */
    public function lab1(): JsonResponse
    {
        $masterClient = app('slave.master');
        $client_id = config('client.client_id');
        $client_secret = config('client.client_secret');
        $master_url = config('client.master_url');
        //  $masterClient = app(MasterClientInterface::class);
        // $token = $masterClient->getAccessToken();
        $masterClient->clearToken();
        $token1 = $masterClient->getAccessToken();
        //  dd($token1);
        $masterKeyBase64 = config('core.base::security.masterkey');
        if (! \is_string($masterKeyBase64)) {
            abort(500, 'Master key not configured');
        }
        $masterKey32 = $this->sodium->parseKey($masterKeyBase64);
        $masterSeed = $this->sodium->genHashByName('master:bakup', $masterKey32, 32); // คีสำรอง
        $bakupBase64 = $this->sodium->prefixKeyBase64('master:bakup', $masterKey32); //  คีสำรอง
        $castBase64 = $this->sodium->prefixKeyBase64('master:cast', $masterKey32); //  เจนคี เครื่องลูก
        //
        $signSeed = $this->sodium->genHashByName('signature_seed', $masterSeed, 32);
        $signSeedb64url = $this->sodium->encodeKey($signSeed, false);
        //   dd($signSeedb64url);
        $generateSignatureKeyPair = $this->sodium->generateSignatureKeyPair($signSeed);
        //   dd($signSeed, $signSeedb64url, $generateSignatureKeyPair['public']);
        //
        $exchangeSeed = $this->sodium->genHashByName('exchange_seed', $masterSeed, 32);
        $exchangeSeedb64url = $this->sodium->encodeKey($exchangeSeed, false);
        // dd($exchangeSeedb64url);
        $x25519KxKey = $this->sodium->generateKxKeyPair($exchangeSeed);
        //
        $boxSeed = $this->sodium->genHashByName('box_seed', $masterSeed, 32);
        $boxSeedb64url = $this->sodium->encodeKey($boxSeed, false);
        //  dd($boxSeedb64url);
        $generateBoxKeyPair = $this->sodium->generateBoxKeyPair($boxSeed);
        //
        $jwtSeed = $this->sodium->genHashByName('jwt_seed', $masterSeed, 32);
        $jwtSeedb64url = $this->sodium->encodeKey($jwtSeed, false);
        //  dd($jwtSeedb64url);
        $generateSignatureKeyPairjwt = $this->jwtHelper->generateSignatureKeyPairforjwt($jwtSeed);
        // dd($generateBoxKeyPair);

        // dd($masterKey32);
        return $this->sendResponse(
            [
                'masterKeyBase64' => $masterKeyBase64,
                'bakupBase64' => $bakupBase64,
                'castBase64' => $castBase64,
                //  'keybakup' => $masterSeed,
                //  'token' => $token1,
                'signatureSeedb64url' => $signSeedb64url,
                'exchangeSeedb64url' => $exchangeSeedb64url,
                'boxSeedb64url' => $boxSeedb64url,
                'jwtSeedb64url' => $jwtSeedb64url,
                'generateSignatureKeyPair' => $generateSignatureKeyPair,
                'x25519KxKey' => $x25519KxKey,
                'generateBoxKeyPair' => $generateBoxKeyPair,
                'generateSignatureKeyPairjwt' => $generateSignatureKeyPairjwt,

                /*  'publickeybox' => $generateBoxKeyPair['public'],
                'privatekeybox' => $generateBoxKeyPair['secret'],
                'publickeyjwt' => $generateSignatureKeyPairjwt['public'],
                'privatekeyjwt' => $generateSignatureKeyPairjwt['secret'], */
            ],
            'Key generated successfully'
        );
        //return "ทดสอบ 123";
    }
}
