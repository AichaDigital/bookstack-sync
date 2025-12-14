<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Contracts;

use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\ChapterDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\DTOs\SearchResultDTO;
use AichaDigital\BookStackSync\DTOs\ShelfDTO;
use AichaDigital\BookStackSync\Enums\ExportFormat;

interface BookStackClientInterface
{
    // Shelves
    /**
     * @return array<ShelfDTO>
     */
    public function listShelves(int $count = 100, int $offset = 0): array;

    public function getShelf(int $id): ShelfDTO;

    public function createShelf(string $name, ?string $description = null, array $bookIds = []): ShelfDTO;

    public function updateShelf(int $id, ?string $name = null, ?string $description = null, ?array $bookIds = null): ShelfDTO;

    public function deleteShelf(int $id): bool;

    // Books
    /**
     * @return array<BookDTO>
     */
    public function listBooks(int $count = 100, int $offset = 0): array;

    public function getBook(int $id): BookDTO;

    public function createBook(string $name, ?string $description = null): BookDTO;

    public function updateBook(int $id, ?string $name = null, ?string $description = null): BookDTO;

    public function deleteBook(int $id): bool;

    public function exportBook(int $id, ExportFormat $format = ExportFormat::MARKDOWN): string;

    // Chapters
    /**
     * @return array<ChapterDTO>
     */
    public function listChapters(int $count = 100, int $offset = 0): array;

    public function getChapter(int $id): ChapterDTO;

    public function createChapter(int $bookId, string $name, ?string $description = null): ChapterDTO;

    public function updateChapter(int $id, ?string $name = null, ?string $description = null, ?int $bookId = null): ChapterDTO;

    public function deleteChapter(int $id): bool;

    public function exportChapter(int $id, ExportFormat $format = ExportFormat::MARKDOWN): string;

    // Pages
    /**
     * @return array<PageDTO>
     */
    public function listPages(int $count = 100, int $offset = 0): array;

    public function getPage(int $id): PageDTO;

    public function createPage(int $bookId, string $name, string $content, ?int $chapterId = null, bool $isMarkdown = true): PageDTO;

    public function updatePage(int $id, ?string $name = null, ?string $content = null, bool $isMarkdown = true): PageDTO;

    public function deletePage(int $id): bool;

    public function exportPage(int $id, ExportFormat $format = ExportFormat::MARKDOWN): string;

    // Search
    /**
     * @return array<SearchResultDTO>
     */
    public function search(string $query, int $count = 100, int $offset = 0): array;
}
