<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Facades;

use AichaDigital\BookStackSync\BookStackSync as BookStackSyncClass;
use AichaDigital\BookStackSync\Contracts\BookStackClientInterface;
use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\ChapterDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\DTOs\SearchResultDTO;
use AichaDigital\BookStackSync\DTOs\ShelfDTO;
use AichaDigital\BookStackSync\Enums\ExportFormat;
use AichaDigital\BookStackSync\Parsers\BookmarkConverter;
use AichaDigital\BookStackSync\Parsers\MarkdownParser;
use AichaDigital\BookStackSync\Services\SyncService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static BookStackClientInterface client()
 * @method static MarkdownParser parser()
 * @method static BookmarkConverter bookmarkConverter()
 * @method static array<ShelfDTO> shelves(int $count = 100, int $offset = 0)
 * @method static ShelfDTO shelf(int $id)
 * @method static ShelfDTO createShelf(string $name, ?string $description = null, array $bookIds = [])
 * @method static array<BookDTO> books(int $count = 100, int $offset = 0)
 * @method static BookDTO book(int $id)
 * @method static BookDTO createBook(string $name, ?string $description = null)
 * @method static string exportBook(int $id, ExportFormat $format = ExportFormat::MARKDOWN)
 * @method static array<ChapterDTO> chapters(int $count = 100, int $offset = 0)
 * @method static ChapterDTO chapter(int $id)
 * @method static ChapterDTO createChapter(int $bookId, string $name, ?string $description = null)
 * @method static array<PageDTO> pages(int $count = 100, int $offset = 0)
 * @method static PageDTO page(int $id)
 * @method static PageDTO createPage(int $bookId, string $name, string $content, ?int $chapterId = null, bool $isMarkdown = true)
 * @method static PageDTO updatePage(int $id, ?string $name = null, ?string $content = null, bool $isMarkdown = true)
 * @method static bool deletePage(int $id)
 * @method static string exportPage(int $id, ExportFormat $format = ExportFormat::MARKDOWN)
 * @method static array<SearchResultDTO> search(string $query, int $count = 100, int $offset = 0)
 * @method static SyncService sync()
 * @method static array pushToBook(string $localPath, int $bookId)
 * @method static array pullFromBook(int $bookId, string $localPath)
 * @method static string encodeBookmark(string $bookmark)
 * @method static string decodeBookmark(string $bookmark)
 * @method static string getBaseUrl()
 *
 * @see BookStackSyncClass
 */
class BookStackSync extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BookStackSyncClass::class;
    }
}
