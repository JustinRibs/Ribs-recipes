import { useForm } from '@inertiajs/react'
import { AlertTriangle, CheckCircle2, Download, FileUp, Loader2, Upload, XCircle } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { SelectField, Switch } from '@/components/ui/Field'
import { AdminLayout } from '@/layouts/AdminLayout'
import { cn } from '@/lib/cn'
import { adminRoutes } from '@/lib/routes'
import type { CategoryOption } from '@/types'

interface PreviewRow {
    index: number
    line: number
    valid: boolean
    errors: string[]
    warnings: string[]
    title: string
    category: string | null
    tags: string[]
    ingredientCount: number
    stepCount: number
    heroImageUrl: string | null
}

interface Preview {
    token: string
    filename: string
    rows: PreviewRow[]
    headers: string[]
    errors: string[]
    validCount: number
}

interface CsvImportProps {
    preview: Preview | null
    categories: CategoryOption[]
}

export default function CsvImport({ preview, categories }: CsvImportProps) {
    return (
        <AdminLayout
            title="Import from a CSV"
            description="Upload a spreadsheet of recipes. Every row is checked and shown to you before anything is saved."
        >
            {preview ? <ConfirmStep preview={preview} categories={categories} /> : <UploadStep />}
        </AdminLayout>
    )
}

function UploadStep() {
    const { setData, post, processing, errors, progress } = useForm<{ file: File | null }>({ file: null })
    const input = useRef<HTMLInputElement>(null)
    const [filename, setFilename] = useState<string | null>(null)
    const [dragging, setDragging] = useState(false)

    const choose = (file: File | undefined) => {
        if (!file) return
        setFilename(file.name)
        setData('file', file)
    }

    const submit = (event: React.FormEvent) => {
        event.preventDefault()
        post(adminRoutes.importCsvPreview, { forceFormData: true })
    }

    return (
        <>
            <form
                onSubmit={submit}
                className="rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line sm:p-6"
            >
                <label
                    onDragOver={(event) => {
                        event.preventDefault()
                        setDragging(true)
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault()
                        setDragging(false)
                        choose(event.dataTransfer.files[0])
                    }}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed px-6 py-12 text-center transition',
                        dragging
                            ? 'border-adriatic bg-adriatic-soft'
                            : 'border-line-strong hover:border-adriatic',
                    )}
                >
                    <input
                        ref={input}
                        type="file"
                        accept=".csv,text/csv"
                        onChange={(event) => choose(event.target.files?.[0])}
                        className="sr-only"
                    />

                    <FileUp className="size-8 text-ink-faint" aria-hidden="true" />

                    <span className="font-medium text-ink">
                        {filename ?? 'Choose a CSV file, or drop one here'}
                    </span>
                    <span className="text-[0.88rem] text-ink-muted">Up to 250 recipes per file.</span>
                </label>

                {errors.file && (
                    <p
                        className="mt-3 rounded-lg bg-croatia-soft px-3 py-2 text-[0.9rem] text-croatia"
                        role="alert"
                    >
                        {errors.file}
                    </p>
                )}

                {progress && (
                    <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-2">
                        <div
                            className="h-full bg-adriatic transition-all"
                            style={{ width: `${progress.percentage}%` }}
                        />
                    </div>
                )}

                <div className="mt-5 flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={processing || !filename}>
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                        ) : (
                            <Upload className="size-4" aria-hidden="true" />
                        )}
                        {processing ? 'Checking…' : 'Check the file'}
                    </Button>

                    <a
                        href={adminRoutes.importCsvTemplate}
                        className="inline-flex h-11 items-center gap-2 rounded-full bg-surface-2 px-5 text-[0.92rem] font-medium text-ink transition hover:bg-surface-3"
                    >
                        <Download className="size-4" aria-hidden="true" />
                        Download the template
                    </a>
                </div>
            </form>

            <FormatReference />
        </>
    )
}

