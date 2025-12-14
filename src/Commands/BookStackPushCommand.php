<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Commands;

use AichaDigital\BookStackSync\BookStackSync;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use AichaDigital\BookStackSync\Exceptions\SyncException;
use Illuminate\Console\Command;

class BookStackPushCommand extends Command
{
    protected $signature = 'bookstack:push
                            {path? : Path to local Markdown directory}
                            {--book= : BookStack book ID to push to}
                            {--dry-run : Preview changes without making them}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Push local Markdown files to a BookStack book';

    public function handle(BookStackSync $bookstack): int
    {
        $pathArg = $this->argument('path');
        $path = $pathArg !== null && $pathArg !== false && ! is_array($pathArg) ? (string) $pathArg : (string) config('bookstack-sync.markdown.source_path', '');
        $bookOption = $this->option('book');
        $bookId = $bookOption !== null && $bookOption !== false ? (string) $bookOption : config('bookstack-sync.defaults.book_id');
        $dryRun = (bool) $this->option('dry-run');

        if (empty($path)) {
            $this->error('No path specified. Provide a path argument or set BOOKSTACK_MARKDOWN_PATH.');

            return self::FAILURE;
        }

        if (empty($bookId)) {
            $this->error('No book ID specified. Use --book option or set BOOKSTACK_BOOK_ID.');
            $this->newLine();
            $this->line('Available books:');
            $this->call('bookstack:status', ['--books' => true]);

            return self::FAILURE;
        }

        try {
            $book = $bookstack->book((int) $bookId);

            $this->info('Push to BookStack');
            $this->line("  Source: <comment>{$path}</comment>");
            $this->line("  Book: <comment>{$book->name}</comment> (ID: {$book->id})");

            if ($dryRun) {
                $this->warn('  Mode: DRY RUN (no changes will be made)');
            }

            $this->newLine();

            if (! $dryRun && ! $this->option('force')) {
                if (! $this->confirm('Do you want to proceed?')) {
                    $this->info('Operation cancelled.');

                    return self::SUCCESS;
                }
            }

            // Configure dry run
            if ($dryRun) {
                $bookstack->sync()->setDryRun(true);
            }

            $this->info('Syncing files...');
            $result = $bookstack->pushToBook($path, (int) $bookId);

            $this->newLine();
            $this->info('Sync completed!');
            $this->table(
                ['Operation', 'Count'],
                [
                    ['Created', $result['created']],
                    ['Updated', $result['updated']],
                    ['Skipped', $result['skipped']],
                    ['Errors', count($result['errors'])],
                ]
            );

            if (! empty($result['errors'])) {
                $this->newLine();
                $this->warn('Errors:');
                foreach ($result['errors'] as $error) {
                    $this->line("  - {$error}");
                }
            }

            return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
        } catch (BookStackException|SyncException $e) {
            $this->error('Push failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
