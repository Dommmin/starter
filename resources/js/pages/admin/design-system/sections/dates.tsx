import { useState } from 'react';
import {
    DateRangeField,
    Stack,
    type DateRangeValue,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

const emptyRange: DateRangeValue = { from: '', to: '' };

/** ADM-05 — dates and ranges: DateRangeField (native inputs, no picker library). */
function DatesSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.dates.${key}`);
    const [ranges, setRanges] = useState<Record<string, DateRangeValue>>({
        value: { from: '2026-10-01', to: '2026-10-03' },
        disabled: { from: '2026-09-01', to: '2026-09-30' },
        error: { from: '2026-10-05', to: '2026-10-01' },
        limits: { from: '', to: '' },
    });
    const range = (key: string) => ({
        value: ranges[key] ?? emptyRange,
        onChange: (next: DateRangeValue) =>
            setRanges((previous) => ({ ...previous, [key]: next })),
    });
    const labels = { from: demo('from'), to: demo('to') };

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="DateRangeField"
                layout="wide"
                notApplicable={[
                    'placeholder',
                    'readonly',
                    'pending',
                    'loading',
                    'empty',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <DateRangeField
                        name="demo-range-default"
                        label={demo('rangeLabel')}
                        labels={labels}
                        {...range('default')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <DateRangeField
                        name="demo-range-value"
                        label={demo('rangeLabel')}
                        labels={labels}
                        {...range('value')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <DateRangeField
                        name="demo-range-description"
                        label={demo('rangeLabel')}
                        labels={labels}
                        description={demo('rangeDescription')}
                        {...range('description')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <DateRangeField
                        name="demo-range-error"
                        label={demo('rangeLabel')}
                        labels={labels}
                        errors={{ to: demo('rangeErrorTo') }}
                        {...range('error')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <DateRangeField
                        name="demo-range-disabled"
                        label={demo('rangeLabel')}
                        labels={labels}
                        disabled
                        {...range('disabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <DateRangeField
                        name="demo-range-required"
                        label={demo('rangeLabel')}
                        labels={labels}
                        required
                        {...range('required')}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="variants"
                    detail={demo('limitsDetail')}
                    fill
                >
                    <DateRangeField
                        name="demo-range-limits"
                        label={demo('rangeLabel')}
                        labels={labels}
                        min="2026-01-01"
                        max="2026-12-31"
                        {...range('limits')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <DateRangeField
                        name="demo-range-long"
                        label={demo('longRangeLabel')}
                        labels={labels}
                        description={demo('rangeDescription')}
                        {...range('long')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const datesFamily: ShowcaseFamily = {
    id: 'adm-05',
    titleKey: 'admin.designSystem.dates.title',
    descriptionKey: 'admin.designSystem.dates.description',
    Component: DatesSection,
};
