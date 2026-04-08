<?php

declare(strict_types=1);

namespace Core\Base\Services\Security;

class SodiumClient
{
    private $keypair;

    private $rx; // Receive Key

    private $tx; // Transmit Key

    public function __construct()
    {
        // 1. สร้าง Keypair ของตัวเองทันทีที่เปิดแอป
        $this->keypair = sodium_crypto_kx_keypair();
    }

    public function getPublicKey()
    {
        return sodium_crypto_kx_publickey($this->keypair);
    }

    // 2. เมื่อได้รับ Public Key จาก Server ให้คำนวณ Session Keys
    public function establishSession($serverPublicKey)
    {
        [$this->rx, $this->tx] = sodium_crypto_kx_client_session_keys(
            $this->keypair,
            $serverPublicKey,
        );
    }

    // 3. ฟังก์ชันส่งข้อมูล (Encrypt)
    public function encrypt($message)
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $message,
            '',
            $nonce,
            $this->tx, // Client ใช้ tx ส่ง
        );

        return ['ciphertext' => bin2hex($ciphertext), 'nonce' => bin2hex($nonce)];
    }

    // 4. ฟังก์ชันรับข้อมูล (Decrypt)
    public function decrypt($hexCiphertext, $hexNonce)
    {
        return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            hex2bin($hexCiphertext),
            '',
            hex2bin($hexNonce),
            $this->rx, // Client ใช้ rx รับ
        );
    }
}
