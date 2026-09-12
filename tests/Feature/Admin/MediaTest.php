<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ImageSource;
use App\Enums\UserRole;
use App\Exceptions\UnsafeUrlException;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\User;
use App\Services\Http\FetchedResource;
use App\Services\Http\UrlFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesCloudflareAccess;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use FakesCloudflareAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'role' => UserRole::Owner]);
        Storage::fake('media');
    }

    #[Test]
    public function an_upload_is_re_encoded_into_a_responsive_ladder(): void
    {
        $response = $this->withHeaders($this->accessHeaders())
            ->post('/admin/media', ['file' => $this->photo(1400, 1000)])
            ->assertCreated();

        $image = RecipeImage::firstOrFail();

        $this->assertSame(ImageSource::Local, $image->source);
        $this->assertNull($image->recipe_id, 'a fresh upload is unattached until a recipe claims it');
        $this->assertSame(1400, $image->width);

        $formats = collect($image->variants)->pluck('format')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['webp', 'jpeg'], $formats);

        // Nothing is upscaled: the widest derivative matches the source.
        $widest = collect($image->variants)->max('width');
        $this->assertSame(1400, $widest);

        // Every declared file actually exists on the disk.
        foreach ($image->storedPaths() as $path) {
            Storage::disk('media')->assertExists($path);
        }

        $this->assertStringStartsWith('data:image/jpeg;base64,', (string) $image->placeholder);
        $this->assertNotNull($image->srcset());

        $response->assertJsonPath('image.source', 'local');
    }

    #[Test]
    public function an_oversized_original_is_scaled_down(): void
    {
        config()->set('ribs.images.max_edge', 800);

        $this->withHeaders($this->accessHeaders())
            ->post('/admin/media', ['file' => $this->photo(2400, 1600)])
            ->assertCreated();

        $this->assertSame(800, RecipeImage::firstOrFail()->width);
    }

    #[Test]
    public function metadata_is_stripped_by_re_encoding(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->post('/admin/media', ['file' => $this->photo(600, 400)])
            ->assertCreated();

        $image = RecipeImage::firstOrFail();
        $binary = Storage::disk('media')->get($image->path);

        // A re-encoded JPEG carries no EXIF/GPS block at all.
        $this->assertFalse(str_contains($binary, 'Exif'));
        $this->assertFalse(str_contains($binary, 'GPS'));
    }

    #[Test]
    public function a_file_that_is_not_an_image_is_refused(): void
    {
        // The editor uploads with fetch(), so it asks for JSON and gets a 422
        // it can show inline rather than a redirect.
        $this->withHeaders([...$this->accessHeaders(), 'Accept' => 'application/json'])
            ->post('/admin/media', ['file' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain')])
            ->assertStatus(422);

        $this->assertDatabaseCount('recipe_images', 0);
    }

    #[Test]
    public function a_remote_image_may_be_downloaded_and_stored(): void
    {
        $this->fakeImageFetch();

        $this->withHeaders($this->accessHeaders())
            ->postJson('/admin/media/remote', [
                'url' => 'https://cdn.example.com/photo.jpg',
                'mode' => 'download',
            ])
            ->assertCreated()
            ->assertJsonPath('image.source', 'local');

        $image = RecipeImage::firstOrFail();
        $this->assertSame(ImageSource::Local, $image->source);
        Storage::disk('media')->assertExists($image->path);
    }

    #[Test]
    public function a_remote_image_may_be_hot_linked_instead(): void
    {
        $this->fakeImageFetch();

        $this->withHeaders($this->accessHeaders())
            ->postJson('/admin/media/remote', [
                'url' => 'https://cdn.example.com/photo.jpg',
                'mode' => 'link',
                'caption' => 'From the original post',
            ])
            ->assertCreated()
            ->assertJsonPath('image.source', 'remote');

        $image = RecipeImage::firstOrFail();

        $this->assertSame(ImageSource::Remote, $image->source);
        $this->assertSame('https://cdn.example.com/photo.jpg', $image->url);
        $this->assertSame('From the original post', $image->caption);
        $this->assertSame([], $image->storedPaths());
    }

    #[Test]
    public function a_remote_image_pointing_at_the_private_network_is_refused(): void
    {
        $this->mock(UrlFetcher::class, function ($mock): void {
            $mock->shouldReceive('fetchImage')
                ->andThrow(new UnsafeUrlException('That host resolves to a private or reserved address.'));
        });

        $this->withHeaders($this->accessHeaders())
            ->postJson('/admin/media/remote', [
                'url' => 'http://192.168.1.1/admin.png',
                'mode' => 'download',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');

        $this->assertDatabaseCount('recipe_images', 0);
    }

    #[Test]
    public function saving_a_recipe_claims_its_images_and_discards_the_rest(): void
    {
        $headers = $this->accessHeaders();

        $first = $this->uploadId($headers);
        $second = $this->uploadId($headers);
        $abandoned = $this->uploadId($headers);

        $this->withHeaders($headers)->post('/admin/recipes', [
            'title' => 'With Photos',
            'slug' => '',
            'description' => '',
            'notes' => '',
            'category_id' => null,
            'tags' => [],
            'prep_minutes' => null,
            'cook_minutes' => null,
            'total_minutes_override' => null,
            'servings' => null,
            'servings_label' => '',
            'calories' => null,
            'source_url' => '',
            'source_name' => '',
            'is_favorite' => false,
            'status' => 'draft',
            'ingredients' => [['quantity_display' => '1', 'unit' => 'cup', 'name' => 'flour', 'note' => '']],
            'steps' => [['instruction' => 'Bake.', 'timer_seconds' => null]],
            'images' => [
                ['id' => $second, 'caption' => 'Plated', 'alt' => 'A plate', 'is_hero' => true],
                ['id' => $first, 'caption' => '', 'alt' => '', 'is_hero' => false],
            ],
        ])->assertRedirect();

        $recipe = Recipe::firstOrFail();

        $this->assertSame($second, $recipe->heroImage->id);
        $this->assertSame('Plated', $recipe->heroImage->caption);
        $this->assertCount(2, $recipe->images);
        $this->assertCount(1, $recipe->galleryImages);

        // The image never referenced by the form is still unattached, ready
        // for `php artisan media:prune` rather than silently deleted.
        $this->assertNull(RecipeImage::find($abandoned)->recipe_id);
    }

    #[Test]
    public function a_recipe_cannot_claim_another_recipes_image(): void
    {
        $headers = $this->accessHeaders();

        $other = Recipe::factory()->create(['slug' => 'other']);
        $stolen = RecipeImage::factory()->create(['recipe_id' => $other->id]);

        $this->withHeaders($headers)->post('/admin/recipes', [
            'title' => 'Thief',
            'slug' => '', 'description' => '', 'notes' => '', 'category_id' => null, 'tags' => [],
            'prep_minutes' => null, 'cook_minutes' => null, 'total_minutes_override' => null,
            'servings' => null, 'servings_label' => '', 'calories' => null,
            'source_url' => '', 'source_name' => '', 'is_favorite' => false, 'status' => 'draft',
            'ingredients' => [['quantity_display' => '', 'unit' => '', 'name' => 'flour', 'note' => '']],
            'steps' => [['instruction' => 'Bake.', 'timer_seconds' => null]],
            'images' => [['id' => $stolen->id, 'caption' => '', 'alt' => '', 'is_hero' => true]],
        ])->assertRedirect();

        $this->assertSame($other->id, $stolen->fresh()->recipe_id);
        $this->assertCount(0, Recipe::firstWhere('title', 'Thief')->images);
    }

    #[Test]
    public function unattached_uploads_are_pruned_once_they_are_stale(): void
    {
        $headers = $this->accessHeaders();
        $id = $this->uploadId($headers);

        RecipeImage::whereKey($id)->update(['created_at' => now()->subDays(2)]);
        $path = RecipeImage::find($id)->path;

        $this->artisan('media:prune')->assertSuccessful();

        $this->assertDatabaseMissing('recipe_images', ['id' => $id]);
        Storage::disk('media')->assertMissing($path);
    }

    #[Test]
    public function a_recent_unattached_upload_is_left_alone(): void
    {
        $id = $this->uploadId($this->accessHeaders());

        $this->artisan('media:prune')->assertSuccessful();

        $this->assertDatabaseHas('recipe_images', ['id' => $id]);
    }

    #[Test]
    public function duplicating_a_recipe_shares_its_photo_files(): void
    {
        $headers = $this->accessHeaders();
        $id = $this->uploadId($headers);

        $this->withHeaders($headers)->post('/admin/recipes', [
            'title' => 'Original',
            'slug' => '', 'description' => '', 'notes' => '', 'category_id' => null, 'tags' => [],
            'prep_minutes' => null, 'cook_minutes' => null, 'total_minutes_override' => null,
            'servings' => null, 'servings_label' => '', 'calories' => null,
            'source_url' => '', 'source_name' => '', 'is_favorite' => false, 'status' => 'draft',
            'ingredients' => [['quantity_display' => '', 'unit' => '', 'name' => 'flour', 'note' => '']],
            'steps' => [['instruction' => 'Bake.', 'timer_seconds' => null]],
            'images' => [['id' => $id, 'caption' => '', 'alt' => '', 'is_hero' => true]],
        ]);

        $original = Recipe::firstOrFail();
        $path = $original->heroImage->path;

        $this->withHeaders($headers)->post("/admin/recipes/{$original->id}/duplicate");
        $copy = Recipe::latest('id')->firstOrFail();

        $this->assertNotSame($original->heroImage->id, $copy->heroImage->id);
        $this->assertSame($path, $copy->heroImage->path);

        // Deleting the copy must not take the original's photo with it.
        $this->withHeaders($headers)->delete("/admin/recipes/{$copy->id}");
        $this->withHeaders($headers)->delete("/admin/recipes/{$copy->id}/force");

        Storage::disk('media')->assertExists($path);
        $this->assertNotNull($original->fresh()->heroImage);
    }

    private function uploadId(array $headers): int
    {
        return (int) $this->withHeaders($headers)
            ->post('/admin/media', ['file' => $this->photo()])
            ->assertCreated()
            ->json('image.id');
    }

    private function photo(int $width = 900, int $height = 600): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.jpg', $this->jpeg($width, $height));
    }

    /** A real JPEG rather than a fake: the pipeline decodes what it is given. */
    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 140, 90));

        ob_start();
        imagejpeg($image, null, 85);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    private function fakeImageFetch(): void
    {
        $binary = $this->jpeg(800, 600);

        $this->mock(UrlFetcher::class, function ($mock) use ($binary): void {
            $mock->shouldReceive('fetchImage')->andReturnUsing(
                fn (string $url) => new FetchedResource($url, $binary, 'image/jpeg', 200)
            );
        });
    }
}
