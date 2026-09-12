<?php

declare(strict_types=1);

namespace App\Services\Recipes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Unique, SEO-friendly slugs.
 *
 * Uniqueness is checked including soft-deleted rows, so restoring a trashed
 * recipe can never collide with one created in the meantime.
 */
final class SlugGenerator
{
    private const MAX_LENGTH = 120;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function generate(string $modelClass, string $source, ?int $ignoreId = null): string
    {
        $base = Str::of($source)->ascii()->slug('-')->limit(self::MAX_LENGTH, '')->toString();

        if ($base === '') {
            $base = 'recipe';
        }

        $slug = $base;
        $suffix = 1;

        while ($this->exists($modelClass, $slug, $ignoreId)) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function exists(string $modelClass, string $slug, ?int $ignoreId): bool
    {
        $query = $modelClass::query()->where('slug', $slug);

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
