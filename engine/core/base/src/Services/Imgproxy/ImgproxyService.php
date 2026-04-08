<?php

declare(strict_types=1);

namespace Core\Base\Services\Imgproxy;

use Core\Base\Services\Imgproxy\Contracts\ImgproxyServiceInterface;
use Core\Base\Services\Imgproxy\Enums\OutputFormat;
use Core\Base\Services\Imgproxy\Enums\SourceUrlMode;
use InvalidArgumentException;
use RuntimeException;

/**
 * ImgproxyService — Singleton service สำหรับสร้าง imgproxy URL
 *
 * หน้าที่:
 *  - เก็บ config (endpoint, key, salt, defaults) — อ่านครั้งเดียวตอน construct
 *  - Factory สำหรับสร้าง ImgproxyUrlBuilder instance ใหม่ต่อ URL
 *  - HMAC-SHA256 signing
 *
 * Thread-safe: ไม่มี mutable state — builder state อยู่ใน ImgproxyUrlBuilder
 *
 * Usage:
 *   // Fluent builder (แนะนำ)
 *   app(ImgproxyServiceInterface::class)
 *       ->url('https://bucket.s3.amazonaws.com/photo.jpg')
 *       ->resize(ResizeType::Fill, 800, 600)
 *       ->quality(80)
 *       ->format(OutputFormat::Webp)
 *       ->build();
 *
 *   // One-shot shortcut
 *   ImgproxyService::make('https://...', ['resize' => ['type' => 'fill', 'width' => 800]]);
 */
class ImgproxyService implements ImgproxyServiceInterface
{
    private readonly string $endpoint;

    private readonly string $key;

    private readonly string $salt;

    private readonly SourceUrlMode $defaultMode;

    private readonly ?OutputFormat $defaultFormat;

    public function __construct()
    {
        $this->endpoint = rtrim((string) config('imgproxy.endpoint', 'http://localhost:8080'), '/');

        $this->defaultMode = SourceUrlMode::tryFrom(
            (string) config('imgproxy.default_source_url_mode', 'encoded'),
        ) ?? SourceUrlMode::Encoded;

        $defaultExt = (string) config('imgproxy.default_output_extension', '');
        $this->defaultFormat = $defaultExt !== '' ? OutputFormat::tryFrom($defaultExt) : null;

        // Decode hex key/salt — ตรวจ validity ก่อน hex2bin เพื่อป้องกัน warning
        $rawKey = trim((string) config('imgproxy.key', ''));
        $rawSalt = trim((string) config('imgproxy.salt', ''));

        $this->key = self::decodeHex($rawKey);
        $this->salt = self::decodeHex($rawSalt);
    }

    // =========================================================================
    // Static shortcut (backwards-compatible)
    // =========================================================================

    /**
     * One-shot URL generation จาก array options
     *
     * เหมาะสำหรับ migration จากโค้ดเดิมที่ส่ง options เป็น array
     *
     * @param  array{
     *     resize?: array{type?: string, width?: int, height?: int, enlarge?: bool},
     *     quality?: int,
     *     format?: string,
     *     blur?: float,
     *     sharpen?: float,
     *     watermark?: array{opacity?: float, position?: string, x_offset?: int, y_offset?: int, scale?: float},
     * }  $options
     */
    public static function make(string $sourceUrl, array $options = [], bool $unsafe = false): string
    {
        /** @var self $service */
        $service = app(ImgproxyServiceInterface::class);
        $builder = $service->url($sourceUrl);

        if (isset($options['resize'])) {
            $r = $options['resize'];
            if (! empty($r['enlarge'])) {
                $builder->enlarge();
            }
            $type = Enums\ResizeType::tryFrom($r['type'] ?? 'fit') ?? Enums\ResizeType::Fit;
            $builder->resize($type, (int) ($r['width'] ?? 0), (int) ($r['height'] ?? 0));
        }

        if (isset($options['quality'])) {
            $builder->quality((int) $options['quality']);
        }

        if (isset($options['format'])) {
            $format = OutputFormat::tryFrom((string) $options['format']);
            if ($format !== null) {
                $builder->format($format);
            }
        }

        if (isset($options['blur'])) {
            $builder->blur((float) $options['blur']);
        }

        if (isset($options['sharpen'])) {
            $builder->sharpen((float) $options['sharpen']);
        }

        if (isset($options['watermark'])) {
            $wm = $options['watermark'];
            $gravity = Enums\Gravity::tryFrom($wm['position'] ?? 'ce') ?? Enums\Gravity::Center;
            $builder->watermark(
                opacity: (float) ($wm['opacity'] ?? 0.5),
                position: $gravity,
                xOffset: (int) ($wm['x_offset'] ?? 0),
                yOffset: (int) ($wm['y_offset'] ?? 0),
                scale: (float) ($wm['scale'] ?? 0),
            );
        }

        return $builder->unsafe($unsafe)->build();
    }

    // =========================================================================
    // Private
    // =========================================================================

    /**
     * Decode hex string อย่างปลอดภัย — คืน empty string ถ้า input ไม่ใช่ valid hex
     */
    private static function decodeHex(string $hex): string
    {
        if ($hex === '' || ! ctype_xdigit($hex) || strlen($hex) % 2 !== 0) {
            return '';
        }

        return (string) hex2bin($hex);
    }

    // =========================================================================
    // ImgproxyServiceInterface
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function url(string $sourceUrl): ImgproxyUrlBuilder
    {
        if (trim($sourceUrl) === '') {
            throw new InvalidArgumentException('Source URL must not be empty.');
        }

        return new ImgproxyUrlBuilder(
            service: $this,
            sourceUrl: $sourceUrl,
            sourceMode: $this->defaultMode,
            defaultFormat: $this->defaultFormat,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function sign(string $path): string
    {
        if (! $this->isSigningEnabled()) {
            throw new RuntimeException('Cannot sign: IMGPROXY_KEY and IMGPROXY_SALT must be configured.');
        }

        $digest = hash_hmac('sha256', $this->salt.$path, $this->key, true);

        return rtrim(strtr(base64_encode($digest), '+/', '-_'), '=');
    }

    /**
     * {@inheritDoc}
     */
    public function isSigningEnabled(): bool
    {
        return $this->key !== '' && $this->salt !== '';
    }

    /**
     * {@inheritDoc}
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
}
