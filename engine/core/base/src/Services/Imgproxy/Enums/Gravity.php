<?php

declare(strict_types=1);

namespace Core\Base\Services\Imgproxy\Enums;

enum Gravity: string
{
    case Center = 'ce';
    case North = 'no';
    case South = 'so';
    case East = 'ea';
    case West = 'we';
    case NorthEast = 'noea';
    case NorthWest = 'nowe';
    case SouthEast = 'soea';
    case SouthWest = 'sowe';
    case Smart = 'sm';
    case FocusPoint = 'fp';
}
