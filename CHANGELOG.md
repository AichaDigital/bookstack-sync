# Changelog

All notable changes to `bookstack-sync` will be documented in this file.

## [Unreleased]

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
