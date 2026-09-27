import { Badge } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

const statusTone = {
    pending: 'neutral',
    sent: 'success',
    failed: 'danger',
} as const satisfies Record<App.Enums.ContactMessageStatus, string>;

export function ContactStatusBadge({
    status,
}: {
    status: App.Enums.ContactMessageStatus;
}) {
    const { t } = useTranslation();

    return (
        <Badge tone={statusTone[status]}>
            {t(`admin.contact.statuses.${status}`)}
        </Badge>
    );
}
