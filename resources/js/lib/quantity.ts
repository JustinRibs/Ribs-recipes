/**
 * Cooking quantity formatting and scaling, in the browser.
 *
 * Mirrors app/Support/Quantity.php so a scaled ingredient reads exactly the
 * way a saved one does. Both implementations are covered by tests.
 *
 * The guiding rule: never invent a number. If a quantity cannot be parsed, the
 * author's original text is passed through untouched rather than turned into
 * something confidently wrong.
 */

const DENOMINATORS = [2, 3, 4, 8, 16]

const VULGAR: Record<string, string> = {
    '½': ' 1/2',
    '⅓': ' 1/3',
    '⅔': ' 2/3',
    '¼': ' 1/4',
    '¾': ' 3/4',
    '⅕': ' 1/5',
    '⅖': ' 2/5',
    '⅗': ' 3/5',
    '⅘': ' 4/5',
    '⅙': ' 1/6',
    '⅚': ' 5/6',
    '⅐': ' 1/7',
    '⅛': ' 1/8',
    '⅜': ' 3/8',
    '⅝': ' 5/8',
    '⅞': ' 7/8',
    '⅑': ' 1/9',
    '⅒': ' 1/10',
}

const RANGE_PATTERN = /^(\d[\d\s/.]*)\s*(-|–|—|to)\s*(\d[\d\s/.]*)$/u

function normalise(input: string | null | undefined): string | null {
    if (input === null || input === undefined) return null

    let value = input.trim()
    if (value === '') return null

    for (const [glyph, replacement] of Object.entries(VULGAR)) {
        if (value.includes(glyph)) value = value.split(glyph).join(replacement)
    }

    return value.replace(/⁄/g, '/').replace(/,/g, '.').replace(/\s+/g, ' ').trim()
}

/** Parse a written amount into a number, or null when it is not one. */
export function parseQuantity(input: string | null | undefined): number | null {
    const value = normalise(input)
    if (value === null || RANGE_PATTERN.test(value)) return null

    const mixed = /^(\d+)\s+(\d+)\s*\/\s*(\d+)$/.exec(value)
    if (mixed) {
        const denominator = Number(mixed[3])
        return denominator === 0 ? null : Number(mixed[1]) + Number(mixed[2]) / denominator
    }

    const fraction = /^(\d+)\s*\/\s*(\d+)$/.exec(value)
    if (fraction) {
        const denominator = Number(fraction[2])
        return denominator === 0 ? null : Number(fraction[1]) / denominator
    }

    return /^\d+(\.\d+)?$/.test(value) ? Number(value) : null
}

function nearestFraction(remainder: number): [number, number] | null {
    let best: [number, number] | null = null
    let bestError = Infinity

    for (const denominator of DENOMINATORS) {
        const numerator = Math.round(remainder * denominator)
        if (numerator === 0) continue

        const error = Math.abs(remainder - numerator / denominator)
        if (error < bestError - 1e-9) {
            bestError = error
            best = [numerator, denominator]
        }
    }

    return bestError <= 0.02 ? best : null
}

/** Render a number the way a recipe writes it: "1 1/2", never "1.5". */
export function formatQuantity(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) return ''
    if (value < 0) return `-${formatQuantity(Math.abs(value))}`
    if (value >= 100) return String(Math.round(value))

    const whole = Math.floor(value + 1e-9)
    const remainder = value - whole

    if (remainder < 1e-6) return String(whole)

    const fraction = nearestFraction(remainder)

    if (!fraction) {
        return value.toFixed(2).replace(/\.?0+$/, '')
    }

    const [numerator, denominator] = fraction

    // Rounding can carry the remainder up to a whole unit.
    if (numerator === denominator) return String(whole + 1)

    return whole > 0 ? `${whole} ${numerator}/${denominator}` : `${numerator}/${denominator}`
}

export interface ScalableQuantity {
    quantity: number | null
    quantityDisplay: string | null
}

/**
 * Scale an ingredient amount for a new serving count.
 *
 * Numeric amounts scale and re-render as fractions. Ranges ("2-3") scale at
 * both ends. Anything else — "to taste", "a pinch" — is returned untouched,
 * because doubling "a pinch" is not a thing.
 */
export function scaleQuantity(ingredient: ScalableQuantity, factor: number): string | null {
    if (factor === 1) return ingredient.quantityDisplay

    if (ingredient.quantity !== null) {
        return formatQuantity(ingredient.quantity * factor)
    }

    const value = normalise(ingredient.quantityDisplay)
    const range = value ? RANGE_PATTERN.exec(value) : null

    if (range) {
        const low = parseQuantity(range[1])
        const high = parseQuantity(range[3])

        if (low !== null && high !== null) {
            const separator = range[2] === 'to' ? ' to ' : '–'
            return `${formatQuantity(low * factor)}${separator}${formatQuantity(high * factor)}`
        }
    }

    return ingredient.quantityDisplay
}

/**
 * Units that read wrong when the amount is scaled.
 *
 * Only whole words, never abbreviations: "2 tsp" is correct and "2 tsps" is
 * not, so tsp/tbsp/g/ml/oz/lb are deliberately absent. Doubling "1 cup" to
 * "2 cup" is the kind of small wrongness that makes a site feel generated.
 */
const PLURALISABLE: Record<string, string> = {
    cup: 'cups',
    clove: 'cloves',
    slice: 'slices',
    stick: 'sticks',
    can: 'cans',
    jar: 'jars',
    bottle: 'bottles',
    box: 'boxes',
    bag: 'bags',
    package: 'packages',
    packet: 'packets',
    container: 'containers',
    sprig: 'sprigs',
    bunch: 'bunches',
    stalk: 'stalks',
    stem: 'stems',
    head: 'heads',
    piece: 'pieces',
    ear: 'ears',
    fillet: 'fillets',
    pinch: 'pinches',
    dash: 'dashes',
    handful: 'handfuls',
    scoop: 'scoops',
    square: 'squares',
    sheet: 'sheets',
    strip: 'strips',
    rasher: 'rashers',
    quart: 'quarts',
    pint: 'pints',
    gallon: 'gallons',
    pound: 'pounds',
    ounce: 'ounces',
    gram: 'grams',
    kilogram: 'kilograms',
    liter: 'liters',
    litre: 'litres',
    milliliter: 'milliliters',
    millilitre: 'millilitres',
    tablespoon: 'tablespoons',
    teaspoon: 'teaspoons',
}

const SINGULARISABLE: Record<string, string> = Object.fromEntries(
    Object.entries(PLURALISABLE).map(([singular, plural]) => [plural, singular]),
)

/**
 * Agree a unit with the amount in front of it, leaving anything unrecognised
 * exactly as the author typed it.
 */
export function agreeUnit(unit: string | null, amount: number | null): string | null {
    if (!unit) return unit

    const key = unit.toLowerCase()
    const plural = amount !== null && amount > 1

    if (plural) {
        return PLURALISABLE[key] ?? unit
    }

    return SINGULARISABLE[key] ?? unit
}

/** "2 cups rolled oats, chopped" from its structured parts. */
export function ingredientLine(parts: {
    quantityDisplay: string | null
    unit: string | null
    name: string
    note: string | null
}): string {
    return [parts.quantityDisplay, parts.unit, parts.name, parts.note ? `(${parts.note})` : null]
        .filter(Boolean)
        .join(' ')
}
