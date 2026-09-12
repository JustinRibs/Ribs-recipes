<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImageSource;
use Database\Factories\RecipeImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A single image attached to a recipe.
 *
 * Local images carry a `variants` ladder produced at upload time; remote images
 * are a single hot-linked URL with no derivatives. Both render through the same
 * front-end component, which simply omits the srcset when there is none.
 *
 * @property ImageSource $source
 * @property array<int, array{width:int,height:int,format:string,path:string,bytes:int}>|null $variants
 */
class RecipeImage extends Model
{
    /** @use HasFactory<RecipeImageFactory> */
    use HasFactory;

    protected $fillable = [
        'source', 'disk', 'path', 'variants', 'url', 'width', 'height',
        'mime', 'bytes', 'placeholder', 'caption', 'alt', 'is_hero', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'source' => ImageSource::class,
            'variants' => 'array',
            'is_hero' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * The URL used as the plain `src` — the widest safe fallback format.
     */
    public function displayUrl(): ?string
    {
        if ($this->source === ImageSource::Remote) {
            return $this->url;
        }

        $fallback = $this->variantsOfFormat('jpeg');

        if ($fallback !== []) {
            return $this->diskUrl(end($fallback)['path']);
        }

        return $this->path ? $this->diskUrl($this->path) : null;
    }

    /**
     * A `srcset` of WebP derivatives, or null for remote/unprocessed images.
     */
    public function srcset(): ?string
    {
        $webp = $this->variantsOfFormat('webp');

        if ($webp === []) {
            return null;
        }

        return collect($webp)
            ->map(fn (array $v): string => $this->diskUrl($v['path']).' '.$v['width'].'w')
            ->implode(', ');
    }

    /**
     * Smallest stored derivative — used for admin thumbnails and compact cards.
     */
    public function thumbnailUrl(): ?string
    {
        $webp = $this->variantsOfFormat('webp');

        return $webp === []
            ? $this->displayUrl()
            : $this->diskUrl($webp[0]['path']);
    }

    /**
     * Every stored file belonging to this image, for cleanup on delete.
     *
     * @return list<string>
     */
    public function storedPaths(): array
    {
        if ($this->source !== ImageSource::Local) {
            return [];
        }

        $paths = collect($this->variants ?? [])->pluck('path')->all();

        if ($this->path) {
            $paths[] = $this->path;
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * @return list<array{width:int,height:int,format:string,path:string,bytes:int}>
     */
    private function variantsOfFormat(string $format): array
    {
        return collect($this->variants ?? [])
            ->where('format', $format)
            ->sortBy('width')
            ->values()
            ->all();
    }

    private function diskUrl(string $path): string
    {
        return Storage::disk($this->disk ?? config('ribs.images.disk'))->url($path);
    }
}
