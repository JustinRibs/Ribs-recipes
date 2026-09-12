<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Duration;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Extracts a Schema.org Recipe from a page's JSON-LD.
 *
 * This is the highest-fidelity source available, and almost every modern
 * recipe site publishes it. The awkward parts of the format are all handled:
 *
 *   - `@graph` containers and top-level arrays,
 *   - `@type` given as a string or an array ("Recipe" vs ["Recipe","NewsArticle"]),
 *   - instructions as plain strings, HowToStep objects, or HowToSection groups
 *     that nest their own step lists,
 *   - images as a string, an array, or an ImageObject with a `url`,
 *   - ISO 8601 durations, and the plain-English ones sites use anyway,
 *   - `recipeYield` as a number, a string, or an array.
 */
final class JsonLdRecipeParser
{
    public function __construct(private readonly IngredientParser $ingredients) {}

    public function parse(string $html, string $sourceUrl): ?ImportedRecipe
    {
        $node = $this->findRecipeNode($html);

        if ($node === null) {
            return null;
        }

        $recipe = new ImportedRecipe(
            title: $this->text($node['name'] ?? '') ?? '',
            description: $this->text($node['description'] ?? null),
            sourceUrl: $sourceUrl,
            sourceName: $this->sourceName($node, $sourceUrl),
            heroImageUrl: $this->image($node['image'] ?? null),
            extractedVia: 'json-ld',
        );

        $recipe->prepMinutes = Duration::toMinutes($node['prepTime'] ?? null);
        $recipe->cookMinutes = Duration::toMinutes($node['cookTime'] ?? null);
        $recipe->totalMinutes = Duration::toMinutes($node['totalTime'] ?? null);

        [$recipe->servings, $recipe->servingsLabel] = $this->yield($node['recipeYield'] ?? null);
        $recipe->calories = $this->calories($node['nutrition'] ?? null);
        $recipe->tags = $this->keywords($node);
        $recipe->ingredients = $this->ingredients($node['recipeIngredient'] ?? $node['ingredients'] ?? null);
        $recipe->steps = $this->instructions($node['recipeInstructions'] ?? null);

        if ($recipe->title === '') {
            $recipe->warnings[] = 'The page did not name the recipe — add a title before saving.';
        }

        if ($recipe->ingredients === []) {
            $recipe->warnings[] = 'No ingredients were published in the page data.';
        }

        if ($recipe->steps === []) {
            $recipe->warnings[] = 'No instructions were published in the page data.';
        }

        return $recipe;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRecipeNode(string $html): ?array
    {
        foreach ($this->jsonLdDocuments($html) as $document) {
            $found = $this->searchForRecipe($document);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    private function jsonLdDocuments(string $html): array
    {
        $documents = [];

        try {
            $crawler = new Crawler($html);
            $scripts = $crawler->filter('script[type="application/ld+json"]');
        } catch (\Throwable) {
            return [];
        }

        foreach ($scripts as $script) {
            $raw = trim((string) $script->textContent);

            if ($raw === '') {
                continue;
            }

            // Some CMS plugins emit JSON wrapped in CDATA or with stray
            // trailing commas; a failed decode is simply skipped.
            $raw = preg_replace('#^\s*/\*\s*<!\[CDATA\[\s*\*/|/\*\s*\]\]>\s*\*/\s*$#', '', $raw) ?? $raw;

            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $documents[] = $decoded;
            }
        }

        return $documents;
    }

    /**
     * Depth-first hunt for the first node typed as a Recipe.
     *
     * @return array<string, mixed>|null
     */
    private function searchForRecipe(mixed $node, int $depth = 0): ?array
    {
        if ($depth > 6 || ! is_array($node)) {
            return null;
        }

        if ($this->isRecipeNode($node)) {
            /** @var array<string, mixed> $node */
            return $node;
        }

        foreach (['@graph', 'mainEntity', 'mainEntityOfPage', 'itemListElement'] as $container) {
            if (isset($node[$container])) {
                $found = $this->searchForRecipe($node[$container], $depth + 1);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $found = $this->searchForRecipe($value, $depth + 1);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function isRecipeNode(array $node): bool
    {
        $types = $node['@type'] ?? null;
        $types = is_array($types) ? $types : [$types];

        foreach ($types as $type) {
            if (is_string($type) && strcasecmp(ltrim($type, '/'), 'Recipe') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function sourceName(array $node, string $url): ?string
    {
        foreach ([$node['publisher']['name'] ?? null, $node['author']['name'] ?? null, $node['author'] ?? null] as $candidate) {
            $name = $this->text($candidate);

            if ($name !== null && $name !== '') {
                return Str::limit($name, 120, '');
            }
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? Str::of($host)->after('www.')->toString() : null;
    }

    private function image(mixed $value, int $depth = 0): ?string
    {
        if ($depth > 3) {
            return null;
        }

        if (is_string($value)) {
            return str_starts_with($value, 'http') ? $value : null;
        }

        if (! is_array($value)) {
            return null;
        }

        if (isset($value['url'])) {
            return $this->image($value['url'], $depth + 1);
        }

        foreach ($value as $item) {
            $url = $this->image($item, $depth + 1);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private function yield(mixed $value): array
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (is_int($value) || is_float($value)) {
            return [max(1, (int) $value), null];
        }

        if (! is_string($value)) {
            return [null, null];
        }

        if (preg_match('/(\d+)/', $value, $m) !== 1) {
            return [null, null];
        }

        $label = trim(preg_replace('/\d+(\s*-\s*\d+)?/', '', $value) ?? '');
        $label = trim($label, " \t\n\r\0\x0B-–—");

        return [max(1, (int) $m[1]), $label !== '' ? Str::limit($label, 40, '') : null];
    }

    private function calories(mixed $nutrition): ?int
    {
        if (! is_array($nutrition)) {
            return null;
        }

        $calories = $nutrition['calories'] ?? null;

        if (is_int($calories) || is_float($calories)) {
            return max(0, (int) $calories);
        }

        if (is_string($calories) && preg_match('/(\d+(?:\.\d+)?)/', $calories, $m) === 1) {
            return max(0, (int) round((float) $m[1]));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function keywords(array $node): array
    {
        $sources = [];

        foreach (['keywords', 'recipeCuisine', 'suitableForDiet'] as $key) {
            $value = $node[$key] ?? null;

            if (is_string($value)) {
                $sources = [...$sources, ...explode(',', $value)];
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $sources[] = $item;
                    }
                }
            }
        }

        $tags = [];

        foreach ($sources as $source) {
            // schema.org diet values arrive as URLs; keep the readable tail.
            $tag = trim(Str::of((string) $source)->afterLast('/')->replace('Diet', '')->toString());
            $tag = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $tag) ?? $tag);

            if ($tag !== '' && mb_strlen($tag) <= 40) {
                $tags[] = Str::title($tag);
            }
        }

        return array_values(array_slice(array_unique($tags), 0, 10));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ingredients(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $item) {
            $line = $this->text($item);

            if ($line === null || $line === '') {
                continue;
            }

            $parsed = $this->ingredients->parse($line);

            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        return array_slice($rows, 0, 200);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function instructions(mixed $value, int $depth = 0): array
    {
        if ($depth > 3 || $value === null) {
            return [];
        }

        // A single blob of text: split it into sentences-as-steps.
        if (is_string($value)) {
            return (new InstructionSplitter)->split($value);
        }

        if (! is_array($value)) {
            return [];
        }

        $steps = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $steps = [...$steps, ...(new InstructionSplitter)->split($item)];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $type = $item['@type'] ?? null;
            $type = is_array($type) ? ($type[0] ?? null) : $type;

            // HowToSection groups steps under a heading ("For the sauce").
            if (is_string($type) && strcasecmp($type, 'HowToSection') === 0) {
                $steps = [...$steps, ...$this->instructions($item['itemListElement'] ?? null, $depth + 1)];

                continue;
            }

            $text = $this->text($item['text'] ?? $item['name'] ?? null);

            if ($text === null || $text === '') {
                continue;
            }

            $steps[] = [
                'instruction' => $text,
                'timer_seconds' => null,
            ];
        }

        return array_slice($steps, 0, 100);
    }

    private function text(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['name'] ?? $value['text'] ?? $value[0] ?? null;

            if (is_array($value)) {
                return null;
            }
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
