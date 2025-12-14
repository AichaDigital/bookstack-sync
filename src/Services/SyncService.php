<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Services;

use AichaDigital\BookStackSync\Contracts\BookStackClientInterface;
use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\Enums\ConflictResolution;
use AichaDigital\BookStackSync\Enums\SyncDirection;
use AichaDigital\BookStackSync\Exceptions\SyncException;
use AichaDigital\BookStackSync\Parsers\MarkdownParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SyncService
{
    private bool $dryRun = false;

    /** @var array<string, mixed> */
    private array $syncLog = [];

    public function __construct(
        private readonly BookStackClientInterface $client,
        private readonly MarkdownParser $parser,
        private readonly SyncDirection $direction = SyncDirection::PUSH,
        private readonly ConflictResolution $conflictResolution = ConflictResolution::MANUAL,
        private readonly bool $autoCreateStructure = true,
        private readonly bool $logging = true,
    ) {}

    /**
     * Get the configured sync direction.
     */
    public function getDirection(): SyncDirection
    {
        return $this->direction;
    }

    /**
     * Set dry run mode (no actual changes).
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    /**
     * Get the sync log.
     *
     * @return array<string, mixed>
     */
    public function getSyncLog(): array
    {
        return $this->syncLog;
    }

    /**
     * Sync a local directory to a BookStack book.
     *
     * @return array{created: int, updated: int, deleted: int, skipped: int, errors: array<string>}
     */
    public function syncDirectoryToBook(string $localPath, int $bookId): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (! File::isDirectory($localPath)) {
            throw SyncException::localFileNotFound($localPath);
        }

        $book = $this->client->getBook($bookId);
        $this->log('info', "Starting sync to book: {$book->name}");

        // Get all markdown files
        $files = $this->getMarkdownFiles($localPath);

        // Get existing pages from BookStack
        $existingPages = $this->getBookPages($bookId);

        foreach ($files as $file) {
            try {
                $syncResult = $this->syncFile($file, $book, $existingPages);
                $result[$syncResult]++;
            } catch (\Throwable $e) {
                $result['errors'][] = "{$file}: {$e->getMessage()}";
                $this->log('error', "Error syncing {$file}: {$e->getMessage()}");
            }
        }

        $this->log('info', "Sync completed: created={$result['created']}, updated={$result['updated']}, skipped={$result['skipped']}");

        return $result;
    }

    /**
     * Sync a single Markdown file to BookStack.
     *
     * @param  array<PageDTO>  $existingPages
     */
    private function syncFile(string $filePath, BookDTO $book, array $existingPages): string
    {
        $content = File::get($filePath);
        $parsed = $this->parser->extractFrontmatter($content);

        // Get page name from frontmatter or filename
        $pageName = $parsed['frontmatter']['title']
            ?? $parsed['frontmatter']['name']
            ?? $this->getPageNameFromFile($filePath);

        // Parse content for BookStack
        $markdownContent = $this->parser->parseForBookStack($parsed['content']);

        // Check if page exists
        $existingPage = $this->findExistingPage($pageName, $existingPages);

        if ($existingPage !== null) {
            return $this->handleExistingPage($existingPage, $pageName, $markdownContent, $filePath);
        }

        // Create new page
        return $this->createNewPage($book, $pageName, $markdownContent, $parsed['frontmatter']);
    }

    /**
     * Handle syncing to an existing page.
     */
    private function handleExistingPage(
        PageDTO $existingPage,
        string $pageName,
        string $content,
        string $filePath
    ): string {
        // Check for conflicts
        if ($this->hasConflict($existingPage, $filePath)) {
            return $this->resolveConflict($existingPage, $pageName, $content, $filePath);
        }

        // Update the page
        if (! $this->dryRun) {
            $this->client->updatePage($existingPage->id, $pageName, $content);
        }

        $this->log('info', "Updated page: {$pageName}");

        return 'updated';
    }

    /**
     * Create a new page in BookStack.
     *
     * @param  array<string, mixed>  $frontmatter
     */
    private function createNewPage(
        BookDTO $book,
        string $pageName,
        string $content,
        array $frontmatter
    ): string {
        $chapterId = null;

        // Check if we need to create/find a chapter
        if (isset($frontmatter['chapter']) && $this->autoCreateStructure) {
            $chapterId = $this->findOrCreateChapter($book->id, $frontmatter['chapter']);
        }

        if (! $this->dryRun) {
            $this->client->createPage($book->id, $pageName, $content, $chapterId);
        }

        $this->log('info', "Created page: {$pageName}");

        return 'created';
    }

    /**
     * Check if there's a conflict between local and remote.
     */
    private function hasConflict(PageDTO $remotePage, string $localPath): bool
    {
        if ($this->conflictResolution === ConflictResolution::LOCAL) {
            return false;
        }

        if ($this->conflictResolution === ConflictResolution::REMOTE) {
            return true;
        }

        if ($this->conflictResolution === ConflictResolution::NEWEST) {
            $localModified = File::lastModified($localPath);
            $remoteModified = strtotime($remotePage->updatedAt ?? '');

            return $remoteModified > $localModified;
        }

        // Manual - always flag as conflict
        return true;
    }

    /**
     * Resolve a sync conflict.
     */
    private function resolveConflict(
        PageDTO $existingPage,
        string $pageName,
        string $content,
        string $filePath
    ): string {
        switch ($this->conflictResolution) {
            case ConflictResolution::LOCAL:
                if (! $this->dryRun) {
                    $this->client->updatePage($existingPage->id, $pageName, $content);
                }
                $this->log('info', "Conflict resolved (local wins): {$pageName}");

                return 'updated';

            case ConflictResolution::REMOTE:
                $this->log('info', "Conflict resolved (remote wins): {$pageName}");

                return 'skipped';

            case ConflictResolution::NEWEST:
                $localModified = File::lastModified($filePath);
                $remoteModified = strtotime($existingPage->updatedAt ?? '');

                if ($localModified > $remoteModified) {
                    if (! $this->dryRun) {
                        $this->client->updatePage($existingPage->id, $pageName, $content);
                    }
                    $this->log('info', "Conflict resolved (local newer): {$pageName}");

                    return 'updated';
                }

                $this->log('info', "Conflict resolved (remote newer): {$pageName}");

                return 'skipped';

            case ConflictResolution::MANUAL:
            default:
                $this->log('warning', "Conflict requires manual resolution: {$pageName}");
                $this->syncLog['conflicts'][] = [
                    'local' => $filePath,
                    'remote_id' => $existingPage->id,
                    'page_name' => $pageName,
                ];

                return 'skipped';
        }
    }

    /**
     * Pull content from BookStack to local files.
     *
     * @return array{created: int, updated: int, skipped: int, errors: array<string>}
     */
    public function pullBookToDirectory(int $bookId, string $localPath): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $book = $this->client->getBook($bookId);
        $this->log('info', "Starting pull from book: {$book->name}");

        // Ensure directory exists
        if (! $this->dryRun && ! File::isDirectory($localPath)) {
            File::makeDirectory($localPath, 0755, true);
        }

        // Get all pages
        $pages = $this->client->listPages(500);
        $bookPages = array_filter($pages, fn (PageDTO $p) => $p->bookId === $bookId);

        foreach ($bookPages as $page) {
            try {
                $filePath = $this->pageToFilePath($page, $localPath);
                $content = $this->client->exportPage($page->id);

                // Convert from BookStack format
                $content = $this->parser->parseFromBookStack($content);

                // Add frontmatter
                $frontmatter = [
                    'title' => $page->name,
                    'bookstack_id' => $page->id,
                ];
                if ($page->chapterId) {
                    $frontmatter['chapter_id'] = $page->chapterId;
                }

                $content = $this->parser->addFrontmatter($content, $frontmatter);

                if (! $this->dryRun) {
                    // Ensure directory exists
                    $dir = dirname($filePath);
                    if (! File::isDirectory($dir)) {
                        File::makeDirectory($dir, 0755, true);
                    }

                    File::put($filePath, $content);
                }

                $action = File::exists($filePath) ? 'updated' : 'created';
                $result[$action]++;
                $this->log('info', "{$action} local file: {$filePath}");
            } catch (\Throwable $e) {
                $result['errors'][] = "{$page->name}: {$e->getMessage()}";
                $this->log('error', "Error pulling {$page->name}: {$e->getMessage()}");
            }
        }

        return $result;
    }

    /**
     * Get all pages from a book.
     *
     * @return array<PageDTO>
     */
    private function getBookPages(int $bookId): array
    {
        $allPages = $this->client->listPages(500);

        return array_filter($allPages, fn (PageDTO $page) => $page->bookId === $bookId);
    }

    /**
     * Find existing page by name.
     *
     * @param  array<PageDTO>  $pages
     */
    private function findExistingPage(string $name, array $pages): ?PageDTO
    {
        foreach ($pages as $page) {
            if (mb_strtolower($page->name) === mb_strtolower($name)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Find or create a chapter.
     */
    private function findOrCreateChapter(int $bookId, string $chapterName): int
    {
        // Search for existing chapter
        $chapters = $this->client->listChapters(500);
        foreach ($chapters as $chapter) {
            if ($chapter->bookId === $bookId && mb_strtolower($chapter->name) === mb_strtolower($chapterName)) {
                return $chapter->id;
            }
        }

        // Create new chapter
        if (! $this->dryRun) {
            $chapter = $this->client->createChapter($bookId, $chapterName);

            return $chapter->id;
        }

        return 0;
    }

    /**
     * Get all Markdown files from a directory.
     *
     * @return array<string>
     */
    private function getMarkdownFiles(string $path): array
    {
        $files = File::allFiles($path);

        return array_values(array_filter(
            array_map(fn ($file) => $file->getPathname(), $files),
            fn (string $file) => preg_match('/\.md$/i', $file)
        ));
    }

    /**
     * Get page name from filename.
     */
    private function getPageNameFromFile(string $filePath): string
    {
        $filename = pathinfo($filePath, PATHINFO_FILENAME);

        // Convert kebab-case or snake_case to Title Case
        $name = preg_replace('/[-_]+/', ' ', $filename) ?? $filename;

        return ucwords($name);
    }

    /**
     * Convert a page to a local file path.
     */
    private function pageToFilePath(PageDTO $page, string $basePath): string
    {
        $slug = $page->slug ?? $this->parser->generateAnchorFromHeading($page->name ?? 'untitled');

        $path = rtrim($basePath, '/');

        // Add chapter subdirectory if applicable
        if ($page->chapterSlug) {
            $path .= '/'.$page->chapterSlug;
        }

        return "{$path}/{$slug}.md";
    }

    /**
     * Log a message.
     */
    private function log(string $level, string $message): void
    {
        $this->syncLog[] = [
            'level' => $level,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($this->logging) {
            Log::channel(config('bookstack-sync.logging.channel', 'stack'))->$level($message);
        }
    }
}
