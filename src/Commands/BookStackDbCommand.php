<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Commands;

use AichaDigital\BookStackSync\Database\Database;
use Illuminate\Console\Command;

/**
 * Query and manage the local SQLite cache.
 *
 * This command allows listing cached wiki structure, viewing statistics,
 * and managing the local database.
 */
class BookStackDbCommand extends Command
{
    protected $signature = 'bookstack:db
                            {action : Action to perform: stats, shelves, books, chapters, pages, path, delete}
                            {--book= : Filter by book BookStack ID}
                            {--chapter= : Filter by chapter BookStack ID}
                            {--deleted : Include deleted items}
                            {--force : Skip confirmation for destructive actions}';

    protected $description = 'Query and manage the local BookStack SQLite cache';

    public function handle(?Database $database): int
    {
        if ($database === null || ! config('bookstack-sync.database.enabled', true)) {
            $this->error('Local database is disabled. Enable it in config or set BOOKSTACK_LOCAL_DB=true');

            return self::FAILURE;
        }

        $action = $this->argument('action');

        return match ($action) {
            'stats' => $this->showStats($database),
            'shelves' => $this->listShelves($database),
            'books' => $this->listBooks($database),
            'chapters' => $this->listChapters($database),
            'pages' => $this->listPages($database),
            'path' => $this->showPath($database),
            'delete' => $this->deleteDatabase($database),
            default => $this->showHelp(),
        };
    }

