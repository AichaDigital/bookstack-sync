<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\ChapterDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\DTOs\SearchResultDTO;
use AichaDigital\BookStackSync\DTOs\ShelfDTO;
use AichaDigital\BookStackSync\Enums\EntityType;

describe('ShelfDTO', function () {
    it('creates from array', function () {
        $data = [
            'id' => 1,
            'name' => 'Mi Estantería',
            'slug' => 'mi-estanteria',
            'description' => 'Descripción con acentos: á é í ó ú ñ',
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-02T00:00:00Z',
        ];

        $shelf = ShelfDTO::fromArray($data);

        expect($shelf->id)->toBe(1);
        expect($shelf->name)->toBe('Mi Estantería');
        expect($shelf->slug)->toBe('mi-estanteria');
        expect($shelf->description)->toContain('ñ');
    });

    it('generates correct URL', function () {
        $shelf = ShelfDTO::fromArray(['slug' => 'mi-estantería']);
        $url = $shelf->getUrl('https://wiki.example.com');

        expect($url)->toBe('https://wiki.example.com/shelves/mi-estanter%C3%ADa');
    });

    it('includes nested books', function () {
        $data = [
            'id' => 1,
            'name' => 'Shelf',
            'books' => [
                ['id' => 1, 'name' => 'Book 1'],
                ['id' => 2, 'name' => 'Book 2'],
            ],
        ];

        $shelf = ShelfDTO::fromArray($data);

        expect($shelf->books)->toHaveCount(2);
        expect($shelf->books[0])->toBeInstanceOf(BookDTO::class);
    });
});

describe('BookDTO', function () {
    it('creates from array', function () {
        $data = [
            'id' => 1,
            'name' => 'Libro de Configuración',
            'slug' => 'libro-configuracion',
            'description' => 'Manual técnico',
        ];

        $book = BookDTO::fromArray($data);

        expect($book->id)->toBe(1);
        expect($book->name)->toBe('Libro de Configuración');
    });

    it('generates correct URL', function () {
        $book = BookDTO::fromArray(['slug' => 'configuración-básica']);
        $url = $book->getUrl('https://wiki.example.com');

        expect($url)->toBe('https://wiki.example.com/books/configuraci%C3%B3n-b%C3%A1sica');
    });

    it('parses contents with chapters and pages', function () {
        $data = [
            'id' => 1,
            'name' => 'Book',
            'contents' => [
                ['type' => 'chapter', 'id' => 1, 'name' => 'Chapter 1'],
                ['type' => 'page', 'id' => 2, 'name' => 'Page 1'],
            ],
        ];

        $book = BookDTO::fromArray($data);

        expect($book->contents)->toHaveCount(2);
        expect($book->contents[0])->toBeInstanceOf(ChapterDTO::class);
        expect($book->contents[1])->toBeInstanceOf(PageDTO::class);
    });
});

describe('ChapterDTO', function () {
    it('creates from array', function () {
        $data = [
            'id' => 1,
            'name' => 'Capítulo Introductorio',
            'slug' => 'capitulo-introductorio',
            'book_id' => 5,
            'book_slug' => 'mi-libro',
        ];

        $chapter = ChapterDTO::fromArray($data);

        expect($chapter->id)->toBe(1);
        expect($chapter->name)->toBe('Capítulo Introductorio');
        expect($chapter->bookId)->toBe(5);
    });

    it('generates correct URL', function () {
        $chapter = ChapterDTO::fromArray([
            'slug' => 'sección-principal',
            'book_slug' => 'configuración',
        ]);
        $url = $chapter->getUrl('https://wiki.example.com');

        expect($url)->toBe('https://wiki.example.com/books/configuraci%C3%B3n/chapter/secci%C3%B3n-principal');
    });

    it('includes nested pages', function () {
        $data = [
            'id' => 1,
            'name' => 'Chapter',
            'pages' => [
                ['id' => 1, 'name' => 'Page 1'],
                ['id' => 2, 'name' => 'Page 2'],
            ],
        ];

        $chapter = ChapterDTO::fromArray($data);

        expect($chapter->pages)->toHaveCount(2);
        expect($chapter->pages[0])->toBeInstanceOf(PageDTO::class);
    });
});

describe('PageDTO', function () {
    it('creates from array with all fields', function () {
        $data = [
            'id' => 1,
            'name' => 'Página de Información',
            'slug' => 'pagina-informacion',
            'book_id' => 5,
            'book_slug' => 'mi-libro',
            'chapter_id' => 2,
            'chapter_slug' => 'mi-capitulo',
            'html' => '<p>Content</p>',
            'markdown' => '# Content',
            'draft' => false,
            'revision_count' => 3,
        ];

        $page = PageDTO::fromArray($data);

        expect($page->id)->toBe(1);
        expect($page->name)->toBe('Página de Información');
        expect($page->chapterId)->toBe(2);
        expect($page->revision)->toBe(3);
    });

    it('generates correct URL', function () {
        $page = PageDTO::fromArray([
            'slug' => 'introducción',
            'book_slug' => 'guía-básica',
        ]);
        $url = $page->getUrl('https://wiki.example.com');

        expect($url)->toBe('https://wiki.example.com/books/gu%C3%ADa-b%C3%A1sica/page/introducci%C3%B3n');
    });

    it('generates anchor link', function () {
        $page = PageDTO::fromArray(['slug' => 'sección-uno']);
        $anchor = $page->getAnchor();

        expect($anchor)->toBe('#secci%C3%B3n-uno');
    });
});

describe('SearchResultDTO', function () {
    it('creates from array with type', function () {
        $data = [
            'id' => 1,
            'name' => 'Resultado de Búsqueda',
            'type' => 'page',
            'url' => '/books/test/page/resultado',
            'book_id' => 5,
        ];

        $result = SearchResultDTO::fromArray($data);

        expect($result->id)->toBe(1);
        expect($result->name)->toBe('Resultado de Búsqueda');
        expect($result->type)->toBe(EntityType::PAGE);
    });

    it('handles unknown type gracefully', function () {
        $data = [
            'id' => 1,
            'type' => 'unknown_type',
        ];

        $result = SearchResultDTO::fromArray($data);

        expect($result->type)->toBeNull();
    });
});
