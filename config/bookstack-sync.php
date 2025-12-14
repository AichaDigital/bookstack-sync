<?php

declare(strict_types=1);

// config for AichaDigital/BookStackSync

return [
    /*
    |--------------------------------------------------------------------------
    | BookStack API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your BookStack instance connection settings here.
    | You can use environment variables for sensitive data.
    |
    */

    'api' => [
        'url' => env('BOOKSTACK_URL', env('WIKI_URL', '')),
        'token_id' => env('BOOKSTACK_TOKEN_ID', env('WIKI_TOKEN_ID', '')),
        'token_secret' => env('BOOKSTACK_TOKEN_SECRET', env('WIKI_TOKEN', '')),
        'timeout' => env('BOOKSTACK_TIMEOUT', 30),
        // Disable SSL verification for local development with self-signed certificates
        'verify_ssl' => env('BOOKSTACK_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Shelf/Book for Development
    |--------------------------------------------------------------------------
    |
    | Optional default target for sync operations.
    |
    */

    'defaults' => [
        'shelf_url' => env('BOOKSTACK_SHELF_URL', env('WIKI_DEVELOP', '')),
        'book_id' => env('BOOKSTACK_BOOK_ID', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for Markdown parsing and conversion.
    |
    */

    'markdown' => [
        // Directory containing local Markdown files
        'source_path' => env('BOOKSTACK_MARKDOWN_PATH', base_path('docs')),

        // File patterns to include (glob patterns)
        'include_patterns' => ['*.md', '**/*.md'],

        // File patterns to exclude
        'exclude_patterns' => ['vendor/**', 'node_modules/**'],

        // Convert AI-style bookmarks to BookStack URL-encoded format
        'convert_bookmarks' => true,

        // Encoding for special characters (UTF-8 characters like á, é, ñ, ç)
        'encoding' => 'UTF-8',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configure synchronization behavior.
    |
    */

    'sync' => [
        // Sync direction: 'push' (local to BookStack), 'pull' (BookStack to local), 'bidirectional'
        'direction' => env('BOOKSTACK_SYNC_DIRECTION', 'push'),

        // Conflict resolution: 'local', 'remote', 'newest', 'manual'
        'conflict_resolution' => env('BOOKSTACK_CONFLICT_RESOLUTION', 'manual'),

        // Create missing structure (books, chapters) automatically
        'auto_create_structure' => env('BOOKSTACK_AUTO_CREATE', true),

        // Delete remote content when local is deleted
        'delete_remote_on_local_delete' => env('BOOKSTACK_DELETE_REMOTE', false),

        // Dry run mode (no actual changes)
        'dry_run' => env('BOOKSTACK_DRY_RUN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for sync operations.
    |
    */

    'logging' => [
        'enabled' => env('BOOKSTACK_LOGGING', true),
        'channel' => env('BOOKSTACK_LOG_CHANNEL', 'stack'),
    ],
];
