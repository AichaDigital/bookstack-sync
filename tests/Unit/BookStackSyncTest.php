<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\BookStackSync;
use AichaDigital\BookStackSync\Contracts\BookStackClientInterface;
use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\ChapterDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\DTOs\SearchResultDTO;
use AichaDigital\BookStackSync\DTOs\ShelfDTO;
use AichaDigital\BookStackSync\Enums\ExportFormat;
use AichaDigital\BookStackSync\Parsers\BookmarkConverter;
use AichaDigital\BookStackSync\Parsers\MarkdownParser;
use AichaDigital\BookStackSync\Services\SyncService;

beforeEach(function () {
    $this->client = Mockery::mock(BookStackClientInterface::class);
    $this->bookstack = new BookStackSync($this->client);
});

afterEach(function () {
    Mockery::close();
});

describe('BookStackSync', function () {
    describe('Accessors', function () {
        it('returns the client instance', function () {
            expect($this->bookstack->client())->toBe($this->client);
        });

        it('returns a markdown parser', function () {
            expect($this->bookstack->parser())->toBeInstanceOf(MarkdownParser::class);
        });

        it('returns a bookmark converter', function () {
            expect($this->bookstack->bookmarkConverter())->toBeInstanceOf(BookmarkConverter::class);
        });

        it('returns the base url from config', function () {
            config()->set('bookstack-sync.api.url', 'https://wiki.example.com');

            $bookstack = new BookStackSync($this->client);

            expect($bookstack->getBaseUrl())->toBe('https://wiki.example.com');
        });
    });

    describe('Shelf Operations', function () {
        it('lists shelves', function () {
            $this->client->shouldReceive('listShelves')
                ->once()
                ->with(100, 0)
                ->andReturn([new ShelfDTO(id: 1, name: 'Shelf 1')]);

            $shelves = $this->bookstack->shelves();

            expect($shelves)->toBeArray()
                ->and($shelves[0])->toBeInstanceOf(ShelfDTO::class);
        });

        it('gets a shelf', function () {
            $this->client->shouldReceive('getShelf')
                ->once()
                ->with(1)
                ->andReturn(new ShelfDTO(id: 1, name: 'Test Shelf'));

            $shelf = $this->bookstack->shelf(1);

            expect($shelf)->toBeInstanceOf(ShelfDTO::class)
                ->and($shelf->name)->toBe('Test Shelf');
        });

        it('creates a shelf', function () {
            $this->client->shouldReceive('createShelf')
                ->once()
                ->with('New Shelf', 'Description', [1, 2])
                ->andReturn(new ShelfDTO(id: 1, name: 'New Shelf'));

            $shelf = $this->bookstack->createShelf('New Shelf', 'Description', [1, 2]);

            expect($shelf)->toBeInstanceOf(ShelfDTO::class);
        });
    });

    describe('Book Operations', function () {
        it('lists books', function () {
            $this->client->shouldReceive('listBooks')
                ->once()
                ->with(50, 10)
                ->andReturn([new BookDTO(id: 1, name: 'Book 1')]);

            $books = $this->bookstack->books(50, 10);

            expect($books)->toBeArray();
        });

        it('gets a book', function () {
            $this->client->shouldReceive('getBook')
                ->once()
                ->with(1)
                ->andReturn(new BookDTO(id: 1, name: 'Test Book'));

            $book = $this->bookstack->book(1);

            expect($book)->toBeInstanceOf(BookDTO::class);
        });

        it('creates a book', function () {
            $this->client->shouldReceive('createBook')
                ->once()
                ->with('New Book', 'Description')
                ->andReturn(new BookDTO(id: 1, name: 'New Book'));

            $book = $this->bookstack->createBook('New Book', 'Description');

            expect($book)->toBeInstanceOf(BookDTO::class);
        });

        it('exports a book', function () {
            $this->client->shouldReceive('exportBook')
                ->once()
                ->with(1, ExportFormat::HTML)
                ->andReturn('<h1>Book Content</h1>');

            $content = $this->bookstack->exportBook(1, ExportFormat::HTML);

            expect($content)->toBe('<h1>Book Content</h1>');
        });
    });

    describe('Chapter Operations', function () {
        it('lists chapters', function () {
            $this->client->shouldReceive('listChapters')
                ->once()
                ->andReturn([new ChapterDTO(id: 1, name: 'Chapter 1', bookId: 1)]);

            $chapters = $this->bookstack->chapters();

            expect($chapters)->toBeArray();
        });

        it('gets a chapter', function () {
            $this->client->shouldReceive('getChapter')
                ->once()
                ->with(1)
                ->andReturn(new ChapterDTO(id: 1, name: 'Test Chapter', bookId: 1));

            $chapter = $this->bookstack->chapter(1);

            expect($chapter)->toBeInstanceOf(ChapterDTO::class);
        });

        it('creates a chapter', function () {
            $this->client->shouldReceive('createChapter')
                ->once()
                ->with(1, 'New Chapter', 'Description')
                ->andReturn(new ChapterDTO(id: 1, name: 'New Chapter', bookId: 1));

            $chapter = $this->bookstack->createChapter(1, 'New Chapter', 'Description');

            expect($chapter)->toBeInstanceOf(ChapterDTO::class);
        });
    });

    describe('Page Operations', function () {
        it('lists pages', function () {
            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([new PageDTO(id: 1, name: 'Page 1', bookId: 1)]);

            $pages = $this->bookstack->pages();

            expect($pages)->toBeArray();
        });

        it('gets a page', function () {
            $this->client->shouldReceive('getPage')
                ->once()
                ->with(1)
                ->andReturn(new PageDTO(id: 1, name: 'Test Page', bookId: 1));

            $page = $this->bookstack->page(1);

            expect($page)->toBeInstanceOf(PageDTO::class);
        });

        it('creates a page with markdown', function () {
            $this->client->shouldReceive('createPage')
                ->once()
                ->andReturn(new PageDTO(id: 1, name: 'New Page', bookId: 1));

            $page = $this->bookstack->createPage(1, 'New Page', '# Content');

            expect($page)->toBeInstanceOf(PageDTO::class);
        });

        it('creates a page in a chapter', function () {
            $this->client->shouldReceive('createPage')
                ->once()
                ->with(1, 'New Page', Mockery::any(), 5, true)
                ->andReturn(new PageDTO(id: 1, name: 'New Page', bookId: 1, chapterId: 5));

            $page = $this->bookstack->createPage(1, 'New Page', '# Content', 5);

            expect($page->chapterId)->toBe(5);
        });

        it('updates a page', function () {
            $this->client->shouldReceive('updatePage')
                ->once()
                ->andReturn(new PageDTO(id: 1, name: 'Updated Page', bookId: 1));

            $page = $this->bookstack->updatePage(1, 'Updated Page', '# New Content');

            expect($page)->toBeInstanceOf(PageDTO::class);
        });

        it('deletes a page', function () {
            $this->client->shouldReceive('deletePage')
                ->once()
                ->with(1)
                ->andReturn(true);

            $result = $this->bookstack->deletePage(1);

            expect($result)->toBeTrue();
        });

        it('exports a page with bookmark conversion', function () {
            $this->client->shouldReceive('exportPage')
                ->once()
                ->with(1, ExportFormat::MARKDOWN)
                ->andReturn('# Page with [link](#bkmrk-test)');

            $content = $this->bookstack->exportPage(1);

            expect($content)->toContain('#');
        });

        it('exports a page without conversion for non-markdown', function () {
            $this->client->shouldReceive('exportPage')
                ->once()
                ->with(1, ExportFormat::HTML)
                ->andReturn('<h1>Page Content</h1>');

            $content = $this->bookstack->exportPage(1, ExportFormat::HTML);

            expect($content)->toBe('<h1>Page Content</h1>');
        });
    });

    describe('Search Operations', function () {
        it('searches content', function () {
            $this->client->shouldReceive('search')
                ->once()
                ->with('query', 100, 0)
                ->andReturn([new SearchResultDTO(id: 1, name: 'Result')]);

            $results = $this->bookstack->search('query');

            expect($results)->toBeArray()
                ->and($results[0])->toBeInstanceOf(SearchResultDTO::class);
        });
    });

    describe('Sync Operations', function () {
        it('returns a sync service', function () {
            expect($this->bookstack->sync())->toBeInstanceOf(SyncService::class);
        });

        it('returns the same sync service instance', function () {
            $sync1 = $this->bookstack->sync();
            $sync2 = $this->bookstack->sync();

            expect($sync1)->toBe($sync2);
        });
    });

    describe('Bookmark Conversion', function () {
        it('encodes bookmarks', function () {
            $encoded = $this->bookstack->encodeBookmark('My Heading');

            expect($encoded)->toBeString();
        });

        it('decodes bookmarks', function () {
            $decoded = $this->bookstack->decodeBookmark('bkmrk-my-heading');

            expect($decoded)->toBeString();
        });
    });
});

describe('BookStackSync without client', function () {
    it('creates default client from config', function () {
        config()->set('bookstack-sync.api.url', 'https://wiki.example.com');
        config()->set('bookstack-sync.api.token_id', 'test_id');
        config()->set('bookstack-sync.api.token_secret', 'test_secret');

        $bookstack = new BookStackSync;

        expect($bookstack->client())->toBeInstanceOf(BookStackClientInterface::class);
    });
});
