<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props available on every page.
     *
     * Kept small on purpose: the navigation categories are cached, and the
     * admin identity block is only attached inside /admin so a public page
     * never carries a hint that an administration area exists.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'site' => fn (): array => [
                'name' => config('app.name'),
                'tagline' => Setting::get('tagline', 'Good food goes further'),
                'heroHeading' => Setting::get('hero_heading', 'What are we cooking?'),
                'heroSubheading' => Setting::get('hero_subheading', 'A family collection of things worth making again.'),
                'footerNote' => Setting::get('footer_note', ''),
            ],

            'navCategories' => fn (): array => $this->navCategories(),

            // A 404 for an unmatched URL is rendered outside the web
            // middleware group, so there may be no session at all.
            'flash' => fn (): array => [
                'success' => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => $request->hasSession() ? $request->session()->get('error') : null,
            ],

            'auth' => fn (): ?array => $this->auth($request),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function navCategories(): array
    {
        return Cache::remember('ribs.nav.categories', now()->addMinutes(10), function (): array {
            return Category::query()
                ->whereHas('recipes', fn ($q) => $q->published())
                ->orderBy('sort_order')->orderBy('name')
                ->limit(8)
                ->get()
                ->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'icon' => $category->icon,
                    'url' => route('categories.show', $category->slug),
                ])
                ->all();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function auth(Request $request): ?array
    {
        if (! $request->is('admin', 'admin/*')) {
            return null;
        }

        $user = $request->user();

        return $user === null ? null : [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}
