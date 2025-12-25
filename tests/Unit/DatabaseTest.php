<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\Database\Database;

beforeEach(function () {
    // Use a temporary database for testing
    $this->dbPath = sys_get_temp_dir().'/bookstack-sync-test-'.uniqid().'.sqlite';
    $this->database = new Database($this->dbPath);
});

afterEach(function () {
    $this->database->disconnect();
    if (file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

describe('Database Connection', function () {
    it('connects to database and creates schema', function () {
        $this->database->connect();

        expect($this->database->isConnected())->toBeTrue();
        expect($this->database->exists())->toBeTrue();
    });

    it('returns the database path', function () {
        expect($this->database->getPath())->toBe($this->dbPath);
    });

    it('disconnects from database', function () {
        $this->database->connect();
        $this->database->disconnect();

        expect($this->database->isConnected())->toBeFalse();
    });

    it('deletes database file', function () {
        $this->database->connect();
        $this->database->disconnect();

        expect($this->database->delete())->toBeTrue();
        expect($this->database->exists())->toBeFalse();
    });
});

describe('Shelf Operations', function () {
    it('upserts a shelf', function () {
        $this->database->connect();

        $id = $this->database->upsertShelf(1, 'Test Shelf', 'test-shelf', 'A test shelf');

        expect($id)->toBeGreaterThan(0);

        $shelf = $this->database->getShelfByBookstackId(1);
        expect($shelf)->not->toBeNull();
        expect($shelf['name'])->toBe('Test Shelf');
        expect($shelf['slug'])->toBe('test-shelf');
    });

    it('updates existing shelf on conflict', function () {
        $this->database->connect();

        $this->database->upsertShelf(1, 'Original Name');
        $this->database->upsertShelf(1, 'Updated Name');

        $shelf = $this->database->getShelfByBookstackId(1);
        expect($shelf['name'])->toBe('Updated Name');
    });

    it('lists shelves', function () {
        $this->database->connect();

        $this->database->upsertShelf(1, 'Shelf A');
        $this->database->upsertShelf(2, 'Shelf B');

        $shelves = $this->database->listShelves();

        expect($shelves)->toHaveCount(2);
    });
});

describe('Book Operations', function () {
    it('upserts a book', function () {
        $this->database->connect();

        $id = $this->database->upsertBook(10, 'Test Book', 'test-book', 'A test book');

        expect($id)->toBeGreaterThan(0);

        $book = $this->database->getBookByBookstackId(10);
        expect($book)->not->toBeNull();
        expect($book['name'])->toBe('Test Book');
    });

    it('lists books excluding deleted', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Book A');
        $this->database->upsertBook(2, 'Book B');
        $this->database->markDeletedBooks([1]); // Mark book 2 as deleted

        $books = $this->database->listBooks(false);

        expect($books)->toHaveCount(1);
        expect($books[0]['name'])->toBe('Book A');
    });

    it('lists books including deleted', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Book A');
        $this->database->upsertBook(2, 'Book B');
        $this->database->markDeletedBooks([1]);

        $books = $this->database->listBooks(true);

        expect($books)->toHaveCount(2);
    });
});

describe('Chapter Operations', function () {
    it('upserts a chapter', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Test Book');
        $id = $this->database->upsertChapter(10, 1, 'Test Chapter', 'test-chapter');

        expect($id)->toBeGreaterThan(0);

        $chapter = $this->database->getChapterByBookstackId(10);
        expect($chapter)->not->toBeNull();
        expect($chapter['name'])->toBe('Test Chapter');
    });

    it('throws exception if book not found', function () {
        $this->database->connect();

        expect(fn () => $this->database->upsertChapter(10, 999, 'Test Chapter'))
            ->toThrow(RuntimeException::class);
    });

    it('lists chapters filtered by book', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Book A');
        $this->database->upsertBook(2, 'Book B');
        $this->database->upsertChapter(10, 1, 'Chapter in Book A');
        $this->database->upsertChapter(20, 2, 'Chapter in Book B');

        $chapters = $this->database->listChapters(1);

        expect($chapters)->toHaveCount(1);
        expect($chapters[0]['name'])->toBe('Chapter in Book A');
    });
});

