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
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function conflictDetected(string $localPath, int $remoteId): static
    {
        return new self(
            "Sync conflict detected between local file '{$localPath}' and remote entity ID {$remoteId}",
            409,
            $localPath,
            $remoteId
        );
    }

    public static function localFileNotFound(string $path): static
    {
        return new static(
            "Local file not found: {$path}",
            404,
            $path
        );
    }

    public static function invalidMarkdown(string $path, string $reason): static
    {
        return new static(
            "Invalid Markdown file at '{$path}': {$reason}",
            400,
            $path
        );
    }

    public static function structureMismatch(string $expected, string $found): static
    {
        return new static(
            "Structure mismatch: expected {$expected}, found {$found}",
            400
        );
    }
}
