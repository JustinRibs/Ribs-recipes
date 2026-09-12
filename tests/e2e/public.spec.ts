import { expect, test, type Page } from '@playwright/test'

/**
 * The public experience, driven the way a visitor would: no logging in, no
 * fixtures beyond the seeded collection.
 */

test.describe('homepage', () => {
    test('shows the collection and nothing about an admin area', async ({ page }) => {
        await page.goto('/')

        await expect(page.getByRole('heading', { level: 1 })).toContainText('What are we cooking?')
        await expect(
            page.getByRole('banner').getByRole('link', { name: 'Ribs Recipes — home' }),
        ).toBeVisible()

        // Editorial rails, not an undifferentiated feed.
        await expect(page.getByRole('heading', { name: 'Recently added' })).toBeVisible()
        await expect(page.getByRole('heading', { name: 'Favorites' })).toBeVisible()

        // Nothing anywhere on the public page hints that /admin exists.
        await expect(page.getByRole('link', { name: /admin/i })).toHaveCount(0)
        await expect(page.locator('a[href^="/admin"]')).toHaveCount(0)
    })

    test('never scrolls sideways', async ({ page }) => {
        await page.goto('/')
        await page.waitForLoadState('networkidle')

        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        )

        expect(overflow).toBeLessThanOrEqual(1)
    })

    test('renders structured data for search engines', async ({ page }) => {
        await page.goto('/recipes/banana-protein-baked-oats')

        const jsonLd = await page.locator('script[type="application/ld+json"]').textContent()
        const data = JSON.parse(jsonLd ?? '{}')

        expect(data['@type']).toBe('Recipe')
        expect(data.name).toBe('Banana Protein Baked Oats')
        expect(data.recipeIngredient.length).toBeGreaterThan(0)
        expect(data.recipeInstructions[0]['@type']).toBe('HowToStep')
    })
})

test.describe('search', () => {
    test('suggests recipes as you type and opens one', async ({ page }) => {
        await page.goto('/')

        await page.getByRole('button', { name: 'Search recipes' }).first().click()

        const field = page.getByRole('searchbox', { name: 'Search recipes' })
        await expect(field).toBeFocused()

        await field.fill('oats')

        const suggestion = page.getByRole('option', { name: /Banana Protein Baked Oats/ })
        await expect(suggestion).toBeVisible()

        await suggestion.click()
        await expect(page).toHaveURL(/\/recipes\/banana-protein-baked-oats/)
    })

    test('matches on an ingredient, not just the title', async ({ page }) => {
        await page.goto('/recipes')

        await page.getByRole('searchbox', { name: 'Search recipes' }).fill('chickpeas')

        await expect(page.getByRole('heading', { name: /Chickpea|Chickpeas/ }).first()).toBeVisible()
    })

    test('filters by tag and clears again', async ({ page }) => {
        await page.goto('/recipes')

        await page.getByRole('button', { name: 'Filter recipes' }).click()
        await page.getByRole('button', { name: /^Croatian/ }).click()
        await page.getByRole('button', { name: /Show \d+ recipe/ }).click()

        // Inertia normalises array parameters; Laravel reads either spelling.
        await expect(page).toHaveURL(/tags(%5B%5D|%5B0%5D|\[\])=croatian/)
        await expect(page.getByRole('heading', { name: 'Ajvar', exact: true })).toBeVisible()

        await page.getByRole('button', { name: 'Clear all' }).first().click()
        await expect(page).not.toHaveURL(/croatian/)
    })
})

test.describe('recipe page', () => {
    test('scales ingredients when the serving count changes', async ({ page }) => {
        await page.goto('/recipes/banana-protein-baked-oats')

        const ingredients = page.getByRole('region', { name: 'Ingredients' }).getByRole('listitem')
        const oats = ingredients.filter({ hasText: 'rolled oats' })
        await expect(oats).toContainText('1 cup')

        await page.getByRole('button', { name: '2×' }).click()
        await expect(oats).toContainText('2 cups')

        await page.getByRole('button', { name: '0.5×' }).click()
        await expect(oats).toContainText('1/2 cup')

        // Amounts that cannot be scaled are left exactly as written.
        await expect(ingredients.filter({ hasText: 'butter' })).toContainText('butter, for the dish')
    })

    test('remembers the checklist across a reload', async ({ page }) => {
        await page.goto('/recipes/banana-protein-baked-oats')

        // A visitor taps the row, not the 20px box — the input is visually
        // hidden and the whole label is the target.
        const firstIngredient = page
            .getByRole('region', { name: 'Ingredients' })
            .getByRole('listitem')
            .first()

        await firstIngredient.click()
        await expect(firstIngredient.getByRole('checkbox')).toBeChecked()

        await page.reload()
        await expect(
            page
                .getByRole('region', { name: 'Ingredients' })
                .getByRole('listitem')
                .first()
                .getByRole('checkbox'),
        ).toBeChecked()
    })

    test('offers a timer for a step that mentions one', async ({ page }) => {
        await page.goto('/recipes/banana-protein-baked-oats')

        await expect(
            page.locator('#step-5').getByRole('button', { name: /Start 25 min timer/ }),
        ).toBeVisible()
    })

    test('attributes an imported recipe to its source', async ({ page }) => {
        await page.goto('/recipes/dalmatian-brudet')

        await expect(page.getByText(/Source: Family recipe/)).toBeVisible()
    })
})

