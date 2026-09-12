<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The public site has to be readable by anyone, with no account, and it must
 * never leak a draft.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Dinner', 'slug' => 'dinner', 'sort_order' => 10]);
        $this->tag = Tag::create(['name' => 'Croatian', 'slug' => 'croatian']);
    }

    #[Test]
    public function the_homepage_is_public(): void
    {
        $this->publishedRecipe('Brudet');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('totalRecipes', 1)
            );
    }

    #[Test]
    public function every_public_route_is_reachable_without_authentication(): void
    {
        $recipe = $this->publishedRecipe('Brudet');

        foreach ([
            '/',
            '/recipes',
            "/recipes/{$recipe->slug}",
            "/categories/{$this->category->slug}",
            "/tags/{$this->tag->slug}",
            '/search/suggest?q=bru',
            '/manifest.webmanifest',
            '/sitemap.xml',
            // robots.txt is a static file in public/, served by the web
            // server rather than by a route, so it is not listed here.
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    #[Test]
    public function the_sitemap_lists_published_recipes_and_nothing_else(): void
    {
        $published = $this->publishedRecipe('In The Sitemap');
        $draft = Recipe::factory()->draft()->create(['title' => 'Hidden', 'slug' => 'hidden']);

        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString(route('recipes.show', $published->slug), $xml);
        $this->assertStringNotContainsString($draft->slug, $xml);
        $this->assertStringNotContainsString('/admin', $xml);
    }

    #[Test]
    public function there_is_no_login_or_registration_route(): void
    {
        foreach (['/login', '/register', '/password/reset', '/logout'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    #[Test]
    public function drafts_are_invisible_to_the_public(): void
    {
        $draft = Recipe::factory()->draft()->create([
            'title' => 'Secret Peka',
            'slug' => 'secret-peka',
            'category_id' => $this->category->id,
        ]);

        $this->get("/recipes/{$draft->slug}")->assertNotFound();

        $this->get('/recipes')->assertInertia(
            fn ($page) => $page->where('recipes.meta.total', 0)
        );
    }

    #[Test]
    public function a_recipe_scheduled_for_the_future_is_not_published_yet(): void
    {
        $recipe = Recipe::factory()->create([
            'title' => 'Tomorrow',
            'slug' => 'tomorrow',
            'published_at' => now()->addDay(),
        ]);

        $this->get("/recipes/{$recipe->slug}")->assertNotFound();
    }

    #[Test]
    public function a_soft_deleted_recipe_disappears_from_the_public_site(): void
    {
        $recipe = $this->publishedRecipe('Gone');
        $recipe->delete();

        $this->get("/recipes/{$recipe->slug}")->assertNotFound();
    }

    #[Test]
    public function the_recipe_page_renders_schema_org_structured_data(): void
    {
        $recipe = $this->publishedRecipe('Banana Protein Baked Oats');
        $recipe->ingredients()->create([
            'quantity' => 1, 'quantity_display' => '1', 'unit' => 'cup',
            'name' => 'rolled oats', 'sort_order' => 0,
        ]);
        $recipe->steps()->create(['instruction' => 'Blend everything.', 'sort_order' => 0]);

        $response = $this->get("/recipes/{$recipe->slug}")->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertMatchesRegularExpression('#<script type="application/ld\+json">(.+?)</script>#s', $html);

        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $matches);
        $data = json_decode($matches[1], true);

        $this->assertSame('Recipe', $data['@type']);
        $this->assertSame('Banana Protein Baked Oats', $data['name']);
        $this->assertSame(['1 cup rolled oats'], $data['recipeIngredient']);
        $this->assertSame('HowToStep', $data['recipeInstructions'][0]['@type']);
        $this->assertSame('PT30M', $data['totalTime']);
    }

    #[Test]
    public function the_recipe_page_sets_canonical_and_open_graph_tags(): void
    {
        $recipe = $this->publishedRecipe('Brudet');

        $html = $this->get("/recipes/{$recipe->slug}")->getContent();

        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('Brudet', $html);
    }

    #[Test]
    public function search_matches_titles_ingredients_categories_and_tags(): void
    {
        $recipe = $this->publishedRecipe('Chickpea Salad');
        $recipe->ingredients()->create([
            'quantity' => 1, 'quantity_display' => '1', 'unit' => 'tbsp',
            'name' => 'pomegranate molasses', 'sort_order' => 0,
        ]);
        $recipe->tags()->attach($this->tag);
        // Pivot writes fire no model events, so the index is refreshed the way
        // the application does it after syncing tags.
        $recipe->touchSearchIndex();

        foreach (['chickpea', 'pomegranate', 'dinner', 'croatian'] as $term) {
            $this->get('/recipes?q='.$term)->assertInertia(
                fn ($page) => $page->where('recipes.meta.total', 1),
                "searching for “{$term}” should find the recipe"
            );
        }
    }

    #[Test]
    public function search_terms_containing_query_syntax_are_treated_as_text(): void
    {
        $this->publishedRecipe('Brudet');

        // A visitor typing FTS5 operators must get a normal (empty) result,
        // not a database error.
        foreach (['"', 'NEAR(', '*', 'a OR b', 'brudet AND'] as $term) {
            $this->get('/recipes?q='.urlencode($term))->assertOk();
        }
    }

    #[Test]
    public function the_suggestion_endpoint_returns_json_and_ignores_short_terms(): void
    {
        $this->publishedRecipe('Brudet');

        $this->getJson('/search/suggest?q=b')->assertOk()->assertJson(['results' => []]);

        $this->getJson('/search/suggest?q=brud')
            ->assertOk()
            ->assertJsonPath('results.0.title', 'Brudet');
    }

    #[Test]
    public function category_and_tag_pages_filter_the_collection(): void
    {
        $tagged = $this->publishedRecipe('Tagged');
        $tagged->tags()->attach($this->tag);

        $other = Category::create(['name' => 'Dessert', 'slug' => 'dessert', 'sort_order' => 20]);
        $this->publishedRecipe('Elsewhere', $other);

        $this->get("/categories/{$this->category->slug}")->assertInertia(
            fn ($page) => $page->where('recipes.meta.total', 1)->where('heading', 'Dinner')
        );

        $this->get("/tags/{$this->tag->slug}")->assertInertia(
            fn ($page) => $page->where('recipes.meta.total', 1)->where('heading', 'Croatian')
        );
    }

    #[Test]
    public function a_missing_recipe_renders_the_branded_error_page(): void
    {
        $this->get('/recipes/nope')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Errors/ErrorPage')->where('status', 404));
    }

    #[Test]
    public function an_unmatched_url_still_renders_the_branded_error_page(): void
    {
        // This path never enters the web middleware group, so the shared props
        // the error page renders have to be supplied by the responder.
        $this->get('/not-a-page')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('Errors/ErrorPage')
                ->where('status', 404)
                ->has('navCategories')
                ->has('site')
            );
    }

    private function publishedRecipe(string $title, ?Category $category = null): Recipe
    {
        return Recipe::factory()->create([
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'category_id' => ($category ?? $this->category)->id,
            'user_id' => User::factory()->owner(),
            'prep_minutes' => 10,
            'cook_minutes' => 20,
            'servings' => 4,
            'published_at' => now()->subDay(),
        ]);
    }
}
