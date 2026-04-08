<?php

declare(strict_types=1);

namespace Core\Base\Services\Security;

use Core\Base\Services\Security\Contracts\SodiumHybridP2pInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use SodiumException;

/**
 * SodiumHybridP2p — extends SodiumHybrid เพิ่ม Cross-host Stateless Mode
 * ─────────────────────────────────────────────────────────────────────────
 * โหมด 1 : Session (สืบทอดจาก SodiumHybrid) — WebSocket, long-lived connection
 *           ทั้งสองฝ่ายต้อง setupSession() ก่อน แล้วค่อย encrypt/decrypt
 *
 * โหมด 2 : Cross-host Stateless (v2) — HTTP API, Queue, Webhook
 *           encryptFor()    → ส่งให้ host ปลายทาง (ephPk bound ใน AAD)
 *           decryptMessage() → ถอดรหัสด้วย static keypair (รองรับ v1/v2/legacy)
 *
 * โหมด 3 : Signed Hybrid (v2s) — HTTP API + Non-repudiation
 *           encryptForSigned()      → Ed25519 sign + X25519/XChaCha20 encrypt
 *           decryptMessageVerified() → ถอดรหัส + ยืนยันลายเซ็น
 *
 * โหมด 4 : Multi-recipient — Group messaging
 *           sealForMany()        → session key เดียว sealed สำหรับหลายผู้รับ
 *           openMultiRecipient() → แต่ละผู้รับถอดรหัสด้วย keypair ตัวเอง
 *
 * constructor รองรับ auto-detect: null / binary / hex (128 chars) / base64 (86-88 chars)
 */
class SodiumHybridP2p extends SodiumHybrid implements SodiumHybridP2pInterface
{
    // ─────────────────────────────────────────────────────────
    //  CONSTANTS
    // ─────────────────────────────────────────────────────────

    /** V1: Cross-host bundle (backward compat — ไม่ bind ephPk ใน AAD) */
    public const PROTOCOL_VERSION = 'v1';

    /** V2: Cross-host bundle — ephPk bound ใน effective AAD (แนะนำ) */
    public const PROTOCOL_VERSION_V2 = 'v2';

    /** V2S: Signed + Encrypted — Ed25519 sig prepended ก่อน encrypt */
    public const PROTOCOL_VERSION_SIGNED = 'v2s';

    /** Multi-recipient envelope version */
    private const MULTI_RECIPIENT_VERSION = 'mr1';

    /**
     * ตัวคั่นระหว่างส่วนต่าง ๆ ใน cross-host bundle
     * เลือก "." เพราะไม่ conflict กับ hex หรือ base64url
     */
    private const SEP = '.';

    // ─────────────────────────────────────────────────────────
    //  CONSTRUCTOR
    // ─────────────────────────────────────────────────────────

    /**
     * @param  string|null  $keypairBinary  null = สร้างใหม่
     *                                      hex (128 chars) = auto-decode
     *                                      base64 (86-88 chars) = auto-decode
     *                                      binary (64 bytes) = ใช้ตรง ๆ
     */
    public function __construct(?string $keypairBinary = null)
    {
        if ($keypairBinary === null || $keypairBinary === '') {
            parent::__construct(null);

            return;
        }

        // Auto-detect: Hex (128 hex chars = 64 bytes binary)
        if (\strlen($keypairBinary) === 128 && \ctype_xdigit($keypairBinary)) {
            parent::__construct(\sodium_hex2bin($keypairBinary));

            return;
        }

        // Auto-detect: Base64 standard (88 chars) หรือ Base64url (86 chars)
        if (\strlen($keypairBinary) === 88 || \strlen($keypairBinary) === 86) {
            $bin = \base64_decode($keypairBinary, strict: true);
            if ($bin === false) {
                throw new RuntimeException('Invalid base64 keypair string. Please provide standardized Base64.');
            }
            parent::__construct($bin);

            return;
        }

        // Binary — ตรวจขนาดก่อนส่งต่อ
        $expectedLen = SODIUM_CRYPTO_KX_SECRETKEYBYTES + SODIUM_CRYPTO_KX_PUBLICKEYBYTES;
        if (\strlen($keypairBinary) !== $expectedLen) {
            throw new RuntimeException(
                "Invalid binary keypair length: expected {$expectedLen} bytes, got ".\strlen($keypairBinary).'. '.
                'Make sure you provided a full X25519 KX keypair (secret + public).',
            );
        }

        parent::__construct($keypairBinary);
    }

