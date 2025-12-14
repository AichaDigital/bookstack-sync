<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync;

use AichaDigital\BookStackSync\Api\BookStackClient;
use AichaDigital\BookStackSync\Contracts\BookStackClientInterface;
use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\ChapterDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\DTOs\SearchResultDTO;
use AichaDigital\BookStackSync\DTOs\ShelfDTO;
use AichaDigital\BookStackSync\Enums\ConflictResolution;
use AichaDigital\BookStackSync\Enums\ExportFormat;
use AichaDigital\BookStackSync\Enums\SyncDirection;
use AichaDigital\BookStackSync\Parsers\BookmarkConverter;
use AichaDigital\BookStackSync\Parsers\MarkdownParser;
use AichaDigital\BookStackSync\Services\SyncService;

/**
 * Main facade class for BookStack synchronization.
 *
 * Provides a fluent API for working with BookStack wiki from Laravel.
 */
class BookStackSync
{
    private BookStackClientInterface $client;

    private MarkdownParser $parser;

    private BookmarkConverter $bookmarkConverter;

    private ?SyncService $syncService = null;

    public function __construct(?BookStackClientInterface $client = null)
    {
        $this->client = $client ?? $this->createDefaultClient();
        $this->parser = new MarkdownParser(
            config('bookstack-sync.markdown.convert_bookmarks', true),
            config('bookstack-sync.markdown.encoding', 'UTF-8')
        );
        $this->bookmarkConverter = new BookmarkConverter(
            config('bookstack-sync.markdown.encoding', 'UTF-8')
        );
    }

    /**
     * Get the API client instance.
     */
    public function client(): BookStackClientInterface
    {
        return $this->client;
    }

    /**
     * Get the Markdown parser instance.
     */
    public function parser(): MarkdownParser
    {
        return $this->parser;
    }

    /**
     * Get the bookmark converter instance.
     */
    public function bookmarkConverter(): BookmarkConverter
    {
        return $this->bookmarkConverter;
    }

    // =========================================================================
    // Shelf Operations
    // =========================================================================

    /**
     * @return array<ShelfDTO>
     */
    public function shelves(int $count = 100, int $offset = 0): array
    {
        return $this->client->listShelves($count, $offset);
    }

    public function shelf(int $id): ShelfDTO
    {
        return $this->client->getShelf($id);
    }

    /**
     * @param  array<int>  $bookIds
     */
    public function createShelf(string $name, ?string $description = null, array $bookIds = []): ShelfDTO
    {
        return $this->client->createShelf($name, $description, $bookIds);
    }

    // =========================================================================
    // Book Operations
    // =========================================================================

    /**
     * @return array<BookDTO>
     */
    public function books(int $count = 100, int $offset = 0): array
    {
        return $this->client->listBooks($count, $offset);
    }

    public function book(int $id): BookDTO
    {
        return $this->client->getBook($id);
    }

    public function createBook(string $name, ?string $description = null): BookDTO
    {
        return $this->client->createBook($name, $description);
    }

    public function exportBook(int $id, ExportFormat $format = ExportFormat::MARKDOWN): string
    {
        return $this->client->exportBook($id, $format);
    }

    // =========================================================================
    // Chapter Operations
    // =========================================================================

    /**
     * @return array<ChapterDTO>
     */
    public function chapters(int $count = 100, int $offset = 0): array
    {
        return $this->client->listChapters($count, $offset);
    }

    public function chapter(int $id): ChapterDTO
    {
        return $this->client->getChapter($id);
    }

    public function createChapter(int $bookId, string $name, ?string $description = null): ChapterDTO
    {
        return $this->client->createChapter($bookId, $name, $description);
    }

    // =========================================================================
    // Page Operations
    // =========================================================================

    /**
     * @return array<PageDTO>
     */
    public function pages(int $count = 100, int $offset = 0): array
    {
        return $this->client->listPages($count, $offset);
    }

