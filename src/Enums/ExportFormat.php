<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Enums;

enum ExportFormat: string
{
    case HTML = 'html';
    case PDF = 'pdf';
    case PLAINTEXT = 'plaintext';
    case MARKDOWN = 'markdown';
    case ZIP = 'zip';
}
