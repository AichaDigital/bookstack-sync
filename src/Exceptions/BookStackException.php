<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Exceptions;

use Exception;

final class BookStackException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        public readonly ?array $response = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function connectionFailed(string $url, ?\Throwable $previous = null): self
    {
        return new self(
            "Failed to connect to BookStack API at: {$url}",
            500,
            null,
            $previous
        );
    }

    public static function authenticationFailed(): self
    {
        return new self('BookStack API authentication failed. Check your token credentials.', 401);
    }

    public static function notFound(string $resource, int|string $id): self
    {
        return new self("Resource not found: {$resource} with ID {$id}", 404);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function validationFailed(array $errors): self
    {
        $message = 'Validation failed: '.json_encode($errors);

        return new self($message, 422, $errors);
    }

    public static function rateLimited(): self
    {
        return new self('API rate limit exceeded. Please wait before making more requests.', 429);
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    public static function serverError(?array $response = null): self
    {
        return new self('BookStack server error occurred.', 500, $response);
    }
}
