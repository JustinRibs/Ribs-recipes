<?php

declare(strict_types=1);

namespace App\Services\Recipes;

use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * Turns free-text tag names into Tag models, matching case-insensitively on
 * slug so "High Protein", "high protein" and "High-Protein" converge.
 */
final class TagResolver
{
    public function __construct(private readonly SlugGenerator $slugs) {}

    /**
     * @param  iterable<int, string>  $names
     * @return list<int>
     */
    public function resolveIds(iterable $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $tag = $this->resolve($name);

            if (! in_array($tag->id, $ids, true)) {
                $ids[] = $tag->id;
            }
        }

        return $ids;
    }

    public function resolve(string $name): Tag
    {
        $slug = Str::of($name)->ascii()->slug('-')->toString();

        $existing = Tag::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            return $existing;
        }

        return Tag::create([
            'name' => Str::limit($name, 60, ''),
            'slug' => $slug !== '' ? $this->slugs->generate(Tag::class, $name) : Str::random(8),
        ]);
    }
}