function ConfirmStep({ preview, categories }: { preview: Preview; categories: CategoryOption[] }) {
    const validRows = useMemo(() => preview.rows.filter((row) => row.valid), [preview.rows])

    const { data, setData, post, processing, errors } = useForm<{
        token: string
        rows: number[]
        category_id: number | null
        status: 'draft' | 'published'
        download_images: boolean
        extra_tags: string[]
    }>({
        token: preview.token,
        rows: validRows.map((row) => row.index),
        category_id: null,
        status: 'draft',
        download_images: true,
        extra_tags: [],
    })

    const toggle = (index: number) => {
        setData(
            'rows',
            data.rows.includes(index) ? data.rows.filter((row) => row !== index) : [...data.rows, index],
        )
    }

    const submit = (event: React.FormEvent) => {
        event.preventDefault()
        post(adminRoutes.importCsv)
    }

    return (
        <form onSubmit={submit}>
            <div className="rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line sm:p-6">
                <h2 className="font-display text-[1.35rem] tracking-tight text-ink">{preview.filename}</h2>
                <p className="mt-1 text-[0.92rem] text-ink-muted">
                    {preview.rows.length} {preview.rows.length === 1 ? 'row' : 'rows'} read ·{' '}
                    <span className="text-olive">{preview.validCount} importable</span>
                    {preview.rows.length - preview.validCount > 0 && (
                        <span className="text-croatia">
                            {' '}
                            · {preview.rows.length - preview.validCount} with errors
                        </span>
                    )}
                </p>

                {preview.errors.map((error, index) => (
                    <p
                        key={index}
                        className="mt-3 rounded-lg bg-croatia-soft px-3 py-2 text-[0.9rem] text-croatia"
                    >
                        {error}
                    </p>
                ))}

                {errors.rows && (
                    <p
                        className="mt-3 rounded-lg bg-croatia-soft px-3 py-2 text-[0.9rem] text-croatia"
                        role="alert"
                    >
                        {errors.rows}
                    </p>
                )}
                {errors.token && (
                    <p
                        className="mt-3 rounded-lg bg-croatia-soft px-3 py-2 text-[0.9rem] text-croatia"
                        role="alert"
                    >
                        {errors.token}
                    </p>
                )}
            </div>

            {/* --- Options for the whole batch ------------------------------ */}
            <div className="mt-4 grid grid-cols-1 gap-4 rounded-2xl bg-surface p-4 ring-1 ring-line sm:grid-cols-2 sm:p-6">
                <SelectField
                    label="Default category"
                    value={data.category_id ?? ''}
                    onChange={(event) =>
                        setData('category_id', event.target.value ? Number(event.target.value) : null)
                    }
                    hint="Used only for rows with no category column value."
                >
                    <option value="">Leave uncategorised</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                        </option>
                    ))}
                </SelectField>

                <SelectField
                    label="Import as"
                    value={data.status}
                    onChange={(event) => setData('status', event.target.value as 'draft' | 'published')}
                    hint="Rows without ingredients or steps are always imported as drafts."
                >
                    <option value="draft">Drafts — review before publishing</option>
                    <option value="published">Published immediately</option>
                </SelectField>

                <div className="sm:col-span-2">
                    <Switch
                        checked={data.download_images}
                        onChange={(value) => setData('download_images', value)}
                        label="Copy hero images onto this server"
                        description="Recommended. Otherwise images are hot-linked and depend on the original site staying up."
                    />
                </div>
            </div>

            {/* --- Row preview ---------------------------------------------- */}
            <div className="mt-6 flex items-center justify-between gap-4">
                <h3 className="font-display text-[1.3rem] tracking-tight text-ink">Rows</h3>

                <div className="flex gap-2">
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            setData(
                                'rows',
                                validRows.map((row) => row.index),
                            )
                        }
                    >
                        Select all
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => setData('rows', [])}>
                        Select none
                    </Button>
                </div>
            </div>

            <ul className="mt-3 space-y-2">
                {preview.rows.map((row) => {
                    const selected = data.rows.includes(row.index)

                    return (
                        <li
                            key={row.index}
                            className={cn(
                                'rounded-2xl p-3.5 ring-1 transition sm:p-4',
                                row.valid
                                    ? selected
                                        ? 'bg-surface ring-adriatic'
                                        : 'bg-surface ring-line'
                                    : 'bg-croatia-soft/50 ring-croatia/30',
                            )}
                        >
                            <label className={cn('flex items-start gap-3', row.valid && 'cursor-pointer')}>
                                {row.valid ? (
                                    <input
                                        type="checkbox"
                                        checked={selected}
                                        onChange={() => toggle(row.index)}
                                        className="mt-1 size-5 shrink-0 accent-[var(--color-adriatic)]"
                                        aria-label={`Import ${row.title}`}
                                    />
                                ) : (
                                    <XCircle
                                        className="mt-0.5 size-5 shrink-0 text-croatia"
                                        aria-hidden="true"
                                    />
                                )}

                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-ink">
                                        {row.title || <span className="text-ink-faint">(no title)</span>}
                                        <span className="ml-2 text-[0.78rem] font-normal text-ink-faint">
                                            line {row.line}
                                        </span>
                                    </p>

                                    <p className="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-[0.82rem] text-ink-muted">
                                        <span>{row.ingredientCount} ingredients</span>
                                        <span>{row.stepCount} steps</span>
                                        {row.category && <span>{row.category}</span>}
                                        {row.tags.length > 0 && <span>{row.tags.join(', ')}</span>}
                                        {row.heroImageUrl && <span>has a photo</span>}
                                    </p>

                                    {row.errors.map((error, index) => (
                                        <p
                                            key={index}
                                            className="mt-1.5 flex items-start gap-1.5 text-[0.85rem] font-medium text-croatia"
                                        >
                                            <XCircle
                                                className="mt-0.5 size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            {error}
                                        </p>
                                    ))}

                                    {row.warnings.map((warning, index) => (
                                        <p
                                            key={index}
                                            className="mt-1.5 flex items-start gap-1.5 text-[0.85rem] text-ink-muted"
                                        >
                                            <AlertTriangle
                                                className="mt-0.5 size-3.5 shrink-0 text-croatia/70"
                                                aria-hidden="true"
                                            />
                                            {warning}
                                        </p>
                                    ))}
                                </div>
                            </label>
                        </li>
                    )
                })}
            </ul>

            <div className="safe-dock sticky bottom-0 z-20 -mx-4 mt-6 border-t border-line bg-canvas/92 px-4 pt-3 backdrop-blur-xl sm:-mx-6 sm:px-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" size="lg" disabled={processing || data.rows.length === 0}>
                        {processing ? (
                            <Loader2 className="size-4.5 animate-spin" aria-hidden="true" />
                        ) : (
                            <CheckCircle2 className="size-4.5" aria-hidden="true" />
                        )}
                        {processing
                            ? 'Importing…'
                            : `Import ${data.rows.length} ${data.rows.length === 1 ? 'recipe' : 'recipes'}`}
                    </Button>

                    <a
                        href={adminRoutes.importCsv}
                        className="text-[0.9rem] font-medium text-ink-muted underline underline-offset-4 hover:text-ink"
                    >
                        Start over
                    </a>
                </div>
            </div>
        </form>
    )
}

