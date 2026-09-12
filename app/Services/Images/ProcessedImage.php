<?php

declare(strict_types=1);

namespace App\Services\Images;

final readonly class ProcessedImage
{
    /**
     * @param  list<array{width:int,height:int,format:string,path:string,bytes:int}>  $variants
     */
    public function __construct(
        public string $path,
        public array $variants,
        public int $width,
        public int $height,
        public string $mime,
        public int $bytes,
        public ?string $placeholder,
    ) {}
}
