import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from '@/i18n';
import { store } from '@/routes/newsletter';
import { store as storeLocalized } from '@/routes/localized/newsletter';
import { Button } from './button';
import { CheckboxField } from './checkbox-field';
import { Container } from './container';
import { Link } from './link';
import { TextField } from './text-field';

type NewsletterSignupProps = {
    privacyUrl?: string | null;
    /** Locale segment of the current page, or null for the default language. */
    localePrefix: string | null;
};

/**
 * Newsletter sign-up above the public footer, rendered only when the server
 * enables it. The provider sends the confirmation e-mail.
 */
export function NewsletterSignup(props: NewsletterSignupProps) {
    const { newsletter } = usePage().props;

    return newsletter ? <NewsletterForm {...props} /> : null;
}

function NewsletterForm({ privacyUrl, localePrefix }: NewsletterSignupProps) {
    const { t } = useTranslation();
    const [isDone, setIsDone] = useState(false);
    const form = useForm({
        newsletter_email: '',
        newsletter_consent: false,
        website: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(
            localePrefix ? storeLocalized.url(localePrefix) : store.url(),
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    setIsDone(true);
                },
            },
        );
    };

    return (
        <section
            aria-labelledby="newsletter-title"
            className="border-border-subtle bg-surface-subtle border-t py-10"
        >
            <Container>
                <form
                    onSubmit={submit}
                    noValidate
                    className="flex max-w-xl flex-col gap-4"
                >
                    <h2
                        id="newsletter-title"
                        className="text-foreground text-lg font-semibold"
                    >
                        {t('newsletter.title')}
                    </h2>
                    <TextField
                        name="newsletter_email"
                        type="email"
                        autoComplete="email"
                        label={t('newsletter.email')}
                        value={form.data.newsletter_email}
                        onChange={(value) =>
                            form.setData('newsletter_email', value)
                        }
                        error={form.errors.newsletter_email}
                        required
                    />
                    <div hidden>
                        <label htmlFor="newsletter-website">Website</label>
                        <input
                            id="newsletter-website"
                            name="website"
                            tabIndex={-1}
                            autoComplete="off"
                            value={form.data.website}
                            onChange={(event) =>
                                form.setData('website', event.target.value)
                            }
                        />
                    </div>
                    <CheckboxField
                        name="newsletter_consent"
                        label={t('newsletter.consent')}
                        checked={form.data.newsletter_consent}
                        onChange={(checked) =>
                            form.setData('newsletter_consent', checked)
                        }
                        error={form.errors.newsletter_consent}
                        required
                    />
                    {privacyUrl && (
                        <Link href={privacyUrl} tone="muted">
                            {t('newsletter.privacy')}
                        </Link>
                    )}
                    <div>
                        <Button type="submit" isPending={form.processing}>
                            {t('newsletter.submit')}
                        </Button>
                    </div>
                    <p role="status" className="text-muted-foreground text-sm">
                        {isDone ? t('newsletter.done') : ''}
                    </p>
                </form>
            </Container>
        </section>
    );
}
