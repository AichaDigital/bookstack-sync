<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Parsers;

/**
 * Converter that matches BookStack's exact bookmark ID generation algorithm.
 *
 * BookStack generates IDs using this algorithm (from PageContent.php):
 * 1. Trim the heading text
 * 2. Replace whitespace sequences with hyphens
 * 3. Convert to lowercase (using strtolower, not mb_strtolower)
 * 4. Take first 20 characters (using mb_substr)
 * 5. Prepend 'bkmrk-'
 * 6. URL-encode the entire result (using urlencode)
 *
 * @see BookStack/app/Entities/Tools/PageContent.php::setUniqueId()
 */
class BookmarkConverter
{
    private const BKMRK_PREFIX = 'bkmrk-';

    private const MAX_LENGTH = 20;

    public function __construct(
        private readonly string $encoding = 'UTF-8'
    ) {}

    /**
     * Convert a heading text to BookStack's bookmark ID format.
     * This matches exactly what BookStack generates.
     */
    public function headingToBookStackId(string $heading): string
    {
        // 1. Trim
        $text = trim($heading);

        // 2. Replace whitespace sequences with hyphens
        $text = preg_replace('/\s+/', '-', $text) ?? $text;

        // 3. Convert to lowercase (using strtolower like BookStack)
        $text = strtolower($text);

        // 4. Take first 20 characters
        $text = mb_substr($text, 0, self::MAX_LENGTH, $this->encoding);

        // 5. Prepend bkmrk-
        $text = self::BKMRK_PREFIX.$text;

        // 6. URL-encode (using urlencode like BookStack)
        return urlencode($text);
    }

    /**
     * Convert an AI-style anchor link to BookStack format.
     *
     * AI tools generate anchors like: #configuración, #mi-sección, #MiSecciónFavorita
     * BookStack expects: #bkmrk-configuraci%C3%B3n
     *
     * This method reverses common AI transformations and applies BookStack's algorithm.
     */
    public function toBookStack(string $anchor): string
    {
        // Remove leading # if present
        $anchor = ltrim($anchor, '#');

        // Reverse common AI transformations to get back to original heading text
        $text = $this->reverseAiTransformations($anchor);

        // Apply BookStack's algorithm
        return $this->headingToBookStackId($text);
    }

    /**
     * Convert a BookStack bookmark back to readable format.
     */
    public function fromBookStack(string $bookmark): string
    {
        // URL decode
        $text = urldecode($bookmark);

        // Remove bkmrk- prefix
        if (str_starts_with($text, self::BKMRK_PREFIX)) {
            $text = substr($text, strlen(self::BKMRK_PREFIX));
        }

        return $text;
    }

    /**
     * Reverse common AI anchor transformations to approximate original heading.
     *
     * AI tools typically:
     * - Convert spaces to hyphens
     * - Remove accents (sometimes)
     * - Use CamelCase or lowercase
     */
    private function reverseAiTransformations(string $anchor): string
    {
        // Convert hyphens and underscores back to spaces
        $text = preg_replace('/[-_]+/', ' ', $anchor) ?? $anchor;

        // Handle CamelCase: insert space before uppercase letters
        // e.g., "MiSecciónFavorita" → "Mi Sección Favorita"
        $text = preg_replace('/(\p{Ll})(\p{Lu})/u', '$1 $2', $text) ?? $text;

        return $text;
    }

    /**
     * Check if a bookmark already has BookStack format (bkmrk- prefix).
     */
    public function isBookStackFormat(string $bookmark): bool
    {
        $decoded = urldecode(ltrim($bookmark, '#'));

        return str_starts_with($decoded, self::BKMRK_PREFIX);
    }

    /**
     * Check if a bookmark needs conversion to BookStack format.
     */
    public function needsConversion(string $bookmark): bool
    {
        return ! $this->isBookStackFormat($bookmark);
    }

    /**
     * Get the encoded version of common Spanish special characters.
     *
     * @return array<string, string>
     */
    public function getSpanishEncodingMap(): array
    {
        return [
            'á' => '%C3%A1',
            'é' => '%C3%A9',
            'í' => '%C3%AD',
            'ó' => '%C3%B3',
            'ú' => '%C3%BA',
            'ñ' => '%C3%B1',
            'ü' => '%C3%BC',
            'Á' => '%C3%81',
            'É' => '%C3%89',
            'Í' => '%C3%8D',
            'Ó' => '%C3%93',
            'Ú' => '%C3%9A',
            'Ñ' => '%C3%91',
            'Ü' => '%C3%9C',
            'ç' => '%C3%A7',
            'Ç' => '%C3%87',
        ];
    }

    /**
     * Generate a normalized anchor from heading text.
     * This is a simplified version for cases where we need a readable anchor.
     */
    public function normalizeAnchor(string $heading): string
    {
        $text = trim($heading);
        $text = preg_replace('/\s+/', '-', $text) ?? $text;
        $text = mb_strtolower($text, $this->encoding);

        return $text;
    }
}
