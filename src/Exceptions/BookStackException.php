<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Exceptions;

use Exception;

final class BookStackException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        public readonly ?array $response = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function connectionFailed(string $url, ?\Throwable $previous = null): static
    {
        return new self(
            "Failed to connect to BookStack API at: {$url}",
            500,
            null,
            $previous
        );
    }

    public static function authenticationFailed(): static
    {
        return new static('BookStack API authentication failed. Check your token credentials.', 401);
    }

    public static function notFound(string $resource, int|string $id): static
    {
        return new static("Resource not found: {$resource} with ID {$id}", 404);
    }

    public static function validationFailed(array $errors): static
    {
        $message = 'Validation failed: '.json_encode($errors);

        return new static($message, 422, $errors);
    }

    public static function rateLimited(): static
    {
        return new static('API rate limit exceeded. Please wait before making more requests.', 429);
    }

    public static function serverError(?array $response = null): static
    {
        return new static('BookStack server error occurred.', 500, $response);
    }
}
