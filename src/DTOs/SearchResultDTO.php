<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\DTOs;

use AichaDigital\BookStackSync\Enums\EntityType;

final class SearchResultDTO extends BaseDTO
{
    /**
     * @param  array<string, mixed>|null  $tags
     */
    public function __construct(
        ?int $id = null,
        ?string $name = null,
        ?string $slug = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        public readonly ?EntityType $type = null,
        public readonly ?string $url = null,
        public readonly ?string $preview = null,
        public readonly ?array $tags = null,
        public readonly ?int $bookId = null,
        public readonly ?int $chapterId = null,
    ) {
        parent::__construct($id, $name, $slug, $createdAt, $updatedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $type = null;
        if (isset($data['type'])) {
            $type = EntityType::tryFrom($data['type']);
        }

        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            type: $type,
            url: $data['url'] ?? null,
            preview: $data['preview_html']['content'] ?? null,
            tags: $data['tags'] ?? null,
            bookId: $data['book_id'] ?? null,
            chapterId: $data['chapter_id'] ?? null,
        );
    }
}
