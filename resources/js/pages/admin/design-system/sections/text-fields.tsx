import { useState } from 'react';
import {
    DateField,
    NumberField,
    PasswordField,
    Stack,
    TextareaField,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

type DemoValues = Record<string, string>;

/**
 * Local controlled values of every demo field, keyed by cell. The showcase
 * never submits anything; typing only updates this state.
 */
function useDemoValues(initial: DemoValues) {
    const [values, setValues] = useState<DemoValues>(initial);

    return {
        value: (key: string) => values[key] ?? '',
        onChange: (key: string) => (next: string) =>
            setValues((previous) => ({ ...previous, [key]: next })),
    };
}

/** ADM-03 — text fields: every implemented field in its applicable states. */
function TextFieldsSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.textFields.${key}`);
    const polishValue = demo('polishValue');
    const { value, onChange } = useDemoValues({
        textValue: demo('nameValue'),
        textError: demo('invalidEmail'),
        textDisabled: demo('nameValue'),
        textLong: polishValue,
        textareaValue: demo('bioValue'),
        textareaError: demo('bioTooLong'),
        textareaDisabled: demo('bioValue'),
        textareaLong: polishValue,
        passwordValue: 'demo-password',
        passwordError: 'short',
        passwordDisabled: 'demo-password',
        passwordLong: 'Zażółć-gęślą-jaźń',
        numberValue: '129.99',
        numberError: '12500',
        numberDisabled: '129.99',
        numberLong: '1234567.89',
        dateValue: '2026-10-15',
        dateError: '2025-12-31',
        dateDisabled: '2026-10-15',
        dateLong: '2026-12-24',
    });
    const passwordLabels = {
        showPasswordLabel: demo('showPassword'),
        hidePasswordLabel: demo('hidePassword'),
    };

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="TextField"
                notApplicable={['readonly', 'pending', 'loading', 'empty']}
            >
                <ShowcaseState state="default" fill>
                    <TextField
                        name="demo-text-default"
                        label={demo('nameLabel')}
                        value={value('textDefault')}
                        onChange={onChange('textDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <TextField
                        name="demo-text-value"
                        label={demo('nameLabel')}
                        value={value('textValue')}
                        onChange={onChange('textValue')}
                        autoComplete="off"
                    />
                </ShowcaseState>
                <ShowcaseState state="placeholder" fill>
                    <TextField
                        name="demo-text-placeholder"
                        label={demo('nameLabel')}
                        placeholder={demo('namePlaceholder')}
                        value={value('textPlaceholder')}
                        onChange={onChange('textPlaceholder')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <TextField
                        name="demo-text-description"
                        type="email"
                        label={demo('emailLabel')}
                        description={demo('emailDescription')}
                        value={value('textDescription')}
                        onChange={onChange('textDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <TextField
                        name="demo-text-error"
                        type="email"
                        label={demo('emailLabel')}
                        description={demo('emailDescription')}
                        error={demo('emailError')}
                        value={value('textError')}
                        onChange={onChange('textError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <TextField
                        name="demo-text-disabled"
                        label={demo('nameLabel')}
                        disabled
                        value={value('textDisabled')}
                        onChange={onChange('textDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <TextField
                        name="demo-text-required"
                        label={demo('nameLabel')}
                        required
                        value={value('textRequired')}
                        onChange={onChange('textRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <TextField
                        name="demo-text-long"
                        label={demo('longLabel')}
                        description={demo('longDescription')}
                        required
                        value={value('textLong')}
                        onChange={onChange('textLong')}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="variants"
                    detail="url / tel / search"
                    fill
                >
                    <Stack gap="tight">
                        <TextField
                            name="demo-text-url"
                            type="url"
                            label={demo('urlLabel')}
                            placeholder="https://example.com"
                            value={value('textUrl')}
                            onChange={onChange('textUrl')}
                        />
                        <TextField
                            name="demo-text-tel"
                            type="tel"
                            label={demo('telLabel')}
                            placeholder="+48 600 000 000"
                            value={value('textTel')}
                            onChange={onChange('textTel')}
                        />
                        <TextField
                            name="demo-text-search"
                            type="search"
                            label={demo('searchLabel')}
                            value={value('textSearch')}
                            onChange={onChange('textSearch')}
                        />
                    </Stack>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="TextareaField"
                notApplicable={['readonly', 'pending', 'loading', 'empty']}
            >
                <ShowcaseState state="default" fill>
                    <TextareaField
                        name="demo-textarea-default"
                        label={demo('bioLabel')}
                        value={value('textareaDefault')}
                        onChange={onChange('textareaDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <TextareaField
                        name="demo-textarea-value"
                        label={demo('bioLabel')}
                        value={value('textareaValue')}
                        onChange={onChange('textareaValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="placeholder" fill>
                    <TextareaField
                        name="demo-textarea-placeholder"
                        label={demo('bioLabel')}
                        placeholder={demo('bioPlaceholder')}
                        rows={3}
                        value={value('textareaPlaceholder')}
                        onChange={onChange('textareaPlaceholder')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <TextareaField
                        name="demo-textarea-description"
                        label={demo('bioLabel')}
                        description={demo('bioDescription')}
                        value={value('textareaDescription')}
                        onChange={onChange('textareaDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <TextareaField
                        name="demo-textarea-error"
                        label={demo('bioLabel')}
                        description={demo('bioDescription')}
                        error={demo('bioError')}
                        value={value('textareaError')}
                        onChange={onChange('textareaError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <TextareaField
                        name="demo-textarea-disabled"
                        label={demo('bioLabel')}
                        disabled
                        value={value('textareaDisabled')}
                        onChange={onChange('textareaDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <TextareaField
                        name="demo-textarea-required"
                        label={demo('bioLabel')}
                        required
                        value={value('textareaRequired')}
                        onChange={onChange('textareaRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <TextareaField
                        name="demo-textarea-long"
                        label={demo('longLabel')}
                        description={demo('longDescription')}
                        value={value('textareaLong')}
                        onChange={onChange('textareaLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="PasswordField"
                notApplicable={[
                    'placeholder',
                    'readonly',
                    'pending',
                    'loading',
                    'empty',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <PasswordField
                        name="demo-password-default"
                        label={demo('passwordLabel')}
                        autoComplete="new-password"
                        showPasswordLabel={passwordLabels.showPasswordLabel}
                        hidePasswordLabel={passwordLabels.hidePasswordLabel}
                        value={value('passwordDefault')}
                        onChange={onChange('passwordDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="withValue"
                    detail={demo('revealHint')}
                    fill
                >
                    <PasswordField
                        name="demo-password-value"
                        label={demo('passwordLabel')}
                        autoComplete="new-password"
                        showPasswordLabel={passwordLabels.showPasswordLabel}
                        hidePasswordLabel={passwordLabels.hidePasswordLabel}
                        value={value('passwordValue')}
                        onChange={onChange('passwordValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <PasswordField
                        name="demo-password-description"
                        label={demo('passwordLabel')}
                        description={demo('passwordDescription')}
                        autoComplete="new-password"
                        showPasswordLabel={passwordLabels.showPasswordLabel}
                        hidePasswordLabel={passwordLabels.hidePasswordLabel}
                        value={value('passwordDescription')}
                        onChange={onChange('passwordDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <PasswordField
                        name="demo-password-error"
                        label={demo('passwordLabel')}
                        description={demo('passwordDescription')}
                        error={demo('passwordError')}
                        autoComplete="new-password"
                        showPasswordLabel={passwordLabels.showPasswordLabel}
                        hidePasswordLabel={passwordLabels.hidePasswordLabel}
                        value={value('passwordError')}
                        onChange={onChange('passwordError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <PasswordField
                        name="demo-password-disabled"
                        label={demo('passwordLabel')}
                        disabled
                        autoComplete="new-password"
                        showPasswordLabel={passwordLabels.showPasswordLabel}
                        hidePasswordLabel={passwordLabels.hidePasswordLabel}
                        value={value('passwordDisabled')}
                        onChange={onChange('passwordDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <PasswordField
                        name="demo-password-required"
                        label={demo('passwordLabel')}
                        required
                        autoComplete="new-password"
                        showPasswordLabel={passwordLabels.showPasswordLabel}
                        hidePasswordLabel={passwordLabels.hidePasswordLabel}
                        value={value('passwordRequired')}
                        onChange={onChange('passwordRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <PasswordField
                        name="demo-password-long"
                        label={demo('longPasswordLabel')}
                        description={demo('longDescription')}
                        autoComplete="new-password"
                        showPasswordLabel={passwordLabels.showPasswordLabel}
                        hidePasswordLabel={passwordLabels.hidePasswordLabel}
                        value={value('passwordLong')}
                        onChange={onChange('passwordLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="NumberField"
                notApplicable={['readonly', 'pending', 'loading', 'empty']}
            >
                <ShowcaseState state="default" fill>
                    <NumberField
                        name="demo-number-default"
                        label={demo('priceLabel')}
                        step={0.01}
                        value={value('numberDefault')}
                        onChange={onChange('numberDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <NumberField
                        name="demo-number-value"
                        label={demo('priceLabel')}
                        step={0.01}
                        value={value('numberValue')}
                        onChange={onChange('numberValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="placeholder" fill>
                    <NumberField
                        name="demo-number-placeholder"
                        label={demo('quantityLabel')}
                        placeholder="1"
                        min={1}
                        step={1}
                        value={value('numberPlaceholder')}
                        onChange={onChange('numberPlaceholder')}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="withDescription"
                    detail="min 0 · max 10000 · step 0.01"
                    fill
                >
                    <NumberField
                        name="demo-number-description"
                        label={demo('priceLabel')}
                        description={demo('priceDescription')}
                        min={0}
                        max={10000}
                        step={0.01}
                        value={value('numberDescription')}
                        onChange={onChange('numberDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <NumberField
                        name="demo-number-error"
                        label={demo('priceLabel')}
                        description={demo('priceDescription')}
                        error={demo('priceError')}
                        min={0}
                        max={10000}
                        step={0.01}
                        value={value('numberError')}
                        onChange={onChange('numberError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <NumberField
                        name="demo-number-disabled"
                        label={demo('priceLabel')}
                        disabled
                        step={0.01}
                        value={value('numberDisabled')}
                        onChange={onChange('numberDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <NumberField
                        name="demo-number-required"
                        label={demo('quantityLabel')}
                        required
                        min={1}
                        step={1}
                        value={value('numberRequired')}
                        onChange={onChange('numberRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <NumberField
                        name="demo-number-long"
                        label={demo('longNumberLabel')}
                        description={demo('longDescription')}
                        step="any"
                        value={value('numberLong')}
                        onChange={onChange('numberLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="DateField"
                notApplicable={[
                    'placeholder',
                    'readonly',
                    'pending',
                    'loading',
                    'empty',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <DateField
                        name="demo-date-default"
                        label={demo('dateLabel')}
                        value={value('dateDefault')}
                        onChange={onChange('dateDefault')}
                    />
                </ShowcaseState>
                <ShowcaseState state="withValue" fill>
                    <DateField
                        name="demo-date-value"
                        label={demo('dateLabel')}
                        value={value('dateValue')}
                        onChange={onChange('dateValue')}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="withDescription"
                    detail="min 2026-01-01 · max 2026-12-31"
                    fill
                >
                    <DateField
                        name="demo-date-description"
                        label={demo('dateLabel')}
                        description={demo('dateDescription')}
                        min="2026-01-01"
                        max="2026-12-31"
                        value={value('dateDescription')}
                        onChange={onChange('dateDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <DateField
                        name="demo-date-error"
                        label={demo('dateLabel')}
                        description={demo('dateDescription')}
                        error={demo('dateError')}
                        min="2026-01-01"
                        max="2026-12-31"
                        value={value('dateError')}
                        onChange={onChange('dateError')}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <DateField
                        name="demo-date-disabled"
                        label={demo('dateLabel')}
                        disabled
                        value={value('dateDisabled')}
                        onChange={onChange('dateDisabled')}
                    />
                </ShowcaseState>
                <ShowcaseState state="required" fill>
                    <DateField
                        name="demo-date-required"
                        label={demo('dateLabel')}
                        required
                        value={value('dateRequired')}
                        onChange={onChange('dateRequired')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <DateField
                        name="demo-date-long"
                        label={demo('longDateLabel')}
                        description={demo('longDescription')}
                        value={value('dateLong')}
                        onChange={onChange('dateLong')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const textFieldsFamily: ShowcaseFamily = {
    id: 'adm-03',
    titleKey: 'admin.designSystem.textFields.title',
    descriptionKey: 'admin.designSystem.textFields.description',
    Component: TextFieldsSection,
};
