import { useEffect, useRef, useState } from 'react';
import {
    Alert,
    Button,
    ContactSection,
    type ContactSectionErrors,
    type ContactSectionProps,
    type ContactSectionValues,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from '../../admin/design-system/sections/showcase';

const emptyValues: ContactSectionValues = { name: '', email: '', message: '' };

type DemoStatus = 'editing' | 'pending' | 'sent';

/** WEB-05 — the contact form in every state, without any request. */
function ContactFamilySection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.web.contact.${key}`);
    const [values, setValues] = useState<ContactSectionValues>(emptyValues);
    const [errors, setErrors] = useState<ContactSectionErrors>({});
    const [status, setStatus] = useState<DemoStatus>('editing');
    const [isStaticErrorsShown, setStaticErrorsShown] = useState(false);
    const timer = useRef<number | null>(null);

    useEffect(
        () => () => {
            if (timer.current !== null) {
                window.clearTimeout(timer.current);
            }
        },
        [],
    );

    const labels: ContactSectionProps['labels'] = {
        name: t('contact.fields.name'),
        email: t('contact.fields.email'),
        message: t('contact.fields.message'),
    };
    const common = {
        title: t('contact.title'),
        description: t('contact.description'),
        labels,
        errorSummaryTitle: t('contact.errorSummaryTitle'),
        submitLabel: t('contact.submit'),
        onChange: () => {},
        onSubmit: () => {},
    };
    const success = (
        <Alert
            tone="success"
            title={t('contact.successTitle')}
            description={t('contact.successDescription')}
        />
    );
    const filled: ContactSectionValues = {
        name: 'Łucja Żółtowska-Wiśniewska',
        email: 'lucja@example.test',
        message: t('admin.designSystem.web.longText'),
    };

    /** Local validation standing in for the backend's 422 response. */
    const submit = () => {
        const nextErrors: ContactSectionErrors = {};

        if (values.name.trim() === '') {
            nextErrors.name = demo('nameRequired');
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) {
            nextErrors.email = demo('emailInvalid');
        }
        if (values.message.trim().length < 10) {
            nextErrors.message = demo('messageTooShort');
        }

        setErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        setStatus('pending');
        timer.current = window.setTimeout(() => setStatus('sent'), 1500);
    };

    const reset = () => {
        setValues(emptyValues);
        setErrors({});
        setStatus('editing');
    };

    return (
        <ShowcaseComponent
            name="ContactSection · ContactForm"
            layout="wide"
            notApplicable={['loading', 'disabled', 'noMedia']}
        >
            <ShowcaseState
                state="focusFirstError"
                detail={demo('liveDemo')}
                fill
            >
                <ContactSection
                    {...common}
                    values={values}
                    onChange={setValues}
                    onSubmit={submit}
                    errors={errors}
                    isPending={status === 'pending'}
                    success={
                        status === 'sent' ? (
                            <Stack gap="tight" align="start">
                                {success}
                                <Button variant="outline" onClick={reset}>
                                    {demo('reset')}
                                </Button>
                            </Stack>
                        ) : undefined
                    }
                />
            </ShowcaseState>
            <ShowcaseState state="error" detail={demo('fieldErrors')} fill>
                {/* Errors on first render would move focus on page load
                    (ErrorSummary focuses the first invalid field), so the
                    static error state is shown on request. */}
                <Stack gap="tight" align="start">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setStaticErrorsShown((shown) => !shown)}
                    >
                        {isStaticErrorsShown
                            ? demo('hideErrors')
                            : demo('showErrors')}
                    </Button>
                </Stack>
                <ContactSection
                    {...common}
                    values={{ ...emptyValues, email: 'lucja@' }}
                    errors={
                        isStaticErrorsShown
                            ? {
                                  name: demo('nameRequired'),
                                  email: demo('emailInvalid'),
                                  message: demo('messageTooShort'),
                              }
                            : {}
                    }
                />
            </ShowcaseState>
            <ShowcaseState state="formError" fill>
                <ContactSection
                    {...common}
                    values={filled}
                    formError={t('contact.tooManyAttempts')}
                />
            </ShowcaseState>
            <ShowcaseState state="pending" fill>
                <ContactSection {...common} values={filled} isPending />
            </ShowcaseState>
            <ShowcaseState state="success" fill>
                <ContactSection
                    {...common}
                    values={emptyValues}
                    success={success}
                />
            </ShowcaseState>
            <ShowcaseState state="longContent" fill>
                <ContactSection
                    {...common}
                    title={t('admin.designSystem.web.longName')}
                    values={filled}
                    formError={t('contact.genericError')}
                />
            </ShowcaseState>
        </ShowcaseComponent>
    );
}

export const contactFamily: ShowcaseFamily = {
    id: 'web-05',
    titleKey: 'admin.designSystem.web.contact.title',
    descriptionKey: 'admin.designSystem.web.contact.description',
    Component: ContactFamilySection,
};
