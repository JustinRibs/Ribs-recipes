<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\UserRole;
use App\Exceptions\FetchFailedException;
use App\Exceptions\UnsafeUrlException;
use App\Models\User;
use App\Services\Http\FetchedResource;
use App\Services\Http\UrlFetcher;
use App\Services\Import\UrlRecipeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesCloudflareAccess;
use Tests\TestCase;

/**
 * The importer is tested against realistic page markup, with the network layer
 * swapped for a stub. The SSRF guard that layer wraps has its own unit tests;
 * here the concern is what gets extracted, and that nothing is ever saved
 * without an explicit second step.
 */
class UrlImportTest extends TestCase
{
    use FakesCloudflareAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'role' => UserRole::Owner]);
    }

    #[Test]
    public function it_reads_a_schema_org_recipe_from_json_ld(): void
    {
        $this->fakeFetch($this->jsonLdPage());

        $recipe = app(UrlRecipeImporter::class)->import('https://example.com/oats');

        $this->assertSame('json-ld', $recipe->extractedVia);
        $this->assertSame('Banana Protein Baked Oats', $recipe->title);
        $this->assertSame('Warm oats that keep you full.', $recipe->description);
        $this->assertSame(10, $recipe->prepMinutes);
        $this->assertSame(25, $recipe->cookMinutes);
        $this->assertSame(35, $recipe->totalMinutes);
        $this->assertSame(2, $recipe->servings);
        $this->assertSame(410, $recipe->calories);
        $this->assertSame('https://cdn.example.com/oats.jpg', $recipe->heroImageUrl);
        $this->assertSame('Example Kitchen', $recipe->sourceName);

        $this->assertCount(3, $recipe->ingredients);
        $this->assertSame('rolled oats', $recipe->ingredients[0]['name']);
        $this->assertSame(1.0, $recipe->ingredients[0]['quantity']);
        $this->assertSame('cup', $recipe->ingredients[0]['unit']);

        $this->assertCount(3, $recipe->steps);
        $this->assertSame('Heat the oven to 350F.', $recipe->steps[0]['instruction']);

        $this->assertContains('Healthy', $recipe->tags);
    }

    #[Test]
    public function it_finds_the_recipe_inside_an_at_graph(): void
    {
        $this->fakeFetch($this->graphPage());

        $recipe = app(UrlRecipeImporter::class)->import('https://example.com/graph');

        $this->assertSame('json-ld', $recipe->extractedVia);
        $this->assertSame('Graph Recipe', $recipe->title);
        $this->assertCount(1, $recipe->ingredients);
    }

    #[Test]
    public function it_flattens_how_to_sections_into_a_single_list_of_steps(): void
    {
        $this->fakeFetch($this->sectionedPage());

        $recipe = app(UrlRecipeImporter::class)->import('https://example.com/sections');

        $this->assertSame(
            ['Chop the onion.', 'Fry it.', 'Whisk the sauce.', 'Combine.'],
            array_column($recipe->steps, 'instruction')
        );
    }

    #[Test]
    public function it_falls_back_to_microdata(): void
    {
        $this->fakeFetch($this->microdataPage());

        $recipe = app(UrlRecipeImporter::class)->import('https://example.com/microdata');

        $this->assertSame('microdata', $recipe->extractedVia);
        $this->assertSame('Microdata Stew', $recipe->title);
        $this->assertSame(45, $recipe->cookMinutes);
        $this->assertCount(2, $recipe->ingredients);
        $this->assertCount(2, $recipe->steps);
    }

    #[Test]
    public function it_falls_back_to_page_metadata_and_says_so(): void
    {
        $this->fakeFetch($this->plainArticlePage());

        $recipe = app(UrlRecipeImporter::class)->import('https://example.com/blog');

        $this->assertSame('fallback', $recipe->extractedVia);
        $this->assertSame('A Long Story About Summer', $recipe->title);
        $this->assertSame('https://cdn.example.com/story.jpg', $recipe->heroImageUrl);

        // The story's paragraphs must not be mistaken for a method.
        $this->assertSame([], $recipe->steps);
        $this->assertSame([], $recipe->ingredients);
        $this->assertNotEmpty($recipe->warnings);
    }

    #[Test]
    public function it_reads_ingredient_and_instruction_lists_when_they_name_themselves(): void
    {
        $this->fakeFetch($this->semanticBlogPage());

        $recipe = app(UrlRecipeImporter::class)->import('https://example.com/blog2');

        $this->assertSame('fallback', $recipe->extractedVia);
        $this->assertCount(2, $recipe->ingredients);
        $this->assertSame('flour', $recipe->ingredients[0]['name']);
        $this->assertCount(2, $recipe->steps);
    }

    #[Test]
    public function it_converts_a_pages_declared_charset(): void
    {
        $latin1 = mb_convert_encoding(
            '<html><head><meta charset="ISO-8859-1"><title>Crème Brûlée</title>'
            .'<meta property="og:title" content="Crème Brûlée"></head><body></body></html>',
            'ISO-8859-1',
            'UTF-8'
        );

        $this->fakeFetch($latin1, 'text/html; charset=ISO-8859-1');

        $recipe = app(UrlRecipeImporter::class)->import('https://example.com/creme');

        $this->assertSame('Crème Brûlée', $recipe->title);
    }

    #[Test]
    public function the_preview_never_saves_anything(): void
    {
        $this->fakeFetch($this->jsonLdPage());

        $this->withHeaders($this->accessHeaders())
            ->post('/admin/import/url', ['url' => 'https://example.com/oats'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Import/Url')
                ->where('draft.form.title', 'Banana Protein Baked Oats')
                ->where('draft.extractedVia', 'json-ld')
                ->has('draft.form.ingredients', 3)
            );

        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('recipe_ingredients', 0);
    }

    #[Test]
    public function an_unsafe_url_is_reported_as_a_validation_error(): void
    {
        $this->mock(UrlFetcher::class, function ($mock): void {
            $mock->shouldReceive('fetchHtml')
                ->andThrow(new UnsafeUrlException('That host resolves to a private or reserved address.'));
        });

        $this->withHeaders($this->accessHeaders())
            ->from('/admin/import/url')
            ->post('/admin/import/url', ['url' => 'http://169.254.169.254/'])
            ->assertRedirect('/admin/import/url')
            ->assertSessionHasErrors('url');

        $this->assertDatabaseCount('recipes', 0);
    }

    #[Test]
    public function a_failed_fetch_is_reported_without_leaking_internals(): void
    {
        $this->mock(UrlFetcher::class, function ($mock): void {
            $mock->shouldReceive('fetchHtml')->andThrow(new FetchFailedException('That page responded with HTTP 404.'));
        });

        $this->withHeaders($this->accessHeaders())
            ->from('/admin/import/url')
            ->post('/admin/import/url', ['url' => 'https://example.com/missing'])
            ->assertSessionHasErrors(['url' => 'That page responded with HTTP 404.']);
    }

    #[Test]
    public function the_url_field_is_validated_before_anything_is_fetched(): void
    {
        foreach (['', 'not a url', 'ftp://example.com/x'] as $url) {
            $this->withHeaders($this->accessHeaders())
                ->from('/admin/import/url')
                ->post('/admin/import/url', ['url' => $url])
                ->assertSessionHasErrors('url');
        }
    }

    private function fakeFetch(string $html, string $contentType = 'text/html; charset=utf-8'): void
    {
        $this->mock(UrlFetcher::class, function ($mock) use ($html, $contentType): void {
            $mock->shouldReceive('fetchHtml')->andReturnUsing(
                fn (string $url) => new FetchedResource($url, $html, $contentType, 200)
            );
        });
    }

    private function jsonLdPage(): string
    {
        $json = json_encode([
            '@context' => 'https://schema.org',
            '@type' => ['Recipe', 'NewsArticle'],
            'name' => 'Banana Protein Baked Oats',
            'description' => 'Warm oats that keep you full.',
            'image' => ['https://cdn.example.com/oats.jpg', 'https://cdn.example.com/oats-2.jpg'],
            'publisher' => ['@type' => 'Organization', 'name' => 'Example Kitchen'],
            'prepTime' => 'PT10M',
            'cookTime' => 'PT25M',
            'totalTime' => 'PT35M',
            'recipeYield' => '2 servings',
            'keywords' => 'healthy, high protein',
            'nutrition' => ['@type' => 'NutritionInformation', 'calories' => '410 kcal'],
            'recipeIngredient' => ['1 cup rolled oats', '2 ripe bananas', 'Pinch of salt'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Heat the oven to 350F.'],
                ['@type' => 'HowToStep', 'text' => 'Blend everything.'],
                ['@type' => 'HowToStep', 'text' => 'Bake for 25 minutes.'],
            ],
        ]);

        return "<html><head><script type=\"application/ld+json\">{$json}</script></head><body></body></html>";
    }

    private function graphPage(): string
    {
        $json = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'WebSite', 'name' => 'Example'],
                ['@type' => 'BreadcrumbList'],
                [
                    '@type' => 'Recipe',
                    'name' => 'Graph Recipe',
                    'recipeIngredient' => ['2 cups flour'],
                    'recipeInstructions' => 'Mix it all together.',
                ],
            ],
        ]);

        return "<html><head><script type=\"application/ld+json\">{$json}</script></head><body></body></html>";
    }

    private function sectionedPage(): string
    {
        $json = json_encode([
            '@type' => 'Recipe',
            'name' => 'Sectioned',
            'recipeIngredient' => ['1 onion'],
            'recipeInstructions' => [
                [
                    '@type' => 'HowToSection',
                    'name' => 'For the base',
                    'itemListElement' => [
                        ['@type' => 'HowToStep', 'text' => 'Chop the onion.'],
                        ['@type' => 'HowToStep', 'text' => 'Fry it.'],
                    ],
                ],
                [
                    '@type' => 'HowToSection',
                    'name' => 'For the sauce',
                    'itemListElement' => [
                        ['@type' => 'HowToStep', 'text' => 'Whisk the sauce.'],
                        ['@type' => 'HowToStep', 'text' => 'Combine.'],
                    ],
                ],
            ],
        ]);

        return "<html><head><script type=\"application/ld+json\">{$json}</script></head><body></body></html>";
    }

    private function microdataPage(): string
    {
        return <<<'HTML'
        <html><body>
        <div itemscope itemtype="https://schema.org/Recipe">
            <h1 itemprop="name">Microdata Stew</h1>
            <meta itemprop="cookTime" content="PT45M">
            <span itemprop="recipeYield">Serves 4</span>
            <ul>
                <li itemprop="recipeIngredient">2 lb white fish</li>
                <li itemprop="recipeIngredient">1 cup white wine</li>
            </ul>
            <div itemprop="recipeInstructions">Season the fish.</div>
            <div itemprop="recipeInstructions">Simmer for 20 minutes.</div>
        </div>
        </body></html>
        HTML;
    }

    private function plainArticlePage(): string
    {
        return <<<'HTML'
        <html><head>
            <meta property="og:title" content="A Long Story About Summer | Example Blog">
            <meta property="og:description" content="A reflection on the sea.">
            <meta property="og:image" content="https://cdn.example.com/story.jpg">
            <meta property="og:site_name" content="Example Blog">
        </head><body>
            <article><p>When I was young we spent every August on the coast.</p>
            <p>My grandmother would wake before anyone else.</p></article>
        </body></html>
        HTML;
    }

    private function semanticBlogPage(): string
    {
        return <<<'HTML'
        <html><head><meta property="og:title" content="Simple Bread"></head><body>
            <div class="recipe-ingredients"><ul>
                <li>500 g flour</li>
                <li>10 g salt</li>
            </ul></div>
            <div class="recipe-instructions"><ul>
                <li>Mix the dough.</li>
                <li>Bake for 40 minutes.</li>
            </ul></div>
        </body></html>
        HTML;
    }
}