function FormatReference() {
    return (
        <section className="mt-8 rounded-2xl bg-surface p-4 ring-1 ring-line sm:p-6">
            <h2 className="font-display text-[1.35rem] tracking-tight text-ink">The format</h2>

            <p className="mt-2 text-[0.95rem] leading-relaxed text-ink-muted">
                One recipe per row. Only{' '}
                <code className="rounded bg-surface-2 px-1.5 py-0.5 text-[0.85rem]">title</code> is required —
                everything else is optional, and unknown columns are ignored.
            </p>

            <dl className="mt-5 space-y-4 text-[0.92rem]">
                <div>
                    <dt className="font-medium text-ink">ingredients</dt>
                    <dd className="mt-1 text-ink-muted">
                        One ingredient per line inside the cell, as{' '}
                        <code className="rounded bg-surface-2 px-1.5 py-0.5 text-[0.85rem]">
                            quantity | unit | ingredient | note
                        </code>
                        .
                        <pre className="mt-2 overflow-x-auto rounded-xl bg-surface-2 p-3 text-[0.82rem] leading-relaxed">
                            {`2 | cups | rolled oats
1 | tsp | cinnamon
0.5 | cup | Greek yogurt | plain
 | | salt | to taste`}
                        </pre>
                    </dd>
                </div>

                <div>
                    <dt className="font-medium text-ink">instructions</dt>
                    <dd className="mt-1 text-ink-muted">
                        One step per line. Add{' '}
                        <code className="rounded bg-surface-2 px-1.5 py-0.5 text-[0.85rem]">| 25</code> to
                        give a step a 25-minute timer.
                        <pre className="mt-2 overflow-x-auto rounded-xl bg-surface-2 p-3 text-[0.82rem] leading-relaxed">
                            {`Preheat the oven to 350°F.
Mix everything together.
Bake until set. | 30`}
                        </pre>
                    </dd>
                </div>

                <div>
                    <dt className="font-medium text-ink">tags</dt>
                    <dd className="mt-1 text-ink-muted">
                        Separated by pipes:{' '}
                        <code className="rounded bg-surface-2 px-1.5 py-0.5 text-[0.85rem]">
                            Healthy|High Protein|Meal Prep
                        </code>
                        . New tags are created automatically.
                    </dd>
                </div>

                <div>
                    <dt className="font-medium text-ink">Everything else</dt>
                    <dd className="mt-1 text-ink-muted">
                        <code className="text-[0.85rem]">description</code>,{' '}
                        <code className="text-[0.85rem]">category</code>,{' '}
                        <code className="text-[0.85rem]">prep_minutes</code>,{' '}
                        <code className="text-[0.85rem]">cook_minutes</code>,{' '}
                        <code className="text-[0.85rem]">servings</code>,{' '}
                        <code className="text-[0.85rem]">calories</code>,{' '}
                        <code className="text-[0.85rem]">source_url</code>,{' '}
                        <code className="text-[0.85rem]">source_name</code>,{' '}
                        <code className="text-[0.85rem]">hero_image_url</code>,{' '}
                        <code className="text-[0.85rem]">notes</code>,{' '}
                        <code className="text-[0.85rem]">favorite</code> (yes/no).
                    </dd>
                </div>
            </dl>
        </section>
    )
}
