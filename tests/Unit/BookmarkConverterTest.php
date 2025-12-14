<?php

declare(strict_types=1);

use AichaDigital\BookStackSync\Parsers\BookmarkConverter;

beforeEach(function () {
    $this->converter = new BookmarkConverter('UTF-8');
});

describe('BookmarkConverter', function () {
    describe('Spanish character encoding', function () {
        it('encodes all Spanish accented vowels', function () {
            $map = $this->converter->getSpanishEncodingMap();

            expect($map['á'])->toBe('%C3%A1');
            expect($map['é'])->toBe('%C3%A9');
            expect($map['í'])->toBe('%C3%AD');
            expect($map['ó'])->toBe('%C3%B3');
            expect($map['ú'])->toBe('%C3%BA');
        });

        it('encodes ñ character', function () {
            $map = $this->converter->getSpanishEncodingMap();

            expect($map['ñ'])->toBe('%C3%B1');
            expect($map['Ñ'])->toBe('%C3%91');
        });

        it('encodes ü and ç characters', function () {
            $map = $this->converter->getSpanishEncodingMap();

            expect($map['ü'])->toBe('%C3%BC');
            expect($map['ç'])->toBe('%C3%A7');
        });
    });

    describe('headingToBookStackId', function () {
        it('generates BookStack ID from heading text', function () {
            $result = $this->converter->headingToBookStackId('Operaciones Básicas');

            // BookStack algorithm: trim -> spaces to hyphens -> strtolower -> 20 chars -> bkmrk- prefix -> urlencode
            expect($result)->toBe('bkmrk-operaciones-b%C3%A1sicas');
        });

        it('truncates to 20 characters', function () {
            $result = $this->converter->headingToBookStackId('Este es un título muy largo que excede veinte caracteres');

            // "este-es-un-título-mu" = 20 chars
            expect($result)->toBe('bkmrk-este-es-un-t%C3%ADtulo-mu');
        });

        it('handles ñ character', function () {
            $result = $this->converter->headingToBookStackId('Año Español');

            expect($result)->toBe('bkmrk-a%C3%B1o-espa%C3%B1ol');
        });

        it('preserves hyphens in heading', function () {
            $result = $this->converter->headingToBookStackId('mi-sección');

            expect($result)->toContain('-');
            expect($result)->toBe('bkmrk-mi-secci%C3%B3n');
        });
    });

    describe('toBookStack conversion', function () {
        it('converts AI-style anchor to BookStack format', function () {
            $result = $this->converter->toBookStack('operaciones-básicas');

            // AI anchor has hyphens already, reverse transforms to "operaciones básicas"
            // Then BookStack algorithm applies
            expect($result)->toBe('bkmrk-operaciones-b%C3%A1sicas');
        });

        it('handles CamelCase anchors', function () {
            $result = $this->converter->toBookStack('MiSecciónFavorita');

            // CamelCase -> "Mi Sección Favorita" -> "mi-sección-favorita" (19 chars) -> bkmrk-
            expect($result)->toBe('bkmrk-mi-secci%C3%B3n-favorita');
        });

        it('removes leading hash if present', function () {
            $result = $this->converter->toBookStack('#sección');

            expect($result)->not->toStartWith('#');
            expect($result)->toBe('bkmrk-secci%C3%B3n');
        });
    });

    describe('fromBookStack conversion', function () {
        it('decodes URL-encoded bookmark and removes prefix', function () {
            $encoded = 'bkmrk-secci%C3%B3n-espa%C3%B1ola';
            $result = $this->converter->fromBookStack($encoded);

            expect($result)->toBe('sección-española');
        });

        it('decodes complex bookmark', function () {
            $encoded = 'bkmrk-configuraci%C3%B3n-del';
            $result = $this->converter->fromBookStack($encoded);

            expect($result)->toBe('configuración-del');
        });

        it('handles non-prefixed bookmark', function () {
            $encoded = 'secci%C3%B3n';
            $result = $this->converter->fromBookStack($encoded);

            expect($result)->toBe('sección');
        });
    });

    describe('isBookStackFormat check', function () {
        it('returns true for bkmrk- prefixed anchors', function () {
            expect($this->converter->isBookStackFormat('bkmrk-section'))->toBeTrue();
            expect($this->converter->isBookStackFormat('bkmrk-secci%C3%B3n'))->toBeTrue();
            expect($this->converter->isBookStackFormat('#bkmrk-test'))->toBeTrue();
        });

        it('returns false for AI-style anchors', function () {
            expect($this->converter->isBookStackFormat('section'))->toBeFalse();
            expect($this->converter->isBookStackFormat('sección'))->toBeFalse();
            expect($this->converter->isBookStackFormat('#my-anchor'))->toBeFalse();
        });
    });

    describe('needsConversion check', function () {
        it('returns true for non-BookStack anchors', function () {
            expect($this->converter->needsConversion('sección'))->toBeTrue();
            expect($this->converter->needsConversion('my-anchor'))->toBeTrue();
        });

        it('returns false for BookStack format anchors', function () {
            expect($this->converter->needsConversion('bkmrk-section'))->toBeFalse();
            expect($this->converter->needsConversion('bkmrk-secci%C3%B3n'))->toBeFalse();
        });
    });

    describe('normalizeAnchor', function () {
        it('converts to lowercase', function () {
            $result = $this->converter->normalizeAnchor('SECCIÓN');

            expect($result)->toBe('sección');
        });

        it('replaces spaces with hyphens', function () {
            $result = $this->converter->normalizeAnchor('mi sección favorita');

            expect($result)->toBe('mi-sección-favorita');
        });
    });
});
