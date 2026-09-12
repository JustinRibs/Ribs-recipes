<?php

declare(strict_types=1);

use App\Http\Controllers\PublicSite\CategoryController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\ManifestController;
use App\Http\Controllers\PublicSite\RecipeController;
use App\Http\Controllers\PublicSite\SearchController;
use App\Http\Controllers\PublicSite\SitemapController;
use App\Http\Controllers\PublicSite\TagController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Public routes
|------------------------------------------------------------------------------
|
| Everything here is readable by anyone, with no account, no login page and no
| write operations of any kind. Administration lives entirely in routes/admin.php
| behind Cloudflare Access.
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
Route::get('/recipes/{slug}', [RecipeController::class, 'show'])->name('recipes.show');

Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/tags/{tag:slug}', [TagController::class, 'show'])->name('tags.show');

// Read-only JSON used by the search overlay for instant suggestions.
Route::get('/search/suggest', SearchController::class)
    ->middleware('throttle:60,1')
    ->name('search.suggest');

// Web app manifest, generated so icons and colours follow the app config.
Route::get('/manifest.webmanifest', ManifestController::class)
    ->name('manifest');

// A plain XML sitemap of every public page, cached for an hour.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
