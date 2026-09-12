<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CsvImportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UrlImportController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Admin routes
|------------------------------------------------------------------------------
|
| Mounted at /admin by bootstrap/app.php and protected in its entirety by the
| Cloudflare Access middleware, which verifies the signed Access JWT on every
| request. Every route in this application that can modify data is in this file.
|
| Bindings here are explicitly by id. Recipes, categories and tags all resolve
| by slug on the public site, but an admin URL must keep working while its
| subject is being renamed.
|
*/

Route::middleware('cloudflare.access')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    // --- Recipes -------------------------------------------------------------
    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/create', [RecipeController::class, 'create'])->name('recipes.create');
    Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store');
    Route::get('recipes/{recipe:id}/edit', [RecipeController::class, 'edit'])->name('recipes.edit');
    Route::put('recipes/{recipe:id}', [RecipeController::class, 'update'])->name('recipes.update');
    Route::delete('recipes/{recipe:id}', [RecipeController::class, 'destroy'])->name('recipes.destroy');

    Route::post('recipes/{recipe:id}/duplicate', [RecipeController::class, 'duplicate'])->name('recipes.duplicate');
    Route::post('recipes/{recipe:id}/favorite', [RecipeController::class, 'toggleFavorite'])->name('recipes.favorite');
    Route::post('recipes/{recipe:id}/publish', [RecipeController::class, 'togglePublished'])->name('recipes.publish');
    Route::post('recipes/{recipe:id}/restore', [RecipeController::class, 'restore'])
        ->withTrashed()->name('recipes.restore');
    Route::delete('recipes/{recipe:id}/force', [RecipeController::class, 'forceDestroy'])
        ->withTrashed()->name('recipes.force-destroy');

    // --- Taxonomy ------------------------------------------------------------
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category:id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category:id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::put('tags/{tag:id}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag:id}', [TagController::class, 'destroy'])->name('tags.destroy');

    // --- Media ---------------------------------------------------------------
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::post('media/remote', [MediaController::class, 'storeRemote'])->name('media.remote');
    Route::delete('media/{image}', [MediaController::class, 'destroy'])->name('media.destroy');

    // --- Import --------------------------------------------------------------
    Route::get('import/url', [UrlImportController::class, 'show'])->name('import.url');
    Route::post('import/url', [UrlImportController::class, 'preview'])->name('import.url.preview');

    Route::get('import/csv', [CsvImportController::class, 'show'])->name('import.csv');
    Route::get('import/csv/template', [CsvImportController::class, 'template'])->name('import.csv.template');
    Route::post('import/csv/preview', [CsvImportController::class, 'preview'])->name('import.csv.preview');
    Route::post('import/csv', [CsvImportController::class, 'store'])->name('import.csv.store');

    // --- Settings ------------------------------------------------------------
    Route::get('settings', [SettingsController::class, 'show'])->name('settings');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/reindex', [SettingsController::class, 'reindex'])->name('settings.reindex');
});
