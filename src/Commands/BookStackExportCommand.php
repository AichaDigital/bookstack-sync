<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Commands;

use AichaDigital\BookStackSync\BookStackSync;
use AichaDigital\BookStackSync\Enums\ExportFormat;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BookStackExportCommand extends Command
{
    protected $signature = 'bookstack:export
                            {type : Entity type (book, chapter, page)}
                            {id : Entity ID to export}
                            {--format=markdown : Export format (html, pdf, plaintext, markdown)}
                            {--output= : Output file path (optional, outputs to stdout if not specified)}';

    protected $description = 'Export a book, chapter, or page from BookStack';

    public function handle(BookStackSync $bookstack): int
    {
        $type = (string) $this->argument('type');
        $id = (int) $this->argument('id');
        $formatStr = (string) $this->option('format');
        $output = $this->option('output');

        // Validate type
        if (! in_array($type, ['book', 'chapter', 'page'], true)) {
            $this->error("Invalid type '{$type}'. Must be: book, chapter, or page");

            return self::FAILURE;
        }

        // Parse format
        $format = ExportFormat::tryFrom($formatStr);
        if ($format === null) {
            $this->error("Invalid format '{$formatStr}'. Must be: html, pdf, plaintext, markdown");

            return self::FAILURE;
        }

        try {
            $this->info("Exporting {$type} #{$id} as {$format->value}...");

            $content = match ($type) {
                'book' => $bookstack->exportBook($id, $format),
                'chapter' => $bookstack->client()->exportChapter($id, $format),
                default => $bookstack->exportPage($id, $format),
            };

            if ($output) {
                File::put($output, $content);
                $this->info("Exported to: {$output}");
            } else {
                $this->line($content);
            }

            return self::SUCCESS;
        } catch (BookStackException $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
