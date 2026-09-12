<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\RecipeImage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Recipes\RecipeSearchIndex;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly RecipeSearchIndex $searchIndex) {}

    public function show(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'tagline' => Setting::get('tagline', 'Good food goes further'),
                'hero_heading' => Setting::get('hero_heading', 'What are we cooking?'),
                'hero_subheading' => Setting::get('hero_subheading', 'A family collection of things worth making again.'),
                'footer_note' => Setting::get('footer_note', ''),
            ],
            'system' => [
                'appVersion' => config('app.version', 'dev'),
                'environment' => app()->environment(),
                'searchDriver' => $this->searchIndex->available() ? 'SQLite FTS5' : 'Indexed LIKE',
                'accessConfigured' => filled(config('ribs.access.team_domain')) && filled(config('ribs.access.aud')),
                'teamDomain' => config('ribs.access.team_domain'),
                'devBypass' => config('ribs.access.dev_bypass') === true
                    && app()->environment((array) config('ribs.access.dev_environments')),
                'storedImages' => RecipeImage::query()->where('source', 'local')->count(),
                'remoteImages' => RecipeImage::query()->where('source', 'remote')->count(),
                'unattachedImages' => RecipeImage::query()->whereNull('recipe_id')->count(),
            ],
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'last_seen_at'])
                ->map(fn (User $u): array => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role->label(),
                    'lastSeenAt' => $u->last_seen_at?->toIso8601String(),
                ])
                ->all(),
            'meta' => ['title' => 'Settings — '.config('app.name')],
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            Setting::put($key, is_string($value) ? trim($value) : $value);
        }

        return back()->with('success', 'Settings saved.');
    }

    public function reindex(): RedirectResponse
    {
        $count = $this->searchIndex->rebuild();

        return back()->with('success', "Search index rebuilt for {$count} recipes.");
    }
}
