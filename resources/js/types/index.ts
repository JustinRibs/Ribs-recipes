import type { PageProps as InertiaPageProps } from '@inertiajs/core'

export interface ImageResource {
    id: number
    src: string
    srcset: string | null
    thumb: string | null
    width: number | null
    height: number | null
    alt: string | null
    caption: string | null
    placeholder: string | null
    source: 'local' | 'remote'
    isHero: boolean
}

export interface CategoryRef {
    id: number
    name: string
    slug: string
    icon: string | null
    color: string | null
    url: string
}

export interface TagRef {
    id: number
    name: string
    slug: string
    url: string
}

/** The lightweight shape used by every grid and rail. */
export interface RecipeCardData {
    id: number
    title: string
    slug: string
    description: string | null
    url: string
    image: ImageResource | null
    category: CategoryRef | null
    totalMinutes: number | null
    totalTime: string | null
    servings: number | null
    isFavorite: boolean
}

export interface Ingredient {
    id: number
    quantity: number | null
    quantityDisplay: string | null
    unit: string | null
    name: string
    note: string | null
}

export interface Step {
    id: number
    instruction: string
    timerSeconds: number | null
    image: ImageResource | null
}

export interface RecipeDetail {
    id: number
    title: string
    slug: string
    description: string | null
    notes: string | null
    url: string
    category: CategoryRef | null
    tags: TagRef[]
    author: string | null
    prepMinutes: number | null
    cookMinutes: number | null
    totalMinutes: number | null
    prepTime: string | null
    cookTime: string | null
    totalTime: string | null
    servings: number | null
    servingsLabel: string
    calories: number | null
    sourceUrl: string | null
    sourceName: string | null
    isFavorite: boolean
    publishedAt: string | null
    updatedAt: string | null
    heroImage: ImageResource | null
    gallery: ImageResource[]
    ingredients: Ingredient[]
    steps: Step[]
}

export interface PaginationMeta {
    currentPage: number
    lastPage: number
    total: number
    perPage?: number
    nextPageUrl: string | null
    prevPageUrl: string | null
}

export interface Paginated<T> {
    data: T[]
    meta: PaginationMeta
}

export interface BrowseFilters {
    q: string | null
    category: string | null
    tags: string[]
    sort: string
    favorites: boolean
}

export interface Facets {
    categories: { name: string; slug: string; icon: string | null; count: number }[]
    tags: { name: string; slug: string; count: number }[]
}

export interface SiteSettings {
    name: string
    tagline: string
    heroHeading: string
    heroSubheading: string
    footerNote: string
}

export interface NavCategory {
    name: string
    slug: string
    icon: string | null
    url: string
}

export interface AdminIdentity {
    name: string
    email: string
    role: 'owner' | 'editor' | 'contributor'
}

export interface SharedProps extends InertiaPageProps {
    site: SiteSettings
    navCategories: NavCategory[]
    flash: { success: string | null; error: string | null }
    auth: AdminIdentity | null
    errors: Record<string, string>
}

/* -------------------------------------------------------------------------- */
/* Admin                                                                      */
/* -------------------------------------------------------------------------- */

export interface AdminRecipeRow {
    id: number
    title: string
    slug: string
    status: 'draft' | 'published'
    isFavorite: boolean
    category: CategoryRef | null
    thumb: string | null
    totalMinutes: number | null
    updatedAt: string | null
    publishedAt: string | null
    deletedAt: string | null
    editUrl: string
    publicUrl: string
}

export interface CategoryOption {
    id: number
    name: string
    slug: string
    icon?: string | null
    color?: string | null
}

export interface IngredientFormRow {
    quantity_display: string
    unit: string
    name: string
    note: string
}

export interface StepFormRow {
    instruction: string
    timer_seconds: number | null
}

export interface ImageFormRow extends Partial<ImageResource> {
    id: number
    src: string
    caption: string
    alt: string
}

/** Exactly what the recipe editor posts, and what the importer pre-fills. */
export interface RecipeFormState {
    title: string
    slug: string
    description: string
    notes: string
    category_id: number | null
    tags: string[]
    prep_minutes: number | null
    cook_minutes: number | null
    total_minutes_override: number | null
    servings: number | null
    servings_label: string
    calories: number | null
    source_url: string
    source_name: string
    is_favorite: boolean
    status: 'draft' | 'published'
    ingredients: IngredientFormRow[]
    steps: StepFormRow[]
    images: ImageFormRow[]
}
