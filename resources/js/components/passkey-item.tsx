import { KeyRound, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    Badge,
    ConfirmDialog,
    Icon,
    IconButton,
    Inline,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import type { Passkey } from '@/types/auth';

type Props = {
    passkey: Passkey;
    onDelete: (id: number, onError: () => void) => void;
};

export default function PasskeyItem({ passkey, onDelete }: Props) {
    const { t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        setIsDeleting(true);
        onDelete(passkey.id, () => setIsDeleting(false));
    };

    return (
        <Inline justify="between" align="center">
            <Inline gap="default" align="center">
                <Icon icon={KeyRound} tone="muted" />
                <Stack gap="none">
                    <Inline gap="tight" align="center" wrap>
                        <Text as="span" variant="label">
                            {passkey.name}
                        </Text>
                        {passkey.authenticator && (
                            <Badge tone="outline">
                                {passkey.authenticator}
                            </Badge>
                        )}
                    </Inline>
                    <Text variant="caption">
                        {t('auth.passkey.added', {
                            date: passkey.created_at_diff,
                        })}
                        {passkey.last_used_at_diff &&
                            ` / ${t('auth.passkey.lastUsed', {
                                date: passkey.last_used_at_diff,
                            })}`}
                    </Text>
                </Stack>
            </Inline>

            <IconButton
                icon={Trash2}
                variant="ghost"
                size="sm"
                ariaLabel={t('auth.passkey.remove')}
                onClick={() => setIsOpen(true)}
            />

            <ConfirmDialog
                open={isOpen}
                onOpenChange={setIsOpen}
                tone="destructive"
                title={t('auth.passkey.removeTitle')}
                description={t('auth.passkey.removeDescription', {
                    name: passkey.name,
                })}
                confirmLabel={
                    isDeleting
                        ? t('auth.passkey.removing')
                        : t('auth.passkey.removeSubmit')
                }
                cancelLabel={t('auth.passkey.cancel')}
                closeLabel={t('actions.close')}
                onConfirm={handleDelete}
                isPending={isDeleting}
            />
        </Inline>
    );
}
