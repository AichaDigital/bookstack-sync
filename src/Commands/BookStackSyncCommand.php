<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Commands;

use AichaDigital\BookStackSync\BookStackSync;
use AichaDigital\BookStackSync\Database\Database;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use Illuminate\Console\Command;

/**
 * Sync wiki structure from BookStack API to local SQLite cache.
 *
 * This command fetches all shelves, books, chapters, and pages from the
 * configured BookStack instance and stores them in the local SQLite database.
 * It also detects deleted items and marks them accordingly.
 */
class BookStackSyncCommand extends Command
{
    protected $signature = 'bookstack:sync
                            {--fresh : Delete existing database and start fresh}
                            {--no-shelves : Skip syncing shelves}
                            {--no-books : Skip syncing books}
                            {--no-chapters : Skip syncing chapters}
                            {--no-pages : Skip syncing pages}';

    protected $description = 'Sync wiki structure from BookStack to local SQLite cache';

    public function handle(BookStackSync $bookstack, ?Database $database): int
    {
        if ($database === null || ! config('bookstack-sync.database.enabled', true)) {
            $this->error('Local database is disabled. Enable it in config or set BOOKSTACK_LOCAL_DB=true');

            return self::FAILURE;
        }

        try {
            $this->info('BookStack Structure Sync');
            $this->line("  URL: <comment>{$bookstack->getBaseUrl()}</comment>");
            $this->line("  Database: <comment>{$database->getPath()}</comment>");
            $this->newLine();

            // Handle --fresh option
            if ($this->option('fresh')) {
                if ($database->exists()) {
                    $this->warn('Deleting existing database...');
                    $database->delete();
                }
            }

            // Connect to database (creates if not exists)
            $database->connect();

            $this->info('Syncing from BookStack API...');
            $this->newLine();

            $database->beginTransaction();

            try {
                $stats = [
                    'shelves' => ['synced' => 0, 'deleted' => 0],
                    'books' => ['synced' => 0, 'deleted' => 0],
                    'chapters' => ['synced' => 0, 'deleted' => 0],
                    'pages' => ['synced' => 0, 'deleted' => 0],
                ];

                // Sync shelves
                if (! $this->option('no-shelves')) {
                    $stats['shelves'] = $this->syncShelves($bookstack, $database);
                }

                // Sync books
                if (! $this->option('no-books')) {
                    $stats['books'] = $this->syncBooks($bookstack, $database);
                }

                // Sync chapters
                if (! $this->option('no-chapters')) {
                    $stats['chapters'] = $this->syncChapters($bookstack, $database);
                }

                // Sync pages
                if (! $this->option('no-pages')) {
                    $stats['pages'] = $this->syncPages($bookstack, $database);
                }

                // Update last sync timestamp
                $database->updateLastSync();

                $database->commit();

                $this->newLine();
                $this->info('Sync completed!');
                $this->newLine();

                $this->table(
                    ['Entity', 'Synced', 'Marked Deleted'],
                    [
                        ['Shelves', $stats['shelves']['synced'], $stats['shelves']['deleted']],
                        ['Books', $stats['books']['synced'], $stats['books']['deleted']],
                        ['Chapters', $stats['chapters']['synced'], $stats['chapters']['deleted']],
                        ['Pages', $stats['pages']['synced'], $stats['pages']['deleted']],
                    ]
                );

                $this->newLine();
                $this->line("Last sync: <comment>{$database->getLastSync()}</comment>");

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $database->rollback();
                throw $e;
            }
        } catch (BookStackException $e) {
            $this->error('API Error: '.$e->getMessage());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Sync shelves from BookStack.
     *
     * @return array{synced: int, deleted: int}
     */
    private function syncShelves(BookStackSync $bookstack, Database $database): array
    {
        $this->line('  Syncing shelves...', null, 'v');

        $shelves = $bookstack->shelves(500);
        $activeIds = [];
        $synced = 0;

        foreach ($shelves as $shelf) {
            if ($shelf->id === null) {
                continue;
            }

            $database->upsertShelf(
                $shelf->id,
                $shelf->name ?? 'Untitled',
                $shelf->slug,
                $shelf->description
            );
            $activeIds[] = $shelf->id;
            $synced++;
        }

        $deleted = $database->markDeletedShelves($activeIds);

        $this->line("    <info>{$synced}</info> shelves synced, <comment>{$deleted}</comment> marked deleted");

        return ['synced' => $synced, 'deleted' => $deleted];
    }

    /**
     * Sync books from BookStack.
     *
     * @return array{synced: int, deleted: int}
     */
    private function syncBooks(BookStackSync $bookstack, Database $database): array
    {
        $this->line('  Syncing books...', null, 'v');

        $books = $bookstack->books(500);
        $activeIds = [];
        $synced = 0;

        foreach ($books as $book) {
            if ($book->id === null) {
                continue;
            }

            $database->upsertBook(
                $book->id,
                $book->name ?? 'Untitled',
                $book->slug,
                $book->description
            );
            $activeIds[] = $book->id;
            $synced++;
        }

        $deleted = $database->markDeletedBooks($activeIds);

        $this->line("    <info>{$synced}</info> books synced, <comment>{$deleted}</comment> marked deleted");

        return ['synced' => $synced, 'deleted' => $deleted];
    }

    /**
     * Sync chapters from BookStack.
     *
     * @return array{synced: int, deleted: int}
     */
    private function syncChapters(BookStackSync $bookstack, Database $database): array
    {
        $this->line('  Syncing chapters...', null, 'v');

        $chapters = $bookstack->chapters(1000);
        $activeIds = [];
        $synced = 0;
        $skipped = 0;

        foreach ($chapters as $chapter) {
            if ($chapter->id === null || $chapter->bookId === null) {
                $skipped++;

                continue;
            }

            // Check if book exists in local cache
            $book = $database->getBookByBookstackId($chapter->bookId);
            if ($book === null) {
                $skipped++;

                continue;
            }

            $database->upsertChapter(
                $chapter->id,
                $chapter->bookId,
                $chapter->name ?? 'Untitled',
                $chapter->slug,
                $chapter->description,
                $chapter->priority ?? 0
            );
            $activeIds[] = $chapter->id;
            $synced++;
        }

        $deleted = $database->markDeletedChapters($activeIds);

        $message = "    <info>{$synced}</info> chapters synced, <comment>{$deleted}</comment> marked deleted";
        if ($skipped > 0) {
            $message .= ", <fg=yellow>{$skipped}</> skipped";
        }
        $this->line($message);

        return ['synced' => $synced, 'deleted' => $deleted];
    }

    /**
     * Sync pages from BookStack.
     *
     * @return array{synced: int, deleted: int}
     */
    private function syncPages(BookStackSync $bookstack, Database $database): array
    {
        $this->line('  Syncing pages...', null, 'v');

        $pages = $bookstack->pages(5000);
        $activeIds = [];
        $synced = 0;
        $skipped = 0;

        foreach ($pages as $page) {
            if ($page->id === null || $page->bookId === null) {
                $skipped++;

                continue;
            }

            // Check if book exists in local cache
            $book = $database->getBookByBookstackId($page->bookId);
            if ($book === null) {
                $skipped++;

                continue;
            }

            $database->upsertPage(
                $page->id,
                $page->bookId,
                $page->name ?? 'Untitled',
                $page->slug,
                $page->chapterId,
                $page->priority ?? 0,
                null, // local_path - set during push/pull
                null, // content_hash - set during push/pull
                $page->updatedAt
            );
            $activeIds[] = $page->id;
            $synced++;
        }

        $deleted = $database->markDeletedPages($activeIds);

        $message = "    <info>{$synced}</info> pages synced, <comment>{$deleted}</comment> marked deleted";
        if ($skipped > 0) {
            $message .= ", <fg=yellow>{$skipped}</> skipped";
        }
        $this->line($message);

        return ['synced' => $synced, 'deleted' => $deleted];
    }
}
