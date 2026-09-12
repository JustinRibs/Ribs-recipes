/**
 * Admin endpoints.
 *
 * The public site gets its URLs from the server inside each prop (`recipe.url`,
 * `category.url`), which keeps slugs authoritative. The admin's own endpoints
 * are stable paths, so they are simply named here rather than shipping a whole
 * route manifest to the browser.
 */
export const adminRoutes = {
    dashboard: '/admin',
    recipes: '/admin/recipes',
    recipeCreate: '/admin/recipes/create',
    recipe: (id: number) => `/admin/recipes/${id}`,
    recipeEdit: (id: number) => `/admin/recipes/${id}/edit`,
    recipeDuplicate: (id: number) => `/admin/recipes/${id}/duplicate`,
    recipeFavorite: (id: number) => `/admin/recipes/${id}/favorite`,
    recipePublish: (id: number) => `/admin/recipes/${id}/publish`,
    recipeRestore: (id: number) => `/admin/recipes/${id}/restore`,
    recipeForceDelete: (id: number) => `/admin/recipes/${id}/force`,
    categories: '/admin/categories',
    category: (id: number) => `/admin/categories/${id}`,
    tags: '/admin/tags',
    tag: (id: number) => `/admin/tags/${id}`,
    media: '/admin/media',
    mediaRemote: '/admin/media/remote',
    mediaDelete: (id: number) => `/admin/media/${id}`,
    importUrl: '/admin/import/url',
    importCsv: '/admin/import/csv',
    importCsvPreview: '/admin/import/csv/preview',
    importCsvTemplate: '/admin/import/csv/template',
    settings: '/admin/settings',
    settingsReindex: '/admin/settings/reindex',
} as const

export const publicRoutes = {
    home: '/',
    recipes: '/recipes',
    searchSuggest: '/search/suggest',
} as const

/** Build a query string, dropping empty values so URLs stay clean. */
export function withQuery(path: string, params: Record<string, unknown>): string {
    const search = new URLSearchParams()

    for (const [key, value] of Object.entries(params)) {
        if (value === null || value === undefined || value === '' || value === false) continue

        if (Array.isArray(value)) {
            value.forEach((item) => search.append(`${key}[]`, String(item)))
        } else {
            search.set(key, String(value))
        }
    }

    const query = search.toString()

    return query ? `${path}?${query}` : path
}
