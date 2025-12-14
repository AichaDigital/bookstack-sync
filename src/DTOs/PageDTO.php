<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\DTOs;

final class PageDTO extends BaseDTO
{
    public function __construct(
        ?int $id = null,
        ?string $name = null,
        ?string $slug = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        public readonly ?int $bookId = null,
        public readonly ?string $bookSlug = null,
        public readonly ?int $chapterId = null,
        public readonly ?string $chapterSlug = null,
        public readonly ?string $html = null,
        public readonly ?string $markdown = null,
        public readonly ?string $rawHtml = null,
        public readonly ?int $priority = null,
        public readonly ?bool $draft = null,
        public readonly ?bool $template = null,
        public readonly ?int $revision = null,
        public readonly ?int $ownedBy = null,
        public readonly ?int $createdBy = null,
        public readonly ?int $updatedBy = null,
        public readonly ?string $editor = null,
    ) {
        parent::__construct($id, $name, $slug, $createdAt, $updatedAt);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            bookId: $data['book_id'] ?? null,
            bookSlug: $data['book_slug'] ?? null,
            chapterId: $data['chapter_id'] ?? null,
            chapterSlug: $data['chapter_slug'] ?? null,
            html: $data['html'] ?? null,
            markdown: $data['markdown'] ?? null,
            rawHtml: $data['raw_html'] ?? null,
            priority: $data['priority'] ?? null,
            draft: $data['draft'] ?? null,
            template: $data['template'] ?? null,
            revision: $data['revision_count'] ?? null,
            ownedBy: self::extractUserId($data['owned_by'] ?? null),
            createdBy: self::extractUserId($data['created_by'] ?? null),
            updatedBy: self::extractUserId($data['updated_by'] ?? null),
            editor: $data['editor'] ?? null,
        );
    }

    public function getUrl(string $baseUrl): string
    {
        $bookSlug = rawurlencode($this->bookSlug ?? '');
        $pageSlug = rawurlencode($this->slug ?? '');

        return rtrim($baseUrl, '/')."/books/{$bookSlug}/page/{$pageSlug}";
    }

    public function getAnchor(): string
    {
        return '#'.rawurlencode($this->slug ?? '');
    }
}
