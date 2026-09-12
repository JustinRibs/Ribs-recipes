<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\RecipeStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesCloudflareAccess;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use FakesCloudflareAccess;
    use RefreshDatabase;

    private User $owner;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@example.test', 'role' => UserRole::Owner,
        ]);
        $this->category = Category::create(['name' => 'Dinner', 'slug' => 'dinner', 'sort_order' => 10]);
    }

    #[Test]
    public function it_creates_a_recipe_with_ingredients_steps_and_tags(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->post('/admin/recipes', $this->payload())
            ->assertRedirect();

        $recipe = Recipe::firstWhere('title', 'Banana Protein Baked Oats');

        $this->assertNotNull($recipe);
        $this->assertSame('banana-protein-baked-oats', $recipe->slug);
        $this->assertSame($this->owner->id, $recipe->user_id);
        $this->assertSame(RecipeStatus::Published, $recipe->status);
        $this->assertNotNull($recipe->published_at);
        $this->assertSame(35, $recipe->totalMinutes());

        $this->assertCount(3, $recipe->ingredients);
        $this->assertCount(2, $recipe->steps);
        $this->assertCount(2, $recipe->tags);

        // Written amounts are normalised for scaling while the author's own
        // wording is preserved for display.
        $oats = $recipe->ingredients->firstWhere('name', 'rolled oats');
        $this->assertSame(1.5, $oats->quantity);
        $this->assertSame('1 1/2', $oats->quantity_display);

        $salt = $recipe->ingredients->firstWhere('name', 'sea salt');
        $this->assertNull($salt->quantity);
        $this->assertSame('to taste', $salt->note);

        $this->assertSame(0, $recipe->steps->first()->sort_order);
        $this->assertSame(1500, $recipe->steps->last()->timer_seconds);
    }

    #[Test]
    public function slugs_are_unique_even_when_titles_collide(): void
    {
        $headers = $this->accessHeaders();

        $this->withHeaders($headers)->post('/admin/recipes', $this->payload());
        $this->withHeaders($headers)->post('/admin/recipes', $this->payload());

        $this->assertSame(
            ['banana-protein-baked-oats', 'banana-protein-baked-oats-2'],
            Recipe::orderBy('id')->pluck('slug')->all()
        );
    }

    #[Test]
    public function a_slug_cannot_collide_with_a_trashed_recipe(): void
    {
        $headers = $this->accessHeaders();

        $this->withHeaders($headers)->post('/admin/recipes', $this->payload());
        Recipe::first()->delete();

        $this->withHeaders($headers)->post('/admin/recipes', $this->payload());

        $this->assertSame('banana-protein-baked-oats-2', Recipe::latest('id')->first()->slug);
    }

    #[Test]
    public function it_updates_a_recipe_and_replaces_its_nested_rows(): void
    {
        $this->withHeaders($this->accessHeaders())->post('/admin/recipes', $this->payload());
        $recipe = Recipe::first();

        $this->withHeaders($this->accessHeaders())
            ->put("/admin/recipes/{$recipe->id}", [
                ...$this->payload(),
                'title' => 'Renamed Oats',
                'ingredients' => [['quantity_display' => '2', 'unit' => 'cups', 'name' => 'oats', 'note' => '']],
                'steps' => [['instruction' => 'Only one step now.', 'timer_seconds' => null]],
                'tags' => ['Quick'],
            ])
            ->assertRedirect();

        $recipe->refresh();

        $this->assertSame('Renamed Oats', $recipe->title);
        // The slug is not rewritten by a title change — existing links survive.
        $this->assertSame('banana-protein-baked-oats', $recipe->slug);
        $this->assertCount(1, $recipe->ingredients);
        $this->assertCount(1, $recipe->steps);
        $this->assertSame(['Quick'], $recipe->tags->pluck('name')->all());
    }

    #[Test]
    public function publishing_requires_something_to_cook(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->post('/admin/recipes', [
                ...$this->payload(),
                'ingredients' => [],
                'steps' => [],
            ])
            ->assertSessionHasErrors(['ingredients', 'steps']);

        $this->assertDatabaseCount('recipes', 0);
    }

    #[Test]
    public function a_draft_may_be_saved_incomplete(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->post('/admin/recipes', [
                ...$this->payload(),
                'status' => 'draft',
                'ingredients' => [],
                'steps' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('recipes', 1);
        $this->assertNull(Recipe::first()->published_at);
    }

    #[Test]
    public function blank_rows_are_dropped_rather_than_rejected(): void
    {
        // The editor always keeps an empty row at the bottom for quick entry.
        $this->withHeaders($this->accessHeaders())
            ->post('/admin/recipes', [
                ...$this->payload(),
                'ingredients' => [
                    ['quantity_display' => '1', 'unit' => 'cup', 'name' => 'oats', 'note' => ''],
                    ['quantity_display' => '', 'unit' => '', 'name' => '', 'note' => ''],
                ],
                'steps' => [
                    ['instruction' => 'Mix.', 'timer_seconds' => null],
                    ['instruction' => '   ', 'timer_seconds' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertCount(1, Recipe::first()->ingredients);
        $this->assertCount(1, Recipe::first()->steps);
    }

    #[Test]
    public function it_validates_the_obvious_mistakes(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->post('/admin/recipes', [
                ...$this->payload(),
                'title' => '',
                'source_url' => 'not-a-url',
                'slug' => 'Not A Slug',
                'category_id' => 9999,
                'prep_minutes' => -5,
            ])
            ->assertSessionHasErrors(['title', 'source_url', 'slug', 'category_id', 'prep_minutes']);
    }

    #[Test]
    public function it_soft_deletes_and_restores(): void
    {
        $this->withHeaders($this->accessHeaders())->post('/admin/recipes', $this->payload());
        $recipe = Recipe::first();

        $this->withHeaders($this->accessHeaders())
            ->delete("/admin/recipes/{$recipe->id}")
            ->assertRedirect('/admin/recipes');

        $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
        $this->get("/recipes/{$recipe->slug}")->assertNotFound();

        $this->withHeaders($this->accessHeaders())
            ->post("/admin/recipes/{$recipe->id}/restore")
            ->assertRedirect();

        $this->assertNotSoftDeleted('recipes', ['id' => $recipe->id]);
        $this->get("/recipes/{$recipe->slug}")->assertOk();
    }

    #[Test]
    public function it_permanently_deletes_only_when_asked(): void
    {
        $this->withHeaders($this->accessHeaders())->post('/admin/recipes', $this->payload());
        $recipe = Recipe::first();
        $recipe->delete();

        $this->withHeaders($this->accessHeaders())
            ->delete("/admin/recipes/{$recipe->id}/force")
            ->assertRedirect();

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $recipe->id]);
    }

    #[Test]
    public function it_duplicates_a_recipe_as_a_draft(): void
    {
        $this->withHeaders($this->accessHeaders())->post('/admin/recipes', $this->payload());
        $original = Recipe::first();

        $this->withHeaders($this->accessHeaders())
            ->post("/admin/recipes/{$original->id}/duplicate")
            ->assertRedirect();

        $copy = Recipe::latest('id')->first();

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame('Banana Protein Baked Oats (copy)', $copy->title);
        $this->assertSame(RecipeStatus::Draft, $copy->status);
        $this->assertFalse($copy->is_favorite);
        $this->assertCount(3, $copy->ingredients);
        $this->assertCount(2, $copy->steps);
        $this->assertCount(2, $copy->tags);
    }

    #[Test]
    public function it_toggles_favorite_and_published_state(): void
    {
        $this->withHeaders($this->accessHeaders())->post('/admin/recipes', $this->payload());
        $recipe = Recipe::first();

        $this->withHeaders($this->accessHeaders())->post("/admin/recipes/{$recipe->id}/favorite");
        $this->assertTrue($recipe->fresh()->is_favorite);

        $this->withHeaders($this->accessHeaders())->post("/admin/recipes/{$recipe->id}/publish");
        $this->assertSame(RecipeStatus::Draft, $recipe->fresh()->status);

        $this->withHeaders($this->accessHeaders())->post("/admin/recipes/{$recipe->id}/publish");
        $this->assertSame(RecipeStatus::Published, $recipe->fresh()->status);
    }

    #[Test]
    public function an_empty_recipe_cannot_be_published_from_the_list(): void
    {
        $recipe = Recipe::factory()->draft()->create(['slug' => 'empty']);

        $this->withHeaders($this->accessHeaders())
            ->post("/admin/recipes/{$recipe->id}/publish")
            ->assertSessionHas('error');

        $this->assertSame(RecipeStatus::Draft, $recipe->fresh()->status);
    }

    #[Test]
    public function the_admin_list_filters_and_searches(): void
    {
        $this->withHeaders($this->accessHeaders())->post('/admin/recipes', $this->payload());
        Recipe::factory()->draft()->create(['title' => 'Peka', 'slug' => 'peka']);

        $headers = $this->accessHeaders();

        $this->withHeaders($headers)->get('/admin/recipes')
            ->assertInertia(fn ($page) => $page->where('recipes.meta.total', 2));

        $this->withHeaders($headers)->get('/admin/recipes?status=draft')
            ->assertInertia(fn ($page) => $page->where('recipes.meta.total', 1));

        $this->withHeaders($headers)->get('/admin/recipes?q=banana')
            ->assertInertia(fn ($page) => $page->where('recipes.meta.total', 1));

        $this->withHeaders($headers)->get('/admin/recipes?category=dinner')
            ->assertInertia(fn ($page) => $page->where('recipes.meta.total', 1));
    }

    #[Test]
    public function a_contributor_may_not_edit_someone_elses_recipe(): void
    {
        $this->withHeaders($this->accessHeaders())->post('/admin/recipes', $this->payload());
        $recipe = Recipe::first();

        User::create([
            'name' => 'Family', 'email' => 'family@example.test', 'role' => UserRole::Contributor,
        ]);

        $this->withHeaders($this->accessHeaders(['email' => 'family@example.test']))
            ->put("/admin/recipes/{$recipe->id}", $this->payload())
            ->assertForbidden();
    }

    #[Test]
    public function a_contributor_may_edit_their_own_recipe(): void
    {
        $contributor = User::create([
            'name' => 'Family', 'email' => 'family@example.test', 'role' => UserRole::Contributor,
        ]);

        $this->withHeaders($this->accessHeaders(['email' => 'family@example.test']))
            ->post('/admin/recipes', $this->payload())
            ->assertRedirect();

        $recipe = Recipe::first();
        $this->assertSame($contributor->id, $recipe->user_id);

        $this->withHeaders($this->accessHeaders(['email' => 'family@example.test']))
            ->put("/admin/recipes/{$recipe->id}", [...$this->payload(), 'title' => 'Mine'])
            ->assertRedirect();

        $this->assertSame('Mine', $recipe->fresh()->title);
    }

    #[Test]
    public function categories_and_tags_are_manageable(): void
    {
        $headers = $this->accessHeaders();

        $this->withHeaders($headers)
            ->post('/admin/categories', ['name' => 'Brunch', 'color' => '#2F6F9F'])
            ->assertRedirect();
        $this->assertDatabaseHas('categories', ['slug' => 'brunch']);

        $brunch = Category::firstWhere('slug', 'brunch');

        $this->withHeaders($headers)
            ->put("/admin/categories/{$brunch->id}", ['name' => 'Late Brunch'])
            ->assertRedirect();
        $this->assertSame('Late Brunch', $brunch->fresh()->name);

        $this->withHeaders($headers)->post('/admin/tags', ['name' => 'Weeknight'])->assertRedirect();
        $this->assertDatabaseHas('tags', ['slug' => 'weeknight']);

        $tag = Tag::firstWhere('slug', 'weeknight');
        $this->withHeaders($headers)->delete("/admin/tags/{$tag->id}")->assertRedirect();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    #[Test]
    public function deleting_a_category_leaves_its_recipes_alone(): void
    {
        $this->withHeaders($this->accessHeaders())->post('/admin/recipes', $this->payload());
        $recipe = Recipe::first();

        $this->withHeaders($this->accessHeaders())
            ->delete("/admin/categories/{$this->category->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
        $this->assertNull($recipe->fresh()->category_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'Banana Protein Baked Oats',
            'slug' => '',
            'description' => 'Warm, and it keeps.',
            'notes' => 'Blend the oats properly.',
            'category_id' => $this->category->id,
            'tags' => ['High Protein', 'Quick'],
            'prep_minutes' => 10,
            'cook_minutes' => 25,
            'total_minutes_override' => null,
            'servings' => 2,
            'servings_label' => 'servings',
            'calories' => 410,
            'source_url' => 'https://example.com/oats',
            'source_name' => 'Example',
            'is_favorite' => false,
            'status' => 'published',
            'ingredients' => [
                ['quantity_display' => '1 1/2', 'unit' => 'cups', 'name' => 'rolled oats', 'note' => ''],
                ['quantity_display' => '2', 'unit' => '', 'name' => 'ripe bananas', 'note' => 'mashed'],
                ['quantity_display' => '', 'unit' => '', 'name' => 'sea salt', 'note' => 'to taste'],
            ],
            'steps' => [
                ['instruction' => 'Blend everything.', 'timer_seconds' => null],
                ['instruction' => 'Bake until set.', 'timer_seconds' => 1500],
            ],
            'images' => [],
        ];
    }
}