    // ─────────────────────────────────────────────────────────
    //  KEY MANAGEMENT
    // ─────────────────────────────────────────────────────────

    /**
     * สร้าง Ed25519 signing keypair สำหรับใช้กับ encryptForSigned()
     *
     * @return array{signing: string, verify: string} hex-encoded keypair
     */
    public static function generateSigningKeyPair(): array
    {
        $kp = \sodium_crypto_sign_keypair();
        $result = [
            'signing' => \sodium_bin2hex(\sodium_crypto_sign_secretkey($kp)),
            'verify' => \sodium_bin2hex(\sodium_crypto_sign_publickey($kp)),
        ];
        \sodium_memzero($kp);

        return $result;
    }

    // ─────────────────────────────────────────────────────────
    //  CROSS-HOST MODE — Stateless, ข้ามโฮสได้
    // ─────────────────────────────────────────────────────────

    /**
     * [ผู้ส่ง] เข้ารหัส message สำหรับส่งข้ามโฮส — STATELESS (v2)
     * ─────────────────────────────────────────────────────────
     * ทุก call สร้าง ephemeral keypair ใหม่ → forward secrecy ทุก message
     * ephPk ถูก bind เข้า effective AAD → ป้องกัน key substitution attack
     *
     * BUNDLE FORMAT (v2): v2.ephem_pub_hex.nonce_hex.ciphertext_hex
     *
     * @param  string  $message  plaintext ที่ต้องการส่ง
     * @param  string  $recipientPubKey  public key ของ host ปลายทาง (hex หรือ base64)
     * @param  string  $aad  context binding เช่น 'host-a→host-b:v2'
     * @return string bundle v2 พร้อมส่งข้ามโฮส
     */
    public static function encryptFor(
        string $message,
        string $recipientPubKey,
        string $aad = '',
    ): string {
        $recipientPubBin = self::resolvePublicKeyStatic($recipientPubKey);

        if (\strlen($recipientPubBin) !== self::PUBKEY_LEN) {
            throw new RuntimeException(
                'Invalid recipient public key length: got '.\strlen($recipientPubBin).' bytes, expected '.self::PUBKEY_LEN.'.',
            );
        }

        $ephPubBin = '';
        $nonce = '';
        $ciphertext = '';

        try {
            $ephKeypair = \sodium_crypto_kx_keypair();
            $ephPubBin = \sodium_crypto_kx_publickey($ephKeypair);

            [$rx, $tx] = \sodium_crypto_kx_client_session_keys($ephKeypair, $recipientPubBin);
            \sodium_memzero($rx);

            $nonce = \random_bytes(self::NONCE_LEN);

            // V2: bind ephPubBin ใน effective AAD ป้องกัน key substitution attack
            $ciphertext = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $message,
                $aad.$ephPubBin,
                $nonce,
                $tx,
            );

            \sodium_memzero($tx);
            \sodium_memzero($ephKeypair);
        } catch (SodiumException $e) {
            throw new RuntimeException('encryptFor failed: '.$e->getMessage(), 0, $e);
        }

