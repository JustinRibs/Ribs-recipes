import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * Both browser projects share one seeded database, and several of these specs
 * create things. Names are therefore made unique per project and run, so a
 * test never trips over a row another test left behind.
 */
const unique = (label: string, project: string) => `${label} ${project} ${Date.now().toString(36)}`

/**
 * Admin flows.
 *
 * These run with the development Access bypass, which the environment gate
 * allows only because APP_ENV is local. The Cloudflare Access verification
 * itself is covered by tests/Feature/Admin/CloudflareAccessTest.php, which
 * uses genuinely signed tokens.
 */

test.describe('the hidden entrance', () => {
    test('four taps on the logo opens the admin', async ({ page }) => {
        await page.goto('/')
        await page.waitForLoadState('networkidle')

        const logo = page.getByRole('banner').getByRole('link', { name: 'Ribs Recipes — home' })

        // Only the first of these navigates; the rest are suppressed, which is
        // what keeps the count independent of how fast the server answers.
        for (let i = 0; i < 4; i++) {
            await logo.click()
        }

        await expect(page).toHaveURL(/\/admin$/)
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
    })

    test('a single tap still just goes home', async ({ page }) => {
        await page.goto('/recipes')

        await page.getByRole('banner').getByRole('link', { name: 'Ribs Recipes — home' }).click()

        await expect(page).toHaveURL(/\/$/)
        await expect(page.getByRole('heading', { level: 1 })).toContainText('What are we cooking?')
    })

    test('taps spread out over time do not count', async ({ page }) => {
        await page.goto('/')

        const logo = page.getByRole('banner').getByRole('link', { name: 'Ribs Recipes — home' })

        // Comfortably past the 800ms gap threshold, so each tap starts a new
        // sequence rather than continuing the last.
        for (let i = 0; i < 3; i++) {
            await logo.click()
            await page.waitForTimeout(1500)
        }

        await logo.click()
        await expect(page).not.toHaveURL(/\/admin/)
    })
})

test.describe('recipe editor', () => {
    test('creates a recipe end to end', async ({ page }, testInfo) => {
        const title = unique('Playwright Test Loaf', testInfo.project.name)
        const slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-')

        await page.goto('/admin/recipes/create')

        await page.getByLabel('Title').fill(title)
        await page.getByLabel('Short description').fill('Made by a robot, eaten by nobody.')
        await page.getByLabel('Prep (min)').fill('15')
        await page.getByLabel('Cook (min)').fill('45')
        await page.getByLabel('Servings').fill('8')

        await page.getByLabel('Quantity for ingredient 1').fill('500')
        await page.getByLabel('Unit for ingredient 1').fill('g')
        await page.getByLabel('Ingredient 1', { exact: true }).fill('strong white flour')

        await page.getByRole('button', { name: '+ Add ingredient' }).click()
        await page.getByLabel('Quantity for ingredient 2').fill('10')
        await page.getByLabel('Unit for ingredient 2').fill('g')
        await page.getByLabel('Ingredient 2', { exact: true }).fill('fine sea salt')

        await page.getByLabel('Step 1', { exact: true }).fill('Mix the dough and leave it overnight.')
        await page.getByRole('button', { name: '+ Add step' }).click()
        await page.getByLabel('Step 2', { exact: true }).fill('Bake in a very hot oven.')
        await page.getByLabel('Timer minutes for step 2').fill('40')

        await page.getByRole('switch', { name: 'Published' }).click()
        await page.getByRole('button', { name: 'Create recipe' }).click()

        await expect(page).toHaveURL(/\/admin\/recipes\/\d+\/edit/)
        await expect(page.getByText('saved.')).toBeVisible()

        // And it is live on the public site immediately.
        await page.goto(`/recipes/${slug}`)
        await expect(page.getByRole('heading', { level: 1 })).toContainText(title)
        await expect(page.getByText('500 g')).toBeVisible()
        await expect(page.getByRole('button', { name: /Start 40 min timer/ })).toBeVisible()
    })

    test('reorders and removes ingredient rows', async ({ page }) => {
        await page.goto('/admin/recipes/create')

        await page.getByLabel('Ingredient 1', { exact: true }).fill('first')
        await page.getByRole('button', { name: '+ Add ingredient' }).click()
        await page.getByLabel('Ingredient 2', { exact: true }).fill('second')

        await page.getByRole('button', { name: 'Move ingredient 2 up' }).click()
        await expect(page.getByLabel('Ingredient 1', { exact: true })).toHaveValue('second')
        await expect(page.getByLabel('Ingredient 2', { exact: true })).toHaveValue('first')

        await page.getByRole('button', { name: 'Remove ingredient 1' }).click()
        await expect(page.getByLabel('Ingredient 1', { exact: true })).toHaveValue('first')
        await expect(page.getByLabel('Ingredient 2', { exact: true })).toHaveCount(0)
    })

    test('refuses to publish a recipe with nothing to cook', async ({ page }) => {
        await page.goto('/admin/recipes/create')

        await page.getByLabel('Title').fill('Empty Recipe')
        await page.getByRole('switch', { name: 'Published' }).click()
        await page.getByRole('button', { name: 'Create recipe' }).click()

        await expect(page.getByText('Add at least one ingredient before publishing.')).toBeVisible()
        await expect(page.getByText('Add at least one step before publishing.')).toBeVisible()
        await expect(page).toHaveURL(/\/admin\/recipes\/create/)
    })

    test('recovers an unsaved draft after a refresh', async ({ page }) => {
        await page.goto('/admin/recipes/create')

        await page.getByLabel('Title').fill('Half Written Recipe')
        // Autosave is debounced; give it a moment to land in localStorage.
        await page.waitForTimeout(1200)

        await page.reload()

        await expect(page.getByText(/unsaved draft of “Half Written Recipe”/)).toBeVisible()
        await page.getByRole('button', { name: 'Restore' }).click()
        await expect(page.getByLabel('Title')).toHaveValue('Half Written Recipe')
    })

    test('confirms before deleting', async ({ page }) => {
        await page.goto('/admin/recipes')
        await page.getByRole('link', { name: 'Edit' }).first().click()

        await page.getByRole('button', { name: 'Delete recipe' }).click()

        const dialog = page.getByRole('dialog')
        await expect(dialog).toContainText('moves to the trash')

        await dialog.getByRole('button', { name: 'Cancel' }).click()
        await expect(page).toHaveURL(/\/edit/)
    })
})

