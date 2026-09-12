<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * A plain XML sitemap of everything public.
 *
 * Generated rather than stored, and cached for an hour — a personal collection
 * changes a few times a week at most, and this keeps one crawler request from
 * turning into a few hundred queries.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('ribs.sitemap', now()->addHour(), fn (): string => $this->build());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function build(): string
    {
        $entries = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('recipes.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
        ];

        foreach (Recipe::query()->published()->orderByDesc('updated_at')->cursor() as $recipe) {
            $entries[] = [
                'loc' => route('recipes.show', $recipe->slug),
                'lastmod' => $recipe->updated_at?->toAtomString(),
                'priority' => '0.8',
                'changefreq' => 'monthly',
            ];
        }

        foreach (Category::query()->whereHas('recipes', fn ($q) => $q->published())->cursor() as $category) {
            $entries[] = ['loc' => route('categories.show', $category->slug), 'priority' => '0.6'];
        }

        foreach (Tag::query()->whereHas('recipes', fn ($q) => $q->published())->cursor() as $tag) {
            $entries[] = ['loc' => route('tags.show', $tag->slug), 'priority' => '0.4'];
        }

        $body = '';

        foreach ($entries as $entry) {
            $body .= '  <url>'.PHP_EOL;
            $body .= '    <loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>'.PHP_EOL;

            if (! empty($entry['lastmod'])) {
                $body .= '    <lastmod>'.$entry['lastmod'].'</lastmod>'.PHP_EOL;
            }

            if (! empty($entry['changefreq'])) {
                $body .= '    <changefreq>'.$entry['changefreq'].'</changefreq>'.PHP_EOL;
            }

            $body .= '    <priority>'.$entry['priority'].'</priority>'.PHP_EOL;
            $body .= '  </url>'.PHP_EOL;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL
            .$body
            .'</urlset>'.PHP_EOL;
    }
}