describe('Page Operations', function () {
    it('upserts a page', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Test Book');
        $id = $this->database->upsertPage(100, 1, 'Test Page', 'test-page');

        expect($id)->toBeGreaterThan(0);

        $page = $this->database->getPageByBookstackId(100);
        expect($page)->not->toBeNull();
        expect($page['name'])->toBe('Test Page');
    });

    it('upserts a page with chapter', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Test Book');
        $this->database->upsertChapter(10, 1, 'Test Chapter');
        $id = $this->database->upsertPage(100, 1, 'Test Page', 'test-page', 10);

        $page = $this->database->getPageByBookstackId(100);
        expect($page['chapter_id'])->not->toBeNull();
    });

    it('updates page local path', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Test Book');
        $this->database->upsertPage(100, 1, 'Test Page');

        $this->database->updatePageLocalPath(100, '/path/to/file.md');

        $page = $this->database->getPageByBookstackId(100);
        expect($page['local_path'])->toBe('/path/to/file.md');
    });

    it('updates page content hash', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Test Book');
        $this->database->upsertPage(100, 1, 'Test Page');

        $hash = Database::generateContentHash('Test content');
        $this->database->updatePageContentHash(100, $hash);

        $page = $this->database->getPageByBookstackId(100);
        expect($page['content_hash'])->toBe($hash);
    });

    it('finds page by local path', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Test Book');
        $this->database->upsertPage(100, 1, 'Test Page', null, null, 0, '/path/to/file.md');

        $page = $this->database->getPageByLocalPath('/path/to/file.md');
        expect($page)->not->toBeNull();
        expect($page['bookstack_id'])->toBe(100);
    });

    it('lists pages filtered by book', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Book A');
        $this->database->upsertBook(2, 'Book B');
        $this->database->upsertPage(100, 1, 'Page in Book A');
        $this->database->upsertPage(200, 2, 'Page in Book B');

        $pages = $this->database->listPages(1);

        expect($pages)->toHaveCount(1);
        expect($pages[0]['name'])->toBe('Page in Book A');
    });
});

describe('Content Hash', function () {
    it('generates consistent content hash', function () {
        $content = 'Test content for hashing';

        $hash1 = Database::generateContentHash($content);
        $hash2 = Database::generateContentHash($content);

        expect($hash1)->toBe($hash2);
    });

    it('generates different hashes for different content', function () {
        $hash1 = Database::generateContentHash('Content A');
        $hash2 = Database::generateContentHash('Content B');

        expect($hash1)->not->toBe($hash2);
    });
});

describe('Mark Deleted Operations', function () {
    it('marks books as deleted when not in active list', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Book A');
        $this->database->upsertBook(2, 'Book B');
        $this->database->upsertBook(3, 'Book C');

        $deleted = $this->database->markDeletedBooks([1, 2]); // 3 not in list

        expect($deleted)->toBe(1);

        $book = $this->database->getBookByBookstackId(3);
        expect($book['is_deleted'])->toBe(1);
    });

    it('marks pages as deleted when not in active list', function () {
        $this->database->connect();

        $this->database->upsertBook(1, 'Test Book');
        $this->database->upsertPage(100, 1, 'Page A');
        $this->database->upsertPage(200, 1, 'Page B');

        $deleted = $this->database->markDeletedPages([100]);

        expect($deleted)->toBe(1);
    });
});

describe('Sync Metadata', function () {
    it('sets and gets metadata', function () {
        $this->database->connect();

        $this->database->setMeta('test_key', 'test_value');

        expect($this->database->getMeta('test_key'))->toBe('test_value');
    });

    it('updates last sync timestamp', function () {
        $this->database->connect();

        $this->database->updateLastSync();

        $lastSync = $this->database->getLastSync();
        expect($lastSync)->not->toBeNull();
    });

    it('returns null for non-existent metadata', function () {
        $this->database->connect();

        expect($this->database->getMeta('non_existent'))->toBeNull();
    });
});

describe('Statistics', function () {
    it('returns database statistics', function () {
        $this->database->connect();

        $this->database->upsertShelf(1, 'Shelf');
        $this->database->upsertBook(1, 'Book A');
        $this->database->upsertBook(2, 'Book B');
        $this->database->upsertChapter(1, 1, 'Chapter');
        $this->database->upsertPage(1, 1, 'Page');
        $this->database->markDeletedBooks([1]); // Mark book 2 as deleted

        $stats = $this->database->getStats();

        expect($stats['shelves']['total'])->toBe(1);
        expect($stats['books']['total'])->toBe(2);
        expect($stats['books']['active'])->toBe(1);
        expect($stats['books']['deleted'])->toBe(1);
        expect($stats['chapters']['total'])->toBe(1);
        expect($stats['pages']['total'])->toBe(1);
    });
});

describe('Transactions', function () {
    it('supports transactions', function () {
        $this->database->connect();

        $this->database->beginTransaction();
        $this->database->upsertBook(1, 'Test Book');
        $this->database->commit();

        $book = $this->database->getBookByBookstackId(1);
        expect($book)->not->toBeNull();
    });

    it('rolls back transaction', function () {
        $this->database->connect();

        $this->database->beginTransaction();
        $this->database->upsertBook(1, 'Test Book');
        $this->database->rollback();

        $book = $this->database->getBookByBookstackId(1);
        expect($book)->toBeNull();
    });
});
