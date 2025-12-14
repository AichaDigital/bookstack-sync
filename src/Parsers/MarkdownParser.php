<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Parsers;

/**
 * Markdown Parser for BookStack synchronization.
 *
 * Handles conversion between AI-style bookmarks (anchor links) and
 * BookStack's bkmrk- prefixed URL-encoded format.
 */
class MarkdownParser
{
    /**
     * Pattern to match Markdown links: [text](url)
     */
    private const LINK_PATTERN = '/\[([^\]]+)\]\(([^)]+)\)/u';

    /**
     * Pattern to match anchor links: #section-name
     */
    private const ANCHOR_PATTERN = '/\(#([^)]+)\)/u';

    /**
     * Pattern to match headings for anchor generation
     */
    private const HEADING_PATTERN = '/^(#{1,6})\s+(.+)$/mu';

    private BookmarkConverter $converter;

    public function __construct(
        private readonly bool $convertBookmarks = true,
        private readonly string $encoding = 'UTF-8'
    ) {
        $this->converter = new BookmarkConverter($this->encoding);
    }

    /**
     * Parse Markdown content and convert bookmarks to BookStack format.
     * AI-style anchors like #configuración become #bkmrk-configuraci%C3%B3n
     */
    public function parseForBookStack(string $content): string
    {
        if (! $this->convertBookmarks) {
            return $content;
        }

        // Convert anchor links to BookStack format (with bkmrk- prefix)
        return $this->convertAnchorsToBookStack($content);
    }

    /**
     * Parse BookStack content back to standard Markdown format.
     */
    public function parseFromBookStack(string $content): string
    {
        if (! $this->convertBookmarks) {
            return $content;
        }

        // Convert BookStack anchors back to readable format
        return $this->convertAnchorsFromBookStack($content);
    }

    /**
     * Convert anchor links from AI-style to BookStack format.
     * Example: (#operaciones-básicas) -> (#bkmrk-operaciones-b%C3%A1sicas)
     */
    public function convertAnchorsToBookStack(string $content): string
    {
        return preg_replace_callback(
            self::ANCHOR_PATTERN,
            function (array $matches): string {
                $anchor = $matches[1];

                // Skip if already in BookStack format
                if ($this->converter->isBookStackFormat($anchor)) {
                    return "(#{$anchor})";
                }

                $encoded = $this->converter->toBookStack($anchor);

                return "(#{$encoded})";
            },
            $content
        ) ?? $content;
    }

    /**
     * Convert anchor links from BookStack format back to readable.
     * Example: (#bkmrk-operaciones-b%C3%A1sicas) -> (#operaciones-básicas)
     */
    public function convertAnchorsFromBookStack(string $content): string
    {
        return preg_replace_callback(
            self::ANCHOR_PATTERN,
            function (array $matches): string {
                $anchor = $matches[1];
                $decoded = $this->converter->fromBookStack($anchor);

                return "(#{$decoded})";
            },
            $content
        ) ?? $content;
    }

    /**
     * Generate a BookStack-compatible anchor ID from a heading text.
     * This matches exactly what BookStack generates.
     */
    public function generateAnchorFromHeading(string $heading): string
    {
        return $this->converter->headingToBookStackId($heading);
    }

    /**
     * Generate a simple normalized anchor (without bkmrk- prefix).
     * Useful for generating readable anchors.
     */
    public function generateSimpleAnchor(string $heading): string
    {
        return $this->converter->normalizeAnchor($heading);
    }

    /**
     * Extract all headings from Markdown content.
     *
     * @return array<array{level: int, text: string, anchor: string, bookstackAnchor: string}>
     */
    public function extractHeadings(string $content): array
    {
        $headings = [];

        if (preg_match_all(self::HEADING_PATTERN, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $level = strlen($match[1]);
                $text = trim($match[2]);

                $headings[] = [
                    'level' => $level,
                    'text' => $text,
                    'anchor' => $this->converter->normalizeAnchor($text),
                    'bookstackAnchor' => $this->converter->headingToBookStackId($text),
                ];
            }
        }

        return $headings;
    }

    /**
     * Extract all links from Markdown content.
     *
     * @return array<array{text: string, url: string, isAnchor: bool}>
     */
    public function extractLinks(string $content): array
    {
        $links = [];

        if (preg_match_all(self::LINK_PATTERN, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $links[] = [
                    'text' => $match[1],
                    'url' => $match[2],
                    'isAnchor' => str_starts_with($match[2], '#'),
                ];
            }
        }

        return $links;
    }

    /**
     * Generate a Table of Contents from Markdown content.
     * Uses BookStack-compatible anchor format.
     */
    public function generateTableOfContents(string $content, int $maxLevel = 3): string
    {
        $headings = $this->extractHeadings($content);
        $toc = [];

        foreach ($headings as $heading) {
            if ($heading['level'] <= $maxLevel) {
                $indent = str_repeat('  ', $heading['level'] - 1);
                $toc[] = "{$indent}- [{$heading['text']}](#{$heading['bookstackAnchor']})";
            }
        }

        return implode("\n", $toc);
    }

    /**
     * Convert a relative file path link to BookStack page URL format.
     */
    public function convertFilePathToBookStackUrl(
        string $filePath,
        string $baseUrl,
        string $bookSlug,
        ?string $chapterSlug = null
    ): string {
        // Remove .md extension
        $slug = preg_replace('/\.md$/i', '', basename($filePath)) ?? basename($filePath);

        // Generate slug from filename
        $slug = $this->converter->normalizeAnchor($slug);

        $url = rtrim($baseUrl, '/').'/books/'.rawurlencode($bookSlug);

        if ($chapterSlug !== null) {
            $url .= '/chapter/'.rawurlencode($chapterSlug);
        }

        $url .= '/page/'.rawurlencode($slug);

        return $url;
    }

    /**
     * Extract frontmatter from Markdown content.
     *
     * @return array{frontmatter: array<string, mixed>, content: string}
     */
    public function extractFrontmatter(string $content): array
    {
        $frontmatter = [];
        $cleanContent = $content;

        // Check for YAML frontmatter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $yamlContent = $matches[1];
            $cleanContent = $matches[2];

            // Simple YAML parsing for key: value pairs
            $lines = explode("\n", $yamlContent);
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):\s*(.*)$/', trim($line), $kvMatch)) {
                    $frontmatter[$kvMatch[1]] = trim($kvMatch[2], '"\'');
                }
            }
        }

        return [
            'frontmatter' => $frontmatter,
            'content' => $cleanContent,
        ];
    }

    /**
     * Add frontmatter to Markdown content.
     *
     * @param  array<string, mixed>  $frontmatter
     */
    public function addFrontmatter(string $content, array $frontmatter): string
    {
        if (empty($frontmatter)) {
            return $content;
        }

        $yaml = "---\n";
        foreach ($frontmatter as $key => $value) {
            $yaml .= "{$key}: {$value}\n";
        }
        $yaml .= "---\n\n";

        return $yaml.$content;
    }

    /**
     * Get the underlying BookmarkConverter instance.
     */
    public function getConverter(): BookmarkConverter
    {
        return $this->converter;
    }
}