    public function page(int $id): PageDTO
    {
        return $this->client->getPage($id);
    }

    public function createPage(
        int $bookId,
        string $name,
        string $content,
        ?int $chapterId = null,
        bool $isMarkdown = true
    ): PageDTO {
        // Convert content if markdown
        if ($isMarkdown) {
            $content = $this->parser->parseForBookStack($content);
        }

        return $this->client->createPage($bookId, $name, $content, $chapterId, $isMarkdown);
    }

    public function updatePage(
        int $id,
        ?string $name = null,
        ?string $content = null,
        bool $isMarkdown = true
    ): PageDTO {
        // Convert content if markdown
        if ($isMarkdown && $content !== null) {
            $content = $this->parser->parseForBookStack($content);
        }

        return $this->client->updatePage($id, $name, $content, $isMarkdown);
    }

    public function deletePage(int $id): bool
    {
        return $this->client->deletePage($id);
    }

    public function exportPage(int $id, ExportFormat $format = ExportFormat::MARKDOWN): string
    {
        $content = $this->client->exportPage($id, $format);

        // Convert from BookStack format if markdown
        if ($format === ExportFormat::MARKDOWN) {
            $content = $this->parser->parseFromBookStack($content);
        }

        return $content;
    }

    // =========================================================================
    // Search Operations
    // =========================================================================

    /**
     * @return array<SearchResultDTO>
     */
    public function search(string $query, int $count = 100, int $offset = 0): array
    {
        return $this->client->search($query, $count, $offset);
    }

    // =========================================================================
    // Sync Operations
    // =========================================================================

    /**
     * Get or create the sync service.
     */
    public function sync(): SyncService
    {
        if ($this->syncService === null) {
            $this->syncService = new SyncService(
                $this->client,
                $this->parser,
                SyncDirection::tryFrom(config('bookstack-sync.sync.direction', 'push')) ?? SyncDirection::PUSH,
                ConflictResolution::tryFrom(config('bookstack-sync.sync.conflict_resolution', 'manual')) ?? ConflictResolution::MANUAL,
                config('bookstack-sync.sync.auto_create_structure', true),
                config('bookstack-sync.logging.enabled', true)
            );

            if (config('bookstack-sync.sync.dry_run', false)) {
                $this->syncService->setDryRun(true);
            }
        }

        return $this->syncService;
    }

    /**
     * Push local Markdown directory to a BookStack book.
     *
     * @return array{created: int, updated: int, deleted: int, skipped: int, errors: array<string>}
     */
    public function pushToBook(string $localPath, int $bookId): array
    {
        return $this->sync()->syncDirectoryToBook($localPath, $bookId);
    }

    /**
     * Pull a BookStack book to local Markdown files.
     *
     * @return array{created: int, updated: int, skipped: int, errors: array<string>}
     */
    public function pullFromBook(int $bookId, string $localPath): array
    {
        return $this->sync()->pullBookToDirectory($bookId, $localPath);
    }

    // =========================================================================
    // Bookmark Conversion Helpers
    // =========================================================================

    /**
     * Convert a bookmark to BookStack URL-encoded format.
     */
    public function encodeBookmark(string $bookmark): string
    {
        return $this->bookmarkConverter->toBookStack($bookmark);
    }

    /**
     * Decode a BookStack URL-encoded bookmark.
     */
    public function decodeBookmark(string $bookmark): string
    {
        return $this->bookmarkConverter->fromBookStack($bookmark);
    }

    /**
     * Get the base URL configured for BookStack.
     */
    public function getBaseUrl(): string
    {
        return config('bookstack-sync.api.url', '');
    }

    /**
     * Create the default BookStack client from config.
     */
    private function createDefaultClient(): BookStackClientInterface
    {
        return new BookStackClient(
            config('bookstack-sync.api.url', ''),
            config('bookstack-sync.api.token_id', ''),
            config('bookstack-sync.api.token_secret', ''),
            config('bookstack-sync.api.timeout', 30)
        );
    }
}
