<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\DTOs;

final class ChapterDTO extends BaseDTO
{
    /**
     * @param  array<PageDTO>  $pages
     */
    public function __construct(
        ?int $id = null,
        ?string $name = null,
        ?string $slug = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        public readonly ?int $bookId = null,
        public readonly ?string $bookSlug = null,
        public readonly ?string $description = null,
        public readonly ?string $descriptionHtml = null,
        public readonly array $pages = [],
        public readonly ?int $priority = null,
        public readonly ?int $ownedBy = null,
        public readonly ?int $createdBy = null,
        public readonly ?int $updatedBy = null,
    ) {
        parent::__construct($id, $name, $slug, $createdAt, $updatedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $pages = [];
        if (isset($data['pages']) && is_array($data['pages'])) {
            $pages = array_map(
                fn (array $page) => PageDTO::fromArray($page),
                $data['pages']
            );
        }

        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            bookId: $data['book_id'] ?? null,
            bookSlug: $data['book_slug'] ?? null,
            description: $data['description'] ?? null,
            descriptionHtml: $data['description_html'] ?? null,
            pages: $pages,
            priority: $data['priority'] ?? null,
            ownedBy: self::extractUserId($data['owned_by'] ?? null),
            createdBy: self::extractUserId($data['created_by'] ?? null),
            updatedBy: self::extractUserId($data['updated_by'] ?? null),
        );
    }

    public function getUrl(string $baseUrl): string
    {
        $bookSlug = rawurlencode($this->bookSlug ?? '');
        $chapterSlug = rawurlencode($this->slug ?? '');

        return rtrim($baseUrl, '/')."/books/{$bookSlug}/chapter/{$chapterSlug}";
    }
}
