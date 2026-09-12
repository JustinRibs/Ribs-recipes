<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Last resort when a page publishes no structured recipe data.
 *
 * This is deliberately narrow. It reads Open Graph / basic page metadata for
 * the title, description and hero image, and only looks for ingredients and
 * steps inside containers that explicitly name themselves as such. It will not
 * scrape an article's paragraphs hoping they are a recipe — an import preview
 * full of a blogger's childhood story is worse than an empty one.
 */
final class HtmlFallbackParser
{
    /** Class/id fragments that reliably denote a real ingredient list. */
    private const INGREDIENT_HINTS = ['ingredient', 'sastojci', 'zutaten'];

    /** Class/id fragments that reliably denote instructions. */
    private const INSTRUCTION_HINTS = ['instruction', 'direction', 'method', 'preparation', 'priprema', 'zubereitung'];

    public function __construct(private readonly IngredientParser $ingredients) {}

    public function parse(string $html, string $sourceUrl): ImportedRecipe
    {
        try {
            $crawler = new Crawler($html);
        } catch (\Throwable) {
            return new ImportedRecipe(
                sourceUrl: $sourceUrl,
                warnings: ['That page could not be read.'],
                extractedVia: 'fallback',
            );
        }

        $recipe = new ImportedRecipe(
            title: $this->title($crawler),
            description: $this->description($crawler),
            sourceUrl: $sourceUrl,
            sourceName: $this->siteName($crawler, $sourceUrl),
            heroImageUrl: $this->heroImage($crawler),
            extractedVia: 'fallback',
        );

        foreach ($this->listItems($crawler, self::INGREDIENT_HINTS) as $line) {
            $parsed = $this->ingredients->parse($line);

            if ($parsed !== null) {
                $recipe->ingredients[] = $parsed;
            }
        }

        $splitter = new InstructionSplitter;

        foreach ($this->listItems($crawler, self::INSTRUCTION_HINTS) as $line) {
            $recipe->steps = [...$recipe->steps, ...$splitter->split($line)];
        }

        $recipe->ingredients = array_slice($recipe->ingredients, 0, 200);
        $recipe->steps = array_slice($recipe->steps, 0, 100);

        $recipe->warnings[] = 'This page does not publish structured recipe data, so only the basics could be read. Check everything below carefully.';

        if ($recipe->ingredients === []) {
            $recipe->warnings[] = 'No ingredient list was found — you will need to type the ingredients in.';
        }

        if ($recipe->steps === []) {
            $recipe->warnings[] = 'No instructions were found — you will need to type the steps in.';
        }

        return $recipe;
    }

    private function title(Crawler $crawler): string
    {
        foreach ([
            fn (): ?string => $this->meta($crawler, 'og:title'),
            fn (): ?string => $this->firstText($crawler, 'h1'),
            fn (): ?string => $this->firstText($crawler, 'title'),
        ] as $strategy) {
            $value = $strategy();

            if ($value !== null && $value !== '') {
                // Strip the "| Site Name" suffix newspapers love.
                return Str::limit(trim(preg_replace('/\s*[|–—]\s*[^|–—]{2,40}$/u', '', $value) ?: $value), 200, '');
            }
        }

        return '';
    }

    private function description(Crawler $crawler): ?string
    {
        $value = $this->meta($crawler, 'og:description') ?? $this->metaName($crawler, 'description');

        return $value === null ? null : Str::limit($value, 500, '');
    }

    private function heroImage(Crawler $crawler): ?string
    {
        foreach (['og:image', 'twitter:image', 'og:image:secure_url'] as $property) {
            $value = $this->meta($crawler, $property) ?? $this->metaName($crawler, $property);

            if (is_string($value) && str_starts_with($value, 'http')) {
                return $value;
            }
        }

        return null;
    }

    private function siteName(Crawler $crawler, string $url): ?string
    {
        $name = $this->meta($crawler, 'og:site_name');

        if ($name !== null && $name !== '') {
            return Str::limit($name, 120, '');
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? Str::of($host)->after('www.')->toString() : null;
    }

    /**
     * Text of every <li> (or <p>) inside a container that names itself with one
     * of the given hints.
     *
     * @param  list<string>  $hints
     * @return list<string>
     */
    private function listItems(Crawler $crawler, array $hints): array
    {
        $items = [];

        foreach ($hints as $hint) {
            foreach (['class', 'id'] as $attribute) {
                try {
                    $containers = $crawler->filter("[{$attribute}*=\"{$hint}\"]");
                } catch (\Throwable) {
                    continue;
                }

                foreach ($containers as $container) {
                    $node = new Crawler($container);

                    try {
                        $children = $node->filter('li');

                        if ($children->count() === 0) {
                            $children = $node->filter('p');
                        }
                    } catch (\Throwable) {
                        continue;
                    }

                    foreach ($children as $child) {
                        $text = trim(preg_replace('/\s+/u', ' ', (new Crawler($child))->text('')) ?? '');

                        // Guard against swallowing a whole article body.
                        if ($text !== '' && mb_strlen($text) <= 600) {
                            $items[] = $text;
                        }
                    }

                    if ($items !== []) {
                        return array_values(array_unique($items));
                    }
                }
            }
        }

        return $items;
    }

    private function meta(Crawler $crawler, string $property): ?string
    {
        return $this->attr($crawler, "meta[property=\"{$property}\"]", 'content');
    }

    private function metaName(Crawler $crawler, string $name): ?string
    {
        return $this->attr($crawler, "meta[name=\"{$name}\"]", 'content');
    }

    private function attr(Crawler $crawler, string $selector, string $attribute): ?string
    {
        try {
            $nodes = $crawler->filter($selector);
        } catch (\Throwable) {
            return null;
        }

        if ($nodes->count() === 0) {
            return null;
        }

        $value = $nodes->first()->attr($attribute);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        try {
            $nodes = $crawler->filter($selector);
        } catch (\Throwable) {
            return null;
        }

        if ($nodes->count() === 0) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $nodes->first()->text('')) ?? '');

        return $text !== '' ? $text : null;
    }
}
