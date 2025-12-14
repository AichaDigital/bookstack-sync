<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\Exceptions\BookStackException;
use AichaDigital\BookStackSync\Exceptions\SyncException;

describe('BookStackException', function () {
    it('creates authentication failed exception', function () {
        $exception = BookStackException::authenticationFailed();

        expect($exception)->toBeInstanceOf(BookStackException::class)
            ->and($exception->getMessage())->toContain('authentication failed')
            ->and($exception->getCode())->toBe(401);
    });

    it('creates not found exception', function () {
        $exception = BookStackException::notFound('page', 123);

        expect($exception)->toBeInstanceOf(BookStackException::class)
            ->and($exception->getMessage())->toContain('page')
            ->and($exception->getMessage())->toContain('123')
            ->and($exception->getCode())->toBe(404);
    });

    it('creates validation failed exception', function () {
        $errors = ['name' => ['The name field is required']];
        $exception = BookStackException::validationFailed($errors);

        expect($exception)->toBeInstanceOf(BookStackException::class)
            ->and($exception->getMessage())->toContain('Validation failed')
            ->and($exception->getCode())->toBe(422)
            ->and($exception->response)->toBe($errors);
    });

    it('creates rate limited exception', function () {
        $exception = BookStackException::rateLimited();

        expect($exception)->toBeInstanceOf(BookStackException::class)
            ->and($exception->getMessage())->toContain('rate limit')
            ->and($exception->getCode())->toBe(429);
    });

    it('creates server error exception', function () {
        $response = ['error' => 'Internal server error'];
        $exception = BookStackException::serverError($response);

        expect($exception)->toBeInstanceOf(BookStackException::class)
            ->and($exception->getCode())->toBe(500)
            ->and($exception->response)->toBe($response);
    });

    it('creates server error exception without response', function () {
        $exception = BookStackException::serverError();

        expect($exception)->toBeInstanceOf(BookStackException::class)
            ->and($exception->getCode())->toBe(500)
            ->and($exception->response)->toBeNull();
    });

    it('creates connection failed exception', function () {
        $previous = new \Exception('Connection timed out');
        $exception = BookStackException::connectionFailed('https://example.com', $previous);

        expect($exception)->toBeInstanceOf(BookStackException::class)
            ->and($exception->getMessage())->toContain('example.com')
            ->and($exception->getPrevious())->toBe($previous);
    });

    it('creates connection failed exception without previous', function () {
        $exception = BookStackException::connectionFailed('https://example.com');

        expect($exception)->toBeInstanceOf(BookStackException::class)
            ->and($exception->getMessage())->toContain('example.com')
            ->and($exception->getPrevious())->toBeNull();
    });

    it('has null response by default', function () {
        $exception = BookStackException::authenticationFailed();

        expect($exception->response)->toBeNull();
    });
});

describe('SyncException', function () {
    it('creates local file not found exception', function () {
        $exception = SyncException::localFileNotFound('/path/to/file.md');

        expect($exception)->toBeInstanceOf(SyncException::class)
            ->and($exception->getMessage())->toContain('/path/to/file.md')
            ->and($exception->localPath)->toBe('/path/to/file.md')
            ->and($exception->getCode())->toBe(404);
    });

    it('creates conflict detected exception', function () {
        $exception = SyncException::conflictDetected('/path/to/file.md', 123);

        expect($exception)->toBeInstanceOf(SyncException::class)
            ->and($exception->getMessage())->toContain('conflict')
            ->and($exception->localPath)->toBe('/path/to/file.md')
            ->and($exception->remoteId)->toBe(123)
            ->and($exception->getCode())->toBe(409);
    });

    it('creates invalid markdown exception', function () {
        $exception = SyncException::invalidMarkdown('/path/to/file.md', 'invalid syntax');

        expect($exception)->toBeInstanceOf(SyncException::class)
            ->and($exception->getMessage())->toContain('Invalid Markdown')
            ->and($exception->getMessage())->toContain('invalid syntax')
            ->and($exception->localPath)->toBe('/path/to/file.md')
            ->and($exception->getCode())->toBe(400);
    });

    it('creates structure mismatch exception', function () {
        $exception = SyncException::structureMismatch('chapter', 'page');

        expect($exception)->toBeInstanceOf(SyncException::class)
            ->and($exception->getMessage())->toContain('Structure mismatch')
            ->and($exception->getMessage())->toContain('chapter')
            ->and($exception->getMessage())->toContain('page')
            ->and($exception->getCode())->toBe(400);
    });

    it('creates exception with previous', function () {
        $previous = new \Exception('Original error');
        $exception = new SyncException('Test message', 0, '/path', 123, $previous);

        expect($exception->getPrevious())->toBe($previous)
            ->and($exception->localPath)->toBe('/path')
            ->and($exception->remoteId)->toBe(123);
    });

    it('has null properties by default', function () {
        $exception = new SyncException('Test');

        expect($exception->localPath)->toBeNull()
            ->and($exception->remoteId)->toBeNull();
    });
});