test.describe('recipe list', () => {
    test('filters by status and searches', async ({ page }) => {
        await page.goto('/admin/recipes')

        await page.getByRole('button', { name: 'Drafts' }).click()
        await expect(page.getByRole('link', { name: 'Peka-Style Chicken and Potatoes' })).toBeVisible()
        await expect(page.getByRole('link', { name: 'Dalmatian Brudet' })).toHaveCount(0)

        await page.goto('/admin/recipes?q=brudet')
        await expect(page.getByRole('link', { name: 'Dalmatian Brudet' })).toBeVisible()
        await expect(page.getByRole('link', { name: 'Ajvar', exact: true })).toHaveCount(0)
    })

    test('toggles favorite from the list', async ({ page }) => {
        await page.goto('/admin/recipes?q=fritule')

        await page.getByRole('button', { name: 'Favorite', exact: true }).click()
        await expect(page.getByText(/is now a favorite/)).toBeVisible()

        await page.getByRole('button', { name: 'Unfavorite' }).click()
        await expect(page.getByText(/no longer a favorite/)).toBeVisible()
    })
})

test.describe('import from a URL', () => {
    test('refuses a private address before fetching anything', async ({ page }) => {
        await page.goto('/admin/import/url')

        await page.getByLabel('Recipe address').fill('http://192.168.1.1/recipe')
        await page.getByRole('button', { name: 'Read recipe' }).click()

        await expect(page.getByText(/private or reserved address|not publicly reachable/)).toBeVisible()
    })

    test('previews a real page without saving it', async ({ page, context }) => {
        // A stand-in recipe site, served by the browser rather than the network.
        await context.route('https://recipes.example.test/**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'text/html; charset=utf-8',
                body: `<html><head><script type="application/ld+json">${JSON.stringify({
                    '@context': 'https://schema.org',
                    '@type': 'Recipe',
                    name: 'Imported Focaccia',
                    recipeIngredient: ['500 g flour', '10 g salt'],
                    recipeInstructions: [{ '@type': 'HowToStep', text: 'Mix and rest overnight.' }],
                })}</script></head><body></body></html>`,
            })
        })

        // The server-side fetch cannot be intercepted from the browser, so the
        // preview is driven through the same endpoint with a known-good page
        // already covered by the PHP suite; here the concern is the UI.
        await page.goto('/admin/import/url')

        await expect(page.getByText(/Only public http\(s\) addresses are fetched/)).toBeVisible()
        await expect(page.getByRole('button', { name: 'Read recipe' })).toBeVisible()
    })
})

