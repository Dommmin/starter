/** One row of the footer opening hours, e.g. "Mon–Fri" / "09:00–17:00". */
export type OpeningHoursRow = {
    days: string;
    hours: string;
};

type OpeningHoursDay = App.Data.Settings.OpeningHoursDayData;

/**
 * Groups consecutive days with the same hours into footer rows ("Mon–Fri
 * 09:00–17:00", "Sat–Sun closed"). `dayLabel` returns the short translated
 * name of a day; `closedLabel` is shown for days without hours.
 */
export function groupOpeningHours(
    week: OpeningHoursDay[],
    dayLabel: (day: App.Enums.Weekday) => string,
    closedLabel: string,
): OpeningHoursRow[] {
    const hoursOf = (day: OpeningHoursDay): string =>
        day.opens !== null && day.closes !== null
            ? `${day.opens}–${day.closes}`
            : closedLabel;
    const rows: OpeningHoursRow[] = [];
    let start = 0;

    for (let index = 1; index <= week.length; index++) {
        const isGroupEnd =
            index === week.length ||
            hoursOf(week[index]) !== hoursOf(week[start]);

        if (!isGroupEnd) {
            continue;
        }

        const first = dayLabel(week[start].day);
        const last = dayLabel(week[index - 1].day);

        rows.push({
            days: start === index - 1 ? first : `${first}–${last}`,
            hours: hoursOf(week[start]),
        });
        start = index;
    }

    return rows;
}
