<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Commands;

use AichaDigital\BookStackSync\BookStackSync;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use AichaDigital\BookStackSync\Exceptions\SyncException;
use Illuminate\Console\Command;

class BookStackPullCommand extends Command
{
    protected $signature = 'bookstack:pull
                            {--book= : BookStack book ID to pull from}
                            {--path= : Local path to save Markdown files}
                            {--dry-run : Preview changes without making them}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Pull content from a BookStack book to local Markdown files';

    public function handle(BookStackSync $bookstack): int
    {
        $bookOption = $this->option('book');
        $bookId = is_string($bookOption) ? $bookOption : config('bookstack-sync.defaults.book_id');
        $pathOption = $this->option('path');
        $path = is_string($pathOption) ? $pathOption : (is_string(config('bookstack-sync.markdown.source_path')) ? config('bookstack-sync.markdown.source_path') : '');
        $dryRun = $this->option('dry-run') === true;

        if (empty($bookId)) {
            $this->error('No book ID specified. Use --book option or set BOOKSTACK_BOOK_ID.');
            $this->newLine();
            $this->line('Available books:');
            $this->call('bookstack:status', ['--books' => true]);

            return self::FAILURE;
        }

        if (empty($path)) {
            $this->error('No path specified. Use --path option or set BOOKSTACK_MARKDOWN_PATH.');

            return self::FAILURE;
        }

        try {
            $book = $bookstack->book((int) $bookId);

            $this->info('Pull from BookStack');
            $this->line("  Book: <comment>{$book->name}</comment> (ID: {$book->id})");
            $this->line("  Destination: <comment>{$path}</comment>");

            if ($dryRun) {
                $this->warn('  Mode: DRY RUN (no changes will be made)');
            }

            $this->newLine();

            if (! $dryRun && ! $this->option('force')) {
                $this->warn('This will overwrite existing local files!');
                if (! $this->confirm('Do you want to proceed?')) {
                    $this->info('Operation cancelled.');

                    return self::SUCCESS;
                }
            }

            // Configure dry run
            if ($dryRun) {
                $bookstack->sync()->setDryRun(true);
            }

            $this->info('Pulling content...');
            $result = $bookstack->pullFromBook((int) $bookId, $path);

            $this->newLine();
            $this->info('Pull completed!');
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
            $this->error('Pull failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
