<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use InvalidArgumentException;
use RuntimeException;

/**
 * HandlesStreamEncryption — File/Stream Encryption + Hybrid Envelope + Large String
 *
 * ครอบคลุม:
 *  - encryptFile / decryptFile                  (File-based streaming)
 *  - encryptStream / decryptStream              (Resource handle streaming)
 *  - sealStream / openSealedStream              (Hybrid envelope encryption)
 *  - encryptHugeString / decryptHugeString      (php://memory bridge)
 *
 * RAM คงที่ = CHUNK_SIZE ไม่ว่าไฟล์จะใหญ่แค่ไหน
 */
trait HandlesStreamEncryption
{
    /**
     * เข้ารหัสไฟล์
     */
    public function encryptFile(string $sourcePath, string $destPath, ?string $keyBase64 = null): void
    {
        $key = $this->resolveKey($keyBase64);
        $in = $this->openFile($sourcePath, 'rb');
        $out = $this->openFile($destPath, 'wb');
        try {
            $this->encryptStreamRaw($in, $out, $key);
        } finally {
            \fclose($in);
            \fclose($out);
        }
    }

    /**
     * ถอดรหัสไฟล์
     */
    public function decryptFile(string $sourcePath, string $destPath, ?string $keyBase64 = null): void
    {
        $key = $this->resolveKey($keyBase64);
        $in = $this->openFile($sourcePath, 'rb');
        $out = $this->openFile($destPath, 'wb');
        try {
            $this->decryptStreamRaw($in, $out, $key);
        } finally {
            \fclose($in);
            \fclose($out);
        }
    }

    /**
     * Encrypt Stream (open handles) with Base64 key
     *
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function encryptStream($inputStream, $outputStream, string $keyBase64): void
    {
        $this->encryptStreamRaw($inputStream, $outputStream, $this->decodeKey($keyBase64));
    }

    /**
     * Decrypt Stream (open handles) with Base64 key
     *
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function decryptStream($inputStream, $outputStream, string $keyBase64): void
    {
        $this->decryptStreamRaw($inputStream, $outputStream, $this->decodeKey($keyBase64));
    }

    /**
     * ปิดผนึก Stream ด้วย Public Key ของผู้รับ
     * Format: pack('v', sealedKeyLen) + sealedKey + secretstream
     *
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function sealStream($inputStream, $outputStream, string $recipientPublicKey): void
    {
        $sessionKey = \sodium_crypto_secretstream_xchacha20poly1305_keygen();
        $pk = self::resolveKey($recipientPublicKey);
        if ($pk === null) {
            throw new InvalidArgumentException('sealStream: recipient public key base64 decode ล้มเหลว');
        }
        $sealedKey = \sodium_crypto_box_seal($sessionKey, $pk);

        \fwrite($outputStream, \pack('v', \strlen($sealedKey)));
        \fwrite($outputStream, $sealedKey);

        $this->encryptStreamRaw($inputStream, $outputStream, $sessionKey);
        \sodium_memzero($sessionKey);
    }

    /**
     * เปิดผนึก Stream ด้วย Secret Key ของผู้รับ
     *
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function openSealedStream(
        $inputStream,
        $outputStream,
        string $recipientPublicKey,
        string $recipientSecretKey,
    ): void {
        $sizeData = \fread($inputStream, 2);
        if ($sizeData === false || \strlen($sizeData) !== 2) {
            throw new RuntimeException('Stream ผิดรูปแบบ: ไม่มี envelope header');
        }
        $sealedKeySize = \unpack('v', $sizeData)[1];

        $sealedKey = \fread($inputStream, $sealedKeySize);
        if ($sealedKey === false || \strlen($sealedKey) !== $sealedKeySize) {
            throw new RuntimeException('Stream ผิดรูปแบบ: envelope ไม่ครบ');
        }

        $pk = $this->resolveKey($recipientPublicKey);
        $sk = $this->resolveKey($recipientSecretKey);

        if ($pk === null || $sk === null) {
            throw new InvalidArgumentException('openSealedStream: recipient key base64 decode ล้มเหลว — ตรวจสอบ key format');
        }

        $kp = \sodium_crypto_box_keypair_from_secretkey_and_publickey($sk, $pk);
        \sodium_memzero($sk);

        $sessionKey = \sodium_crypto_box_seal_open($sealedKey, $kp);
        \sodium_memzero($kp);

        if ($sessionKey === false) {
            throw new RuntimeException('ไม่สามารถเปิด envelope ได้: กุญแจไม่ถูกต้องหรือข้อมูลถูกดัดแปลง');
        }

        $this->decryptStreamRaw($inputStream, $outputStream, $sessionKey);
        \sodium_memzero($sessionKey);
    }

    /**
     * เข้ารหัส String ขนาดใหญ่ผ่าน temp stream — คืน Base64
     */
    public function encryptHugeString(string $hugeText, string $keyBase64, bool $useBinary = false): string
    {
        $key = $this->resolveKey($keyBase64, 32);
        if ($key === null) {
            throw new RuntimeException('encryptHugeString: key decoding failed');
        }
        $in = \fopen('php://memory', 'r+b');
        $out = \fopen('php://memory', 'r+b');
        try {
            \fwrite($in, $hugeText);
            \rewind($in);
            $this->encryptStreamRaw($in, $out, $key);
            \rewind($out);

            return self::encodeKey(\stream_get_contents($out), $useBinary);
        } finally {
            \fclose($in);
            \fclose($out);
        }
    }

