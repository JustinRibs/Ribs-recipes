<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\FetchFailedException;
use App\Exceptions\UnsafeUrlException;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ImagePresenter;
use App\Http\Requests\Admin\MediaRemoteRequest;
use App\Http\Requests\Admin\MediaUploadRequest;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Services\Images\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Image endpoints for the recipe editor.
 *
 * Photos are processed the moment they are chosen so the author sees a real
 * thumbnail immediately, and the recipe claims them on save. Rows that are
 * never claimed are swept up by `php artisan media:prune`.
 *
 * These live under /admin and are therefore behind Cloudflare Access like
 * every other mutating route.
 */
class MediaController extends Controller
{
    public function __construct(private readonly ImageStore $images) {}

    public function store(MediaUploadRequest $request): JsonResponse
    {
        $this->authorize('create', Recipe::class);

        try {
            $image = $this->images->storeUpload($request->file('file'), $request->input('alt'));
        } catch (FetchFailedException $e) {
            Log::channel('media')->warning('Upload rejected', ['reason' => $e->getMessage()]);

            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return response()->json(['image' => ImagePresenter::make($image)], 201);
    }

    public function storeRemote(MediaRemoteRequest $request): JsonResponse
    {
        $this->authorize('create', Recipe::class);

        $url = (string) $request->input('url');

        try {
            $image = $request->input('mode') === 'download'
                ? $this->images->downloadRemote($url, $request->input('alt'))
                : $this->images->linkRemote($url, $request->input('alt'), $request->input('caption'));
        } catch (UnsafeUrlException|FetchFailedException $e) {
            Log::channel('media')->warning('Remote image rejected', [
                'host' => parse_url($url, PHP_URL_HOST),
                'reason' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        if ($request->filled('caption')) {
            $image->update(['caption' => $request->input('caption')]);
        }

        return response()->json(['image' => ImagePresenter::make($image)], 201);
    }

    public function destroy(RecipeImage $image): JsonResponse
    {
        $recipe = $image->recipe;

        if ($recipe !== null) {
            $this->authorize('update', $recipe);
        } else {
            $this->authorize('create', Recipe::class);
        }

        $this->images->deleteFiles($image);
        $image->delete();

        return response()->json(['deleted' => true]);
    }
}
