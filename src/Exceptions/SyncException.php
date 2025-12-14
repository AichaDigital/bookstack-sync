<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Exceptions;

use Exception;

final class SyncException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        public readonly ?string $localPath = null,
        public readonly ?int $remoteId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function conflictDetected(string $localPath, int $remoteId): self
    {
        return new self(
            "Sync conflict detected between local file '{$localPath}' and remote entity ID {$remoteId}",
            409,
            $localPath,
            $remoteId
        );
    }

    public static function localFileNotFound(string $path): self
    {
        return new self(
            "Local file not found: {$path}",
            404,
            $path
        );
    }

    public static function invalidMarkdown(string $path, string $reason): self
    {
        return new self(
            "Invalid Markdown file at '{$path}': {$reason}",
            400,
            $path
        );
    }

    public static function structureMismatch(string $expected, string $found): self
    {
        return new self(
            "Structure mismatch: expected {$expected}, found {$found}",
            400
        );
    }
}
