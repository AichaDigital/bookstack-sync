<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Enums;

enum SyncDirection: string
{
    case PUSH = 'push';
    case PULL = 'pull';
    case BIDIRECTIONAL = 'bidirectional';
}
