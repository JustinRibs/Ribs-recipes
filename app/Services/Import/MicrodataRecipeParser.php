<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Duration;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Schema.org Recipe published as HTML microdata rather than JSON-LD.
 *
 * Plenty of older food blogs — and a few big ones — still mark recipes up this
 * way, so supporting it meaningfully widens what the importer can read.
 */
final class MicrodataRecipeParser
{
    public function __construct(private readonly IngredientParser $ingredients) {}

    public function parse(string $html, string $sourceUrl): ?ImportedRecipe
    {
        try {
            $crawler = new Crawler($html);
            $scopes = $crawler->filter('[itemtype*="schema.org/Recipe"]');
        } catch (\Throwable) {
            return null;
        }

        if ($scopes->count() === 0) {
            return null;
        }

        $scope = $scopes->first();

        $recipe = new ImportedRecipe(
            title: $this->prop($scope, 'name') ?? '',
            description: $this->prop($scope, 'description'),
            sourceUrl: $sourceUrl,
            sourceName: $this->sourceName($scope, $sourceUrl),
            heroImageUrl: $this->imageProp($scope),
            extractedVia: 'microdata',
        );

        $recipe->prepMinutes = Duration::toMinutes($this->prop($scope, 'prepTime'));
        $recipe->cookMinutes = Duration::toMinutes($this->prop($scope, 'cookTime'));
        $recipe->totalMinutes = Duration::toMinutes($this->prop($scope, 'totalTime'));

        $yieldText = $this->prop($scope, 'recipeYield');

        if ($yieldText !== null && preg_match('/(\d+)/', $yieldText, $m) === 1) {
            $recipe->servings = max(1, (int) $m[1]);
        }

        $calories = $this->prop($scope, 'calories');

        if ($calories !== null && preg_match('/(\d+)/', $calories, $m) === 1) {
            $recipe->calories = (int) $m[1];
        }

        foreach ($this->propList($scope, ['recipeIngredient', 'ingredients']) as $line) {
            $parsed = $this->ingredients->parse($line);

            if ($parsed !== null) {
                $recipe->ingredients[] = $parsed;
            }
        }

        $splitter = new InstructionSplitter;

        foreach ($this->propList($scope, ['recipeInstructions']) as $line) {
            $recipe->steps = [...$recipe->steps, ...$splitter->split($line)];
        }

        $recipe->ingredients = array_slice($recipe->ingredients, 0, 200);
        $recipe->steps = array_slice($recipe->steps, 0, 100);

        return $recipe->title !== '' || $recipe->ingredients !== [] ? $recipe : null;
    }

    private function prop(Crawler $scope, string $name): ?string
    {
        try {
            $nodes = $scope->filter('[itemprop="'.$name.'"]');
        } catch (\Throwable) {
            return null;
        }

        if ($nodes->count() === 0) {
            return null;
        }

        $node = $nodes->first();

        // <meta itemprop="prepTime" content="PT20M"> carries its value in an
        // attribute rather than as text.
        foreach (['content', 'datetime', 'value'] as $attribute) {
            $value = $node->attr($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $this->clean($node->text(''));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function propList(Crawler $scope, array $names): array
    {
        $values = [];

        foreach ($names as $name) {
            try {
                $nodes = $scope->filter('[itemprop="'.$name.'"]');
            } catch (\Throwable) {
                continue;
            }

            foreach ($nodes as $node) {
                $text = $this->clean((new Crawler($node))->text(''));

                if ($text !== '') {
                    $values[] = $text;
                }
            }

            if ($values !== []) {
                break;
            }
        }

        return $values;
    }

    private function imageProp(Crawler $scope): ?string
    {
        try {
            $nodes = $scope->filter('[itemprop="image"]');
        } catch (\Throwable) {
            return null;
        }

        if ($nodes->count() === 0) {
            return null;
        }

        $node = $nodes->first();

        foreach (['src', 'content', 'href'] as $attribute) {
            $value = $node->attr($attribute);

            if (is_string($value) && str_starts_with($value, 'http')) {
                return $value;
            }
        }

        return null;
    }

    private function sourceName(Crawler $scope, string $url): ?string
    {
        $author = $this->prop($scope, 'author');

        if ($author !== null && $author !== '') {
            return Str::limit($author, 120, '');
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? Str::of($host)->after('www.')->toString() : null;
    }

    private function clean(string $text): string
    {
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