test.describe('cooking mode', () => {
    test('walks through the steps and back', async ({ page }) => {
        await openCookingMode(page)

        const cooking = page.getByRole('dialog', { name: /^Cooking / })

        await expect(page.getByText('Step 1 of 6')).toBeVisible()
        await expect(cooking.getByText(/Heat the oven/)).toBeVisible()

        await page.getByRole('button', { name: 'Next step' }).click()
        await expect(page.getByText('Step 2 of 6')).toBeVisible()

        await page.getByRole('button', { name: 'Previous step' }).click()
        await expect(page.getByText('Step 1 of 6')).toBeVisible()
    })

    test('moves with the arrow keys too', async ({ page }) => {
        await openCookingMode(page)

        await page.keyboard.press('ArrowRight')
        await expect(page.getByText('Step 2 of 6')).toBeVisible()

        await page.keyboard.press('ArrowLeft')
        await expect(page.getByText('Step 1 of 6')).toBeVisible()
    })

    test('keeps the ingredients one tap away, at the chosen scale', async ({ page }) => {
        await page.goto('/recipes/banana-protein-baked-oats')
        await page.getByRole('button', { name: '2×' }).click()
        await page.getByRole('button', { name: 'Start cooking' }).click()

        await page.getByRole('button', { name: /Ingredients/ }).click()

        const sheet = page.getByRole('dialog', { name: 'Ingredients' })
        await expect(sheet).toBeVisible()
        await expect(sheet.getByText('2 cups')).toBeVisible()
    })

    test('starts a timer from the step that mentions one', async ({ page }) => {
        await openCookingMode(page)

        for (let i = 0; i < 4; i++) {
            await page.getByRole('button', { name: 'Next step' }).click()
        }

        await expect(page.getByText('Step 5 of 6')).toBeVisible()
        await page
            .getByRole('dialog', { name: /^Cooking / })
            .getByRole('button', { name: /Start 25 min timer/ })
            .click()

        // The dock follows you to the next step rather than living in it.
        await expect(page.getByText(/24:5\d/)).toBeVisible()
        await page.getByRole('button', { name: 'Next step' }).click()
        await expect(page.getByText(/24:5\d/)).toBeVisible()
    })

    test('asks before discarding progress', async ({ page }) => {
        await openCookingMode(page)

        await page.getByRole('button', { name: 'Next step' }).click()
        await page.getByRole('button', { name: 'Leave cooking mode' }).click()

        await expect(page.getByRole('alertdialog')).toBeVisible()
        await page.getByRole('button', { name: 'Keep cooking' }).click()
        await expect(page.getByText('Step 2 of 6')).toBeVisible()

        await page.getByRole('button', { name: 'Leave cooking mode' }).click()
        await page.getByRole('button', { name: 'Leave', exact: true }).click()
        await expect(page.getByRole('heading', { name: 'Ingredients' })).toBeVisible()
    })
})

test.describe('theme', () => {
    test('switches and is remembered', async ({ page }) => {
        await page.goto('/')

        await page.getByRole('radio', { name: 'Dark' }).first().click()
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

        await page.reload()
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
    })
})

test.describe('errors', () => {
    test('a missing recipe gets a branded page', async ({ page }) => {
        const response = await page.goto('/recipes/no-such-recipe')

        expect(response?.status()).toBe(404)
        await expect(page.getByRole('heading', { name: 'This page is off the menu' })).toBeVisible()
        await expect(page.getByRole('link', { name: 'Back to the collection' })).toBeVisible()
    })
})

async function openCookingMode(page: Page): Promise<void> {
    await page.goto('/recipes/banana-protein-baked-oats')
    await page.getByRole('button', { name: 'Start cooking' }).click()
    await expect(page.getByText('Step 1 of 6')).toBeVisible()
}
