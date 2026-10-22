import { describe, expect, it } from 'vite-plus/test';
import { groupOpeningHours } from './opening-hours';

const days: App.Enums.Weekday[] = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
];

function week(
    hours: Array<[string, string] | null>,
): App.Data.Settings.OpeningHoursDayData[] {
    return days.map((day, index) => ({
        day,
        opens: hours[index]?.[0] ?? null,
        closes: hours[index]?.[1] ?? null,
    }));
}

const label = (day: App.Enums.Weekday) => day.slice(0, 3);

describe('groupOpeningHours', () => {
    it('joins consecutive days with the same hours', () => {
        const office: [string, string] = ['09:00', '17:00'];

        expect(
            groupOpeningHours(
                week([
                    office,
                    office,
                    office,
                    office,
                    office,
                    ['10:00', '14:00'],
                    null,
                ]),
                label,
                'closed',
            ),
        ).toEqual([
            { days: 'mon–fri', hours: '09:00–17:00' },
            { days: 'sat', hours: '10:00–14:00' },
            { days: 'sun', hours: 'closed' },
        ]);
    });

    it('does not join equal hours separated by another day', () => {
        const office: [string, string] = ['09:00', '17:00'];

        expect(
            groupOpeningHours(
                week([office, null, office, null, null, null, null]),
                label,
                'closed',
            ).map((row) => row.days),
        ).toEqual(['mon', 'tue', 'wed', 'thu–sun']);
    });
});
