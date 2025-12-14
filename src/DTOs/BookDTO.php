<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\DTOs;

final class BookDTO extends BaseDTO
{
    /**
     * @param  array<ChapterDTO|PageDTO>  $contents
     */
    public function __construct(
        ?int $id = null,
        ?string $name = null,
        ?string $slug = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        public readonly ?string $description = null,
        public readonly ?string $descriptionHtml = null,
        public readonly array $contents = [],
        public readonly ?int $ownedBy = null,
        public readonly ?int $createdBy = null,
        public readonly ?int $updatedBy = null,
        public readonly ?string $defaultTemplateId = null,
    ) {
        parent::__construct($id, $name, $slug, $createdAt, $updatedAt);
    }

    public static function fromArray(array $data): static
    {
        $contents = [];
        if (isset($data['contents']) && is_array($data['contents'])) {
            foreach ($data['contents'] as $item) {
                $contents[] = match ($item['type'] ?? null) {
                    'chapter' => ChapterDTO::fromArray($item),
                    'page' => PageDTO::fromArray($item),
                    default => null,
                };
            }
            $contents = array_filter($contents);
        }

        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            description: $data['description'] ?? null,
            descriptionHtml: $data['description_html'] ?? null,
            contents: $contents,
            ownedBy: self::extractUserId($data['owned_by'] ?? null),
            createdBy: self::extractUserId($data['created_by'] ?? null),
            updatedBy: self::extractUserId($data['updated_by'] ?? null),
            defaultTemplateId: $data['default_template_id'] ?? null,
        );
    }

    public function getUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/').'/books/'.rawurlencode($this->slug ?? '');
    }
}
