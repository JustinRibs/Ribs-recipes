<?php

declare(strict_types=1);

namespace App\Services\Http;

final readonly class FetchedResource
{
    public function __construct(
        public string $finalUrl,
        public string $body,
        public string $contentType,
        public int $status,
    ) {}

    public function isHtml(): bool
    {
        return str_contains($this->contentType, 'text/html')
            || str_contains($this->contentType, 'application/xhtml+xml');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->contentType, 'image/');
    }
}
