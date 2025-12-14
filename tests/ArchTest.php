<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('strict types are used in all files')
    ->expect('AichaDigital\BookStackSync')
    ->toUseStrictTypes();

arch('enums are backed by string or int')
    ->expect('AichaDigital\BookStackSync\Enums')
    ->toBeEnums();

arch('exceptions extend base exception')
    ->expect('AichaDigital\BookStackSync\Exceptions')
    ->toExtend(Exception::class);

arch('commands extend Illuminate Command')
    ->expect('AichaDigital\BookStackSync\Commands')
    ->toExtend(Illuminate\Console\Command::class);