    /**
     * ถอดรหัส String ขนาดใหญ่จาก Base64
     */
    public function decryptHugeString(string $encryptedBase64, string $keyBase64): string
    {
        $key = $this->resolveKey($keyBase64);
        $payload = $this->resolveKey($encryptedBase64);
        if ($key === null || $payload === null) {
            throw new RuntimeException('decryptHugeString: decoding failed');
        }
        $in = \fopen('php://memory', 'r+b');
        $out = \fopen('php://memory', 'r+b');
        try {
            \fwrite($in, $payload);
            \rewind($in);
            $this->decryptStreamRaw($in, $out, $key);
            \rewind($out);

            return \stream_get_contents($out);
        } finally {
            \fclose($in);
            \fclose($out);
        }
    }

    /**
     * @param  resource  $in
     * @param  resource  $out
     */
    private function encryptStreamRaw($in, $out, string $rawKey): void
    {
        [$state, $header] = \sodium_crypto_secretstream_xchacha20poly1305_init_push($rawKey);
        \fwrite($out, $header);

        while (! \feof($in)) {
            $chunk = \fread($in, self::CHUNK_SIZE);
            if ($chunk === false || $chunk === '') {
                break;
            }
            \fwrite($out, \sodium_crypto_secretstream_xchacha20poly1305_push(
                $state,
                $chunk,
                '',
                SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
            ));
        }

        \fwrite($out, \sodium_crypto_secretstream_xchacha20poly1305_push(
            $state,
            '',
            '',
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL,
        ));
    }

    /**
     * @param  resource  $in
     * @param  resource  $out
     */
    private function decryptStreamRaw($in, $out, string $rawKey): void
    {
        $header = \fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if ($header === false || \strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
            throw new RuntimeException('Stream ผิดรูปแบบ หรือไม่มี Header');
        }

        $state = \sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $rawKey);
        $chunkSize = self::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

        while (! \feof($in)) {
            $chunk = \fread($in, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $result = \sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
            if ($result === false) {
                throw new RuntimeException('ถอดรหัส Chunk ล้มเหลว: ข้อมูลถูกดัดแปลง');
            }
            [$decrypted, $tag] = $result;
            if ($decrypted !== '') {
                \fwrite($out, $decrypted);
            }
            if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                break;
            }
        }
    }

    /**
     * @return resource
     */
    private function openFile(string $path, string $mode)
    {
        if ($mode === 'rb' && ! \is_readable($path)) {
            throw new RuntimeException("ไม่อาจอ่านไฟล์: {$path}");
        }
        $stream = \fopen($path, $mode);
        if ($stream === false) {
            throw new RuntimeException("เปิดไฟล์ล้มเหลว: {$path}");
        }

        return $stream;
    }
}
