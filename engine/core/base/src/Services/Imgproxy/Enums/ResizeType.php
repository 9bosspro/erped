<?php

declare(strict_types=1);

namespace Core\Base\Services\Imgproxy\Enums;

enum ResizeType: string
{
    case Fit = 'fit';
    case Fill = 'fill';
    case FillDown = 'fill-down';
    case Force = 'force';
    case Auto = 'auto';
}
