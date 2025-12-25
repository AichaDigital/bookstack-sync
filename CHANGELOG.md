# Changelog

All notable changes to `bookstack-sync` will be documented in this file.

## [1.0.0] - 2025-12-25

### Added

- **SQLite Local Cache**: New local database for caching wiki structure
  - Reduces API calls by storing shelves, books, chapters, and pages locally
  - Tracks sync state with `synced_at` and `is_deleted` flags
  - Maps local files to remote pages with `local_path`
  - Detects content changes efficiently with `content_hash` (xxh3)
- New Artisan commands for database management:
  - `bookstack:sync` - Sync wiki structure from BookStack API to local SQLite cache
  - `bookstack:db stats` - Show database statistics
  - `bookstack:db shelves|books|chapters|pages` - List cached entities
  - `bookstack:db path` - Show database file path and size
  - `bookstack:db delete` - Delete the local database
- Configuration options for local database:
  - `BOOKSTACK_LOCAL_DB` - Enable/disable local database (default: true)
  - `BOOKSTACK_DB_PATH` - Custom database path (default: storage/bookstack-sync.sqlite)
- Database class with PDO for lightweight SQLite operations
- Content hash generation using xxh3 algorithm for change detection
- Integration with SyncService for automatic local_path and content_hash tracking

### Changed

- SyncService now uses local cache to skip unchanged files during sync
- BookStackSync facade includes database access via `database()` method

## [0.1.0] - 2025-12-14

### Added

- Initial package release
- BookStack API client with full CRUD support for shelves, books, chapters, and pages
- Markdown parser with URL-encoding for special characters (Spanish, UTF-8)
- Bookmark converter for AI-generated bookmark formats
- Bidirectional sync service (push/pull)
- Artisan commands:
  - `bookstack:status` - Check API connection and list content
  - `bookstack:push` - Push local Markdown to BookStack
  - `bookstack:pull` - Pull BookStack content to local files
  - `bookstack:export` - Export books, chapters, or pages
  - `bookstack:search` - Search across BookStack content
- DTOs for type-safe API responses
- Conflict resolution strategies (local, remote, newest, manual)
- Dry run mode for safe previews
- Comprehensive test suite with Spanish character support
- Full Laravel 10, 11, and 12 compatibility
