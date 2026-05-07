<?php

namespace Engine\Modules\Demo\Services;

use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;

class KeyLabService
{
    public function __construct(
        private readonly SodiumHelper $sodium,
        private readonly JwtHelper $jwtHelper,
    ) {}

    public function generateKeys(): array
    {
        $masterKeyBase64 = config('core.base::security.masterkey');
        if (! \is_string($masterKeyBase64)) {
            throw new \RuntimeException('Master key not configured');
        }

        $masterKey32 = $this->sodium->parseKey($masterKeyBase64);
        $masterSeed = $this->sodium->genHashByName('master:bakup', $masterKey32, 32);

        $bakupBase64 = $this->sodium->prefixKeyBase64('master:bakup', $masterKey32);
        $castBase64 = $this->sodium->prefixKeyBase64('master:cast', $masterKey32);

        $signSeed = $this->sodium->genHashByName('signature_seed', $masterSeed, 32);
        $signSeedb64url = $this->sodium->encodeKey($signSeed, false);
        $generateSignatureKeyPair = $this->sodium->generateSignatureKeyPair($signSeed);

        $exchangeSeed = $this->sodium->genHashByName('exchange_seed', $masterSeed, 32);
        $exchangeSeedb64url = $this->sodium->encodeKey($exchangeSeed, false);
        $x25519KxKey = $this->sodium->generateKxKeyPair($exchangeSeed);

        $boxSeed = $this->sodium->genHashByName('box_seed', $masterSeed, 32);
        $boxSeedb64url = $this->sodium->encodeKey($boxSeed, false);
        $generateBoxKeyPair = $this->sodium->generateBoxKeyPair($boxSeed);

        $jwtSeed = $this->sodium->genHashByName('jwt_seed', $masterSeed, 32);
        $jwtSeedb64url = $this->sodium->encodeKey($jwtSeed, false);
        $generateSignatureKeyPairjwt = $this->jwtHelper->generateSignatureKeyPairforjwt($jwtSeed);

        return [
            'masterKeyBase64' => $masterKeyBase64,
            'bakupBase64' => $bakupBase64,
            'castBase64' => $castBase64,
            'signatureSeedb64url' => $signSeedb64url,
            'exchangeSeedb64url' => $exchangeSeedb64url,
            'boxSeedb64url' => $boxSeedb64url,
            'jwtSeedb64url' => $jwtSeedb64url,
            'generateSignatureKeyPair' => $generateSignatureKeyPair,
            'x25519KxKey' => $x25519KxKey,
            'generateBoxKeyPair' => $generateBoxKeyPair,
            'generateSignatureKeyPairjwt' => $generateSignatureKeyPairjwt,
        ];
    }
}