        return \implode(self::SEP, [
            self::PROTOCOL_VERSION_V2,
            \sodium_bin2hex($ephPubBin),
            \sodium_bin2hex($nonce),
            \sodium_bin2hex($ciphertext),
        ]);
    }

    /**
     * [helper] สร้าง bundle ตอบกลับฝั่งส่ง (bidirectional cross-host)
     *
     * @param  string  $message  reply message
     * @param  string  $senderPubKey  public key ของฝั่งที่ส่ง bundle มา
     * @param  string  $aad  context binding สำหรับ reply
     */
    public static function replyTo(
        string $message,
        string $senderPubKey,
        string $aad = '',
    ): string {
        return static::encryptFor($message, $senderPubKey, $aad);
    }

    /**
     * [helper] สร้าง Standard AAD สำหรับ Cross-host communication
     * เพื่อป้องกันการนำ bundle ไปใช้ผิด context (Replay/Context attack)
     *
     * @param  string  $senderId  ID ของผู้ส่ง
     * @param  string  $recipientId  ID ของผู้รับ
     * @param  string  $version  เวอร์ชันของโปรโตคอล (default: v2)
     * @return string 'sender:recipient:version'
     */
    public static function generateStandardAad(string $senderId, string $recipientId, string $version = 'v2'): string
    {
        return "{$senderId}:{$recipientId}:{$version}";
    }

    // ─────────────────────────────────────────────────────────
    //  SIGNED HYBRID MODE — Authentication + Confidentiality
    // ─────────────────────────────────────────────────────────

    /**
     * [ผู้ส่ง] เซ็น + เข้ารหัส message — Non-repudiation + Forward Secrecy (v2s)
     * ─────────────────────────────────────────────────────────
     * ขั้นตอน: Ed25519 sign(message) → prepend sig(64 bytes) → X25519/XChaCha20 encrypt
     *
     * BUNDLE FORMAT (v2s): v2s.ephem_pub_hex.nonce_hex.ciphertext_hex
     * ผู้รับต้องมี verify key (Ed25519 public key) ของผู้ส่งล่วงหน้า
     *
     * @param  string  $message  plaintext ที่ต้องการส่ง
     * @param  string  $recipientPubKey  X25519 public key ของผู้รับ (hex หรือ base64)
     * @param  string  $signingSecretKeyHex  Ed25519 secret key ของผู้ส่ง (hex 128 chars)
     * @param  string  $aad  context binding
     */
    public static function encryptForSigned(
        string $message,
        string $recipientPubKey,
        string $signingSecretKeyHex,
        string $aad = '',
    ): string {
        $recipientPubBin = self::resolvePublicKeyStatic($recipientPubKey);

        if (\strlen($recipientPubBin) !== self::PUBKEY_LEN) {
            throw new RuntimeException(
                'Invalid recipient public key length: expected '.self::PUBKEY_LEN.' bytes.',
            );
        }

        $ephPubBin = '';
        $nonce = '';
        $ciphertext = '';

        try {
            // 1. Sign message ด้วย Ed25519 signing key
            $signingKey = \sodium_hex2bin($signingSecretKeyHex);

            if (\strlen($signingKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                \sodium_memzero($signingKey);
                throw new InvalidArgumentException(
                    'Invalid signing key length: expected '.SODIUM_CRYPTO_SIGN_SECRETKEYBYTES.' bytes.',
                );
            }

            $sig = \sodium_crypto_sign_detached($message, $signingKey);
            \sodium_memzero($signingKey);

            // 2. Prepend signature: sig(64 bytes) || plaintext
            $signedPlaintext = $sig.$message;

            // 3. Encrypt ด้วย X25519 ephemeral + XChaCha20-Poly1305 (เหมือน v2)
            $ephKeypair = \sodium_crypto_kx_keypair();
            $ephPubBin = \sodium_crypto_kx_publickey($ephKeypair);

            [$rx, $tx] = \sodium_crypto_kx_client_session_keys($ephKeypair, $recipientPubBin);
            \sodium_memzero($rx);

            $nonce = \random_bytes(self::NONCE_LEN);
            $ciphertext = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $signedPlaintext,
                $aad.$ephPubBin,
                $nonce,
                $tx,
            );

            \sodium_memzero($tx);
            \sodium_memzero($ephKeypair);
        } catch (SodiumException $e) {
            throw new RuntimeException('encryptForSigned failed: '.$e->getMessage(), 0, $e);
        }

        return \implode(self::SEP, [
            self::PROTOCOL_VERSION_SIGNED,
            \sodium_bin2hex($ephPubBin),
            \sodium_bin2hex($nonce),
            \sodium_bin2hex($ciphertext),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  MULTI-RECIPIENT MODE — Group Messaging
    // ─────────────────────────────────────────────────────────

    /**
     * [ผู้ส่ง] เข้ารหัสครั้งเดียวสำหรับหลายผู้รับ — ประหยัด bandwidth
     * ─────────────────────────────────────────────────────────
     * session key สุ่มครั้งเดียว → sealed สำหรับแต่ละผู้รับด้วย X25519 box seal
     * ข้อมูลถูกเข้ารหัสเพียงครั้งเดียว — ผู้รับแต่ละคนถอดรหัส sealed key ของตัวเอง
     *
     * ENVELOPE FORMAT (JSON): {v:'mr1', r:{id:sealedKeyHex,...}, n:nonceHex, c:ciphertextHex}
     *
     * @param  string  $message  plaintext ที่ต้องการส่ง
     * @param  array<string,string>  $recipients  ['recipientId' => pubKeyHex/base64]
     * @param  string  $aad  context binding
     */
    public static function sealForMany(
        string $message,
        array $recipients,
        string $aad = '',
    ): string {
        if (empty($recipients)) {
            throw new InvalidArgumentException('ต้องระบุผู้รับอย่างน้อยหนึ่งคน');
        }

        $sessionKey = '';
        $sealedKeys = [];
        $nonce = '';
        $ciphertext = '';

        try {
            // 1. สร้าง one-time session key
            $sessionKey = \sodium_crypto_aead_xchacha20poly1305_ietf_keygen();

            // 2. Seal session key สำหรับแต่ละผู้รับด้วย X25519 anonymous seal
            //    KX public key (X25519) ใช้แทน box public key ได้เลย — key material เหมือนกัน
            foreach ($recipients as $id => $pubKey) {
                $pubKeyBin = self::resolvePublicKeyStatic($pubKey);

                if (\strlen($pubKeyBin) !== self::PUBKEY_LEN) {
                    throw new RuntimeException("Invalid public key length for recipient '{$id}'.");
                }

                $sealedKeys[(string) $id] = \sodium_bin2hex(
                    \sodium_crypto_box_seal($sessionKey, $pubKeyBin),
                );
            }

            // 3. เข้ารหัส message ด้วย session key (AEAD)
            $nonce = \random_bytes(self::NONCE_LEN);
            $ciphertext = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $message,
                $aad,
                $nonce,
                $sessionKey,
            );

            \sodium_memzero($sessionKey);
        } catch (SodiumException $e) {
            if ($sessionKey !== '') {
                \sodium_memzero($sessionKey);
            }
            throw new RuntimeException('sealForMany failed: '.$e->getMessage(), 0, $e);
        }

        try {
            return \json_encode([
                'v' => self::MULTI_RECIPIENT_VERSION,
                'r' => $sealedKeys,
                'n' => \sodium_bin2hex($nonce),
                'c' => \sodium_bin2hex($ciphertext),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new RuntimeException('sealForMany JSON encode failed: '.$e->getMessage(), 0, $e);
        }
    }

    // ─────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────

    /**
     * แปลง public key string → binary (static version สำหรับ encryptFor)
     * auto-detect: hex (ctype_xdigit) หรือ base64
     */
    private static function resolvePublicKeyStatic(string $key): string
    {
        if (\ctype_xdigit($key)) {
            return \sodium_hex2bin($key);
        }

        $bin = \base64_decode($key, strict: true);

        if ($bin === false) {
            throw new RuntimeException(
                'Invalid public key format. Expected hex (64 chars) or base64 (44 chars).',
            );
        }

        return $bin;
    }

    // ─────────────────────────────────────────────────────────
    //  DECRYPT METHODS (instance)
    // ─────────────────────────────────────────────────────────

    /**
     * [ผู้รับ] ถอดรหัส cross-host bundle — ใช้ static keypair ของ instance นี้
     * ─────────────────────────────────────────────────────────
     * รองรับทุก format อัตโนมัติ:
     *   legacy (3-part)  — ไม่มี version prefix
     *   v1    (4-part)   — ไม่ bind ephPk ใน AAD (backward compat)
     *   v2    (4-part)   — bind ephPk ใน AAD (แนะนำ, ปลอดภัยกว่า)
     *
     * @param  string  $bundle  output จาก encryptFor()
     * @param  string  $aad  ต้องตรงกับตอน encryptFor() ทุกตัวอักษร
     *
     * @throws RuntimeException ถ้า bundle format ผิด, tag ไม่ผ่าน, หรือ key ไม่ตรง
     */
    public function decryptMessage(string $bundle, string $aad = ''): string
    {
        $parts = \explode(self::SEP, $bundle);
        $count = \count($parts);

        $version = 'legacy';

        if ($count === 4 && $parts[0] === self::PROTOCOL_VERSION_V2) {
            // V2 format: v2.ephPub.nonce.cipher
            [, $ephPubHex, $nonceHex, $ciphertextHex] = $parts;
            $version = self::PROTOCOL_VERSION_V2;
        } elseif ($count === 4 && $parts[0] === self::PROTOCOL_VERSION) {
            // V1 format: v1.ephPub.nonce.cipher
            [, $ephPubHex, $nonceHex, $ciphertextHex] = $parts;
            $version = self::PROTOCOL_VERSION;
        } elseif ($count === 3) {
            // Legacy format: ephPub.nonce.cipher
            [$ephPubHex, $nonceHex, $ciphertextHex] = $parts;
        } else {
            throw new RuntimeException(
                'Invalid bundle: unrecognized format or version. Expected '.self::PROTOCOL_VERSION_V2.'.<ephem_pub>.<nonce>.<ciphertext>',
            );
        }

        foreach (['ephem_pub' => $ephPubHex, 'nonce' => $nonceHex, 'ciphertext' => $ciphertextHex] as $field => $hex) {
            if (empty($hex) || ! \ctype_xdigit($hex)) {
                throw new RuntimeException("Bundle field '{$field}' is not valid hex.");
            }
        }

        $ephPubBin = \sodium_hex2bin($ephPubHex);
        $nonce = \sodium_hex2bin($nonceHex);
        $ciphertextBin = \sodium_hex2bin($ciphertextHex);

        if (\strlen($ephPubBin) !== self::PUBKEY_LEN) {
            throw new RuntimeException('Invalid ephem_pub length: '.\strlen($ephPubBin).' bytes.');
        }

        if (\strlen($nonce) !== self::NONCE_LEN) {
            throw new RuntimeException('Invalid nonce length: '.\strlen($nonce).' bytes.');
        }

        if (\strlen($ciphertextBin) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new RuntimeException('Ciphertext too short — possibly truncated.');
        }

        try {
            [$rx, $tx] = \sodium_crypto_kx_server_session_keys($this->keypair, $ephPubBin);
            \sodium_memzero($tx);

            // V2: bind ephPubBin ใน effective AAD; v1/legacy: ใช้ aad ตรง ๆ
            $effectiveAad = ($version === self::PROTOCOL_VERSION_V2)
                ? $aad.$ephPubBin
                : $aad;

            $plaintext = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertextBin,
                $effectiveAad,
                $nonce,
                $rx,
            );

            \sodium_memzero($rx);
        } catch (SodiumException $e) {
            throw new RuntimeException('decryptMessage failed: '.$e->getMessage(), 0, $e);
        }

        if ($plaintext === false) {
            throw new RuntimeException(
                'Poly1305 authentication failed — bundle may be tampered, wrong keypair, or mismatched AAD.',
            );
        }

        return $plaintext;
    }

    /**
     * [ผู้รับ] ถอดรหัส + ยืนยันลายเซ็น Ed25519 จาก bundle v2s
     * ─────────────────────────────────────────────────────────
     * ขั้นตอน: decrypt → แยก sig(64 bytes) + plaintext → Ed25519 verify
     *
     * @param  string  $bundle  output จาก encryptForSigned()
     * @param  string  $senderVerifyKeyHex  Ed25519 public key ของผู้ส่ง (hex 64 chars)
     * @param  string  $aad  ต้องตรงกับตอน encryptForSigned() ทุกตัวอักษร
     *
     * @throws RuntimeException ถ้า bundle ผิดรูปแบบ, decrypt ล้มเหลว, หรือ signature ไม่ถูกต้อง
     */
    public function decryptMessageVerified(
        string $bundle,
        string $senderVerifyKeyHex,
        string $aad = '',
    ): string {
        $parts = \explode(self::SEP, $bundle);

        if (\count($parts) !== 4 || $parts[0] !== self::PROTOCOL_VERSION_SIGNED) {
            throw new RuntimeException(
                'Invalid signed bundle: expected '.self::PROTOCOL_VERSION_SIGNED.'.<ephem_pub>.<nonce>.<ciphertext>',
            );
        }

        [, $ephPubHex, $nonceHex, $ciphertextHex] = $parts;

        foreach (['ephem_pub' => $ephPubHex, 'nonce' => $nonceHex, 'ciphertext' => $ciphertextHex] as $field => $hex) {
            if (empty($hex) || ! \ctype_xdigit($hex)) {
                throw new RuntimeException("Bundle field '{$field}' is not valid hex.");
            }
        }

        $ephPubBin = \sodium_hex2bin($ephPubHex);
        $nonce = \sodium_hex2bin($nonceHex);
        $ciphertextBin = \sodium_hex2bin($ciphertextHex);

        if (\strlen($ephPubBin) !== self::PUBKEY_LEN) {
            throw new RuntimeException('Invalid ephem_pub length.');
        }

        if (\strlen($nonce) !== self::NONCE_LEN) {
            throw new RuntimeException('Invalid nonce length.');
        }

        if (\strlen($ciphertextBin) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new RuntimeException('Ciphertext too short.');
        }

        try {
            [$rx, $tx] = \sodium_crypto_kx_server_session_keys($this->keypair, $ephPubBin);
            \sodium_memzero($tx);

            $signedPlaintext = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertextBin,
                $aad.$ephPubBin,
                $nonce,
                $rx,
            );

            \sodium_memzero($rx);
        } catch (SodiumException $e) {
            throw new RuntimeException('decryptMessageVerified decrypt failed: '.$e->getMessage(), 0, $e);
        }

        if ($signedPlaintext === false) {
            throw new RuntimeException(
                'Authentication failed — bundle tampered, wrong keypair, or mismatched AAD.',
            );
        }

        // แยก sig(64 bytes Ed25519) + plaintext
        if (\strlen($signedPlaintext) <= SODIUM_CRYPTO_SIGN_BYTES) {
            throw new RuntimeException('Decrypted content too short — missing Ed25519 signature.');
        }

        $sig = \substr($signedPlaintext, 0, SODIUM_CRYPTO_SIGN_BYTES);
        $message = \substr($signedPlaintext, SODIUM_CRYPTO_SIGN_BYTES);

        try {
            $verifyKey = \sodium_hex2bin($senderVerifyKeyHex);

            if (\strlen($verifyKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                throw new InvalidArgumentException(
                    'Invalid verify key length: expected '.SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES.' bytes.',
                );
            }

            $valid = \sodium_crypto_sign_verify_detached($sig, $message, $verifyKey);
            \sodium_memzero($verifyKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('Ed25519 verify failed: '.$e->getMessage(), 0, $e);
        }

        if (! $valid) {
            throw new RuntimeException(
                'Ed25519 signature verification failed — sender identity not confirmed.',
            );
        }

        return $message;
    }

    /**
     * [ผู้รับ] ถอดรหัส multi-recipient envelope
     * ─────────────────────────────────────────────────────────
     * ผู้รับดึง sealed session key ของตัวเองออกจาก envelope แล้วถอดรหัส message
     * KX keypair ใช้เป็น box keypair ได้โดยตรง (X25519 key material เหมือนกัน)
     *
     * @param  string  $envelope  output จาก sealForMany()
     * @param  string  $recipientId  ID ที่ตรงกับ key ที่ส่งใน sealForMany()
     * @param  string  $aad  ต้องตรงกับตอน sealForMany() ทุกตัวอักษร
     */
    public function openMultiRecipient(
        string $envelope,
        string $recipientId,
        string $aad = '',
    ): string {
        try {
            $payload = \json_decode($envelope, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid envelope JSON: '.$e->getMessage(), 0, $e);
        }

        if (! isset($payload['v'], $payload['r'], $payload['n'], $payload['c'])) {
            throw new RuntimeException('Invalid multi-recipient envelope structure.');
        }

        if ($payload['v'] !== self::MULTI_RECIPIENT_VERSION) {
            throw new RuntimeException('Unsupported multi-recipient version: '.$payload['v']);
        }

        if (! isset($payload['r'][$recipientId])) {
            throw new RuntimeException("Recipient '{$recipientId}' not found in envelope.");
        }

        foreach (['nonce' => $payload['n'], 'ciphertext' => $payload['c']] as $field => $hex) {
            if (empty($hex) || ! \ctype_xdigit($hex)) {
                throw new RuntimeException("Envelope field '{$field}' is not valid hex.");
            }
        }

        $sessionKey = '';

        try {
            $sealedKeyBin = \sodium_hex2bin($payload['r'][$recipientId]);

            // สร้าง box keypair จาก KX keypair — X25519 key material เหมือนกันทุกประการ
            $kxSk = \sodium_crypto_kx_secretkey($this->keypair);
            $kxPk = \sodium_crypto_kx_publickey($this->keypair);
            $boxKp = \sodium_crypto_box_keypair_from_secretkey_and_publickey($kxSk, $kxPk);
            \sodium_memzero($kxSk);

            $sessionKey = \sodium_crypto_box_seal_open($sealedKeyBin, $boxKp);
            \sodium_memzero($boxKp);

            if ($sessionKey === false) {
                throw new RuntimeException(
                    'Cannot open sealed session key — wrong keypair or tampered envelope.',
                );
            }

            $nonce = \sodium_hex2bin($payload['n']);
            $ciphertextBin = \sodium_hex2bin($payload['c']);

            if (\strlen($nonce) !== self::NONCE_LEN) {
                throw new RuntimeException('Invalid nonce length in envelope.');
            }

            $plaintext = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertextBin,
                $aad,
                $nonce,
                $sessionKey,
            );

            \sodium_memzero($sessionKey);
        } catch (SodiumException $e) {
            if ($sessionKey !== '') {
                \sodium_memzero($sessionKey);
            }
            throw new RuntimeException('openMultiRecipient failed: '.$e->getMessage(), 0, $e);
        }

        if ($plaintext === false) {
            throw new RuntimeException(
                'Multi-recipient decrypt failed: authentication tag mismatch.',
            );
        }

        return $plaintext;
    }
}
