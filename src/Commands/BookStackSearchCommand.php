<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Commands;

use AichaDigital\BookStackSync\BookStackSync;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use Illuminate\Console\Command;

class BookStackSearchCommand extends Command
{
    protected $signature = 'bookstack:search
                            {query : Search query}
                            {--limit=20 : Maximum results to return}';

    protected $description = 'Search BookStack content';

    public function handle(BookStackSync $bookstack): int
    {
        $query = (string) $this->argument('query');
        $limit = (int) $this->option('limit');

        $this->info("Searching for: \"{$query}\"");
        $this->newLine();

        try {
            $results = $bookstack->search($query, $limit);

            if (empty($results)) {
                $this->warn('No results found.');

                return self::SUCCESS;
            }

            $this->line('Found <info>'.count($results).'</info> results:');
            $this->newLine();

            $tableData = [];
            foreach ($results as $result) {
                $tableData[] = [
                    $result->type !== null ? $result->type->value : 'unknown',
                    $result->id,
                    mb_substr($result->name ?? '', 0, 50),
                    mb_substr($result->url ?? '', 0, 60),
                ];
            }

            $this->table(['Type', 'ID', 'Name', 'URL'], $tableData);

            return self::SUCCESS;
        } catch (BookStackException $e) {
            $this->error('Search failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
