<?php

declare(strict_types=1);

namespace Core\Base\Services\Imgproxy;

use Core\Base\Services\Imgproxy\Contracts\ImgproxyServiceInterface;
use Core\Base\Services\Imgproxy\Enums\Gravity;
use Core\Base\Services\Imgproxy\Enums\OutputFormat;
use Core\Base\Services\Imgproxy\Enums\ResizeType;
use Core\Base\Services\Imgproxy\Enums\SourceUrlMode;
use InvalidArgumentException;

/**
 * Fluent builder สำหรับประกอบ imgproxy URL ทีละ option
 *
 * Instance ใหม่ทุกครั้งที่เรียก ImgproxyService::url()
 * ไม่ควร reuse — สร้างใหม่ต่อ URL เพื่อป้องกัน state ปนกัน
 *
 * Usage:
 *   app(ImgproxyServiceInterface::class)
 *       ->url('https://bucket.s3.amazonaws.com/photo.jpg')
 *       ->resize(ResizeType::Fill, 800, 600)
 *       ->quality(80)
 *       ->format(OutputFormat::Webp)
 *       ->build();
 */
final class ImgproxyUrlBuilder
{
    /** @var string[] Processing option segments */
    private array $parts = [];

    private ?OutputFormat $outputFormat = null;

    private bool $forceUnsafe = false;

    private bool $enlarge = false;

    public function __construct(
        private readonly ImgproxyServiceInterface $service,
        private readonly string $sourceUrl,
        private readonly SourceUrlMode $sourceMode,
        private readonly ?OutputFormat $defaultFormat,
    ) {}

    // =========================================================================
    // Processing Options
    // =========================================================================

    public function resize(ResizeType $type, int $width = 0, int $height = 0): self
    {
        $enlarge = $this->enlarge ? 1 : 0;
        $this->parts[] = "rs:{$type->value}:{$width}:{$height}:{$enlarge}";

        return $this;
    }

    public function width(int $width): self
    {
        $this->parts[] = "w:{$width}";

        return $this;
    }

    public function height(int $height): self
    {
        $this->parts[] = "h:{$height}";

        return $this;
    }

    public function quality(int $quality): self
    {
        if ($quality < 1 || $quality > 100) {
            throw new InvalidArgumentException('Quality must be between 1 and 100.');
        }
        $this->parts[] = "q:{$quality}";

        return $this;
    }

    public function format(OutputFormat $format): self
    {
        $this->outputFormat = $format;

        return $this;
    }

    public function blur(float $sigma): self
    {
        if ($sigma > 0) {
            $this->parts[] = "bl:{$sigma}";
        }

        return $this;
    }

    public function sharpen(float $sigma): self
    {
        if ($sigma > 0) {
            $this->parts[] = "sh:{$sigma}";
        }

        return $this;
    }

    public function watermark(
        float $opacity = 0.5,
        Gravity $position = Gravity::Center,
        int $xOffset = 0,
        int $yOffset = 0,
        float $scale = 0,
    ): self {
        $this->parts[] = sprintf(
            'wm:%.2f:%s:%d:%d:%.2f',
            $opacity,
            $position->value,
            $xOffset,
            $yOffset,
            $scale,
        );

        return $this;
    }

    public function gravity(Gravity $type, float $xOffset = 0, float $yOffset = 0): self
    {
        $this->parts[] = "g:{$type->value}:{$xOffset}:{$yOffset}";

        return $this;
    }

    public function crop(int $width, int $height, Gravity $gravity = Gravity::Center): self
    {
        $this->parts[] = "c:{$width}:{$height}:{$gravity->value}";

        return $this;
    }

    public function enlarge(bool $enlarge = true): self
    {
        $this->enlarge = $enlarge;

        return $this;
    }

    public function rotate(int $angle): self
    {
        if ($angle % 90 !== 0) {
            throw new InvalidArgumentException('Rotate angle must be a multiple of 90.');
        }
        $this->parts[] = "rt:{$angle}";

        return $this;
    }

    public function trim(int $threshold, string $color = '', bool $equalHor = false, bool $equalVer = false): self
    {
        $parts = ["t:{$threshold}"];
        if ($color !== '') {
            $parts[0] .= ":{$color}";
        }
        if ($equalHor) {
            $parts[0] .= ':1';
        }
        if ($equalVer) {
            $parts[0] .= ':1';
        }
        $this->parts[] = $parts[0];

        return $this;
    }

    public function padding(int $top, int $right = 0, int $bottom = 0, int $left = 0): self
    {
        $this->parts[] = "pd:{$top}:{$right}:{$bottom}:{$left}";

        return $this;
    }

    public function dpr(float $ratio): self
    {
        if ($ratio <= 0) {
            throw new InvalidArgumentException('DPR ratio must be greater than 0.');
        }
        $this->parts[] = "dpr:{$ratio}";

        return $this;
    }

    /**
     * เพิ่ม raw processing option โดยตรง
     *
     * ใช้สำหรับ option ที่ builder ยังไม่รองรับ
     * เช่น raw('bg:ff00ff') หรือ raw('strip_metadata:1')
     */
    public function raw(string $option): self
    {
        if (trim($option) === '') {
            throw new InvalidArgumentException('Raw option must not be empty.');
        }
        $this->parts[] = $option;

        return $this;
    }

    public function unsafe(bool $unsafe = true): self
    {
        $this->forceUnsafe = $unsafe;

        return $this;
    }

    // =========================================================================
    // Build
    // =========================================================================

    /**
     * ประกอบ imgproxy URL จาก builder state ทั้งหมด
     *
     * ลำดับ: /{signature|unsafe}/{processing_options}/{source_path}
     */
    public function build(): string
    {
        $format = $this->outputFormat ?? $this->defaultFormat;

        $optionsPath = $this->parts !== [] ? '/'.implode('/', $this->parts) : '';

        $sourcePath = $this->buildSourcePath($format);

        $path = $optionsPath.$sourcePath;

        if (! $this->forceUnsafe && $this->service->isSigningEnabled()) {
            $signature = $this->service->sign($path);
            $path = '/'.$signature.$path;
        } else {
            $path = '/unsafe'.$path;
        }

        return $this->service->getEndpoint().$path;
    }

    // =========================================================================
    // Private
    // =========================================================================

    private function buildSourcePath(?OutputFormat $format): string
    {
        if ($this->sourceMode === SourceUrlMode::Plain) {
            $encoded = urlencode($this->sourceUrl);
            $suffix = $format !== null ? "@{$format->value}" : '';

            return "/plain/{$encoded}{$suffix}";
        }

        // encoded mode — base64url (RFC 4648 Section 5)
        $base64 = rtrim(strtr(base64_encode($this->sourceUrl), '+/', '-_'), '=');
        $extension = $format !== null ? ".{$format->value}" : '';

        return "/{$base64}{$extension}";
    }

    public function __toString(): string
    {
        return $this->build();
    }
}
