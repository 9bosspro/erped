<?php

declare(strict_types=1);

namespace Core\Base\Services\Imgproxy\Enums;

enum SourceUrlMode: string
{
    case Plain = 'plain';
    case Encoded = 'encoded';
}