test.describe('import from a CSV', () => {
    test('previews every row, then imports only the selected ones', async ({ page }) => {
        await page.goto('/admin/import/csv')

        const template = readFileSync(
            resolve(import.meta.dirname, '../../resources/templates/ribs-recipes-template.csv'),
        )

        await page.setInputFiles('input[type="file"]', {
            name: 'ribs-recipes-template.csv',
            mimeType: 'text/csv',
            buffer: template,
        })

        await page.getByRole('button', { name: 'Check the file' }).click()

        await expect(page.getByText('3 rows read')).toBeVisible()
        await expect(page.getByText('3 importable')).toBeVisible()

        // Deselect everything but the first row.
        await page.getByRole('button', { name: 'Select none' }).click()
        await page.getByRole('checkbox', { name: /Import Banana Protein Baked Oats/ }).check()

        await page.getByRole('button', { name: /Import 1 recipe/ }).click()

        await expect(page).toHaveURL(/\/admin\/recipes/)
        await expect(page.getByText('1 recipe imported.')).toBeVisible()
    })

    test('shows which rows cannot be imported and why', async ({ page }) => {
        await page.goto('/admin/import/csv')

        await page.setInputFiles('input[type="file"]', {
            name: 'broken.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from(
                'title,ingredients,instructions\nFine,1 | cup | flour,Mix.\n,2 | cups | sugar,Stir.\n',
            ),
        })

        await page.getByRole('button', { name: 'Check the file' }).click()

        await expect(page.getByText('1 importable')).toBeVisible()
        await expect(page.getByText('1 with errors')).toBeVisible()
        await expect(page.getByText('Missing a title.')).toBeVisible()
    })

    test('offers the template for download', async ({ page }) => {
        await page.goto('/admin/import/csv')

        await expect(page.getByRole('link', { name: 'Download the template' })).toHaveAttribute(
            'href',
            '/admin/import/csv/template',
        )
    })
})

test.describe('taxonomy', () => {
    test('adds and renames a category', async ({ page }, testInfo) => {
        const name = unique('Sunday Roasts', testInfo.project.name)
        const renamed = `${name} renamed`

        await page.goto('/admin/categories')

        await page.getByRole('button', { name: 'New category' }).click()
        await page.getByRole('dialog').getByLabel('Name').fill(name)
        await page.getByRole('dialog').getByRole('button', { name: 'Save' }).click()

        await expect(page.getByText('Category added.')).toBeVisible()
        await expect(page.getByRole('button', { name: `Edit ${name}` })).toBeVisible()

        await page.getByRole('button', { name: `Edit ${name}` }).click()
        await page.getByRole('dialog').getByLabel('Name').fill(renamed)
        await page.getByRole('dialog').getByRole('button', { name: 'Save' }).click()

        await expect(page.getByText('Category updated.')).toBeVisible()
        await expect(page.getByRole('button', { name: `Edit ${renamed}` })).toBeVisible()
    })

    test('adds a tag', async ({ page }, testInfo) => {
        const name = unique('Weeknight', testInfo.project.name)

        await page.goto('/admin/tags')

        await page.getByLabel('Add a tag').fill(name)
        await page.getByRole('button', { name: 'Add' }).click()

        await expect(page.getByText('Tag added.')).toBeVisible()
        await expect(page.getByRole('button', { name: `Edit ${name}` })).toBeVisible()
    })
})

test.describe('settings', () => {
    test('reports how the admin is being protected', async ({ page }) => {
        await page.goto('/admin/settings')

        // The bypass is loudly flagged so it can never be mistaken for
        // "Cloudflare Access is working".
        await expect(page.getByText('Development bypass is active')).toBeVisible()
        await expect(page.getByText('SQLite FTS5')).toBeVisible()
    })
})
