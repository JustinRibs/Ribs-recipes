import { describe, expect, it } from 'vitest'
import { detectDurations, formatClock, humaniseMinutes, timerLabel } from '../time'

describe('humaniseMinutes', () => {
    it.each([
        [25, '25 min'],
        [60, '1 hr'],
        [90, '1 hr 30 min'],
        [155, '2 hr 35 min'],
    ])('renders %s minutes as %s', (minutes, expected) => {
        expect(humaniseMinutes(minutes)).toBe(expected)
    })

    it('returns null when there is no time to show', () => {
        expect(humaniseMinutes(0)).toBeNull()
        expect(humaniseMinutes(null)).toBeNull()
    })
})

describe('formatClock', () => {
    it.each([
        [0, '0:00'],
        [9, '0:09'],
        [90, '1:30'],
        [1500, '25:00'],
        [3930, '1:05:30'],
    ])('renders %s seconds as %s', (seconds, expected) => {
        expect(formatClock(seconds)).toBe(expected)
    })

    it('never shows a negative clock', () => {
        expect(formatClock(-10)).toBe('0:00')
    })
})

describe('timerLabel', () => {
    it('reads naturally at every scale', () => {
        expect(timerLabel(45)).toBe('45 sec')
        expect(timerLabel(1500)).toBe('25 min')
        expect(timerLabel(5400)).toBe('1 hr 30 min')
    })
})

/**
 * Timer detection is pure pattern matching — no model, no network call. What
 * matters most is what it refuses: offering a "350 minute" timer because a step
 * said 350°F would be worse than offering no timer at all.
 */
describe('detectDurations', () => {
    it('finds an explicit duration in a step', () => {
        expect(detectDurations('Bake for 25 minutes, until set.')[0]).toEqual({
            seconds: 1500,
            text: '25 minutes',
        })
    })

    it.each([
        ['Simmer for 1 hour.', 3600],
        ['Rest 90 seconds before slicing.', 90],
        ['Fry for 2 mins per side.', 120],
        ['Chill for 2 hrs.', 7200],
    ])('reads "%s"', (text, seconds) => {
        expect(detectDurations(text)[0]?.seconds).toBe(seconds)
    })

    it('takes the lower bound of a range so nothing burns', () => {
        expect(detectDurations('Cook for 2-3 minutes.')[0]?.seconds).toBe(120)
        expect(detectDurations('Cook for 2 to 3 minutes.')[0]?.seconds).toBe(120)
    })

    it('ignores numbers that are not times', () => {
        expect(detectDurations('Heat the oven to 350°F / 175°C.')).toEqual([])
        expect(detectDurations('Use a 9-inch tin.')).toEqual([])
        expect(detectDurations('Add 2 eggs and 200 g of flour.')).toEqual([])
    })

    it('only offers a temperature step a timer when it really has one', () => {
        const found = detectDurations('Heat the oven to 350°F and bake for 40 minutes.')

        expect(found).toHaveLength(1)
        expect(found[0]?.seconds).toBe(2400)
    })

    it('refuses overnight marinades — those are notes, not timers', () => {
        expect(detectDurations('Marinate for 24 hours.')).toEqual([])
    })

    it('returns at most three suggestions', () => {
        const text = 'Wait 1 minute, then 2 minutes, then 3 minutes, then 4 minutes, then 5 minutes.'

        expect(detectDurations(text).length).toBeLessThanOrEqual(3)
    })
})
