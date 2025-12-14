<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\DTOs;

use JsonSerializable;

abstract class BaseDTO implements JsonSerializable
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return array_filter(
            get_object_vars($this),
            fn ($value) => $value !== null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Extract user ID from either an integer or user object array.
     * BookStack API returns integers in list endpoints but arrays in show endpoints.
     */
    protected static function extractUserId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_array($value) && isset($value['id'])) {
            return (int) $value['id'];
        }

        return null;
    }

    abstract public static function fromArray(array $data): static;
}
