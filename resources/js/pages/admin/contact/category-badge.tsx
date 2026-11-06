import { Badge } from '@/design-system/primitives';
import { type TranslationFunction, useTranslation } from '@/i18n';

type Category = App.Enums.ContactMessageCategory;

const categoryTone = {
    lead: 'primary',
    question: 'outline',
    spam: 'danger',
    other: 'neutral',
} as const satisfies Record<Category, string>;

/**
 * Translated labels of the AI categories. Flat keys: a nested group with an
 * `other` key would be read as a plural group.
 */
export function contactCategoryLabels(
    t: TranslationFunction,
): Record<Category, string> {
    return {
        lead: t('admin.contact.categoryLead'),
        question: t('admin.contact.categoryQuestion'),
        spam: t('admin.contact.categorySpam'),
        other: t('admin.contact.categoryOther'),
    };
}

/**
 * AI category of a contact message; a dash until it is classified.
 */
export function ContactCategoryBadge({
    category,
}: {
    category: Category | null;
}) {
    const { t } = useTranslation();

    if (category === null) {
        return '—';
    }

    return (
        <Badge tone={categoryTone[category]}>
            {contactCategoryLabels(t)[category]}
        </Badge>
    );
}
