# BookStack Sync

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aichadigital/bookstack-sync.svg?style=flat-square)](https://packagist.org/packages/aichadigital/bookstack-sync)
[![Total Downloads](https://img.shields.io/packagist/dt/aichadigital/bookstack-sync.svg?style=flat-square)](https://packagist.org/packages/aichadigital/bookstack-sync)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/AichaDigital/bookstack-sync/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/AichaDigital/bookstack-sync/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/AichaDigital/bookstack-sync/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/AichaDigital/bookstack-sync/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![codecov](https://codecov.io/gh/AichaDigital/bookstack-sync/branch/main/graph/badge.svg)](https://codecov.io/gh/AichaDigital/bookstack-sync)
[![PHP Version](https://img.shields.io/packagist/php-v/aichadigital/bookstack-sync.svg?style=flat-square)](https://packagist.org/packages/aichadigital/bookstack-sync)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20|%2011.x%20|%2012.x-red.svg?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/badge/License-AGPL--3.0--or--later-blue.svg?style=flat-square)](LICENSE.md)

A Laravel package for synchronizing Markdown documentation with [BookStack](https://www.bookstackapp.com/) wiki. Perfect for keeping your project documentation in sync between your codebase and BookStack.

## Features

- **Bidirectional Sync**: Push local Markdown files to BookStack or pull BookStack content to local files
- **Bookmark Conversion**: Automatically converts AI-generated bookmarks (Claude, Cursor, etc.) to BookStack's URL-encoded format
- **Spanish Character Support**: Full support for UTF-8 characters (á, é, í, ó, ú, ñ, ç, ü, etc.)
- **Artisan Commands**: Easy-to-use CLI commands for all operations
- **Conflict Resolution**: Multiple strategies for handling sync conflicts
- **Dry Run Mode**: Preview changes before making them

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- BookStack instance with API access

## Installation

```bash
composer require aichadigital/bookstack-sync --dev
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="bookstack-sync-config"
```

## Configuration

Add these variables to your `.env` file:

```env
BOOKSTACK_URL=https://your-bookstack-instance.com
BOOKSTACK_TOKEN_ID=your-token-id
BOOKSTACK_TOKEN_SECRET=your-token-secret

# Optional defaults
BOOKSTACK_BOOK_ID=123
BOOKSTACK_MARKDOWN_PATH=docs
```

Alternative variable names are also supported for compatibility:

```env
WIKI_URL=https://your-bookstack-instance.com
WIKI_TOKEN_ID=your-token-id
WIKI_TOKEN=your-token-secret
```

## Usage

### Artisan Commands

#### Check Connection Status

```bash
php artisan bookstack:status
php artisan bookstack:status --books
php artisan bookstack:status --shelves
```

#### Push Local Files to BookStack

```bash
# Push docs directory to book ID 5
php artisan bookstack:push docs --book=5

# Dry run (preview without changes)
php artisan bookstack:push docs --book=5 --dry-run

# Skip confirmation
php artisan bookstack:push docs --book=5 --force
```

#### Pull from BookStack to Local Files

```bash
# Pull book ID 5 to local directory
php artisan bookstack:pull --book=5 --path=docs

# Dry run
php artisan bookstack:pull --book=5 --path=docs --dry-run
```

#### Export Content

```bash
# Export as Markdown
php artisan bookstack:export page 123

# Export book as PDF
php artisan bookstack:export book 5 --format=pdf --output=manual.pdf

# Available formats: markdown, html, pdf, plaintext
```

#### Search

```bash
php artisan bookstack:search "configuration"
php artisan bookstack:search "instalación" --limit=50
```

### Programmatic Usage

```php
use AichaDigital\BookStackSync\Facades\BookStackSync;

// List all books
$books = BookStackSync::books();

// Get a specific page
$page = BookStackSync::page(123);

// Create a new page with Markdown content
$page = BookStackSync::createPage(
    bookId: 5,
    name: 'Configuración Inicial',
    content: '# Introducción\n\nContenido aquí...',
    chapterId: 10
);

// Search
$results = BookStackSync::search('instalación');

// Sync operations
$result = BookStackSync::pushToBook('/path/to/docs', 5);
$result = BookStackSync::pullFromBook(5, '/path/to/docs');
```

### Bookmark Conversion

The package automatically handles the conversion between AI-generated bookmark formats and BookStack's URL-encoded format:

```php
use AichaDigital\BookStackSync\Facades\BookStackSync;

// Encode for BookStack
$encoded = BookStackSync::encodeBookmark('sección-principal');
// Returns: secci%C3%B3n-principal

// Decode from BookStack
$decoded = BookStackSync::decodeBookmark('secci%C3%B3n-principal');
// Returns: sección-principal
```

### Direct Parser Usage

```php
use AichaDigital\BookStackSync\Parsers\MarkdownParser;

$parser = new MarkdownParser();

// Convert content for BookStack
$content = '# Introducción\n\nSee [sección](#sección) for details.';
$converted = $parser->parseForBookStack($content);
// Anchors are now URL-encoded

// Extract headings
$headings = $parser->extractHeadings($content);

// Generate Table of Contents
$toc = $parser->generateTableOfContents($content);
```

## Markdown Frontmatter

You can use YAML frontmatter in your Markdown files to specify metadata:

```markdown
---
title: Mi Página
chapter: Introducción
---

# Content here...
```

Supported frontmatter fields:

- `title` / `name`: Page title (overrides filename)
- `chapter`: Chapter name (creates if doesn't exist)
- `bookstack_id`: Links to existing BookStack page for updates

## Sync Configuration

Configure sync behavior in `config/bookstack-sync.php`:

```php
'sync' => [
    // Direction: 'push', 'pull', or 'bidirectional'
    'direction' => env('BOOKSTACK_SYNC_DIRECTION', 'push'),

    // Conflict resolution: 'local', 'remote', 'newest', 'manual'
    'conflict_resolution' => env('BOOKSTACK_CONFLICT_RESOLUTION', 'manual'),

    // Auto-create missing structure
    'auto_create_structure' => true,

    // Dry run mode
    'dry_run' => false,
],
```

## Spanish Character Encoding Reference

| Character | URL Encoded |
|-----------|-------------|
| á | %C3%A1 |
| é | %C3%A9 |
| í | %C3%AD |
| ó | %C3%B3 |
| ú | %C3%BA |
| ñ | %C3%B1 |
| ü | %C3%BC |
| ç | %C3%A7 |

## Testing

```bash
composer test
```

To run tests against your actual BookStack instance, copy `.env.example` to `.env` and configure your credentials:

```bash
cp .env.example .env
# Edit .env with your BookStack credentials
composer test
```

## API Reference

This package uses the [BookStack REST API](https://demo.bookstackapp.com/api/docs). Key endpoints used:

- `/api/shelves` - Shelf operations
- `/api/books` - Book operations
- `/api/chapters` - Chapter operations
- `/api/pages` - Page operations
- `/api/search` - Search across content

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [AichaDigital](https://github.com/aichadigital)
- [All Contributors](../../contributors)

Special thanks to:

- [BookStack](https://www.bookstackapp.com/) - The excellent open-source wiki platform that this package integrates with
- [Dan Brown](https://github.com/ssddanbrown) - Creator and maintainer of BookStack

> **Trademark Notice**: BookStack® is a registered trademark of Daniel Brown. This package is not affiliated with, endorsed by, or sponsored by BookStack or Daniel Brown.

## License

This package is licensed under the **GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later)**. Please see [License File](LICENSE.md) for more information.
