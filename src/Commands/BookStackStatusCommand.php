<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Commands;

use AichaDigital\BookStackSync\BookStackSync;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use Illuminate\Console\Command;

class BookStackStatusCommand extends Command
{
    protected $signature = 'bookstack:status
                            {--books : List all books}
                            {--shelves : List all shelves}';

    protected $description = 'Check BookStack API connection status and list available content';

    public function handle(BookStackSync $bookstack): int
    {
        $this->info('Checking BookStack connection...');
        $this->newLine();

        $baseUrl = $bookstack->getBaseUrl();
        if (empty($baseUrl)) {
            $this->error('BookStack URL not configured. Set BOOKSTACK_URL or WIKI_URL in your .env file.');

            return self::FAILURE;
        }

        $this->line("URL: <comment>{$baseUrl}</comment>");

        try {
            // Test connection by listing books
            $books = $bookstack->books(10);

            $this->info('✓ Connection successful!');
            $this->newLine();

            // Show summary
            $this->line('Found <info>'.count($books).'</info> books (showing first 10)');

            if ($this->option('books') || $this->option('shelves')) {
                $this->showDetails($bookstack);
            } else {
                $this->newLine();
                $this->line('Use <comment>--books</comment> or <comment>--shelves</comment> to see details.');
            }

            return self::SUCCESS;
        } catch (BookStackException $e) {
            $this->error('Connection failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function showDetails(BookStackSync $bookstack): void
    {
        if ($this->option('shelves')) {
            $this->newLine();
            $this->info('Shelves:');
            $shelves = $bookstack->shelves(50);

            if (empty($shelves)) {
                $this->line('  No shelves found.');
            } else {
                $tableData = [];
                foreach ($shelves as $shelf) {
                    $tableData[] = [
                        $shelf->id,
                        mb_substr($shelf->name ?? '', 0, 40),
                        $shelf->slug,
                    ];
                }
                $this->table(['ID', 'Name', 'Slug'], $tableData);
            }
        }

        if ($this->option('books')) {
            $this->newLine();
            $this->info('Books:');
            $books = $bookstack->books(50);

            if (empty($books)) {
                $this->line('  No books found.');
            } else {
                $tableData = [];
                foreach ($books as $book) {
                    $tableData[] = [
                        $book->id,
                        mb_substr($book->name ?? '', 0, 40),
                        $book->slug,
                    ];
                }
                $this->table(['ID', 'Name', 'Slug'], $tableData);
            }
        }
    }
}
