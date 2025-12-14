<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Api;

use AichaDigital\BookStackSync\Contracts\BookStackClientInterface;
use AichaDigital\BookStackSync\DTOs\BookDTO;
use AichaDigital\BookStackSync\DTOs\ChapterDTO;
use AichaDigital\BookStackSync\DTOs\PageDTO;
use AichaDigital\BookStackSync\DTOs\SearchResultDTO;
use AichaDigital\BookStackSync\DTOs\ShelfDTO;
use AichaDigital\BookStackSync\Enums\ExportFormat;
use AichaDigital\BookStackSync\Exceptions\BookStackException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class BookStackClient implements BookStackClientInterface
{
    private Client $httpClient;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $tokenId,
        private readonly string $tokenSecret,
        private readonly int $timeout = 30,
        private readonly bool $verifySsl = true
    ) {
        $this->httpClient = new Client([
            'base_uri' => rtrim($this->baseUrl, '/').'/api/',
            'timeout' => $this->timeout,
            'verify' => $this->verifySsl,
            'headers' => [
                'Authorization' => "Token {$this->tokenId}:{$this->tokenSecret}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    // Shelves
    public function listShelves(int $count = 100, int $offset = 0): array
    {
        $response = $this->get('shelves', ['count' => $count, 'offset' => $offset]);

        return array_map(
            fn (array $shelf) => ShelfDTO::fromArray($shelf),
            $response['data'] ?? []
        );
    }

    public function getShelf(int $id): ShelfDTO
    {
        $response = $this->get("shelves/{$id}");

        return ShelfDTO::fromArray($response);
    }

    /**
     * @param  array<int>  $bookIds
     */
    public function createShelf(string $name, ?string $description = null, array $bookIds = []): ShelfDTO
    {
        $data = ['name' => $name];
        if ($description !== null) {
            $data['description'] = $description;
        }
        if (! empty($bookIds)) {
            $data['books'] = $bookIds;
        }

        $response = $this->post('shelves', $data);

        return ShelfDTO::fromArray($response);
    }

    /**
     * @param  array<int>|null  $bookIds
     */
    public function updateShelf(int $id, ?string $name = null, ?string $description = null, ?array $bookIds = null): ShelfDTO
    {
        $data = array_filter([
            'name' => $name,
            'description' => $description,
            'books' => $bookIds,
        ], fn ($v) => $v !== null);

        $response = $this->put("shelves/{$id}", $data);

        return ShelfDTO::fromArray($response);
    }

    public function deleteShelf(int $id): bool
    {
        $this->delete("shelves/{$id}");

        return true;
    }

    // Books
    public function listBooks(int $count = 100, int $offset = 0): array
    {
        $response = $this->get('books', ['count' => $count, 'offset' => $offset]);

        return array_map(
            fn (array $book) => BookDTO::fromArray($book),
            $response['data'] ?? []
        );
    }

    public function getBook(int $id): BookDTO
    {
        $response = $this->get("books/{$id}");

        return BookDTO::fromArray($response);
    }

    public function createBook(string $name, ?string $description = null): BookDTO
    {
        $data = ['name' => $name];
        if ($description !== null) {
            $data['description'] = $description;
        }

        $response = $this->post('books', $data);

        return BookDTO::fromArray($response);
    }

    public function updateBook(int $id, ?string $name = null, ?string $description = null): BookDTO
    {
        $data = array_filter([
            'name' => $name,
            'description' => $description,
        ], fn ($v) => $v !== null);

        $response = $this->put("books/{$id}", $data);

        return BookDTO::fromArray($response);
    }

    public function deleteBook(int $id): bool
    {
        $this->delete("books/{$id}");

        return true;
    }

    public function exportBook(int $id, ExportFormat $format = ExportFormat::MARKDOWN): string
    {
        return $this->getRaw("books/{$id}/export/{$format->value}");
    }

    // Chapters
    public function listChapters(int $count = 100, int $offset = 0): array
    {
        $response = $this->get('chapters', ['count' => $count, 'offset' => $offset]);

        return array_map(
            fn (array $chapter) => ChapterDTO::fromArray($chapter),
            $response['data'] ?? []
        );
    }

    public function getChapter(int $id): ChapterDTO
    {
        $response = $this->get("chapters/{$id}");

        return ChapterDTO::fromArray($response);
    }

    public function createChapter(int $bookId, string $name, ?string $description = null): ChapterDTO
    {
        $data = [
            'book_id' => $bookId,
            'name' => $name,
        ];
        if ($description !== null) {
            $data['description'] = $description;
        }

        $response = $this->post('chapters', $data);

        return ChapterDTO::fromArray($response);
    }

    public function updateChapter(int $id, ?string $name = null, ?string $description = null, ?int $bookId = null): ChapterDTO
    {
        $data = array_filter([
            'name' => $name,
            'description' => $description,
            'book_id' => $bookId,
        ], fn ($v) => $v !== null);

        $response = $this->put("chapters/{$id}", $data);

        return ChapterDTO::fromArray($response);
    }

    public function deleteChapter(int $id): bool
    {
        $this->delete("chapters/{$id}");

        return true;
    }

    public function exportChapter(int $id, ExportFormat $format = ExportFormat::MARKDOWN): string
    {
        return $this->getRaw("chapters/{$id}/export/{$format->value}");
    }

    // Pages
    public function listPages(int $count = 100, int $offset = 0): array
    {
        $response = $this->get('pages', ['count' => $count, 'offset' => $offset]);

        return array_map(
            fn (array $page) => PageDTO::fromArray($page),
            $response['data'] ?? []
        );
    }

    public function getPage(int $id): PageDTO
    {
        $response = $this->get("pages/{$id}");

        return PageDTO::fromArray($response);
    }

    public function createPage(int $bookId, string $name, string $content, ?int $chapterId = null, bool $isMarkdown = true): PageDTO
    {
        $data = [
            'book_id' => $bookId,
            'name' => $name,
        ];

        if ($isMarkdown) {
            $data['markdown'] = $content;
        } else {
            $data['html'] = $content;
        }

        if ($chapterId !== null) {
            $data['chapter_id'] = $chapterId;
        }

        $response = $this->post('pages', $data);

        return PageDTO::fromArray($response);
    }

    public function updatePage(int $id, ?string $name = null, ?string $content = null, bool $isMarkdown = true): PageDTO
    {
        $data = [];

        if ($name !== null) {
            $data['name'] = $name;
        }

        if ($content !== null) {
            if ($isMarkdown) {
                $data['markdown'] = $content;
            } else {
                $data['html'] = $content;
            }
        }

        $response = $this->put("pages/{$id}", $data);

        return PageDTO::fromArray($response);
    }

    public function deletePage(int $id): bool
    {
        $this->delete("pages/{$id}");

        return true;
    }

    public function exportPage(int $id, ExportFormat $format = ExportFormat::MARKDOWN): string
    {
        return $this->getRaw("pages/{$id}/export/{$format->value}");
    }

    // Search
    public function search(string $query, int $count = 100, int $offset = 0): array
    {
        $response = $this->get('search', [
            'query' => $query,
            'count' => $count,
            'offset' => $offset,
        ]);

        return array_map(
            fn (array $result) => SearchResultDTO::fromArray($result),
            $response['data'] ?? []
        );
    }

    // HTTP methods
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->httpClient->get($endpoint, ['query' => $query]);

            return $this->parseResponse($response);
        } catch (RequestException $e) {
            throw $this->handleException($e);
        } catch (GuzzleException $e) {
            throw BookStackException::connectionFailed($this->baseUrl, $e);
        }
    }

    private function getRaw(string $endpoint): string
    {
        try {
            $response = $this->httpClient->get($endpoint);

            return (string) $response->getBody();
        } catch (RequestException $e) {
            throw $this->handleException($e);
        } catch (GuzzleException $e) {
            throw BookStackException::connectionFailed($this->baseUrl, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->httpClient->post($endpoint, ['json' => $data]);

            return $this->parseResponse($response);
        } catch (RequestException $e) {
            throw $this->handleException($e);
        } catch (GuzzleException $e) {
            throw BookStackException::connectionFailed($this->baseUrl, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function put(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->httpClient->put($endpoint, ['json' => $data]);

            return $this->parseResponse($response);
        } catch (RequestException $e) {
            throw $this->handleException($e);
        } catch (GuzzleException $e) {
            throw BookStackException::connectionFailed($this->baseUrl, $e);
        }
    }

    private function delete(string $endpoint): void
    {
        try {
            $this->httpClient->delete($endpoint);
        } catch (RequestException $e) {
            throw $this->handleException($e);
        } catch (GuzzleException $e) {
            throw BookStackException::connectionFailed($this->baseUrl, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        return json_decode($body, true) ?? [];
    }

    private function handleException(RequestException $e): BookStackException
    {
        $response = $e->getResponse();

        if ($response === null) {
            return BookStackException::connectionFailed($this->baseUrl, $e);
        }

        $statusCode = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);

        return match ($statusCode) {
            401 => BookStackException::authenticationFailed(),
            404 => BookStackException::notFound('resource', 0),
            422 => BookStackException::validationFailed($body['error'] ?? $body ?? []),
            429 => BookStackException::rateLimited(),
            default => BookStackException::serverError($body),
        };
    }
}
