import { Clock, Image as ImageIcon, Info, ListOrdered, Salad, Timer } from 'lucide-react'
import type { ReactNode } from 'react'
import { ImagePicker } from './ImagePicker'
import { RowList } from './RowList'
import { TagInput } from './TagInput'
import { SelectField, Switch, TextAreaField, TextField, controlClasses } from '@/components/ui/Field'
import { cn } from '@/lib/cn'
import { humaniseMinutes } from '@/lib/time'
import type { CategoryOption, ImageFormRow, IngredientFormRow, RecipeFormState, StepFormRow } from '@/types'

type Errors = Partial<Record<string, string>>

interface RecipeFormProps {
    data: RecipeFormState
    setData: <K extends keyof RecipeFormState>(key: K, value: RecipeFormState[K]) => void
    errors: Errors
    categories: CategoryOption[]
    allTags: string[]
}

/**
 * The recipe editor.
 *
 * Grouped into the sections an author actually thinks in — basics, timing,
 * ingredients, method, photos, details — rather than one long column of
 * inputs. Shared verbatim with the URL importer's preview, so reviewing an
 * import and writing a recipe are the same screen with the same validation.
 */
export function RecipeForm({ data, setData, errors, categories, allTags }: RecipeFormProps) {
    const computedTotal = (data.prep_minutes ?? 0) + (data.cook_minutes ?? 0)

    return (
        <div className="space-y-6">
            <Section title="Basics" Icon={Info}>
                <TextField
                    label="Title"
                    required
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    error={errors.title}
                    placeholder="Banana Protein Baked Oats"
                    autoComplete="off"
                />

                <TextAreaField
                    label="Short description"
                    value={data.description}
                    onChange={(event) => setData('description', event.target.value)}
                    error={errors.description}
                    rows={3}
                    maxLength={500}
                    hint="One or two sentences. This is what shows on cards and in search results."
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <SelectField
                        label="Category"
                        value={data.category_id ?? ''}
                        onChange={(event) =>
                            setData('category_id', event.target.value ? Number(event.target.value) : null)
                        }
                        error={errors.category_id}
                    >
                        <option value="">Uncategorised</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </SelectField>

                    <TagInput
                        value={data.tags}
                        onChange={(tags) => setData('tags', tags)}
                        suggestions={allTags}
                        error={errors.tags}
                        hint="Press Enter to add. New tags are created automatically."
                    />
                </div>
            </Section>

            <Section title="Timing and yield" Icon={Clock}>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <NumberField
                        label="Prep (min)"
                        value={data.prep_minutes}
                        onChange={(value) => setData('prep_minutes', value)}
                        error={errors.prep_minutes}
                    />
                    <NumberField
                        label="Cook (min)"
                        value={data.cook_minutes}
                        onChange={(value) => setData('cook_minutes', value)}
                        error={errors.cook_minutes}
                    />
                    <NumberField
                        label="Servings"
                        value={data.servings}
                        onChange={(value) => setData('servings', value)}
                        error={errors.servings}
                        min={1}
                    />
                    <NumberField
                        label="Calories"
                        value={data.calories}
                        onChange={(value) => setData('calories', value)}
                        error={errors.calories}
                        hint="Per serving"
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextField
                        label="Yield label"
                        value={data.servings_label}
                        onChange={(event) => setData('servings_label', event.target.value)}
                        error={errors.servings_label}
                        placeholder="servings"
                        hint="What the number counts — servings, cookies, jars."
                    />

                    <NumberField
                        label="Total time override (min)"
                        value={data.total_minutes_override}
                        onChange={(value) => setData('total_minutes_override', value)}
                        error={errors.total_minutes_override}
                        hint={
                            computedTotal > 0
                                ? `Leave empty to use prep + cook (${humaniseMinutes(computedTotal)}). Set it when there is resting or chilling time.`
                                : 'Set this when total time is more than prep + cook.'
                        }
                    />
                </div>
            </Section>

            <Section
                title="Ingredients"
                Icon={Salad}
                error={typeof errors.ingredients === 'string' ? errors.ingredients : undefined}
            >
                <RowList<IngredientFormRow>
                    rows={data.ingredients}
                    onChange={(rows) => setData('ingredients', rows)}
                    makeEmpty={() => ({ quantity_display: '', unit: '', name: '', note: '' })}
                    addLabel="+ Add ingredient"
                    emptyLabel="No ingredients yet."
                    itemNoun="ingredient"
                    renderRow={(row, index, update) => (
                        // On a phone the amount and unit share the first line
                        // and the ingredient gets a line of its own — three
                        // fields across 390px leaves none of them usable.
                        <div className="grid grid-cols-12 gap-1.5">
                            <input
                                value={row.quantity_display}
                                onChange={(event) => update({ quantity_display: event.target.value })}
                                placeholder="1 1/2"
                                inputMode="decimal"
                                aria-label={`Quantity for ingredient ${index + 1}`}
                                className={cn(
                                    controlClasses,
                                    'col-span-4 h-10 px-2.5 py-0 text-[0.92rem] sm:col-span-2',
                                )}
                            />
                            <input
                                value={row.unit}
                                onChange={(event) => update({ unit: event.target.value })}
                                placeholder="cups"
                                aria-label={`Unit for ingredient ${index + 1}`}
                                className={cn(
                                    controlClasses,
                                    'col-span-8 h-10 px-2.5 py-0 text-[0.92rem] sm:col-span-2',
                                )}
                            />
                            <input
                                value={row.name}
                                onChange={(event) => update({ name: event.target.value })}
                                placeholder="rolled oats"
                                aria-label={`Ingredient ${index + 1}`}
                                className={cn(
                                    controlClasses,
                                    'col-span-12 h-10 px-2.5 py-0 text-[0.92rem] sm:col-span-5',
                                    errors[`ingredients.${index}.name`] && 'ring-croatia',
                                )}
                            />
                            <input
                                value={row.note}
                                onChange={(event) => update({ note: event.target.value })}
                                placeholder="note — chopped, optional…"
                                aria-label={`Note for ingredient ${index + 1}`}
                                className={cn(
                                    controlClasses,
                                    'col-span-12 h-10 px-2.5 py-0 text-[0.92rem] sm:col-span-3',
                                )}
                            />
                        </div>
                    )}
                />

                <p className="text-[0.85rem] text-ink-muted">
                    Leave the amount blank for things like “salt, to taste”. Fractions such as
                    <code className="mx-1 rounded bg-surface-2 px-1.5 py-0.5 text-[0.8rem]">1 1/2</code>
                    are understood and scale correctly.
                </p>
            </Section>

            <Section
                title="Method"
                Icon={ListOrdered}
                error={typeof errors.steps === 'string' ? errors.steps : undefined}
            >
                <RowList<StepFormRow>
                    rows={data.steps}
                    onChange={(rows) => setData('steps', rows)}
                    makeEmpty={() => ({ instruction: '', timer_seconds: null })}
                    addLabel="+ Add step"
                    emptyLabel="No steps yet."
                    itemNoun="step"
                    renderRow={(row, index, update) => (
                        <div className="space-y-1.5">
                            <textarea
                                value={row.instruction}
                                onChange={(event) => update({ instruction: event.target.value })}
                                placeholder={`Step ${index + 1}`}
                                aria-label={`Step ${index + 1}`}
                                rows={2}
                                className={cn(
                                    controlClasses,
                                    'min-h-20 resize-y py-2 text-[0.95rem] leading-relaxed',
                                    errors[`steps.${index}.instruction`] && 'ring-croatia',
                                )}
                            />

                            <label className="flex items-center gap-2 text-[0.85rem] text-ink-muted">
                                <Timer className="size-3.5 shrink-0" aria-hidden="true" />
                                Timer
                                <input
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    max={1440}
                                    value={row.timer_seconds ? Math.round(row.timer_seconds / 60) : ''}
                                    onChange={(event) =>
                                        update({
                                            timer_seconds: event.target.value
                                                ? Math.max(0, Number(event.target.value)) * 60
                                                : null,
                                        })
                                    }
                                    placeholder="—"
                                    aria-label={`Timer minutes for step ${index + 1}`}
                                    className="h-9 w-20 rounded-lg bg-surface px-2 text-[0.88rem] tabular-nums text-ink ring-1 ring-line-strong focus:outline-none focus:ring-2 focus:ring-adriatic"
                                />
                                minutes
                            </label>
                        </div>
                    )}
                />

                <p className="text-[0.85rem] text-ink-muted">
                    Timers are optional. Cooking mode also offers a timer when a step mentions one (“bake for
                    25 minutes”), so only set this when you want to be explicit.
                </p>
            </Section>

            <Section title="Photos" Icon={ImageIcon}>
                <ImagePicker
                    images={data.images}
                    onChange={(images: ImageFormRow[]) => setData('images', images)}
                />
            </Section>

            <Section title="Details" Icon={Info}>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextField
                        label="Source URL"
                        type="url"
                        inputMode="url"
                        value={data.source_url}
                        onChange={(event) => setData('source_url', event.target.value)}
                        error={errors.source_url}
                        placeholder="https://…"
                        hint="Shown as attribution on the recipe page."
                    />
                    <TextField
                        label="Source name"
                        value={data.source_name}
                        onChange={(event) => setData('source_name', event.target.value)}
                        error={errors.source_name}
                        placeholder="Serious Eats, Grandma, …"
                    />
                </div>

                <TextField
                    label="Web address"
                    value={data.slug}
                    onChange={(event) => setData('slug', event.target.value)}
                    error={errors.slug}
                    placeholder="banana-protein-baked-oats"
                    hint={
                        data.slug
                            ? 'Changing this breaks any link anyone already has to this recipe.'
                            : 'Left blank, this is generated from the title.'
                    }
                />

                <TextAreaField
                    label="Notes"
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                    error={errors.notes}
                    rows={5}
                    hint="Anything that is not a step: substitutions, what went wrong last time, how it keeps."
                />

                <div className="space-y-1 rounded-xl bg-surface-2 p-4">
                    <Switch
                        checked={data.is_favorite}
                        onChange={(value) => setData('is_favorite', value)}
                        label="Favorite"
                        description="Featured in the Favorites row on the homepage."
                    />
                    <Switch
                        checked={data.status === 'published'}
                        onChange={(value) => setData('status', value ? 'published' : 'draft')}
                        label="Published"
                        description="Drafts are invisible to visitors and never appear in search."
                    />
                </div>
            </Section>
        </div>
    )
}

function Section({
    title,
    Icon,
    error,
    children,
}: {
    title: string
    Icon: typeof Info
    error?: string
    children: ReactNode
}) {
    return (
        <section className="rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line sm:p-6">
            <h2 className="flex items-center gap-2.5 font-display text-[1.35rem] tracking-tight text-ink">
                <Icon className="size-4.5 text-ink-faint" aria-hidden="true" />
                {title}
            </h2>

            {error && (
                <p
                    className="mt-3 rounded-lg bg-croatia-soft px-3 py-2 text-[0.88rem] text-croatia"
                    role="alert"
                >
                    {error}
                </p>
            )}

            <div className="mt-5 space-y-4">{children}</div>
        </section>
    )
}

function NumberField({
    label,
    value,
    onChange,
    error,
    hint,
    min = 0,
}: {
    label: string
    value: number | null
    onChange: (value: number | null) => void
    error?: string
    hint?: string
    min?: number
}) {
    return (
        <TextField
            label={label}
            type="number"
            inputMode="numeric"
            min={min}
            value={value ?? ''}
            onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
            error={error}
            hint={hint}
            className="tabular-nums"
        />
    )
}