    /**
     * Show database statistics.
     */
    private function showStats(Database $database): int
    {
        if (! $database->exists()) {
            $this->warn('Database does not exist. Run "php artisan bookstack:sync" first.');

            return self::SUCCESS;
        }

        $database->connect();

        $this->info('BookStack Local Cache Statistics');
        $this->newLine();

        $this->line("  Database: <comment>{$database->getPath()}</comment>");
        $this->line('  Last sync: <comment>'.($database->getLastSync() ?? 'Never').'</comment>');
        $this->newLine();

        $stats = $database->getStats();

        $this->table(
            ['Entity', 'Active', 'Deleted', 'Total'],
            [
                ['Shelves', $stats['shelves']['active'], $stats['shelves']['deleted'], $stats['shelves']['total']],
                ['Books', $stats['books']['active'], $stats['books']['deleted'], $stats['books']['total']],
                ['Chapters', $stats['chapters']['active'], $stats['chapters']['deleted'], $stats['chapters']['total']],
                ['Pages', $stats['pages']['active'], $stats['pages']['deleted'], $stats['pages']['total']],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * List shelves from cache.
     */
    private function listShelves(Database $database): int
    {
        if (! $database->exists()) {
            $this->warn('Database does not exist. Run "php artisan bookstack:sync" first.');

            return self::SUCCESS;
        }

        $database->connect();

        $shelves = $database->listShelves((bool) $this->option('deleted'));

        if (empty($shelves)) {
            $this->info('No shelves found.');

            return self::SUCCESS;
        }

        $this->info('Cached Shelves');
        $this->newLine();

        $headers = ['ID', 'BS_ID', 'Name', 'Slug', 'Synced At', 'Status'];
        $rows = [];

        foreach ($shelves as $shelf) {
            $status = $shelf['is_deleted'] ? '<fg=red>DELETED</>' : '<fg=green>active</>';
            $rows[] = [
                $shelf['id'],
                $shelf['bookstack_id'],
                $this->truncate($shelf['name'], 40),
                $this->truncate($shelf['slug'] ?? '', 25),
                $this->formatDate($shelf['synced_at']),
                $status,
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }

    /**
     * List books from cache.
     */
    private function listBooks(Database $database): int
    {
        if (! $database->exists()) {
            $this->warn('Database does not exist. Run "php artisan bookstack:sync" first.');

            return self::SUCCESS;
        }

        $database->connect();

        $books = $database->listBooks((bool) $this->option('deleted'));

        if (empty($books)) {
            $this->info('No books found.');

            return self::SUCCESS;
        }

        $this->info('Cached Books');
        $this->newLine();

        $headers = ['ID', 'BS_ID', 'Name', 'Slug', 'Synced At', 'Status'];
        $rows = [];

        foreach ($books as $book) {
            $status = $book['is_deleted'] ? '<fg=red>DELETED</>' : '<fg=green>active</>';
            $rows[] = [
                $book['id'],
                $book['bookstack_id'],
                $this->truncate($book['name'], 40),
                $this->truncate($book['slug'] ?? '', 25),
                $this->formatDate($book['synced_at']),
                $status,
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }

    /**
     * List chapters from cache.
     */
    private function listChapters(Database $database): int
    {
        if (! $database->exists()) {
            $this->warn('Database does not exist. Run "php artisan bookstack:sync" first.');

            return self::SUCCESS;
        }

        $database->connect();

        $bookId = $this->option('book');
        $bookIdInt = $bookId !== null ? (int) $bookId : null;

        $chapters = $database->listChapters($bookIdInt, (bool) $this->option('deleted'));

        if (empty($chapters)) {
            $this->info('No chapters found.');

            return self::SUCCESS;
        }

        $this->info('Cached Chapters');
        if ($bookIdInt !== null) {
            $this->line("  Filtered by book: <comment>{$bookIdInt}</comment>");
        }
        $this->newLine();

        $headers = ['ID', 'BS_ID', 'Book', 'Priority', 'Name', 'Synced At', 'Status'];
        $rows = [];

        foreach ($chapters as $chapter) {
            $status = $chapter['is_deleted'] ? '<fg=red>DELETED</>' : '<fg=green>active</>';
            $rows[] = [
                $chapter['id'],
                $chapter['bookstack_id'],
                $chapter['book_bookstack_id'] ?? '-',
                $chapter['priority'],
                $this->truncate($chapter['name'], 35),
                $this->formatDate($chapter['synced_at']),
                $status,
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }

    /**
     * List pages from cache.
     */
    private function listPages(Database $database): int
    {
        if (! $database->exists()) {
            $this->warn('Database does not exist. Run "php artisan bookstack:sync" first.');

            return self::SUCCESS;
        }

        $database->connect();

        $bookId = $this->option('book');
        $chapterId = $this->option('chapter');
        $bookIdInt = $bookId !== null ? (int) $bookId : null;
        $chapterIdInt = $chapterId !== null ? (int) $chapterId : null;

        $pages = $database->listPages($bookIdInt, $chapterIdInt, (bool) $this->option('deleted'));

        if (empty($pages)) {
            $this->info('No pages found.');

            return self::SUCCESS;
        }

        $this->info('Cached Pages');
        if ($bookIdInt !== null) {
            $this->line("  Filtered by book: <comment>{$bookIdInt}</comment>");
        }
        if ($chapterIdInt !== null) {
            $this->line("  Filtered by chapter: <comment>{$chapterIdInt}</comment>");
        }
        $this->newLine();

        $headers = ['ID', 'BS_ID', 'Book', 'Ch', 'Name', 'Local Path', 'Hash', 'Status'];
        $rows = [];

        foreach ($pages as $page) {
            $status = $page['is_deleted'] ? '<fg=red>DEL</>' : '<fg=green>OK</>';
            $localPath = $page['local_path'] ? $this->truncate(basename($page['local_path']), 20) : '-';
            $hash = $page['content_hash'] ? substr($page['content_hash'], 0, 8) : '-';

            $rows[] = [
                $page['id'],
                $page['bookstack_id'],
                $page['book_bookstack_id'] ?? '-',
                $page['chapter_bookstack_id'] ?? '-',
                $this->truncate($page['name'], 30),
                $localPath,
                $hash,
                $status,
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }

    /**
     * Show database path.
     */
    private function showPath(Database $database): int
    {
        $this->info('BookStack Local Database Path');
        $this->newLine();

        $this->line("  Path: <comment>{$database->getPath()}</comment>");
        $this->line('  Exists: '.($database->exists() ? '<fg=green>Yes</>' : '<fg=yellow>No</>'));

        if ($database->exists()) {
            $size = filesize($database->getPath());
            if ($size !== false) {
                $this->line('  Size: <comment>'.number_format($size).' bytes ('.number_format($size / 1024, 1).' KB)</comment>');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Delete the database.
     */
    private function deleteDatabase(Database $database): int
    {
        if (! $database->exists()) {
            $this->info('Database does not exist.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to delete the local database?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        if ($database->delete()) {
            $this->info('Database deleted successfully.');

            return self::SUCCESS;
        }

        $this->error('Failed to delete database.');

        return self::FAILURE;
    }

    /**
     * Show help for available actions.
     */
    private function showHelp(): int
    {
        $this->error('Unknown action. Available actions:');
        $this->newLine();
        $this->line('  <comment>stats</comment>     Show database statistics');
        $this->line('  <comment>shelves</comment>   List cached shelves');
        $this->line('  <comment>books</comment>     List cached books');
        $this->line('  <comment>chapters</comment>  List cached chapters (--book=ID to filter)');
        $this->line('  <comment>pages</comment>     List cached pages (--book=ID, --chapter=ID to filter)');
        $this->line('  <comment>path</comment>      Show database file path');
        $this->line('  <comment>delete</comment>    Delete the database (--force to skip confirmation)');
        $this->newLine();
        $this->line('Options:');
        $this->line('  <comment>--deleted</comment>  Include deleted items in listings');

        return self::FAILURE;
    }

    /**
     * Truncate string to max length.
     */
    private function truncate(string $string, int $maxLength): string
    {
        if (strlen($string) <= $maxLength) {
            return $string;
        }

        return substr($string, 0, $maxLength - 3).'...';
    }

    /**
     * Format date for display.
     */
    private function formatDate(?string $date): string
    {
        if ($date === null) {
            return '-';
        }

        try {
            $datetime = new \DateTime($date);

            return $datetime->format('Y-m-d H:i');
        } catch (\Exception) {
            return $date;
        }
    }
}
