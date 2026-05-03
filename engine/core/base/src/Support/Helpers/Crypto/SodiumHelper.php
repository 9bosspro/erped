<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Support\Helpers\Crypto\Concerns\DataNormalization;
use Core\Base\Support\Helpers\Crypto\Concerns\HandlesAsymmetricEncryption;
use Core\Base\Support\Helpers\Crypto\Concerns\HandlesHashingKdf;
use Core\Base\Support\Helpers\Crypto\Concerns\HandlesKeyManagement;
use Core\Base\Support\Helpers\Crypto\Concerns\HandlesSignatures;
use Core\Base\Support\Helpers\Crypto\Concerns\HandlesStreamEncryption;
use Core\Base\Support\Helpers\Crypto\Concerns\HandlesSymmetricEncryption;
use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\Contracts\SodiumHelperInterface;
use Exception;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * SodiumHelper — ผู้ช่วยจัดการความปลอดภัยขั้นสูงด้วย Libsodium (Production-Grade)
 *
 * ใช้ PHP ext-sodium (libsodium) เป็น Backend หลัก — ประสิทธิภาพสูงสุด ความปลอดภัยระดับสูงสุด
 *
 * ═══════════════════════════════════════════════════════════════
 *  ความสามารถหลัก (Core Capabilities):
 * ═══════════════════════════════════════════════════════════════
 *  1.  Key Management          — HandlesKeyManagement
 *  2.  Symmetric Encryption    — HandlesSymmetricEncryption (XSalsa20-Poly1305 SecretBox)
 *  3.  AEAD                    — HandlesSymmetricEncryption (XChaCha20-Poly1305-IETF)
 *  4.  Asymmetric Encryption   — HandlesAsymmetricEncryption (Box & Seal X25519)
 *  5.  Digital Signatures      — HandlesSignatures (Ed25519 detached & multi-part)
 *  6.  Password Hashing        — (ใช้ ArgonLevel Enum + sodium_crypto_pwhash_str ตรงๆ)
 *  7.  File/Stream Encryption  — HandlesStreamEncryption (SecretStream XChaCha20-Poly1305)
 *  8.  Hybrid Stream Encryption— HandlesStreamEncryption (Envelope Encryption)
 *  9.  Stream Signatures       — HandlesSignatures (BLAKE2b digest + Ed25519)
 *  10. Hashing & KDF           — HandlesHashingKdf (BLAKE2b generic hash, KDF subkey)
 *  11. Key Exchange            — HandlesKeyManagement (X25519 Diffie-Hellman)
 *  12. Large String Helpers    — HandlesStreamEncryption (php://memory bridge)
 *
 * กุญแจทุกประเภทรับ/คืนเป็น Base64 (SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING)
 * ยกเว้นเมธอดที่มีชื่อลงท้ายว่า UrlSafe ซึ่งใช้ Base64url (ไม่มี padding)
 */
final class SodiumHelper implements SodiumHelperInterface
{
    use DataNormalization;
    use HandlesAsymmetricEncryption;
    use HandlesHashingKdf;
    use HandlesKeyManagement;
    use HandlesSignatures;
    use HandlesStreamEncryption;
    use HandlesSymmetricEncryption;
    use ParsesEncryptionKey;

    /** XChaCha20-Poly1305-IETF — แนะนำ: nonce 192-bit ไม่ซ้ำได้แม้สุ่ม */
    public const AEAD_XCHACHA20POLY1305_IETF = 'xchacha20poly1305_ietf';

    /** ChaCha20-Poly1305-IETF — nonce 96-bit (ต้องระวังการสุ่มซ้ำ) */
    public const AEAD_CHACHA20POLY1305_IETF = 'chacha20poly1305_ietf';

    /** AES-256-GCM — เร็วที่สุดบน CPU ที่มี AES-NI, ต้องตรวจ availability ก่อน */
    public const AEAD_AES256GCM = 'aes256gcm';

    /** ขนาด Chunk สำหรับ streaming (256 KB) — balance ระหว่าง throughput และ latency */
    protected const CHUNK_SIZE = 262144;

    /** Context เริ่มต้นสำหรับ BLAKE2b KDF (ต้องเป็น 8 bytes พอดี) */
    protected const KDF_DEFAULT_CONTEXT = 'app_kdf_';

    /** Version byte สำหรับ AEAD XChaCha20-Poly1305 payload (v2) */
    protected const string FORMAT_AEAD_V1 = "\x02";

    /**
     * Application Master Key — raw binary 32 bytes
     * nullable เพื่อให้ sodium_memzero() ใน __destruct set ค่าเป็น null ได้
     */
    private ?string $appKey = null;

    private $app_cipher = 'aes-256-gcm';

    // private const string DEFAULT_ALGO = 'sha3-256';
    //   protected static array $contextCache = [];

    /**
     * @param  string  $appKey  Base64-encoded key ขนาด SODIUM_CRYPTO_SECRETBOX_KEYBYTES (32 bytes)
     *                          ส่งเข้ามาจาก ServiceProvider ผ่าน config('core.base::security.key32')
     *
     * @throws InvalidArgumentException ถ้า key ไม่ถูกขนาดหรือ decode ล้มเหลว
     */
    public function __construct(string $appKey)
    {
        $parsed = $this->parseKey($appKey);
        // dd($parsed);

        if ($parsed === '') {
            throw new InvalidArgumentException('SodiumHelper: key ไม่ถูกต้องหรือว่าง — ตรวจสอบ config key32');
        }

        $this->appKey = $parsed;
        // dd($this->appKey);
    }

    /**
     * ล้างข้อมูลกุญแจออกจากหน่วยความจำเมื่อทำลาย Object
     */
    public function __destruct()
    {
        if ($this->appKey !== null) {
            \sodium_memzero($this->appKey);
        }
    }

    public function coreEncrypt(string $value, ?string $keys = null): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $encrypter = $this->getAppEncrypter($keys);

            return $encrypter->encryptString($value);
        } catch (Exception $e) {
            Log::error('Encryption failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * ฟังก์ชันถอดรหัส
     */
    public function coreDecrypt(string $payload, ?string $keys = null): ?string
    {
        if (empty($payload)) {
            return null;
        }

        try {
            $encrypter = $this->getAppEncrypter($keys);

            return $encrypter->decryptString($payload);
        } catch (Exception $e) {
            // จะทำงานเมื่อ Key ผิด หรือ ข้อมูลถูกแก้ไข (Tampered)
            Log::error('Decryption failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * เข้ารหัสแบบ Hybrid โดยใช้ X25519 Key Exchange สำหรับ Key Derivation
     *
     * การทำงาน:
     * 1. สร้าง ephemeral keypair (ผู้ส่ง)
     * 2. ทำ Diffie-Hellman เพื่อหา shared secret จาก public key ของผู้รับ
     * 3. ใช้ HKDF-SHA256 เพื่อ derive symmetric key (XChaCha20)
     * 4. เข้ารหัสข้อมูลด้วย XChaCha20-Poly1305
     * 5. รวม ephemeral public key + nonce + ciphertext เป็น payload
     *
     * @param  mixed  $plaintext  ข้อมูลที่ต้องการเข้ารหัส (จะถูก normalize เป็น JSON)
     * @param  string  $recipientPublicKey  Base64-encoded X25519 public key ของผู้รับ (32 bytes)
     * @param  bool  $useBinary  ถ้า true จะคืนค่า raw binary; ถ้า false จะคืนค่า Base64 (default)
     * @return string เข้ารหัสแล้วในรูปแบบ Base64 (หรือ binary ถ้า $useBinary = true)
     *
     * @throws InvalidArgumentException ถ้าrecipientPublicKey ไม่ใช่ 32 bytes
     * @throws RuntimeException ถ้าขั้นตอนการเข้ารหัสล้มเหลว
     *
     * @example
     * ```php
     * $ciphertext = $sodium->hybridEncrypt(
     *     ['message' => 'Hello, world!'],
     *     $recipientPublicKeyBase64
     * );
     * ```
     */
    public function hybridEncrypt(
        mixed $plaintext,
        string $recipientPublicKey, // base64 x25519 publickey ของผู้รับ
        bool $useBinary = false,
        //    bool $useSign = false,
    ): string {
        $plaintext = $this->normalizeData($plaintext);
        $recipientPublicKey = $this->decodeKey($recipientPublicKey);
        // ephemeral keypair
        $ephemeralKeypair = sodium_crypto_box_keypair();

        $ephemeralSecret =
            sodium_crypto_box_secretkey(
                $ephemeralKeypair,
            );

        $ephemeralPublic =
            sodium_crypto_box_publickey(
                $ephemeralKeypair,
            );

        // shared key
        $sharedKey =
            sodium_crypto_scalarmult(
                $ephemeralSecret,
                $recipientPublicKey,
            );

        // derive symmetric key
        $symmetricKey = $this->genHashByName('HYBRID-KDF-v1', $sharedKey, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES); // คีสุ่ม

        // nonce
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        // encrypt
        // return $this->encryptAead($plaintext, $ephemeralPublic, $nonce, $symmetricKey, true);
        $ciphertext =
            sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $plaintext,
                $ephemeralPublic, // '', // $ephemeralPublic, // '',
                $nonce,
                $symmetricKey,
            );
        \sodium_memzero($ephemeralSecret);
        \sodium_memzero($sharedKey);
        \sodium_memzero($symmetricKey);
        \sodium_memzero($ephemeralKeypair);

        // output
        return $this->encodeKey(
            $ephemeralPublic.
                $nonce.
                $ciphertext,
            $useBinary,
        );
    }

    //  การทำงานของ hybridDecrypt
    /**
     * ถอดรหัสแบบ Hybrid โดยใช้ X25519 Key Exchange
     *
     * การทำงาน:
     * 1. แยก ephemeral public key, nonce, และ ciphertext ออกจาก payload
     * 2. ทำ Diffie-Hellman เพื่อหา shared secret จาก ephemeral public key และ recipient secret key
     * 3. ใช้ HKDF-SHA256 เพื่อ derive symmetric key เดียวกันกับที่ใช้เข้ารหัส
     * 4. ถอดรหัส ciphertext ด้วย XChaCha20-Poly1305
     * 5. normalize และ return plaintext
     *
     * @param  string  $payload  เข้ารหัสแล้วในรูปแบบ Base64 (ephemeralPublic + nonce + ciphertext)
     * @param  string  $recipientSecretKey  Base64-encoded X25519 secret key ของผู้รับ (64 bytes)
     * @return mixed ข้อมูลที่ถอดรหัสแล้ว (จะถูก normalize เป็น JSON ถ้ามี prefix)
     *
     * @throws InvalidArgumentException ถ้า key ไม่ถูกต้องหรือ payload สั้นเกินไป
     * @throws RuntimeException ถ้าการถอดรหัสหรือการตรวจสอบความสมบูรณ์ล้มเหลว
     *
     * @example
     * ```php
     * $plaintext = $sodium->hybridDecrypt($ciphertext, $recipientSecretKeyBase64);
     * // $plaintext อาจจะเป็น string หรือ array ขึ้นอยู่กับ original data type
     * ```
     */
    public function hybridDecrypt(
        string $payload,
        string $recipientSecretKey,
    ): mixed {
        $recipientSecretKey = $this->decodeKey($recipientSecretKey);
        //
        if (mb_strlen($recipientSecretKey, '8bit') !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
            throw new InvalidArgumentException('Invalid secret key length');
        }

        $decoded = $this->decodeKey($payload);
        if ($decoded === false) {
            throw new RuntimeException(
                'Invalid payload',
            );
        }

        $minLength =
            SODIUM_CRYPTO_BOX_PUBLICKEYBYTES +
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        if (strlen($decoded) < $minLength) {
            throw new RuntimeException(
                'Payload too short',
            );
        }

        $offset = 0;

        // extract parts

        $ephemeralPublic =
            substr(
                $decoded,
                $offset,
                SODIUM_CRYPTO_BOX_PUBLICKEYBYTES,
            );

        $offset +=
            SODIUM_CRYPTO_BOX_PUBLICKEYBYTES;

        $nonce =
            substr(
                $decoded,
                $offset,
                SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES,
            );

        $offset +=
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        $ciphertext =
            substr(
                $decoded,
                $offset,
            );

        // shared key

        $sharedKey =
            sodium_crypto_scalarmult(
                $recipientSecretKey,
                $ephemeralPublic,
            );
        //
        $symmetricKey = $this->genHashByName('HYBRID-KDF-v1', $sharedKey, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES); // คีสุ่ม

        // decrypt

        $plaintext =
            sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                $ephemeralPublic,
                $nonce,
                $symmetricKey,
            );

        if ($plaintext === false) {
            throw new RuntimeException(
                'Decryption failed',
            );
        }
        sodium_memzero($sharedKey);
        sodium_memzero($symmetricKey);

        //  sodium_memzero($plaintext);
        return $this->deserializeData($plaintext);
    }

    protected function getAppKey(): string
    {
        return $this->appKey ?? throw new RuntimeException('SodiumHelper: appKey ถูก zeroed แล้ว');
    }

    private function getAppEncrypter(?string $customKey = null): Encrypter
    {
        $customKey = $customKey ?? $this->appKey;

        if ($customKey === null || \strlen($customKey) !== 32) {
            throw new Exception('Key must be exactly 32 characters for AES-256-GCM.');
        }

        return new Encrypter($customKey, $this->app_cipher);
    }
}
