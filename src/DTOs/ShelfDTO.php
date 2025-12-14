<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\DTOs;

final class ShelfDTO extends BaseDTO
{
    /**
     * @param  array<BookDTO>  $books
     */
    public function __construct(
        ?int $id = null,
        ?string $name = null,
        ?string $slug = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        public readonly ?string $description = null,
        public readonly ?string $descriptionHtml = null,
        public readonly array $books = [],
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
        $books = [];
        if (isset($data['books']) && is_array($data['books'])) {
            $books = array_map(
                fn (array $book) => BookDTO::fromArray($book),
                $data['books']
            );
        }

        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            description: $data['description'] ?? null,
            descriptionHtml: $data['description_html'] ?? null,
            books: $books,
            ownedBy: self::extractUserId($data['owned_by'] ?? null),
            createdBy: self::extractUserId($data['created_by'] ?? null),
            updatedBy: self::extractUserId($data['updated_by'] ?? null),
        );
    }

    public function getUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/').'/shelves/'.rawurlencode($this->slug ?? '');
    }
}
