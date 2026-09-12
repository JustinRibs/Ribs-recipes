<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\RecipeStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesCloudflareAccess;
use Tests\TestCase;

/**
 * The CSV importer's contract: nothing is written until the rows have been
 * reviewed and confirmed, and one malformed row never takes the batch down
 * with it.
 */
class CsvImportTest extends TestCase
{
    use FakesCloudflareAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'role' => UserRole::Owner]);
        Category::create(['name' => 'Breakfast', 'slug' => 'breakfast', 'sort_order' => 10]);

        Storage::fake('local');
    }

    #[Test]
    public function the_committed_template_parses_cleanly(): void
    {
        $csv = file_get_contents(resource_path('templates/ribs-recipes-template.csv'));

        $preview = $this->preview($csv);

        $preview->assertInertia(fn ($page) => $page
            ->component('Admin/Import/Csv')
            ->where('preview.validCount', 3)
            ->where('preview.errors', [])
            ->where('preview.rows.0.title', 'Banana Protein Baked Oats')
            ->where('preview.rows.0.ingredientCount', 6)
            ->where('preview.rows.0.stepCount', 4)
        );
    }

    #[Test]
    public function the_preview_writes_nothing_to_the_database(): void
    {
        $this->preview($this->validCsv())->assertOk();

        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('recipe_ingredients', 0);
    }

    #[Test]
    public function it_imports_only_the_rows_that_were_selected(): void
    {
        $token = $this->previewToken($this->validCsv());

        $this->withHeaders($this->accessHeaders())
            ->post('/admin/import/csv', [
                'token' => $token,
                'rows' => [0],
                'status' => 'draft',
                'download_images' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseHas('recipes', ['title' => 'Oat Bowl']);
        $this->assertDatabaseMissing('recipes', ['title' => 'Second Recipe']);
    }

    #[Test]
    public function it_maps_every_supported_column(): void
    {
        $token = $this->previewToken($this->validCsv());

        $this->withHeaders($this->accessHeaders())->post('/admin/import/csv', [
            'token' => $token,
            'rows' => [0],
            'status' => 'published',
            'download_images' => false,
        ]);

        $recipe = Recipe::firstWhere('title', 'Oat Bowl');

        $this->assertSame('A quick breakfast.', $recipe->description);
        $this->assertSame(5, $recipe->prep_minutes);
        $this->assertSame(15, $recipe->cook_minutes);
        $this->assertSame(2, $recipe->servings);
        $this->assertSame(350, $recipe->calories);
        $this->assertSame('https://example.com/oats', $recipe->source_url);
        $this->assertTrue($recipe->is_favorite);
        $this->assertSame(RecipeStatus::Published, $recipe->status);
        $this->assertSame('Breakfast', $recipe->category->name);
        $this->assertEqualsCanonicalizing(['Healthy', 'Quick'], $recipe->tags->pluck('name')->all());
        $this->assertSame('Keeps for three days.', $recipe->notes);

        // quantity | unit | ingredient | note
        $this->assertCount(3, $recipe->ingredients);
        $oats = $recipe->ingredients[0];
        $this->assertSame(2.0, $oats->quantity);
        $this->assertSame('cups', $oats->unit);
        $this->assertSame('rolled oats', $oats->name);

        $salt = $recipe->ingredients[2];
        $this->assertNull($salt->quantity);
        $this->assertSame('salt', $salt->name);
        $this->assertSame('to taste', $salt->note);

        // One step per line, with an optional trailing timer in minutes.
        $this->assertCount(2, $recipe->steps);
        $this->assertSame('Mix everything.', $recipe->steps[0]->instruction);
        $this->assertNull($recipe->steps[0]->timer_seconds);
        $this->assertSame('Bake until golden.', $recipe->steps[1]->instruction);
        $this->assertSame(1800, $recipe->steps[1]->timer_seconds);
    }

    #[Test]
    public function it_flags_rows_that_cannot_be_imported_without_rejecting_the_file(): void
    {
        $csv = "title,ingredients,instructions\n"
            ."Good One,1 | cup | flour,Mix it.\n"
            .",2 | cups | sugar,Stir it.\n"
            ."Another Good One,1 | tsp | salt,Season it.\n";

        $this->preview($csv)->assertInertia(fn ($page) => $page
            ->where('preview.validCount', 2)
            ->where('preview.rows.1.valid', false)
            ->where('preview.rows.1.errors.0', 'Missing a title.')
            ->where('preview.rows.1.line', 3)
        );
    }

    #[Test]
    public function a_row_without_a_method_is_imported_as_a_draft_whatever_was_asked(): void
    {
        $csv = "title,ingredients,instructions\nHalf Written,1 | cup | flour,\n";
        $token = $this->previewToken($csv);

        $this->withHeaders($this->accessHeaders())->post('/admin/import/csv', [
            'token' => $token,
            'rows' => [0],
            'status' => 'published',
            'download_images' => false,
        ]);

        $this->assertSame(RecipeStatus::Draft, Recipe::first()->status);
    }

    #[Test]
    public function it_creates_categories_and_tags_that_do_not_exist_yet(): void
    {
        $csv = "title,category,tags,ingredients,instructions\n"
            ."Peka,Sunday Roasts,Croatian|Slow,1 | | chicken,Roast it.\n";

        $token = $this->previewToken($csv);

        $this->withHeaders($this->accessHeaders())->post('/admin/import/csv', [
            'token' => $token,
            'rows' => [0],
            'status' => 'draft',
            'download_images' => false,
        ]);

        $this->assertDatabaseHas('categories', ['slug' => 'sunday-roasts']);
        $this->assertDatabaseHas('tags', ['slug' => 'croatian']);
        $this->assertDatabaseHas('tags', ['slug' => 'slow']);
    }

    #[Test]
    public function it_tolerates_untidy_headers_and_blank_rows(): void
    {
        $csv = "\u{FEFF}Title, Prep Minutes ,ingredients,instructions\n"
            ."Tidy,20,1 | cup | flour,Mix.\n"
            ."\n";

        $this->preview($csv)->assertInertia(fn ($page) => $page
            ->where('preview.validCount', 1)
            ->where('preview.rows.0.title', 'Tidy')
        );
    }

    #[Test]
    public function it_accepts_free_text_ingredient_lines_without_pipes(): void
    {
        $csv = "title,ingredients,instructions\n"
            ."Loose,\"2 cups rolled oats\n1 tsp cinnamon\",Mix.\n";

        $token = $this->previewToken($csv);

        $this->withHeaders($this->accessHeaders())->post('/admin/import/csv', [
            'token' => $token, 'rows' => [0], 'status' => 'draft', 'download_images' => false,
        ]);

        $ingredients = Recipe::first()->ingredients;

        $this->assertCount(2, $ingredients);
        $this->assertSame('rolled oats', $ingredients[0]->name);
        $this->assertSame('cups', $ingredients[0]->unit);
        $this->assertSame(2.0, $ingredients[0]->quantity);
    }

    #[Test]
    public function a_file_with_no_title_column_is_refused_with_an_explanation(): void
    {
        $this->preview("name,steps\nSomething,Do it.\n")->assertInertia(fn ($page) => $page
            ->where('preview.validCount', 0)
            ->where('preview.errors.0', 'The file has no "title" column. Download the template to see the expected columns.')
        );
    }

    #[Test]
    public function it_rejects_files_that_are_not_csv(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->from('/admin/import/csv')
            ->post('/admin/import/csv/preview', [
                'file' => UploadedFile::fake()->create('recipes.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    #[Test]
    public function confirming_with_an_unknown_token_fails_safely(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->from('/admin/import/csv')
            ->post('/admin/import/csv', [
                'token' => (string) Str::ulid(),
                'rows' => [0],
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('token');

        $this->assertDatabaseCount('recipes', 0);
    }

    #[Test]
    public function the_uploaded_file_is_removed_once_the_import_finishes(): void
    {
        $token = $this->previewToken($this->validCsv());

        Storage::disk('local')->assertExists("imports/{$token}.csv");

        $this->withHeaders($this->accessHeaders())->post('/admin/import/csv', [
            'token' => $token, 'rows' => [0], 'status' => 'draft', 'download_images' => false,
        ]);

        Storage::disk('local')->assertMissing("imports/{$token}.csv");
    }

    #[Test]
    public function the_template_can_be_downloaded(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->get('/admin/import/csv/template')
            ->assertOk()
            ->assertDownload('ribs-recipes-template.csv');
    }

    private function validCsv(): string
    {
        $ingredients = "2 | cups | rolled oats\n1 | tsp | cinnamon\n | | salt | to taste";
        $instructions = "Mix everything.\nBake until golden. | 30";

        return "title,description,category,tags,prep_minutes,cook_minutes,servings,calories,source_url,ingredients,instructions,notes,favorite\n"
            ."Oat Bowl,A quick breakfast.,Breakfast,Healthy|Quick,5,15,2,350,https://example.com/oats,\"{$ingredients}\",\"{$instructions}\",Keeps for three days.,yes\n"
            ."Second Recipe,Another one.,Breakfast,Quick,5,5,1,200,,\"1 | cup | milk\",\"Warm it.\",,no\n";
    }

    private function preview(string $csv): TestResponse
    {
        return $this->withHeaders($this->accessHeaders())
            ->post('/admin/import/csv/preview', [
                'file' => UploadedFile::fake()->createWithContent('recipes.csv', $csv),
            ]);
    }

    private function previewToken(string $csv): string
    {
        $response = $this->preview($csv)->assertOk();

        return data_get($response->viewData('page'), 'props.preview.token');
    }
}
