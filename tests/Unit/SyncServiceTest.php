<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\Contracts\BookStackClientInterface;
use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\ChapterDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\Enums\ConflictResolution;
use AichaDigital\BookStackSync\Enums\SyncDirection;
use AichaDigital\BookStackSync\Exceptions\SyncException;
use AichaDigital\BookStackSync\Parsers\MarkdownParser;
use AichaDigital\BookStackSync\Services\SyncService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->client = Mockery::mock(BookStackClientInterface::class);
    $this->parser = new MarkdownParser;
    $this->tempDir = sys_get_temp_dir().'/bookstack-sync-test-'.uniqid();
    File::makeDirectory($this->tempDir, 0755, true);
});

afterEach(function () {
    if (File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
    Mockery::close();
});

describe('SyncService', function () {
    describe('Configuration', function () {
        it('returns configured sync direction', function () {
            $service = new SyncService(
                $this->client,
                $this->parser,
                SyncDirection::PULL
            );

            expect($service->getDirection())->toBe(SyncDirection::PULL);
        });

        it('sets dry run mode', function () {
            $service = new SyncService($this->client, $this->parser);
            $result = $service->setDryRun(true);

            expect($result)->toBe($service);
        });

        it('returns empty sync log initially', function () {
            $service = new SyncService($this->client, $this->parser);

            expect($service->getSyncLog())->toBe([]);
        });
    });

    describe('syncDirectoryToBook', function () {
        it('throws exception for non-existent directory', function () {
            $service = new SyncService($this->client, $this->parser);

            expect(fn () => $service->syncDirectoryToBook('/non/existent/path', 1))
                ->toThrow(SyncException::class);
        });

        it('syncs empty directory', function () {
            $this->client->shouldReceive('getBook')
                ->once()
                ->with(1)
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([]);

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::LOCAL, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result)->toBeArray()
                ->and($result['created'])->toBe(0)
                ->and($result['updated'])->toBe(0)
                ->and($result['deleted'])->toBe(0)
                ->and($result['skipped'])->toBe(0)
                ->and($result['errors'])->toBe([]);
        });

        it('creates new page from markdown file', function () {
            File::put($this->tempDir.'/test-page.md', "# Test Page\n\nContent here");

            $this->client->shouldReceive('getBook')
                ->once()
                ->with(1)
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([]);

            $this->client->shouldReceive('createPage')
                ->once()
                ->andReturn(new PageDTO(id: 1, name: 'Test Page', slug: 'test-page', bookId: 1));

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::LOCAL, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['created'])->toBe(1);
        });

        it('updates existing page', function () {
            File::put($this->tempDir.'/existing-page.md', "---\ntitle: Existing Page\n---\n\n# Existing Page\n\nUpdated content");

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $existingPage = new PageDTO(id: 10, name: 'Existing Page', slug: 'existing-page', bookId: 1, updatedAt: '2020-01-01T00:00:00Z');

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$existingPage]);

            $this->client->shouldReceive('updatePage')
                ->once()
                ->with(10, 'Existing Page', Mockery::any())
                ->andReturn($existingPage);

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::LOCAL, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['updated'])->toBe(1);
        });

        it('skips file when remote wins conflict', function () {
            File::put($this->tempDir.'/conflict-page.md', "---\ntitle: Conflict Page\n---\n\n# Conflict Page\n\nLocal content");

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $existingPage = new PageDTO(id: 10, name: 'Conflict Page', slug: 'conflict-page', bookId: 1, updatedAt: date('c'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$existingPage]);

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::REMOTE, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['skipped'])->toBe(1);
        });

        it('handles sync errors gracefully', function () {
            File::put($this->tempDir.'/error.md', "# Error Page\n\nContent");

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([]);

            $this->client->shouldReceive('createPage')
                ->once()
                ->andThrow(new \Exception('API Error'));

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::LOCAL, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['errors'])->toHaveCount(1)
                ->and($result['errors'][0])->toContain('API Error');
        });

        it('creates chapter when frontmatter specifies it', function () {
            File::put($this->tempDir.'/with-chapter.md', "---\ntitle: My Page\nchapter: New Chapter\n---\n\n# Content");

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([]);

            $this->client->shouldReceive('listChapters')
                ->once()
                ->andReturn([]);

            $this->client->shouldReceive('createChapter')
                ->once()
                ->with(1, 'New Chapter')
                ->andReturn(new ChapterDTO(id: 5, name: 'New Chapter', slug: 'new-chapter', bookId: 1));

            $this->client->shouldReceive('createPage')
                ->once()
                ->andReturn(new PageDTO(id: 1, name: 'My Page', slug: 'my-page', bookId: 1, chapterId: 5));

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::LOCAL, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['created'])->toBe(1);
        });

        it('uses existing chapter when found', function () {
            File::put($this->tempDir.'/existing-chapter.md', "---\nchapter: Existing Chapter\n---\n\n# Content");

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([]);

            $existingChapter = new ChapterDTO(id: 3, name: 'Existing Chapter', slug: 'existing-chapter', bookId: 1);

            $this->client->shouldReceive('listChapters')
                ->once()
                ->andReturn([$existingChapter]);

            $this->client->shouldReceive('createPage')
                ->once()
                ->andReturn(new PageDTO(id: 1, name: 'Existing Chapter', slug: 'existing-chapter', bookId: 1, chapterId: 3));

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::LOCAL, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['created'])->toBe(1);
        });

        it('resolves conflict with newest strategy - local wins', function () {
            $filePath = $this->tempDir.'/newest-page.md';
            File::put($filePath, "---\ntitle: Newest Page\n---\n\n# Newest Page\n\nLocal content");
            touch($filePath, time() + 3600); // Future time

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $existingPage = new PageDTO(id: 10, name: 'Newest Page', slug: 'newest-page', bookId: 1, updatedAt: '2020-01-01T00:00:00Z');

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$existingPage]);

            $this->client->shouldReceive('updatePage')
                ->once()
                ->andReturn($existingPage);

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::NEWEST, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['updated'])->toBe(1);
        });

        it('resolves conflict with newest strategy - remote wins', function () {
            $filePath = $this->tempDir.'/newest-remote-page.md';
            File::put($filePath, "---\ntitle: Newest Remote Page\n---\n\n# Newest Remote Page\n\nLocal content");
            touch($filePath, strtotime('2019-01-01')); // Old time

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $existingPage = new PageDTO(id: 10, name: 'Newest Remote Page', slug: 'newest-remote-page', bookId: 1, updatedAt: date('c'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$existingPage]);

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::NEWEST, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['skipped'])->toBe(1);
        });

        it('logs conflict for manual resolution', function () {
            File::put($this->tempDir.'/manual-page.md', "---\ntitle: Manual Page\n---\n\n# Manual Page\n\nContent");

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $existingPage = new PageDTO(id: 10, name: 'Manual Page', slug: 'manual-page', bookId: 1, updatedAt: date('c'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$existingPage]);

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::MANUAL, true, false);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['skipped'])->toBe(1);

            $log = $service->getSyncLog();
            expect($log)->toHaveKey('conflicts')
                ->and($log['conflicts'])->toHaveCount(1);
        });

        it('runs in dry run mode without making changes', function () {
            File::put($this->tempDir.'/dry-run.md', "# Dry Run Page\n\nContent");

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([]);

            // createPage should NOT be called in dry run mode
            $this->client->shouldNotReceive('createPage');

            $service = new SyncService($this->client, $this->parser, SyncDirection::PUSH, ConflictResolution::LOCAL, true, false);
            $service->setDryRun(true);
            $result = $service->syncDirectoryToBook($this->tempDir, 1);

            expect($result['created'])->toBe(1);
        });
    });

    describe('pullBookToDirectory', function () {
        it('pulls pages from book to directory', function () {
            $page = new PageDTO(id: 1, name: 'Test Page', slug: 'test-page', bookId: 1);

            $this->client->shouldReceive('getBook')
                ->once()
                ->with(1)
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$page]);

            $this->client->shouldReceive('exportPage')
                ->once()
                ->with(1)
                ->andReturn('# Test Page\n\nContent');

            $service = new SyncService($this->client, $this->parser, SyncDirection::PULL, ConflictResolution::LOCAL, true, false);
            $result = $service->pullBookToDirectory(1, $this->tempDir);

            expect($result)->toBeArray()
                ->and($result['created'] + $result['updated'])->toBeGreaterThanOrEqual(1);
        });

        it('creates directory if not exists', function () {
            $newDir = $this->tempDir.'/new-subdir';

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([]);

            $service = new SyncService($this->client, $this->parser, SyncDirection::PULL, ConflictResolution::LOCAL, true, false);
            $result = $service->pullBookToDirectory(1, $newDir);

            expect(File::isDirectory($newDir))->toBeTrue();
        });

        it('skips pages without id', function () {
            $page = new PageDTO(id: null, name: 'No ID Page', slug: 'no-id', bookId: 1);

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$page]);

            $service = new SyncService($this->client, $this->parser, SyncDirection::PULL, ConflictResolution::LOCAL, true, false);
            $result = $service->pullBookToDirectory(1, $this->tempDir);

            expect($result['created'])->toBe(0);
        });

        it('organizes pages by chapter', function () {
            $page = new PageDTO(id: 1, name: 'Chapter Page', slug: 'chapter-page', bookId: 1, chapterId: 5, chapterSlug: 'my-chapter');

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$page]);

            $this->client->shouldReceive('exportPage')
                ->once()
                ->andReturn('# Chapter Page\n\nContent');

            $service = new SyncService($this->client, $this->parser, SyncDirection::PULL, ConflictResolution::LOCAL, true, false);
            $service->pullBookToDirectory(1, $this->tempDir);

            expect(File::exists($this->tempDir.'/my-chapter/chapter-page.md'))->toBeTrue();
        });

        it('handles pull errors gracefully', function () {
            $page = new PageDTO(id: 1, name: 'Error Page', slug: 'error-page', bookId: 1);

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$page]);

            $this->client->shouldReceive('exportPage')
                ->once()
                ->andThrow(new \Exception('Export error'));

            $service = new SyncService($this->client, $this->parser, SyncDirection::PULL, ConflictResolution::LOCAL, true, false);
            $result = $service->pullBookToDirectory(1, $this->tempDir);

            expect($result['errors'])->toHaveCount(1);
        });

        it('runs pull in dry run mode', function () {
            $page = new PageDTO(id: 1, name: 'Dry Run Page', slug: 'dry-run-page', bookId: 1);

            $this->client->shouldReceive('getBook')
                ->once()
                ->andReturn(new BookDTO(id: 1, name: 'Test Book', slug: 'test-book'));

            $this->client->shouldReceive('listPages')
                ->once()
                ->andReturn([$page]);

            $this->client->shouldReceive('exportPage')
                ->once()
                ->andReturn('# Dry Run\n\nContent');

            $service = new SyncService($this->client, $this->parser, SyncDirection::PULL, ConflictResolution::LOCAL, true, false);
            $service->setDryRun(true);
            $result = $service->pullBookToDirectory(1, $this->tempDir);

            // File should not be created in dry run
            expect(File::exists($this->tempDir.'/dry-run-page.md'))->toBeFalse();
        });
    });
});
