import { describe, expect, it } from 'vitest'
import { agreeUnit, formatQuantity, ingredientLine, parseQuantity, scaleQuantity } from '../quantity'

/**
 * These mirror tests/Unit/QuantityTest.php case for case. The browser does the
 * scaling a visitor actually sees, so the two implementations have to agree —
 * a difference between them would show up as an ingredient list that changes
 * when the page is reloaded.
 */
describe('parseQuantity', () => {
    it.each([
        ['2', 2],
        ['0.5', 0.5],
        ['3/4', 0.75],
        ['1 1/2', 1.5],
        ['½', 0.5],
        ['1½', 1.5],
        ['1,5', 1.5],
        ['  2  ', 2],
    ])('reads %s as %s', (input, expected) => {
        expect(parseQuantity(input)).toBeCloseTo(expected, 5)
    })

    it.each([['2-3'], ['2–3'], ['1 to 2'], ['a pinch'], [''], ['1/0'], [null], [undefined]])(
        'returns null for %s',
        (input) => {
            expect(parseQuantity(input as string | null)).toBeNull()
        },
    )
})

describe('formatQuantity', () => {
    it.each([
        [3, '3'],
        [0.5, '1/2'],
        [1 / 3, '1/3'],
        [2 / 3, '2/3'],
        [0.25, '1/4'],
        [1.5, '1 1/2'],
        [2.25, '2 1/4'],
        [0.125, '1/8'],
        [0.99, '1'],
        [120.4, '120'],
        [0, '0'],
    ])('renders %s as %s', (value, expected) => {
        expect(formatQuantity(value)).toBe(expected)
    })

    it('returns an empty string for nothing', () => {
        expect(formatQuantity(null)).toBe('')
        expect(formatQuantity(undefined)).toBe('')
        expect(formatQuantity(Number.NaN)).toBe('')
    })
})

describe('scaleQuantity', () => {
    it('scales numeric amounts and renders them as fractions', () => {
        expect(scaleQuantity({ quantity: 0.5, quantityDisplay: '1/2' }, 2)).toBe('1')
        expect(scaleQuantity({ quantity: 1.5, quantityDisplay: '1 1/2' }, 0.5)).toBe('3/4')
        expect(scaleQuantity({ quantity: 1.5, quantityDisplay: '1 1/2' }, 3)).toBe('4 1/2')
    })

    it('scales both ends of a range', () => {
        expect(scaleQuantity({ quantity: null, quantityDisplay: '2-3' }, 2)).toBe('4–6')
        expect(scaleQuantity({ quantity: null, quantityDisplay: '2 to 4' }, 0.5)).toBe('1 to 2')
    })

    it('never invents a number for text it cannot read', () => {
        expect(scaleQuantity({ quantity: null, quantityDisplay: 'to taste' }, 2)).toBe('to taste')
        expect(scaleQuantity({ quantity: null, quantityDisplay: 'a pinch' }, 4)).toBe('a pinch')
        expect(scaleQuantity({ quantity: null, quantityDisplay: null }, 2)).toBeNull()
    })

    it('leaves the original wording alone at 1x', () => {
        expect(scaleQuantity({ quantity: 1.5, quantityDisplay: '1 1/2' }, 1)).toBe('1 1/2')
    })
})

describe('agreeUnit', () => {
    it('pluralises whole words', () => {
        expect(agreeUnit('cup', 2)).toBe('cups')
        expect(agreeUnit('clove', 3)).toBe('cloves')
        expect(agreeUnit('bunch', 2)).toBe('bunches')
    })

    it('singularises when the amount drops to one', () => {
        expect(agreeUnit('cups', 1)).toBe('cup')
        expect(agreeUnit('cups', 0.5)).toBe('cup')
    })

    it('never touches abbreviations', () => {
        for (const unit of ['tsp', 'tbsp', 'g', 'ml', 'oz', 'lb', 'kg']) {
            expect(agreeUnit(unit, 4)).toBe(unit)
        }
    })

    it('passes unknown units straight through', () => {
        expect(agreeUnit('glug', 3)).toBe('glug')
        expect(agreeUnit(null, 3)).toBeNull()
    })
})

describe('ingredientLine', () => {
    it('assembles the parts a recipe would write', () => {
        expect(
            ingredientLine({
                quantityDisplay: '1 1/2',
                unit: 'cups',
                name: 'rolled oats',
                note: 'gluten free',
            }),
        ).toBe('1 1/2 cups rolled oats (gluten free)')

        expect(ingredientLine({ quantityDisplay: null, unit: null, name: 'salt', note: 'to taste' })).toBe(
            'salt (to taste)',
        )
    })
})
