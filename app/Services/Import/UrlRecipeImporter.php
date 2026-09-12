<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Exceptions\FetchFailedException;
use App\Exceptions\UnsafeUrlException;
use App\Services\Http\UrlFetcher;
use App\Services\Http\UrlGuard;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a recipe page and extracts what it can, best source first.
 *
 * Nothing here writes to the database. The result is handed to an editable
 * preview, and only an explicit Save turns it into a recipe.
 */
final class UrlRecipeImporter
{
    public function __construct(
        private readonly UrlFetcher $http,
        private readonly UrlGuard $guard,
        private readonly JsonLdRecipeParser $jsonLd,
        private readonly MicrodataRecipeParser $microdata,
        private readonly HtmlFallbackParser $fallback,
    ) {}

    /**
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function import(string $url): ImportedRecipe
    {
        $response = $this->http->fetchHtml($url);
        $html = $this->decodeBody($response->body, $response->contentType);

        $recipe = $this->jsonLd->parse($html, $response->finalUrl);

        if ($recipe === null || ! $recipe->isUsable()) {
            $microdata = $this->microdata->parse($html, $response->finalUrl);

            if ($microdata !== null && $microdata->isUsable()) {
                $recipe = $microdata;
            }
        }

        if ($recipe === null || ! $recipe->isUsable()) {
            $recipe = $this->fallback->parse($html, $response->finalUrl);
        }

        $recipe->heroImageUrl = $this->normaliseImageUrl($recipe->heroImageUrl, $response->finalUrl);

        Log::channel('import')->info('URL import parsed', [
            'host' => parse_url($response->finalUrl, PHP_URL_HOST),
            'via' => $recipe->extractedVia,
            'ingredients' => count($recipe->ingredients),
            'steps' => count($recipe->steps),
        ]);

        return $recipe;
    }

    /**
     * Normalise the page's declared charset to UTF-8 so accented ingredients
     * ("crème fraîche", "ćevapi") do not arrive as mojibake.
     */
    private function decodeBody(string $body, string $contentType): string
    {
        $charset = null;

        if (preg_match('/charset=["\']?([\w-]+)/i', $contentType, $m) === 1) {
            $charset = strtoupper($m[1]);
        }

        if ($charset === null && preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', substr($body, 0, 2048), $m) === 1) {
            $charset = strtoupper($m[1]);
        }

        if ($charset === null || in_array($charset, ['UTF-8', 'UTF8'], true)) {
            return $body;
        }

        $converted = @mb_convert_encoding($body, 'UTF-8', $charset);

        if (! is_string($converted)) {
            return $body;
        }

        // The document still declares its old charset, and the HTML parser
        // believes the declaration over the bytes — leaving it would undo the
        // conversion and turn "crème" back into mojibake.
        return preg_replace(
            '/<meta[^>]+charset=["\']?[\w-]+["\']?[^>]*>/i',
            '<meta charset="utf-8">',
            $converted,
            1
        ) ?? $converted;
    }

    /**
     * Make a hero image URL absolute, dropping anything obviously unusable.
     *
     * Only the cheap syntactic check runs here. The URL is not fetched at this
     * point — it is shown in the preview, and the full SSRF validation happens
     * if and when the administrator asks for the photo to be downloaded or
     * linked.
     */
    private function normaliseImageUrl(?string $url, string $base): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $absolute = $this->guard->absolutise(trim($url), $base);

        return $this->guard->isPlausible($absolute) ? $absolute : null;
    }
}
