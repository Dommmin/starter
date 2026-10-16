import { useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Alert, ContactSection } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { store as contactStore } from '@/routes/contact';
import { store as localizedContactStore } from '@/routes/localized/contact';

type ContactFormProps = {
    /** Server-issued form state (encrypted render-time token). */
    contactForm: App.Data.Contact.ContactFormData;
    /** Short label above the heading. */
    eyebrow?: string;
    /** Section heading; the translated default when omitted. */
    title?: string;
    description?: string | null;
    /** Supporting content beside the form, see `ContactSection`. */
    aside?: ReactNode;
    /** Band background, see `ContactSection`. */
    tone?: 'default' | 'subtle';
};

type ContactFormFields = {
    name: string;
    email: string;
    message: string;
    website: string;
    form_token: string;
    form: string | null;
    fields: Record<string, string | boolean>;
};

type ExtraField = App.Data.Contact.PublicContactFieldData;

function emptyExtraValues(
    fields: ExtraField[],
): Record<string, string | boolean> {
    return Object.fromEntries(
        fields.map((field) => [
            field.key,
            field.type === 'checkbox' ? false : '',
        ]),
    );
}

const HONEYPOT_FIELD = 'website';

/**
 * Public contact form wired to `contact.store` in the current public locale.
 * Field errors come from the backend (422 → redirect with errors); a rate
 * limit (429) or another HTTP failure shows a form-level alert instead of
 * the Inertia error modal. The response never reveals delivery status.
 */
export function ContactForm({
    contactForm,
    eyebrow,
    title,
    description,
    aside,
    tone,
}: ContactFormProps) {
    const { t, locale, defaultLocale } = useTranslation();
    const [isSent, setIsSent] = useState(false);
    const [requestError, setRequestError] = useState<string | undefined>();
    const extraFields = contactForm.form?.fields ?? [];
    const form = useForm<ContactFormFields>({
        name: '',
        email: '',
        message: '',
        website: '',
        form_token: contactForm.token,
        form: contactForm.form?.key ?? null,
        fields: emptyExtraValues(extraFields),
    });
    const errors = form.errors as Partial<Record<string, string>>;

    function setExtraValue(key: string, value: string | boolean) {
        form.setData((data) => ({
            ...data,
            fields: { ...data.fields, [key]: value },
        }));
    }

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
            eyebrow={eyebrow}
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
            extraFields={extraFields.map((field) =>
                field.type === 'checkbox'
                    ? {
                          name: `fields[${field.key}]`,
                          type: 'checkbox' as const,
                          label: field.label,
                          required: field.required,
                          value: form.data.fields[field.key] === true,
                          error: errors[`fields.${field.key}`],
                          onChange: (value: boolean) =>
                              setExtraValue(field.key, value),
                      }
                    : {
                          name: `fields[${field.key}]`,
                          type: field.type,
                          label: field.label,
                          required: field.required,
                          value: String(form.data.fields[field.key] ?? ''),
                          error: errors[`fields.${field.key}`],
                          options: field.options,
                          placeholder: t('contact.selectPlaceholder'),
                          onChange: (value: string) =>
                              setExtraValue(field.key, value),
                      },
            )}
            onSubmit={submit}
            errors={{
                name: form.errors.name,
                email: form.errors.email,
                message: form.errors.message,
            }}
            formError={requestError ?? form.errors.form_token ?? errors.form}
            spamTrap={{
                name: HONEYPOT_FIELD,
                label: t('contact.honeypotLabel'),
                value: form.data.website,
                onChange: (value) => form.setData('website', value),
            }}
            aside={aside}
            tone={tone}
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
