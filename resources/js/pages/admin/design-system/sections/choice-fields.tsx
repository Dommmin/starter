import { useState } from 'react';
import {
    CheckboxField,
    CheckboxGroupField,
    MultiSelectField,
    RadioGroupField,
    SelectField,
    Stack,
    SwitchField,
    type CheckboxGroupOption,
    type MultiSelectOption,
    type RadioGroupOption,
    type SelectFieldOption,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { multiSelectLabels } from '@/lib/multi-select-labels';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

/**
 * Local controlled values of every demo control, keyed by cell. Nothing is
 * submitted; choosing an option only updates this state.
 */
function useDemoChoices<Value>(initial: Record<string, Value>, empty: Value) {
    const [values, setValues] = useState(initial);

    return {
        value: (key: string): Value => values[key] ?? empty,
        onChange: (key: string) => (next: Value) =>
            setValues((previous) => ({ ...previous, [key]: next })),
    };
}

/** ADM-04 — value choice: every implemented control in its applicable states. */
function ChoiceFieldsSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.choiceFields.${key}`);
    const checks = useDemoChoices<boolean>(
        {
            checkboxValue: true,
            checkboxDisabledChecked: true,
            checkboxLong: true,
            switchValue: true,
            switchDisabledOn: true,
            switchLong: true,
        },
        false,
    );
    const choices = useDemoChoices<string>(
        {
            selectValue: 'review',
            selectDisabled: 'published',
            selectLong: 'pl',
            radioValue: 'private',
            radioDisabled: 'public',
            radioLong: 'long',
        },
        '',
    );

    const statusOptions: SelectFieldOption[] = [
        { value: 'draft', label: demo('statusDraft') },
        { value: 'review', label: demo('statusReview') },
        { value: 'published', label: demo('statusPublished') },
        { value: 'archived', label: demo('statusArchived'), disabled: true },
    ];
    const languageOptions: SelectFieldOption[] = [
        { value: 'pl', label: demo('languagePolish') },
        { value: 'en', label: demo('languageEnglish') },
        { value: 'de', label: demo('languageGerman') },
    ];
    const visibilityOptions: RadioGroupOption[] = [
        {
            value: 'public',
            label: demo('visibilityPublic'),
            description: demo('visibilityPublicDescription'),
        },
        {
            value: 'private',
            label: demo('visibilityPrivate'),
            description: demo('visibilityPrivateDescription'),
        },
        {
            value: 'scheduled',
            label: demo('visibilityScheduled'),
            description: demo('visibilityScheduledDescription'),
            disabled: true,
        },
    ];
    const groups = useDemoChoices<string[]>(
        {
            groupValue: ['email', 'sms'],
            groupDisabled: ['email'],
            groupLong: ['email'],
        },
        [],
    );
    const channelOptions: CheckboxGroupOption[] = [
        { value: 'email', label: demo('channelEmail') },
        {
            value: 'sms',
            label: demo('channelSms'),
            description: demo('channelSmsDescription'),
        },
        { value: 'push', label: demo('channelPush'), disabled: true },
    ];
    const offices = useDemoChoices<string[]>(
        {
            officesValue: ['waw', 'krk'],
            officesDisabled: ['waw'],
            officesError: ['waw', 'krk', 'gdn', 'wro'],
            officesLong: ['long', 'waw'],
        },
        [],
    );
    const officeOptions: MultiSelectOption[] = [
        { value: 'waw', label: demo('officeWarsaw') },
        { value: 'krk', label: demo('officeKrakow') },
        { value: 'gdn', label: demo('officeGdansk') },
        { value: 'wro', label: demo('officeWroclaw') },
        { value: 'poz', label: demo('officePoznan'), disabled: true },
        { value: 'ber', label: demo('officeBerlin') },
        { value: 'long', label: demo('officeLong') },
    ];
    const officeLabels = multiSelectLabels(t);
    const plainVisibilityOptions: RadioGroupOption[] = visibilityOptions.map(
        (option) => ({ value: option.value, label: option.label }),
    );
    const longRadioOptions: RadioGroupOption[] = [
        {
            value: 'long',
            label: demo('longRadioOption'),
            description: demo('longDescription'),
        },
        { value: 'short', label: demo('visibilityPrivate') },
    ];

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="CheckboxField"
                notApplicable={[
                    'placeholder',
                    'readonly',
                    'indeterminate',
                    'pending',
                    'loading',
                    'empty',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <CheckboxField
                        name="demo-checkbox-default"
                        label={demo('newsletterLabel')}
                        checked={checks.value('checkboxDefault')}
                        onChange={checks.onChange('checkboxDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <CheckboxField
                        name="demo-checkbox-value"
                        label={demo('newsletterLabel')}
                        checked={checks.value('checkboxValue')}
                        onChange={checks.onChange('checkboxValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <CheckboxField
                        name="demo-checkbox-description"
                        label={demo('newsletterLabel')}
                        description={demo('newsletterDescription')}
                        checked={checks.value('checkboxDescription')}
                        onChange={checks.onChange('checkboxDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <CheckboxField
                        name="demo-checkbox-error"
                        label={demo('termsLabel')}
                        error={demo('termsError')}
                        required
                        checked={checks.value('checkboxError')}
                        onChange={checks.onChange('checkboxError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <Stack gap="tight">
                        <CheckboxField
                            name="demo-checkbox-disabled"
                            label={demo('newsletterLabel')}
                            disabled
                            checked={checks.value('checkboxDisabled')}
                            onChange={checks.onChange('checkboxDisabled')}
                        />
                        <CheckboxField
                            name="demo-checkbox-disabled-checked"
                            label={demo('termsLabel')}
                            disabled
                            checked={checks.value('checkboxDisabledChecked')}
                            onChange={checks.onChange(
                                'checkboxDisabledChecked',
                            )}
                        />
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <CheckboxField
                        name="demo-checkbox-required"
                        label={demo('termsLabel')}
                        required
                        checked={checks.value('checkboxRequired')}
                        onChange={checks.onChange('checkboxRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <CheckboxField
                        name="demo-checkbox-long"
                        label={demo('longCheckboxLabel')}
                        description={demo('longDescription')}
                        required
                        checked={checks.value('checkboxLong')}
                        onChange={checks.onChange('checkboxLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="SelectField"
                notApplicable={['readonly', 'pending', 'loading', 'empty']}
            >
                <ShowcaseState state="default" fill>
                    <SelectField
                        name="demo-select-default"
                        label={demo('statusLabel')}
                        options={statusOptions}
                        value={choices.value('selectDefault')}
                        onChange={choices.onChange('selectDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <SelectField
                        name="demo-select-value"
                        label={demo('statusLabel')}
                        options={statusOptions}
                        value={choices.value('selectValue')}
                        onChange={choices.onChange('selectValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="placeholder" fill>
                    <SelectField
                        name="demo-select-placeholder"
                        label={demo('statusLabel')}
                        placeholder={demo('statusPlaceholder')}
                        options={statusOptions}
                        value={choices.value('selectPlaceholder')}
                        onChange={choices.onChange('selectPlaceholder')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <SelectField
                        name="demo-select-description"
                        label={demo('statusLabel')}
                        placeholder={demo('statusPlaceholder')}
                        description={demo('statusDescription')}
                        options={statusOptions}
                        value={choices.value('selectDescription')}
                        onChange={choices.onChange('selectDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <SelectField
                        name="demo-select-error"
                        label={demo('statusLabel')}
                        placeholder={demo('statusPlaceholder')}
                        description={demo('statusDescription')}
                        error={demo('statusError')}
                        required
                        options={statusOptions}
                        value={choices.value('selectError')}
                        onChange={choices.onChange('selectError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <SelectField
                        name="demo-select-disabled"
                        label={demo('statusLabel')}
                        disabled
                        options={statusOptions}
                        value={choices.value('selectDisabled')}
                        onChange={choices.onChange('selectDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <SelectField
                        name="demo-select-required"
                        label={demo('statusLabel')}
                        placeholder={demo('statusPlaceholder')}
                        required
                        options={statusOptions}
                        value={choices.value('selectRequired')}
                        onChange={choices.onChange('selectRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <SelectField
                        name="demo-select-long"
                        label={demo('longSelectLabel')}
                        description={demo('longDescription')}
                        options={languageOptions}
                        value={choices.value('selectLong')}
                        onChange={choices.onChange('selectLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="RadioGroupField"
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
                    <RadioGroupField
                        name="demo-radio-default"
                        label={demo('visibilityLabel')}
                        options={plainVisibilityOptions}
                        value={choices.value('radioDefault')}
                        onChange={choices.onChange('radioDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <RadioGroupField
                        name="demo-radio-value"
                        label={demo('visibilityLabel')}
                        options={plainVisibilityOptions}
                        value={choices.value('radioValue')}
                        onChange={choices.onChange('radioValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <RadioGroupField
                        name="demo-radio-description"
                        label={demo('visibilityLabel')}
                        description={demo('visibilityDescription')}
                        options={visibilityOptions}
                        value={choices.value('radioDescription')}
                        onChange={choices.onChange('radioDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <RadioGroupField
                        name="demo-radio-error"
                        label={demo('visibilityLabel')}
                        error={demo('visibilityError')}
                        required
                        options={plainVisibilityOptions}
                        value={choices.value('radioError')}
                        onChange={choices.onChange('radioError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <RadioGroupField
                        name="demo-radio-disabled"
                        label={demo('visibilityLabel')}
                        disabled
                        options={plainVisibilityOptions}
                        value={choices.value('radioDisabled')}
                        onChange={choices.onChange('radioDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <RadioGroupField
                        name="demo-radio-required"
                        label={demo('visibilityLabel')}
                        required
                        options={plainVisibilityOptions}
                        value={choices.value('radioRequired')}
                        onChange={choices.onChange('radioRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <RadioGroupField
                        name="demo-radio-long"
                        label={demo('longRadioLabel')}
                        options={longRadioOptions}
                        value={choices.value('radioLong')}
                        onChange={choices.onChange('radioLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="CheckboxGroupField"
                layout="wide"
                notApplicable={[
                    'placeholder',
                    'readonly',
                    'indeterminate',
                    'pending',
                    'loading',
                    'empty',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <CheckboxGroupField
                        name="demo-group-default"
                        label={demo('channelsLabel')}
                        options={channelOptions}
                        value={groups.value('groupDefault')}
                        onChange={groups.onChange('groupDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <CheckboxGroupField
                        name="demo-group-value"
                        label={demo('channelsLabel')}
                        options={channelOptions}
                        value={groups.value('groupValue')}
                        onChange={groups.onChange('groupValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <CheckboxGroupField
                        name="demo-group-description"
                        label={demo('channelsLabel')}
                        description={demo('channelsDescription')}
                        options={channelOptions}
                        value={groups.value('groupDescription')}
                        onChange={groups.onChange('groupDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <CheckboxGroupField
                        name="demo-group-error"
                        label={demo('channelsLabel')}
                        error={demo('channelsError')}
                        required
                        options={channelOptions}
                        value={groups.value('groupError')}
                        onChange={groups.onChange('groupError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <CheckboxGroupField
                        name="demo-group-disabled"
                        label={demo('channelsLabel')}
                        disabled
                        options={channelOptions}
                        value={groups.value('groupDisabled')}
                        onChange={groups.onChange('groupDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <CheckboxGroupField
                        name="demo-group-required"
                        label={demo('channelsLabel')}
                        required
                        options={channelOptions}
                        value={groups.value('groupRequired')}
                        onChange={groups.onChange('groupRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <CheckboxGroupField
                        name="demo-group-long"
                        label={demo('longChannelsLabel')}
                        description={demo('longDescription')}
                        options={channelOptions}
                        value={groups.value('groupLong')}
                        onChange={groups.onChange('groupLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="MultiSelectField"
                layout="wide"
                notApplicable={[
                    'readonly',
                    'indeterminate',
                    'pending',
                    'loading',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <MultiSelectField
                        name="demo-offices-default"
                        label={demo('officesLabel')}
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesDefault')}
                        onChange={offices.onChange('officesDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="placeholder" fill>
                    <MultiSelectField
                        name="demo-offices-placeholder"
                        label={demo('officesLabel')}
                        placeholder={demo('officesPlaceholder')}
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesPlaceholder')}
                        onChange={offices.onChange('officesPlaceholder')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <MultiSelectField
                        name="demo-offices-value"
                        label={demo('officesLabel')}
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesValue')}
                        onChange={offices.onChange('officesValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <MultiSelectField
                        name="demo-offices-description"
                        label={demo('officesLabel')}
                        description={demo('officesDescription')}
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesDescription')}
                        onChange={offices.onChange('officesDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <MultiSelectField
                        name="demo-offices-error"
                        label={demo('officesLabel')}
                        error={demo('officesError')}
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesError')}
                        onChange={offices.onChange('officesError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <MultiSelectField
                        name="demo-offices-disabled"
                        label={demo('officesLabel')}
                        disabled
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesDisabled')}
                        onChange={offices.onChange('officesDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <MultiSelectField
                        name="demo-offices-required"
                        label={demo('officesLabel')}
                        required
                        max={3}
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesRequired')}
                        onChange={offices.onChange('officesRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="variants"
                    detail={demo('officesMaxDetail')}
                    fill
                >
                    <MultiSelectField
                        name="demo-offices-max"
                        label={demo('officesLabel')}
                        max={2}
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesValue')}
                        onChange={offices.onChange('officesValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="empty" fill>
                    <MultiSelectField
                        name="demo-offices-empty"
                        label={demo('officesLabel')}
                        options={[]}
                        labels={officeLabels}
                        value={offices.value('officesEmpty')}
                        onChange={offices.onChange('officesEmpty')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <MultiSelectField
                        name="demo-offices-long"
                        label={demo('longOfficesLabel')}
                        description={demo('longDescription')}
                        options={officeOptions}
                        labels={officeLabels}
                        value={offices.value('officesLong')}
                        onChange={offices.onChange('officesLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="SwitchField"
                notApplicable={[
                    'placeholder',
                    'required',
                    'readonly',
                    'pending',
                    'loading',
                    'empty',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <SwitchField
                        name="demo-switch-default"
                        label={demo('maintenanceLabel')}
                        checked={checks.value('switchDefault')}
                        onChange={checks.onChange('switchDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <SwitchField
                        name="demo-switch-value"
                        label={demo('maintenanceLabel')}
                        checked={checks.value('switchValue')}
                        onChange={checks.onChange('switchValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <SwitchField
                        name="demo-switch-description"
                        label={demo('maintenanceLabel')}
                        description={demo('maintenanceDescription')}
                        checked={checks.value('switchDescription')}
                        onChange={checks.onChange('switchDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <SwitchField
                        name="demo-switch-error"
                        label={demo('maintenanceLabel')}
                        description={demo('maintenanceDescription')}
                        error={demo('maintenanceError')}
                        checked={checks.value('switchError')}
                        onChange={checks.onChange('switchError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <Stack gap="tight">
                        <SwitchField
                            name="demo-switch-disabled"
                            label={demo('maintenanceLabel')}
                            disabled
                            checked={checks.value('switchDisabled')}
                            onChange={checks.onChange('switchDisabled')}
                        />
                        <SwitchField
                            name="demo-switch-disabled-on"
                            label={demo('maintenanceLabel')}
                            disabled
                            checked={checks.value('switchDisabledOn')}
                            onChange={checks.onChange('switchDisabledOn')}
                        />
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <SwitchField
                        name="demo-switch-long"
                        label={demo('longSwitchLabel')}
                        description={demo('longDescription')}
                        checked={checks.value('switchLong')}
                        onChange={checks.onChange('switchLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const choiceFieldsFamily: ShowcaseFamily = {
    id: 'adm-04',
    titleKey: 'admin.designSystem.choiceFields.title',
    descriptionKey: 'admin.designSystem.choiceFields.description',
    Component: ChoiceFieldsSection,
};
