<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\Parsers\MarkdownParser;

beforeEach(function () {
    $this->parser = new MarkdownParser(true, 'UTF-8');
});

describe('MarkdownParser', function () {
    describe('content conversion for BookStack', function () {
        it('converts anchor links to BookStack format with bkmrk- prefix', function () {
            $content = 'See [Introducción](#introducción) for more details.';
            $converted = $this->parser->parseForBookStack($content);

            // BookStack format includes bkmrk- prefix
            expect($converted)->toBe('See [Introducción](#bkmrk-introducci%C3%B3n) for more details.');
        });

        it('converts multiple anchor links', function () {
            $content = <<<'MD'
            # Tabla de Contenidos

            - [Sección](#sección)
            - [Capítulo](#capítulo)
            - [Año](#año)
            MD;

            $converted = $this->parser->parseForBookStack($content);

            expect($converted)
                ->toContain('(#bkmrk-secci%C3%B3n)')
                ->toContain('(#bkmrk-cap%C3%ADtulo)')
                ->toContain('(#bkmrk-a%C3%B1o)');
        });

        it('skips anchors that already have bkmrk- prefix', function () {
            $content = 'See [Section](#bkmrk-section) for more.';
            $converted = $this->parser->parseForBookStack($content);

            // Should not double-prefix
            expect($converted)->toBe('See [Section](#bkmrk-section) for more.');
        });

        it('handles CamelCase anchors', function () {
            $content = 'See [My Section](#MiSecciónFavorita) for details.';
            $converted = $this->parser->parseForBookStack($content);

            // CamelCase is converted to spaces, then processed
            expect($converted)->toContain('bkmrk-');
        });
    });

    describe('content conversion from BookStack', function () {
        it('decodes URL-encoded anchors back to readable format', function () {
            $content = 'See [Introducción](#bkmrk-introducci%C3%B3n) for more details.';
            $converted = $this->parser->parseFromBookStack($content);

            // Should decode and remove bkmrk- prefix
            expect($converted)->toBe('See [Introducción](#introducción) for more details.');
        });
    });

    describe('heading extraction', function () {
        it('extracts all headings from content', function () {
            $content = <<<'MD'
            # Título Principal

            ## Sección Uno

            ### Subsección

            ## Sección Dos
            MD;

            $headings = $this->parser->extractHeadings($content);

            expect($headings)->toHaveCount(4);
            expect($headings[0]['text'])->toBe('Título Principal');
            expect($headings[0]['level'])->toBe(1);
            expect($headings[1]['text'])->toBe('Sección Uno');
            expect($headings[1]['level'])->toBe(2);
        });

        it('generates both simple and BookStack anchors for Spanish headings', function () {
            $content = '# Configuración del Año';
            $headings = $this->parser->extractHeadings($content);

            // Simple anchor (normalized, readable)
            expect($headings[0]['anchor'])->toBe('configuración-del-año');

            // BookStack anchor (with bkmrk- prefix, URL-encoded, truncated to 20 chars)
            // "configuración-del-año" = 21 chars, truncated to "configuración-del-añ" = 20 chars
            expect($headings[0]['bookstackAnchor'])->toBe('bkmrk-configuraci%C3%B3n-del-a%C3%B1');
        });

        it('truncates BookStack anchors to 20 characters', function () {
            $content = '# Este es un título muy largo que excede veinte caracteres';
            $headings = $this->parser->extractHeadings($content);

            // BookStack truncates to 20 chars before adding prefix
            expect($headings[0]['bookstackAnchor'])->toBe('bkmrk-este-es-un-t%C3%ADtulo-mu');
        });
    });

    describe('table of contents generation', function () {
        it('generates TOC with BookStack-format anchors', function () {
            $content = <<<'MD'
            # Introducción

            ## Sección Española

            ## Configuración
            MD;

            $toc = $this->parser->generateTableOfContents($content);

            expect($toc)
                ->toContain('(#bkmrk-introducci%C3%B3n)')
                ->toContain('(#bkmrk-secci%C3%B3n-espa%C3%B1ola)')
                ->toContain('(#bkmrk-configuraci%C3%B3n)');
        });
    });

    describe('anchor generation', function () {
        it('generates BookStack-compatible anchor from heading', function () {
            $anchor = $this->parser->generateAnchorFromHeading('Operaciones Básicas');

            expect($anchor)->toBe('bkmrk-operaciones-b%C3%A1sicas');
        });

        it('generates simple normalized anchor', function () {
            $anchor = $this->parser->generateSimpleAnchor('Operaciones Básicas');

            expect($anchor)->toBe('operaciones-básicas');
        });
    });

    describe('frontmatter handling', function () {
        it('extracts YAML frontmatter from content', function () {
            $content = <<<'MD'
            ---
            title: Mi Página
            chapter: Introducción
            ---

            # Contenido

            El texto aquí.
            MD;

            $result = $this->parser->extractFrontmatter($content);

            expect($result['frontmatter']['title'])->toBe('Mi Página');
            expect($result['frontmatter']['chapter'])->toBe('Introducción');
            expect($result['content'])->toContain('# Contenido');
        });

        it('returns content as-is when no frontmatter', function () {
            $content = '# Just a Heading';
            $result = $this->parser->extractFrontmatter($content);

            expect($result['frontmatter'])->toBeEmpty();
            expect($result['content'])->toBe($content);
        });
    });

    describe('converter access', function () {
        it('provides access to underlying BookmarkConverter', function () {
            $converter = $this->parser->getConverter();

            expect($converter)->toBeInstanceOf(\AichaDigital\BookStackSync\Parsers\BookmarkConverter::class);
        });
    });
});
