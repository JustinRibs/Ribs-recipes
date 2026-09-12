<?php

declare(strict_types=1);

namespace App\Services\Images;

use App\Enums\ImageSource;
use App\Exceptions\FetchFailedException;
use App\Exceptions\UnsafeUrlException;
use App\Models\RecipeImage;
use App\Services\Http\UrlFetcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Creates and destroys RecipeImage records.
 *
 * Three routes in: a direct upload, a download of a remote photo (imported
 * recipes usually want their hero copied locally so it survives the source
 * site), and a plain hot-link for when copying is not wanted.
 */
final class ImageStore
{
    public function __construct(
        private readonly ImageProcessor $processor,
        private readonly UrlFetcher $http,
    ) {}

    /**
     * @throws FetchFailedException
     */
    public function storeUpload(UploadedFile $file, ?string $alt = null): RecipeImage
    {
        $binary = @file_get_contents($file->getRealPath());

        if ($binary === false) {
            throw new FetchFailedException('The uploaded file could not be read.');
        }

        return $this->storeBinary($binary, $file->getClientOriginalName(), $alt);
    }

    /**
     * @throws FetchFailedException
     */
    public function storeBinary(string $binary, ?string $originalName = null, ?string $alt = null): RecipeImage
    {
        $this->assertLooksLikeImage($binary);

        $processed = $this->processor->process($binary, $originalName);

        return RecipeImage::create([
            'source' => ImageSource::Local,
            'disk' => config('ribs.images.disk'),
            'path' => $processed->path,
            'variants' => $processed->variants,
            'width' => $processed->width,
            'height' => $processed->height,
            'mime' => $processed->mime,
            'bytes' => $processed->bytes,
            'placeholder' => $processed->placeholder,
            'alt' => $alt,
            'is_hero' => false,
            'sort_order' => 0,
        ]);
    }

    /**
     * Download a remote image and store it locally.
     *
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function downloadRemote(string $url, ?string $alt = null): RecipeImage
    {
        $resource = $this->http->fetchImage($url);

        $name = basename((string) parse_url($resource->finalUrl, PHP_URL_PATH)) ?: 'photo';

        return $this->storeBinary($resource->body, $name, $alt);
    }

    /**
     * Keep an image where it lives and hot-link it.
     *
     * The URL is still validated and probed, so a hot-link can never be used to
     * point the site at an internal service or a non-image resource.
     *
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function linkRemote(string $url, ?string $alt = null, ?string $caption = null): RecipeImage
    {
        // Downloading a small slice would need range support that many hosts
        // ignore, so the image is fetched once to confirm what it is, then
        // discarded. Only the URL is stored.
        $resource = $this->http->fetchImage($url);

        $dimensions = @getimagesizefromstring($resource->body);

        return RecipeImage::create([
            'source' => ImageSource::Remote,
            'url' => $resource->finalUrl,
            'width' => $dimensions === false ? null : $dimensions[0],
            'height' => $dimensions === false ? null : $dimensions[1],
            'mime' => $resource->contentType,
            'bytes' => strlen($resource->body),
            'alt' => $alt,
            'caption' => $caption,
            'is_hero' => false,
            'sort_order' => 0,
        ]);
    }

    /**
     * Remove the files behind an image, unless another record still points at
     * them (duplicating a recipe shares its photos rather than copying them).
     */
    public function deleteFiles(RecipeImage $image): void
    {
        $paths = $image->storedPaths();

        if ($paths === []) {
            return;
        }

        $stillReferenced = RecipeImage::query()
            ->whereKeyNot($image->getKey())
            ->where('path', $image->path)
            ->exists();

        if ($stillReferenced) {
            return;
        }

        $disk = Storage::disk($image->disk ?? (string) config('ribs.images.disk'));

        foreach ($paths as $path) {
            try {
                $disk->delete($path);
            } catch (\Throwable $e) {
                Log::channel('media')->warning('Could not delete image file', [
                    'path' => $path,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        // Tidy the (now empty) per-image directory.
        $directory = dirname((string) $image->path);

        if ($directory !== '.' && $disk->files($directory) === []) {
            $disk->deleteDirectory($directory);
        }
    }

    /**
     * Delete image records that were uploaded but never attached to a recipe —
     * the residue of an admin abandoning a half-filled form.
     */
    public function pruneUnattached(int $olderThanHours = 24): int
    {
        $stale = RecipeImage::query()
            ->whereNull('recipe_id')
            ->where('created_at', '<', now()->subHours($olderThanHours))
            ->get();

        $deleted = 0;

        DB::transaction(function () use ($stale, &$deleted): void {
            foreach ($stale as $image) {
                $this->deleteFiles($image);
                $image->delete();
                $deleted++;
            }
        });

        return $deleted;
    }

    /**
     * @throws FetchFailedException
     */
    private function assertLooksLikeImage(string $binary): void
    {
        $info = @getimagesizefromstring($binary);

        if ($info === false) {
            throw new FetchFailedException('That file is not a readable image.');
        }

        $mime = strtolower((string) ($info['mime'] ?? ''));

        if (! in_array($mime, (array) config('ribs.images.accepted_mimes', []), true)) {
            throw new FetchFailedException("Images of type {$mime} are not supported.");
        }

        // 100 MP is far beyond any camera output and is a decompression-bomb
        // signature; refuse before handing the bytes to the decoder.
        if (($info[0] ?? 0) * ($info[1] ?? 0) > 100_000_000) {
            throw new FetchFailedException('That image is too large to process.');
        }
    }
}
