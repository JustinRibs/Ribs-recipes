<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Http\SafeHttpClient;
use App\Services\Http\UrlFetcher;
use App\Services\Http\UrlGuard;
use App\Services\Recipes\RecipeSearchIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The guard's policy comes from configuration, so it is constructed
        // once here rather than resolved by autowiring with a default flag.
        $this->app->singleton(UrlGuard::class, fn (): UrlGuard => UrlGuard::fromConfig());

        // Shared so the one-off "is FTS5 available?" probe runs once per
        // request rather than once per model event.
        $this->app->singleton(RecipeSearchIndex::class);

        // Every outbound fetch of an administrator-supplied URL goes through
        // the SSRF-guarded client. Nothing else may be bound here in
        // production; tests substitute a fake to exercise the parsers.
        $this->app->bind(UrlFetcher::class, SafeHttpClient::class);
    }

    public function boot(): void
    {
        // Catches missing eager loads in development; never throws in
        // production, where a missing `with()` should be slow, not fatal.
        Model::preventLazyLoading($this->app->isLocal());
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());

        // Behind Cloudflare + Traefik the app only ever speaks https publicly.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
