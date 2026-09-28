import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, ContactSection } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { store as contactStore } from '@/routes/contact';
import { store as localizedContactStore } from '@/routes/localized/contact';

type ContactFormProps = {
    /** Server-issued form state (encrypted render-time token). */
    contactForm: App.Data.Contact.ContactFormData;
    /** Section heading; the translated default when omitted. */
    title?: string;
    description?: string | null;
};

type ContactFormFields = {
    name: string;
    email: string;
    message: string;
    website: string;
    form_token: string;
};

const HONEYPOT_FIELD = 'website';

/**
 * Public contact form wired to `contact.store` in the current public locale.
 * Field errors come from the backend (422 → redirect with errors); a rate
 * limit (429) or another HTTP failure shows a form-level alert instead of
 * the Inertia error modal. The response never reveals delivery status.
 */
export function ContactForm({
    contactForm,
    title,
    description,
}: ContactFormProps) {
    const { t, locale, defaultLocale } = useTranslation();
    const [isSent, setIsSent] = useState(false);
    const [requestError, setRequestError] = useState<string | undefined>();
    const form = useForm<ContactFormFields>({
        name: '',
        email: '',
        message: '',
        website: '',
        form_token: contactForm.token,
    });

    function submit() {
        setRequestError(undefined);
        // Direct route imports keep the public page chunk small (bundle budget).
        const route =
            locale === defaultLocale
                ? contactStore()
                : localizedContactStore({ locale });

        form.submit(route, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setIsSent(true);
                form.reset();
            },
            onHttpException: (response) => {
                setRequestError(
                    response.status === 429
                        ? t('contact.tooManyAttempts')
                        : t('contact.genericError'),
                );

                return false;
            },
            onNetworkError: () => {
                setRequestError(t('contact.genericError'));

                return false;
            },
        });
    }

    return (
        <ContactSection
            title={title ?? t('contact.title')}
            description={
                description === undefined
                    ? t('contact.description')
                    : (description ?? undefined)
            }
            labels={{
                name: t('contact.fields.name'),
                email: t('contact.fields.email'),
                message: t('contact.fields.message'),
            }}
            errorSummaryTitle={t('contact.errorSummaryTitle')}
            values={{
                name: form.data.name,
                email: form.data.email,
                message: form.data.message,
            }}
            onChange={(values) =>
                form.setData((data) => ({ ...data, ...values }))
            }
            onSubmit={submit}
            errors={{
                name: form.errors.name,
                email: form.errors.email,
                message: form.errors.message,
            }}
            formError={requestError ?? form.errors.form_token}
            spamTrap={{
                name: HONEYPOT_FIELD,
                label: t('contact.honeypotLabel'),
                value: form.data.website,
                onChange: (value) => form.setData('website', value),
            }}
            submitLabel={t('contact.submit')}
            isPending={form.processing}
            success={
                isSent ? (
                    <Alert
                        tone="success"
                        title={t('contact.successTitle')}
                        description={t('contact.successDescription')}
                    />
                ) : undefined
            }
        />
    );
}
