<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CsvConfirmRequest;
use App\Http\Requests\Admin\CsvUploadRequest;
use App\Models\Category;
use App\Models\Recipe;
use App\Services\Import\CsvImportRunner;
use App\Services\Import\CsvRecipeParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Two-step CSV import: upload and review, then confirm.
 *
 * The uploaded file is held in private storage between the two steps and the
 * confirm step re-parses it from disk rather than trusting anything the browser
 * sends back, so the rows that get imported are provably the rows that were
 * reviewed.
 */
class CsvImportController extends Controller
{
    private const STORAGE_DIRECTORY = 'imports';

    public function __construct(
        private readonly CsvRecipeParser $parser,
        private readonly CsvImportRunner $runner,
    ) {}

    public function show(): Response
    {
        $this->authorize('create', Recipe::class);

        return Inertia::render('Admin/Import/Csv', [
            'preview' => null,
            'categories' => $this->categories(),
            'meta' => ['title' => 'Import from CSV — '.config('app.name')],
        ]);
    }

    public function preview(CsvUploadRequest $request): Response
    {
        $this->authorize('create', Recipe::class);

        $token = (string) Str::ulid();
        $path = self::STORAGE_DIRECTORY.'/'.$token.'.csv';

        Storage::disk('local')->put($path, (string) file_get_contents($request->file('file')->getRealPath()));

        $result = $this->parser->parse(Storage::disk('local')->path($path));

        return Inertia::render('Admin/Import/Csv', [
            'preview' => [
                'token' => $token,
                'filename' => $request->file('file')->getClientOriginalName(),
                'rows' => $result['rows'],
                'headers' => $result['headers'],
                'errors' => $result['errors'],
                'validCount' => count(array_filter($result['rows'], fn (array $r): bool => $r['valid'] === true)),
            ],
            'categories' => $this->categories(),
            'meta' => ['title' => 'Review CSV import — '.config('app.name')],
        ]);
    }

    public function store(CsvConfirmRequest $request): RedirectResponse
    {
        $this->authorize('create', Recipe::class);

        $token = (string) $request->validated('token');
        $path = self::STORAGE_DIRECTORY.'/'.$token.'.csv';

        if (! Storage::disk('local')->exists($path)) {
            return back()->withErrors([
                'token' => 'That upload is no longer available. Upload the file again.',
            ]);
        }

        // Re-parsed from disk: the browser chooses *which* rows, never *what*
        // is in them.
        $result = $this->parser->parse(Storage::disk('local')->path($path));

        $outcome = $this->runner->run(
            $result['rows'],
            array_map(intval(...), (array) $request->validated('rows')),
            [
                'status' => $request->validated('status'),
                'category_id' => $request->validated('category_id'),
                'download_images' => $request->boolean('download_images'),
                'extra_tags' => (array) $request->input('extra_tags', []),
            ],
            $request->user()?->id,
        );

        Storage::disk('local')->delete($path);

        $importedCount = count($outcome['imported']);
        $failedCount = count($outcome['failed']);

        Log::channel('import')->info('CSV import finished', [
            'imported' => $importedCount,
            'failed' => $failedCount,
        ]);

        if ($importedCount === 0) {
            return back()->with('error', 'Nothing was imported. '.($outcome['failed'][0]['reason'] ?? ''));
        }

        $message = $importedCount.' '.Str::plural('recipe', $importedCount).' imported.';

        if ($failedCount > 0) {
            $message .= ' '.$failedCount.' '.Str::plural('row', $failedCount).' failed — see the import log.';
        }

        return redirect()
            ->route('admin.recipes.index', ['sort' => 'created'])
            ->with($failedCount > 0 ? 'error' : 'success', $message);
    }

    /**
     * The canonical template, committed to the repository and served from disk
     * so the documentation and the download can never drift apart.
     */
    public function template(): BinaryFileResponse
    {
        $this->authorize('create', Recipe::class);

        return response()->download(
            resource_path('templates/ribs-recipes-template.csv'),
            'ribs-recipes-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(): array
    {
        return Category::query()
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $c): array => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])
            ->all();
    }
}
