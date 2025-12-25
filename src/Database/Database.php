<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Database;

use PDO;
use PDOException;

/**
 * SQLite database for local BookStack cache.
 *
 * Provides a local cache of the BookStack wiki structure to:
 * - Reduce API calls by caching shelves, books, chapters, pages
 * - Track synchronization state (synced_at, is_deleted)
 * - Map local files to remote pages (local_path)
 * - Detect content changes efficiently (content_hash)
 */
class Database
{
    private ?PDO $pdo = null;

    private string $dbPath;

    /**
     * SQL schema for the local cache database.
     */
    private const SCHEMA = <<<'SQL'
        -- Shelves
        CREATE TABLE IF NOT EXISTS shelves (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bookstack_id INTEGER UNIQUE NOT NULL,
            name TEXT NOT NULL,
            slug TEXT,
            description TEXT,
            is_deleted INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            synced_at TEXT
        );

        -- Books
        CREATE TABLE IF NOT EXISTS books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bookstack_id INTEGER UNIQUE NOT NULL,
            shelf_id INTEGER,
            name TEXT NOT NULL,
            slug TEXT,
            description TEXT,
            is_deleted INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            synced_at TEXT,
            FOREIGN KEY (shelf_id) REFERENCES shelves(id) ON DELETE SET NULL
        );

        -- Chapters
        CREATE TABLE IF NOT EXISTS chapters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bookstack_id INTEGER UNIQUE NOT NULL,
            book_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            slug TEXT,
            description TEXT,
            priority INTEGER DEFAULT 0,
            is_deleted INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            synced_at TEXT,
            FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
        );

        -- Pages
        CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bookstack_id INTEGER UNIQUE NOT NULL,
            book_id INTEGER NOT NULL,
            chapter_id INTEGER,
            name TEXT NOT NULL,
            slug TEXT,
            priority INTEGER DEFAULT 0,
            local_path TEXT,
            content_hash TEXT,
            remote_updated_at TEXT,
            is_deleted INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            synced_at TEXT,
            FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
            FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE SET NULL
        );

        -- Sync metadata
        CREATE TABLE IF NOT EXISTS sync_meta (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key TEXT UNIQUE NOT NULL,
            value TEXT,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        -- Indexes for performance
        CREATE INDEX IF NOT EXISTS idx_books_shelf ON books(shelf_id);
        CREATE INDEX IF NOT EXISTS idx_books_deleted ON books(is_deleted);
        CREATE INDEX IF NOT EXISTS idx_chapters_book ON chapters(book_id);
        CREATE INDEX IF NOT EXISTS idx_chapters_deleted ON chapters(is_deleted);
        CREATE INDEX IF NOT EXISTS idx_pages_book ON pages(book_id);
        CREATE INDEX IF NOT EXISTS idx_pages_chapter ON pages(chapter_id);
        CREATE INDEX IF NOT EXISTS idx_pages_deleted ON pages(is_deleted);
        CREATE INDEX IF NOT EXISTS idx_pages_local_path ON pages(local_path);
        CREATE INDEX IF NOT EXISTS idx_pages_content_hash ON pages(content_hash);
        SQL;

    public function __construct(?string $dbPath = null)
    {
        $this->dbPath = $dbPath ?? $this->getDefaultPath();
    }

    /**
     * Get the default database path.
     */
    private function getDefaultPath(): string
    {
        if (function_exists('storage_path')) {
            return storage_path('bookstack-sync.sqlite');
        }

        return getcwd().'/storage/bookstack-sync.sqlite';
    }

    /**
     * Get the database path.
     */
    public function getPath(): string
    {
        return $this->dbPath;
    }

    /**
     * Connect to the database and initialize schema.
     */
    public function connect(): self
    {
        if ($this->pdo !== null) {
            return $this;
        }

        // Ensure directory exists
        $dir = dirname($this->dbPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $this->pdo = new PDO("sqlite:{$this->dbPath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Enable foreign keys
            $this->pdo->exec('PRAGMA foreign_keys = ON');

            // Initialize schema
            $this->pdo->exec(self::SCHEMA);
        } catch (PDOException $e) {
            throw new \RuntimeException("Failed to connect to database: {$e->getMessage()}", 0, $e);
        }

        return $this;
    }

    /**
     * Disconnect from database.
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Check if connected.
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * Get the PDO instance.
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        if ($this->pdo === null) {
            throw new \RuntimeException('Database connection failed');
        }

        return $this->pdo;
    }

    // =========================================================================
    // Shelf Operations
    // =========================================================================

    /**
     * Upsert a shelf.
     */
    public function upsertShelf(
        int $bookstackId,
        string $name,
        ?string $slug = null,
        ?string $description = null
    ): int {
        $now = $this->now();

        $stmt = $this->getPdo()->prepare(<<<'SQL'
            INSERT INTO shelves (bookstack_id, name, slug, description, synced_at, is_deleted)
            VALUES (:bookstack_id, :name, :slug, :description, :synced_at, 0)
            ON CONFLICT(bookstack_id) DO UPDATE SET
                name = excluded.name,
                slug = excluded.slug,
                description = excluded.description,
                synced_at = excluded.synced_at,
                updated_at = :updated_at,
                is_deleted = 0
            SQL);

        $stmt->execute([
            'bookstack_id' => $bookstackId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'synced_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->getShelfIdByBookstackId($bookstackId) ?? 0;
    }

    /**
     * Get shelf by BookStack ID.
     *
     * @return array<string, mixed>|null
     */
    public function getShelfByBookstackId(int $bookstackId): ?array
    {
        $stmt = $this->getPdo()->prepare('SELECT * FROM shelves WHERE bookstack_id = :id');
        $stmt->execute(['id' => $bookstackId]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    /**
     * Get local shelf ID by BookStack ID.
     */
    public function getShelfIdByBookstackId(int $bookstackId): ?int
    {
        $shelf = $this->getShelfByBookstackId($bookstackId);

        return $shelf ? (int) $shelf['id'] : null;
    }

    /**
     * List all shelves.
     *
     * @return array<array<string, mixed>>
     */
    public function listShelves(bool $includeDeleted = false): array
    {
        $sql = 'SELECT * FROM shelves';
        if (! $includeDeleted) {
            $sql .= ' WHERE is_deleted = 0';
        }
        $sql .= ' ORDER BY name';

        $stmt = $this->getPdo()->query($sql);

        return $stmt !== false ? $stmt->fetchAll() : [];
    }

    // =========================================================================
    // Book Operations
    // =========================================================================

    /**
     * Upsert a book.
     */
    public function upsertBook(
        int $bookstackId,
        string $name,
        ?string $slug = null,
        ?string $description = null,
        ?int $shelfBookstackId = null
    ): int {
        $now = $this->now();
        $shelfId = $shelfBookstackId ? $this->getShelfIdByBookstackId($shelfBookstackId) : null;

        $stmt = $this->getPdo()->prepare(<<<'SQL'
            INSERT INTO books (bookstack_id, shelf_id, name, slug, description, synced_at, is_deleted)
            VALUES (:bookstack_id, :shelf_id, :name, :slug, :description, :synced_at, 0)
            ON CONFLICT(bookstack_id) DO UPDATE SET
                shelf_id = excluded.shelf_id,
                name = excluded.name,
                slug = excluded.slug,
                description = excluded.description,
                synced_at = excluded.synced_at,
                updated_at = :updated_at,
                is_deleted = 0
            SQL);

        $stmt->execute([
            'bookstack_id' => $bookstackId,
            'shelf_id' => $shelfId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'synced_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->getBookIdByBookstackId($bookstackId) ?? 0;
    }

    /**
     * Get book by BookStack ID.
     *
     * @return array<string, mixed>|null
     */
    public function getBookByBookstackId(int $bookstackId): ?array
    {
        $stmt = $this->getPdo()->prepare('SELECT * FROM books WHERE bookstack_id = :id');
        $stmt->execute(['id' => $bookstackId]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    /**
     * Get local book ID by BookStack ID.
     */
    public function getBookIdByBookstackId(int $bookstackId): ?int
    {
        $book = $this->getBookByBookstackId($bookstackId);

        return $book ? (int) $book['id'] : null;
    }

    /**
     * List all books.
     *
     * @return array<array<string, mixed>>
     */
    public function listBooks(bool $includeDeleted = false): array
    {
        $sql = 'SELECT * FROM books';
        if (! $includeDeleted) {
            $sql .= ' WHERE is_deleted = 0';
        }
        $sql .= ' ORDER BY name';

        $stmt = $this->getPdo()->query($sql);

        return $stmt !== false ? $stmt->fetchAll() : [];
    }

    // =========================================================================
    // Chapter Operations
    // =========================================================================

    /**
     * Upsert a chapter.
     */
    public function upsertChapter(
        int $bookstackId,
        int $bookBookstackId,
        string $name,
        ?string $slug = null,
        ?string $description = null,
        int $priority = 0
    ): int {
        $now = $this->now();
        $bookId = $this->getBookIdByBookstackId($bookBookstackId);

        if ($bookId === null) {
            throw new \RuntimeException("Book with BookStack ID {$bookBookstackId} not found in local cache");
        }

        $stmt = $this->getPdo()->prepare(<<<'SQL'
            INSERT INTO chapters (bookstack_id, book_id, name, slug, description, priority, synced_at, is_deleted)
            VALUES (:bookstack_id, :book_id, :name, :slug, :description, :priority, :synced_at, 0)
            ON CONFLICT(bookstack_id) DO UPDATE SET
                book_id = excluded.book_id,
                name = excluded.name,
                slug = excluded.slug,
                description = excluded.description,
                priority = excluded.priority,
                synced_at = excluded.synced_at,
                updated_at = :updated_at,
                is_deleted = 0
            SQL);

        $stmt->execute([
            'bookstack_id' => $bookstackId,
            'book_id' => $bookId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'priority' => $priority,
            'synced_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->getChapterIdByBookstackId($bookstackId) ?? 0;
    }

    /**
     * Get chapter by BookStack ID.
     *
     * @return array<string, mixed>|null
     */
    public function getChapterByBookstackId(int $bookstackId): ?array
    {
        $stmt = $this->getPdo()->prepare('SELECT * FROM chapters WHERE bookstack_id = :id');
        $stmt->execute(['id' => $bookstackId]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    /**
     * Get local chapter ID by BookStack ID.
     */
    public function getChapterIdByBookstackId(int $bookstackId): ?int
    {
        $chapter = $this->getChapterByBookstackId($bookstackId);

        return $chapter ? (int) $chapter['id'] : null;
    }

    /**
     * List chapters, optionally filtered by book.
     *
     * @return array<array<string, mixed>>
     */
    public function listChapters(?int $bookBookstackId = null, bool $includeDeleted = false): array
    {
        $params = [];
        $sql = 'SELECT c.*, b.bookstack_id as book_bookstack_id FROM chapters c JOIN books b ON c.book_id = b.id';

        $conditions = [];
        if (! $includeDeleted) {
            $conditions[] = 'c.is_deleted = 0';
        }
        if ($bookBookstackId !== null) {
            $conditions[] = 'b.bookstack_id = :book_id';
            $params['book_id'] = $bookBookstackId;
        }

        if (! empty($conditions)) {
            $sql .= ' WHERE '.implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY c.priority, c.name';

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // =========================================================================
    // Page Operations
    // =========================================================================

    /**
     * Upsert a page.
     */
    public function upsertPage(
        int $bookstackId,
        int $bookBookstackId,
        string $name,
        ?string $slug = null,
        ?int $chapterBookstackId = null,
        int $priority = 0,
        ?string $localPath = null,
        ?string $contentHash = null,
        ?string $remoteUpdatedAt = null
    ): int {
        $now = $this->now();
        $bookId = $this->getBookIdByBookstackId($bookBookstackId);

        if ($bookId === null) {
            throw new \RuntimeException("Book with BookStack ID {$bookBookstackId} not found in local cache");
        }

        $chapterId = $chapterBookstackId ? $this->getChapterIdByBookstackId($chapterBookstackId) : null;

        $stmt = $this->getPdo()->prepare(<<<'SQL'
            INSERT INTO pages (bookstack_id, book_id, chapter_id, name, slug, priority, local_path, content_hash, remote_updated_at, synced_at, is_deleted)
            VALUES (:bookstack_id, :book_id, :chapter_id, :name, :slug, :priority, :local_path, :content_hash, :remote_updated_at, :synced_at, 0)
            ON CONFLICT(bookstack_id) DO UPDATE SET
                book_id = excluded.book_id,
                chapter_id = excluded.chapter_id,
                name = excluded.name,
                slug = excluded.slug,
                priority = excluded.priority,
                local_path = COALESCE(excluded.local_path, pages.local_path),
                content_hash = COALESCE(excluded.content_hash, pages.content_hash),
                remote_updated_at = COALESCE(excluded.remote_updated_at, pages.remote_updated_at),
                synced_at = excluded.synced_at,
                updated_at = :updated_at,
                is_deleted = 0
            SQL);

        $stmt->execute([
            'bookstack_id' => $bookstackId,
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'name' => $name,
            'slug' => $slug,
            'priority' => $priority,
            'local_path' => $localPath,
            'content_hash' => $contentHash,
            'remote_updated_at' => $remoteUpdatedAt,
            'synced_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->getPageIdByBookstackId($bookstackId) ?? 0;
    }

    /**
     * Get page by BookStack ID.
     *
     * @return array<string, mixed>|null
     */
    public function getPageByBookstackId(int $bookstackId): ?array
    {
        $stmt = $this->getPdo()->prepare('SELECT * FROM pages WHERE bookstack_id = :id');
        $stmt->execute(['id' => $bookstackId]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    /**
     * Get local page ID by BookStack ID.
     */
    public function getPageIdByBookstackId(int $bookstackId): ?int
    {
        $page = $this->getPageByBookstackId($bookstackId);

        return $page ? (int) $page['id'] : null;
    }

    /**
     * Get page by local path.
     *
     * @return array<string, mixed>|null
     */
    public function getPageByLocalPath(string $localPath): ?array
    {
        $stmt = $this->getPdo()->prepare('SELECT * FROM pages WHERE local_path = :path AND is_deleted = 0');
        $stmt->execute(['path' => $localPath]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    /**
     * Update page local path.
     */
    public function updatePageLocalPath(int $bookstackId, string $localPath): bool
    {
        $stmt = $this->getPdo()->prepare(<<<'SQL'
            UPDATE pages SET local_path = :path, updated_at = :updated_at WHERE bookstack_id = :id
            SQL);

        return $stmt->execute([
            'path' => $localPath,
            'updated_at' => $this->now(),
            'id' => $bookstackId,
        ]);
    }

    /**
     * Update page content hash.
     */
    public function updatePageContentHash(int $bookstackId, string $contentHash): bool
    {
        $stmt = $this->getPdo()->prepare(<<<'SQL'
            UPDATE pages SET content_hash = :hash, updated_at = :updated_at WHERE bookstack_id = :id
            SQL);

        return $stmt->execute([
            'hash' => $contentHash,
            'updated_at' => $this->now(),
            'id' => $bookstackId,
        ]);
    }

    /**
     * List pages with optional filters.
     *
     * @return array<array<string, mixed>>
     */
    public function listPages(
        ?int $bookBookstackId = null,
        ?int $chapterBookstackId = null,
        bool $includeDeleted = false
    ): array {
        $params = [];
        $sql = <<<'SQL'
            SELECT p.*, b.bookstack_id as book_bookstack_id, c.bookstack_id as chapter_bookstack_id
            FROM pages p
            JOIN books b ON p.book_id = b.id
            LEFT JOIN chapters c ON p.chapter_id = c.id
            SQL;

        $conditions = [];
        if (! $includeDeleted) {
            $conditions[] = 'p.is_deleted = 0';
        }
        if ($bookBookstackId !== null) {
            $conditions[] = 'b.bookstack_id = :book_id';
            $params['book_id'] = $bookBookstackId;
        }
        if ($chapterBookstackId !== null) {
            $conditions[] = 'c.bookstack_id = :chapter_id';
            $params['chapter_id'] = $chapterBookstackId;
        }

        if (! empty($conditions)) {
            $sql .= ' WHERE '.implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.priority, p.name';

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // =========================================================================
    // Mark Deleted Operations
    // =========================================================================

    /**
     * Mark shelves as deleted if not in active list.
     *
     * @param  array<int>  $activeBookstackIds
     */
    public function markDeletedShelves(array $activeBookstackIds): int
    {
        if (empty($activeBookstackIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($activeBookstackIds), '?'));
        $stmt = $this->getPdo()->prepare(<<<SQL
            UPDATE shelves SET is_deleted = 1, updated_at = ?
            WHERE bookstack_id NOT IN ({$placeholders}) AND is_deleted = 0
            SQL);

        $params = array_merge([$this->now()], $activeBookstackIds);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Mark books as deleted if not in active list.
     *
     * @param  array<int>  $activeBookstackIds
     */
    public function markDeletedBooks(array $activeBookstackIds): int
    {
        if (empty($activeBookstackIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($activeBookstackIds), '?'));
        $stmt = $this->getPdo()->prepare(<<<SQL
            UPDATE books SET is_deleted = 1, updated_at = ?
            WHERE bookstack_id NOT IN ({$placeholders}) AND is_deleted = 0
            SQL);

        $params = array_merge([$this->now()], $activeBookstackIds);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Mark chapters as deleted if not in active list.
     *
     * @param  array<int>  $activeBookstackIds
     */
    public function markDeletedChapters(array $activeBookstackIds): int
    {
        if (empty($activeBookstackIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($activeBookstackIds), '?'));
        $stmt = $this->getPdo()->prepare(<<<SQL
            UPDATE chapters SET is_deleted = 1, updated_at = ?
            WHERE bookstack_id NOT IN ({$placeholders}) AND is_deleted = 0
            SQL);

        $params = array_merge([$this->now()], $activeBookstackIds);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Mark pages as deleted if not in active list.
     *
     * @param  array<int>  $activeBookstackIds
     */
    public function markDeletedPages(array $activeBookstackIds): int
    {
        if (empty($activeBookstackIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($activeBookstackIds), '?'));
        $stmt = $this->getPdo()->prepare(<<<SQL
            UPDATE pages SET is_deleted = 1, updated_at = ?
            WHERE bookstack_id NOT IN ({$placeholders}) AND is_deleted = 0
            SQL);

        $params = array_merge([$this->now()], $activeBookstackIds);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    // =========================================================================
    // Sync Metadata Operations
    // =========================================================================

    /**
     * Set a sync metadata value.
     */
    public function setMeta(string $key, string $value): void
    {
        $stmt = $this->getPdo()->prepare(<<<'SQL'
            INSERT INTO sync_meta (key, value, updated_at)
            VALUES (:key, :value, :updated_at)
            ON CONFLICT(key) DO UPDATE SET
                value = excluded.value,
                updated_at = excluded.updated_at
            SQL);

        $stmt->execute([
            'key' => $key,
            'value' => $value,
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * Get a sync metadata value.
     */
    public function getMeta(string $key): ?string
    {
        $stmt = $this->getPdo()->prepare('SELECT value FROM sync_meta WHERE key = :key');
        $stmt->execute(['key' => $key]);
        $result = $stmt->fetch();

        return $result !== false ? $result['value'] : null;
    }

    /**
     * Update last sync timestamp.
     */
    public function updateLastSync(): void
    {
        $this->setMeta('last_sync', $this->now());
    }

    /**
     * Get last sync timestamp.
     */
    public function getLastSync(): ?string
    {
        return $this->getMeta('last_sync');
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    /**
     * Get database statistics.
     *
     * @return array<string, array{total: int, active: int, deleted: int}>
     */
    public function getStats(): array
    {
        $stats = [];
        $tables = ['shelves', 'books', 'chapters', 'pages'];

        foreach ($tables as $table) {
            $stmt = $this->getPdo()->query(<<<SQL
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN is_deleted = 0 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_deleted = 1 THEN 1 ELSE 0 END) as deleted
                FROM {$table}
                SQL);
            $result = $stmt !== false ? $stmt->fetch() : false;
            $stats[$table] = [
                'total' => (int) ($result['total'] ?? 0),
                'active' => (int) ($result['active'] ?? 0),
                'deleted' => (int) ($result['deleted'] ?? 0),
            ];
        }

        return $stats;
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Generate content hash from string.
     */
    public static function generateContentHash(string $content): string
    {
        return hash('xxh3', $content);
    }

    /**
     * Check if database file exists.
     */
    public function exists(): bool
    {
        return file_exists($this->dbPath);
    }

    /**
     * Delete the database file.
     */
    public function delete(): bool
    {
        $this->disconnect();

        if ($this->exists()) {
            return unlink($this->dbPath);
        }

        return true;
    }

    /**
     * Get current timestamp in ISO 8601 format.
     */
    private function now(): string
    {
        return date('c');
    }

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Commit a transaction.
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Rollback a transaction.
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }
}
