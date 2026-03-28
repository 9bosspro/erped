<?php

declare(strict_types=1);

namespace Core\Base\Services\Imgproxy\Enums;

enum OutputFormat: string
{
    case Jpeg = 'jpeg';
    case Jpg = 'jpg';
    case Png = 'png';
    case Webp = 'webp';
    case Avif = 'avif';
    case Gif = 'gif';
    case Ico = 'ico';
    case Bmp = 'bmp';
    case Tiff = 'tiff';
}
