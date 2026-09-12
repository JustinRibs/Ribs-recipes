<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\Recipes\RecipeBrowser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function __construct(private readonly RecipeBrowser $browser) {}

    public function show(Request $request, Tag $tag): Response
    {
        return Inertia::render('Recipes/Index', [
            ...$this->browser->browse($request, ['tags' => [$tag->slug]]),
            'heading' => $tag->name,
            'lede' => null,
            'lockedFilter' => ['type' => 'tag', 'name' => $tag->name, 'slug' => $tag->slug],
            'meta' => [
                'title' => $tag->name.' recipes — '.config('app.name'),
                'description' => "Recipes tagged {$tag->name}.",
                'canonical' => route('tags.show', $tag->slug),
            ],
        ]);
    }
}
