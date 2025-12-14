<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Enums;

enum EntityType: string
{
    case SHELF = 'bookshelf';
    case BOOK = 'book';
    case CHAPTER = 'chapter';
    case PAGE = 'page';

    public function apiEndpoint(): string
    {
        return match ($this) {
            self::SHELF => 'shelves',
            self::BOOK => 'books',
            self::CHAPTER => 'chapters',
            self::PAGE => 'pages',
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::SHELF => 'shelf',
            self::BOOK => 'book',
            self::CHAPTER => 'chapter',
            self::PAGE => 'page',
        };
    }
}
