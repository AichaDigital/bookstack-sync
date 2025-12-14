<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\Enums\ConflictResolution;
use AichaDigital\BookStackSync\Enums\EntityType;
use AichaDigital\BookStackSync\Enums\ExportFormat;
use AichaDigital\BookStackSync\Enums\SyncDirection;

describe('EntityType', function () {
    it('has correct API endpoints', function () {
        expect(EntityType::SHELF->apiEndpoint())->toBe('shelves');
        expect(EntityType::BOOK->apiEndpoint())->toBe('books');
        expect(EntityType::CHAPTER->apiEndpoint())->toBe('chapters');
        expect(EntityType::PAGE->apiEndpoint())->toBe('pages');
    });

    it('has correct singular names', function () {
        expect(EntityType::SHELF->singular())->toBe('shelf');
        expect(EntityType::BOOK->singular())->toBe('book');
        expect(EntityType::CHAPTER->singular())->toBe('chapter');
        expect(EntityType::PAGE->singular())->toBe('page');
    });

    it('has correct values', function () {
        expect(EntityType::SHELF->value)->toBe('bookshelf');
        expect(EntityType::BOOK->value)->toBe('book');
        expect(EntityType::CHAPTER->value)->toBe('chapter');
        expect(EntityType::PAGE->value)->toBe('page');
    });
});

describe('SyncDirection', function () {
    it('has all directions', function () {
        expect(SyncDirection::cases())->toHaveCount(3);
        expect(SyncDirection::PUSH->value)->toBe('push');
        expect(SyncDirection::PULL->value)->toBe('pull');
        expect(SyncDirection::BIDIRECTIONAL->value)->toBe('bidirectional');
    });
});

describe('ConflictResolution', function () {
    it('has all strategies', function () {
        expect(ConflictResolution::cases())->toHaveCount(4);
        expect(ConflictResolution::LOCAL->value)->toBe('local');
        expect(ConflictResolution::REMOTE->value)->toBe('remote');
        expect(ConflictResolution::NEWEST->value)->toBe('newest');
        expect(ConflictResolution::MANUAL->value)->toBe('manual');
    });
});

describe('ExportFormat', function () {
    it('has all formats', function () {
        expect(ExportFormat::cases())->toHaveCount(5);
        expect(ExportFormat::HTML->value)->toBe('html');
        expect(ExportFormat::PDF->value)->toBe('pdf');
        expect(ExportFormat::PLAINTEXT->value)->toBe('plaintext');
        expect(ExportFormat::MARKDOWN->value)->toBe('markdown');
        expect(ExportFormat::ZIP->value)->toBe('zip');
    });
});
