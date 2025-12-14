<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Enums;

enum ConflictResolution: string
{
    case LOCAL = 'local';
    case REMOTE = 'remote';
    case NEWEST = 'newest';
    case MANUAL = 'manual';
}
