<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\BookStackSync;
use AichaDigital\BookStackSync\Contracts\BookStackClientInterface;
use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\SearchResultDTO;
use AichaDigital\BookStackSync\DTOs\ShelfDTO;
use AichaDigital\BookStackSync\Enums\EntityType;
use AichaDigital\BookStackSync\Enums\ExportFormat;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use AichaDigital\BookStackSync\Services\SyncService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->client = Mockery::mock(BookStackClientInterface::class);
    $this->bookstack = Mockery::mock(BookStackSync::class);
    $this->bookstack->shouldReceive('client')->andReturn($this->client);

    $this->app->instance(BookStackSync::class, $this->bookstack);

    $this->tempDir = sys_get_temp_dir().'/bookstack-cmd-test-'.uniqid();
    File::makeDirectory($this->tempDir, 0755, true);
});

afterEach(function () {
    if (File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
    Mockery::close();
});

describe('bookstack:status command', function () {
    it('shows connection status successfully', function () {
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('https://wiki.example.com');

        $this->bookstack->shouldReceive('books')
            ->with(10)
            ->andReturn([new BookDTO(id: 1, name: 'Book 1', slug: 'book-1')]);

        $this->artisan('bookstack:status')
            ->expectsOutputToContain('Connection successful')
            ->assertSuccessful();
    });

    it('fails when no url configured', function () {
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('');

        $this->artisan('bookstack:status')
            ->expectsOutputToContain('not configured')
            ->assertFailed();
    });

    it('shows books list with --books option', function () {
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('https://wiki.example.com');

        $this->bookstack->shouldReceive('books')
            ->andReturn([
                new BookDTO(id: 1, name: 'Book 1', slug: 'book-1'),
                new BookDTO(id: 2, name: 'Book 2', slug: 'book-2'),
            ]);

        $this->artisan('bookstack:status', ['--books' => true])
            ->assertSuccessful();
    });

    it('shows shelves list with --shelves option', function () {
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('https://wiki.example.com');

        $this->bookstack->shouldReceive('books')
            ->andReturn([new BookDTO(id: 1, name: 'Book 1', slug: 'book-1')]);

        $this->bookstack->shouldReceive('shelves')
            ->with(50)
            ->andReturn([
                new ShelfDTO(id: 1, name: 'Shelf 1', slug: 'shelf-1'),
            ]);

        $this->artisan('bookstack:status', ['--shelves' => true])
            ->assertSuccessful();
    });

    it('handles connection failure', function () {
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('https://wiki.example.com');

        $this->bookstack->shouldReceive('books')
            ->andThrow(BookStackException::connectionFailed('https://wiki.example.com'));

        $this->artisan('bookstack:status')
            ->expectsOutputToContain('Connection failed')
            ->assertFailed();
    });

    it('shows empty shelves message', function () {
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('https://wiki.example.com');

        $this->bookstack->shouldReceive('books')
            ->andReturn([new BookDTO(id: 1, name: 'Book 1', slug: 'book-1')]);

        $this->bookstack->shouldReceive('shelves')
            ->andReturn([]);

        $this->artisan('bookstack:status', ['--shelves' => true])
            ->expectsOutputToContain('No shelves found')
            ->assertSuccessful();
    });

    it('shows empty books message', function () {
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('https://wiki.example.com');

        $this->bookstack->shouldReceive('books')
            ->andReturn([]);

        $this->artisan('bookstack:status', ['--books' => true])
            ->expectsOutputToContain('No books found')
            ->assertSuccessful();
    });
});

describe('bookstack:search command', function () {
    it('searches and displays results', function () {
        $this->bookstack->shouldReceive('search')
            ->with('test query', 20)
            ->andReturn([
                new SearchResultDTO(id: 1, name: 'Test Page', type: EntityType::PAGE, url: '/page/1'),
                new SearchResultDTO(id: 2, name: 'Test Book', type: EntityType::BOOK, url: '/book/1'),
            ]);

        $this->artisan('bookstack:search', ['query' => 'test query'])
            ->expectsOutputToContain('Found')
            ->assertSuccessful();
    });

    it('shows no results message', function () {
        $this->bookstack->shouldReceive('search')
            ->andReturn([]);

        $this->artisan('bookstack:search', ['query' => 'nonexistent'])
            ->expectsOutputToContain('No results found')
            ->assertSuccessful();
    });

    it('respects limit option', function () {
        $this->bookstack->shouldReceive('search')
            ->with('query', 5)
            ->andReturn([new SearchResultDTO(id: 1, name: 'Result')]);

        $this->artisan('bookstack:search', ['query' => 'query', '--limit' => 5])
            ->assertSuccessful();
    });

    it('handles search failure', function () {
        $this->bookstack->shouldReceive('search')
            ->andThrow(BookStackException::connectionFailed('https://wiki.example.com'));

        $this->artisan('bookstack:search', ['query' => 'test'])
            ->expectsOutputToContain('Search failed')
            ->assertFailed();
    });

    it('handles results with null type', function () {
        $this->bookstack->shouldReceive('search')
            ->andReturn([new SearchResultDTO(id: 1, name: 'Unknown Type', type: null)]);

        $this->artisan('bookstack:search', ['query' => 'test'])
            ->assertSuccessful();
    });
});

describe('bookstack:export command', function () {
    it('exports a book', function () {
        $this->bookstack->shouldReceive('exportBook')
            ->with(1, ExportFormat::MARKDOWN)
            ->andReturn('# Book Content');

        $this->artisan('bookstack:export', ['type' => 'book', 'id' => 1])
            ->expectsOutputToContain('Book Content')
            ->assertSuccessful();
    });

    it('exports a chapter', function () {
        $this->client->shouldReceive('exportChapter')
            ->with(1, ExportFormat::MARKDOWN)
            ->andReturn('# Chapter Content');

        $this->artisan('bookstack:export', ['type' => 'chapter', 'id' => 1])
            ->expectsOutputToContain('Chapter Content')
            ->assertSuccessful();
    });

    it('exports a page', function () {
        $this->bookstack->shouldReceive('exportPage')
            ->with(1, ExportFormat::MARKDOWN)
            ->andReturn('# Page Content');

        $this->artisan('bookstack:export', ['type' => 'page', 'id' => 1])
            ->expectsOutputToContain('Page Content')
            ->assertSuccessful();
    });

    it('exports to file', function () {
        $outputFile = $this->tempDir.'/export.md';

        $this->bookstack->shouldReceive('exportPage')
            ->andReturn('# Exported Content');

        $this->artisan('bookstack:export', ['type' => 'page', 'id' => 1, '--output' => $outputFile])
            ->expectsOutputToContain('Exported to')
            ->assertSuccessful();

        expect(File::exists($outputFile))->toBeTrue()
            ->and(File::get($outputFile))->toBe('# Exported Content');
    });

    it('rejects invalid type', function () {
        $this->artisan('bookstack:export', ['type' => 'invalid', 'id' => 1])
            ->expectsOutputToContain('Invalid type')
            ->assertFailed();
    });

    it('rejects invalid format', function () {
        $this->artisan('bookstack:export', ['type' => 'page', 'id' => 1, '--format' => 'invalid'])
            ->expectsOutputToContain('Invalid format')
            ->assertFailed();
    });

    it('handles export failure', function () {
        $this->bookstack->shouldReceive('exportPage')
            ->andThrow(BookStackException::notFound('page', 999));

        $this->artisan('bookstack:export', ['type' => 'page', 'id' => 999])
            ->expectsOutputToContain('Export failed')
            ->assertFailed();
    });

    it('exports with different formats', function () {
        $this->bookstack->shouldReceive('exportBook')
            ->with(1, ExportFormat::HTML)
            ->andReturn('<h1>HTML Content</h1>');

        $this->artisan('bookstack:export', ['type' => 'book', 'id' => 1, '--format' => 'html'])
            ->assertSuccessful();
    });
});

describe('bookstack:push command', function () {
    it('fails without path', function () {
        config()->set('bookstack-sync.markdown.source_path', null);

        $this->artisan('bookstack:push')
            ->expectsOutputToContain('No path specified')
            ->assertFailed();
    });

    it('fails without book id', function () {
        config()->set('bookstack-sync.defaults.book_id', null);

        // Mock getBaseUrl and books for the nested bookstack:status call
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('https://wiki.example.com');
        $this->bookstack->shouldReceive('books')
            ->andReturn([new BookDTO(id: 1, name: 'Book 1', slug: 'book-1')]);

        $this->artisan('bookstack:push', ['path' => $this->tempDir])
            ->expectsOutputToContain('No book ID specified')
            ->assertFailed();
    });

    it('pushes with force option', function () {
        File::put($this->tempDir.'/test.md', '# Test');

        $this->bookstack->shouldReceive('book')
            ->with(1)
            ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

        $syncService = Mockery::mock(SyncService::class);
        $this->bookstack->shouldReceive('sync')
            ->andReturn($syncService);

        $this->bookstack->shouldReceive('pushToBook')
            ->with($this->tempDir, 1)
            ->andReturn(['created' => 1, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => []]);

        $this->artisan('bookstack:push', ['path' => $this->tempDir, '--book' => 1, '--force' => true])
            ->expectsOutputToContain('Sync completed')
            ->assertSuccessful();
    });

    it('runs in dry-run mode', function () {
        File::put($this->tempDir.'/test.md', '# Test');

        $this->bookstack->shouldReceive('book')
            ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

        $syncService = Mockery::mock(SyncService::class);
        $syncService->shouldReceive('setDryRun')->with(true)->once();

        $this->bookstack->shouldReceive('sync')
            ->andReturn($syncService);

        $this->bookstack->shouldReceive('pushToBook')
            ->andReturn(['created' => 1, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => []]);

        $this->artisan('bookstack:push', ['path' => $this->tempDir, '--book' => 1, '--force' => true, '--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
    });

    it('displays errors', function () {
        $this->bookstack->shouldReceive('book')
            ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

        $syncService = Mockery::mock(SyncService::class);
        $this->bookstack->shouldReceive('sync')
            ->andReturn($syncService);

        $this->bookstack->shouldReceive('pushToBook')
            ->andReturn(['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => ['Error 1', 'Error 2']]);

        $this->artisan('bookstack:push', ['path' => $this->tempDir, '--book' => 1, '--force' => true])
            ->expectsOutputToContain('Errors')
            ->assertFailed();
    });

    it('handles push exceptions', function () {
        $this->bookstack->shouldReceive('book')
            ->andThrow(BookStackException::notFound('book', 999));

        $this->artisan('bookstack:push', ['path' => $this->tempDir, '--book' => 999, '--force' => true])
            ->expectsOutputToContain('Push failed')
            ->assertFailed();
    });

    it('cancels when user declines confirmation', function () {
        $this->bookstack->shouldReceive('book')
            ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

        $this->artisan('bookstack:push', ['path' => $this->tempDir, '--book' => 1])
            ->expectsConfirmation('Do you want to proceed?', 'no')
            ->expectsOutputToContain('cancelled')
            ->assertSuccessful();
    });
});

describe('bookstack:pull command', function () {
    it('fails without book id', function () {
        config()->set('bookstack-sync.defaults.book_id', null);

        // Mock getBaseUrl and books for the nested bookstack:status call
        $this->bookstack->shouldReceive('getBaseUrl')
            ->andReturn('https://wiki.example.com');
        $this->bookstack->shouldReceive('books')
            ->andReturn([new BookDTO(id: 1, name: 'Book 1', slug: 'book-1')]);

        $this->artisan('bookstack:pull')
            ->expectsOutputToContain('No book ID specified')
            ->assertFailed();
    });

    it('fails without path', function () {
        config()->set('bookstack-sync.markdown.source_path', null);

        $this->artisan('bookstack:pull', ['--book' => 1])
            ->expectsOutputToContain('No path specified')
            ->assertFailed();
    });

    it('pulls with force option', function () {
        $this->bookstack->shouldReceive('book')
            ->with(1)
            ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

        $syncService = Mockery::mock(SyncService::class);
        $this->bookstack->shouldReceive('sync')
            ->andReturn($syncService);

        $this->bookstack->shouldReceive('pullFromBook')
            ->with(1, $this->tempDir)
            ->andReturn(['created' => 2, 'updated' => 0, 'skipped' => 0, 'errors' => []]);

        $this->artisan('bookstack:pull', ['--book' => 1, '--path' => $this->tempDir, '--force' => true])
            ->expectsOutputToContain('Pull completed')
            ->assertSuccessful();
    });

    it('runs in dry-run mode', function () {
        $this->bookstack->shouldReceive('book')
            ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

        $syncService = Mockery::mock(SyncService::class);
        $syncService->shouldReceive('setDryRun')->with(true)->once();

        $this->bookstack->shouldReceive('sync')
            ->andReturn($syncService);

        $this->bookstack->shouldReceive('pullFromBook')
            ->andReturn(['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []]);

        $this->artisan('bookstack:pull', ['--book' => 1, '--path' => $this->tempDir, '--force' => true, '--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
    });

    it('displays errors', function () {
        $this->bookstack->shouldReceive('book')
            ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

        $syncService = Mockery::mock(SyncService::class);
        $this->bookstack->shouldReceive('sync')
            ->andReturn($syncService);

        $this->bookstack->shouldReceive('pullFromBook')
            ->andReturn(['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Pull error']]);

        $this->artisan('bookstack:pull', ['--book' => 1, '--path' => $this->tempDir, '--force' => true])
            ->expectsOutputToContain('Errors')
            ->assertFailed();
    });

    it('handles pull exceptions', function () {
        $this->bookstack->shouldReceive('book')
            ->andThrow(BookStackException::notFound('book', 999));

        $this->artisan('bookstack:pull', ['--book' => 999, '--path' => $this->tempDir, '--force' => true])
            ->expectsOutputToContain('Pull failed')
            ->assertFailed();
    });

    it('cancels when user declines confirmation', function () {
        $this->bookstack->shouldReceive('book')
            ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

        $this->artisan('bookstack:pull', ['--book' => 1, '--path' => $this->tempDir])
            ->expectsConfirmation('Do you want to proceed?', 'no')
            ->expectsOutputToContain('cancelled')
            ->assertSuccessful();
    });
});
