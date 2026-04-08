<?php

declare(strict_types=1);

namespace Core\Base\Services\Security;

class SodiumServer
{
    private $keypair;

    private $rx;

    private $tx;

    public function __construct()
    {
        // Server มักจะมี Keypair ถาวร (Static)
        $this->keypair = sodium_crypto_kx_keypair();
    }

    public function getPublicKey()
    {
        return sodium_crypto_kx_publickey($this->keypair);
    }

    // 1. เมื่อ Client ส่ง Public Key มาให้
    public function establishSession($clientPublicKey)
    {
        [$this->rx, $this->tx] = sodium_crypto_kx_server_session_keys(
            $this->keypair,
            $clientPublicKey,
        );
    }

    // 2. Server ใช้ tx ส่งข้อมูลกลับ
    public function encrypt($message)
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $message,
            '',
            $nonce,
            $this->tx,
        );

        return ['ciphertext' => bin2hex($ciphertext), 'nonce' => bin2hex($nonce)];
    }

    // 3. Server ใช้ rx ถอดรหัสข้อมูลที่ Client ส่งมา
    public function decrypt($hexCiphertext, $hexNonce)
    {
        return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            hex2bin($hexCiphertext),
            '',
            hex2bin($hexNonce),
            $this->rx,
        );
    }
}
