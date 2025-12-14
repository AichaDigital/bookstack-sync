<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\Api\BookStackClient;
use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\ChapterDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\DTOs\SearchResultDTO;
use AichaDigital\BookStackSync\DTOs\ShelfDTO;
use AichaDigital\BookStackSync\Enums\ExportFormat;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function createMockedClient(array $responses): BookStackClient
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $httpClient = new Client(['handler' => $handlerStack]);

    $client = new BookStackClient('https://example.com', 'token_id', 'token_secret');

    // Use reflection to inject the mocked HTTP client
    $reflection = new ReflectionClass($client);
    $property = $reflection->getProperty('httpClient');
    $property->setAccessible(true);
    $property->setValue($client, $httpClient);

    return $client;
}

describe('BookStackClient', function () {
    describe('Shelf Operations', function () {
        it('lists shelves', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'data' => [
                        ['id' => 1, 'name' => 'Shelf 1', 'slug' => 'shelf-1'],
                        ['id' => 2, 'name' => 'Shelf 2', 'slug' => 'shelf-2'],
                    ],
                ])),
            ]);

            $shelves = $client->listShelves();

            expect($shelves)->toBeArray()
                ->and($shelves)->toHaveCount(2)
                ->and($shelves[0])->toBeInstanceOf(ShelfDTO::class)
                ->and($shelves[0]->name)->toBe('Shelf 1');
        });

        it('gets a shelf by id', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Test Shelf',
                    'slug' => 'test-shelf',
                    'description' => 'A test shelf',
                ])),
            ]);

            $shelf = $client->getShelf(1);

            expect($shelf)->toBeInstanceOf(ShelfDTO::class)
                ->and($shelf->id)->toBe(1)
                ->and($shelf->name)->toBe('Test Shelf');
        });

        it('creates a shelf', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'New Shelf',
                    'slug' => 'new-shelf',
                ])),
            ]);

            $shelf = $client->createShelf('New Shelf', 'Description', [1, 2]);

            expect($shelf)->toBeInstanceOf(ShelfDTO::class)
                ->and($shelf->name)->toBe('New Shelf');
        });

        it('updates a shelf', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Updated Shelf',
                    'slug' => 'updated-shelf',
                ])),
            ]);

            $shelf = $client->updateShelf(1, 'Updated Shelf', 'New desc', [1]);

            expect($shelf)->toBeInstanceOf(ShelfDTO::class)
                ->and($shelf->name)->toBe('Updated Shelf');
        });

        it('deletes a shelf', function () {
            $client = createMockedClient([
                new Response(204, []),
            ]);

            $result = $client->deleteShelf(1);

            expect($result)->toBeTrue();
        });
    });

    describe('Book Operations', function () {
        it('lists books', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'data' => [
                        ['id' => 1, 'name' => 'Book 1', 'slug' => 'book-1'],
                        ['id' => 2, 'name' => 'Book 2', 'slug' => 'book-2'],
                    ],
                ])),
            ]);

            $books = $client->listBooks();

            expect($books)->toBeArray()
                ->and($books)->toHaveCount(2)
                ->and($books[0])->toBeInstanceOf(BookDTO::class);
        });

        it('gets a book by id', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Test Book',
                    'slug' => 'test-book',
                ])),
            ]);

            $book = $client->getBook(1);

            expect($book)->toBeInstanceOf(BookDTO::class)
                ->and($book->id)->toBe(1);
        });

        it('creates a book', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'New Book',
                    'slug' => 'new-book',
                ])),
            ]);

            $book = $client->createBook('New Book', 'Description');

            expect($book)->toBeInstanceOf(BookDTO::class)
                ->and($book->name)->toBe('New Book');
        });

        it('updates a book', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Updated Book',
                    'slug' => 'updated-book',
                ])),
            ]);

            $book = $client->updateBook(1, 'Updated Book');

            expect($book)->toBeInstanceOf(BookDTO::class)
                ->and($book->name)->toBe('Updated Book');
        });

        it('deletes a book', function () {
            $client = createMockedClient([
                new Response(204, []),
            ]);

            $result = $client->deleteBook(1);

            expect($result)->toBeTrue();
        });

        it('exports a book', function () {
            $client = createMockedClient([
                new Response(200, [], '# Book Content'),
            ]);

            $content = $client->exportBook(1, ExportFormat::MARKDOWN);

            expect($content)->toBe('# Book Content');
        });
    });

    describe('Chapter Operations', function () {
        it('lists chapters', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'data' => [
                        ['id' => 1, 'name' => 'Chapter 1', 'slug' => 'chapter-1', 'book_id' => 1],
                    ],
                ])),
            ]);

            $chapters = $client->listChapters();

            expect($chapters)->toBeArray()
                ->and($chapters[0])->toBeInstanceOf(ChapterDTO::class);
        });

        it('gets a chapter by id', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Test Chapter',
                    'slug' => 'test-chapter',
                    'book_id' => 1,
                ])),
            ]);

            $chapter = $client->getChapter(1);

            expect($chapter)->toBeInstanceOf(ChapterDTO::class)
                ->and($chapter->id)->toBe(1);
        });

        it('creates a chapter', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'New Chapter',
                    'slug' => 'new-chapter',
                    'book_id' => 1,
                ])),
            ]);

            $chapter = $client->createChapter(1, 'New Chapter', 'Description');

            expect($chapter)->toBeInstanceOf(ChapterDTO::class)
                ->and($chapter->name)->toBe('New Chapter');
        });

        it('updates a chapter', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Updated Chapter',
                    'slug' => 'updated-chapter',
                    'book_id' => 1,
                ])),
            ]);

            $chapter = $client->updateChapter(1, 'Updated Chapter', null, 2);

            expect($chapter)->toBeInstanceOf(ChapterDTO::class)
                ->and($chapter->name)->toBe('Updated Chapter');
        });

        it('deletes a chapter', function () {
            $client = createMockedClient([
                new Response(204, []),
            ]);

            $result = $client->deleteChapter(1);

            expect($result)->toBeTrue();
        });

        it('exports a chapter', function () {
            $client = createMockedClient([
                new Response(200, [], '# Chapter Content'),
            ]);

            $content = $client->exportChapter(1, ExportFormat::HTML);

            expect($content)->toBe('# Chapter Content');
        });
    });

    describe('Page Operations', function () {
        it('lists pages', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'data' => [
                        ['id' => 1, 'name' => 'Page 1', 'slug' => 'page-1', 'book_id' => 1],
                    ],
                ])),
            ]);

            $pages = $client->listPages();

            expect($pages)->toBeArray()
                ->and($pages[0])->toBeInstanceOf(PageDTO::class);
        });

        it('gets a page by id', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Test Page',
                    'slug' => 'test-page',
                    'book_id' => 1,
                ])),
            ]);

            $page = $client->getPage(1);

            expect($page)->toBeInstanceOf(PageDTO::class)
                ->and($page->id)->toBe(1);
        });

        it('creates a page with markdown', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'New Page',
                    'slug' => 'new-page',
                    'book_id' => 1,
                ])),
            ]);

            $page = $client->createPage(1, 'New Page', '# Content', null, true);

            expect($page)->toBeInstanceOf(PageDTO::class)
                ->and($page->name)->toBe('New Page');
        });

        it('creates a page with html', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'New Page',
                    'slug' => 'new-page',
                    'book_id' => 1,
                ])),
            ]);

            $page = $client->createPage(1, 'New Page', '<h1>Content</h1>', null, false);

            expect($page)->toBeInstanceOf(PageDTO::class);
        });

        it('creates a page in a chapter', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'New Page',
                    'slug' => 'new-page',
                    'book_id' => 1,
                    'chapter_id' => 5,
                ])),
            ]);

            $page = $client->createPage(1, 'New Page', '# Content', 5);

            expect($page)->toBeInstanceOf(PageDTO::class)
                ->and($page->chapterId)->toBe(5);
        });

        it('updates a page', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Updated Page',
                    'slug' => 'updated-page',
                    'book_id' => 1,
                ])),
            ]);

            $page = $client->updatePage(1, 'Updated Page', '# New Content');

            expect($page)->toBeInstanceOf(PageDTO::class)
                ->and($page->name)->toBe('Updated Page');
        });

        it('updates a page with html', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'id' => 1,
                    'name' => 'Updated Page',
                    'slug' => 'updated-page',
                    'book_id' => 1,
                ])),
            ]);

            $page = $client->updatePage(1, null, '<p>Content</p>', false);

            expect($page)->toBeInstanceOf(PageDTO::class);
        });

        it('deletes a page', function () {
            $client = createMockedClient([
                new Response(204, []),
            ]);

            $result = $client->deletePage(1);

            expect($result)->toBeTrue();
        });

        it('exports a page', function () {
            $client = createMockedClient([
                new Response(200, [], '# Page Content'),
            ]);

            $content = $client->exportPage(1);

            expect($content)->toBe('# Page Content');
        });
    });

    describe('Search', function () {
        it('searches content', function () {
            $client = createMockedClient([
                new Response(200, [], json_encode([
                    'data' => [
                        ['id' => 1, 'name' => 'Result 1', 'type' => 'page'],
                        ['id' => 2, 'name' => 'Result 2', 'type' => 'book'],
                    ],
                ])),
            ]);

            $results = $client->search('test query');

            expect($results)->toBeArray()
                ->and($results)->toHaveCount(2)
                ->and($results[0])->toBeInstanceOf(SearchResultDTO::class);
        });
    });

    describe('Error Handling', function () {
        it('throws authentication failed on 401', function () {
            $client = createMockedClient([
                new Response(401, [], json_encode(['error' => 'Unauthorized'])),
            ]);

            expect(fn () => $client->listBooks())->toThrow(BookStackException::class);
        });

        it('throws not found on 404', function () {
            $client = createMockedClient([
                new Response(404, [], json_encode(['error' => 'Not found'])),
            ]);

            expect(fn () => $client->getBook(999))->toThrow(BookStackException::class);
        });

        it('throws validation failed on 422', function () {
            $client = createMockedClient([
                new Response(422, [], json_encode(['error' => ['name' => ['required']]])),
            ]);

            expect(fn () => $client->createBook(''))->toThrow(BookStackException::class);
        });

        it('throws rate limited on 429', function () {
            $client = createMockedClient([
                new Response(429, [], json_encode(['error' => 'Rate limited'])),
            ]);

            expect(fn () => $client->listBooks())->toThrow(BookStackException::class);
        });

        it('throws server error on 500', function () {
            $client = createMockedClient([
                new Response(500, [], json_encode(['error' => 'Server error'])),
            ]);

            expect(fn () => $client->listBooks())->toThrow(BookStackException::class);
        });
    });
});
