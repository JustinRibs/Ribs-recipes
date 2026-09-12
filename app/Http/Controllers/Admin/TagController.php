<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TagRequest;
use App\Models\Tag;
use App\Services\Recipes\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function __construct(private readonly SlugGenerator $slugs) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Tag::class);

        return Inertia::render('Admin/Tags/Index', [
            'tags' => Tag::query()
                ->withCount('recipes')
                ->orderByDesc('recipes_count')->orderBy('name')
                ->get()
                ->map(fn (Tag $t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'recipesCount' => $t->recipes_count,
                    'url' => route('tags.show', $t->slug),
                ])
                ->all(),
            'meta' => ['title' => 'Tags — '.config('app.name')],
        ]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $this->authorize('create', Tag::class);

        $data = $request->validated();

        Tag::create([
            'name' => $data['name'],
            'slug' => $this->slugs->generate(Tag::class, $data['slug'] ?? $data['name']),
        ]);

        return back()->with('success', 'Tag added.');
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $data = $request->validated();

        if (filled($data['slug'] ?? null) && $data['slug'] !== $tag->slug) {
            $data['slug'] = $this->slugs->generate(Tag::class, $data['slug'], $tag->id);
        } else {
            unset($data['slug']);
        }

        $tag->update($data);

        return back()->with('success', 'Tag updated.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return back()->with('success', "“{$tag->name}” removed.");
    }
}
