import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';
import {
    Alert,
    Button,
    Inline,
    Stack,
    Surface,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

type Props = {
    onSuccess: () => void;
};

export default function PasskeyRegistration({ onSuccess }: Props) {
    const { t } = useTranslation();
    const [name, setName] = useState(() => {
        const ua = navigator.userAgent;

        const browser = [
            { pattern: /Edg|Edge/, name: 'Edge' },
            { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
            { pattern: /Firefox|FxiOS/, name: 'Firefox' },
            { pattern: /Chrome|CriOS/, name: 'Chrome' },
            { pattern: /Safari/, name: 'Safari' },
        ].find(({ pattern }) => pattern.test(ua))?.name;

        const os = [
            { pattern: /iPhone/, name: 'iPhone' },
            { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
            { pattern: /Android/, name: 'Android' },
            { pattern: /Mac/, name: 'Mac' },
            { pattern: /Windows/, name: 'Windows' },
        ].find(({ pattern }) => pattern.test(ua))?.name;

        return [browser, os].filter(Boolean).join(' on ') || '';
    });

    const [showForm, setShowForm] = useState(false);
    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess: () => {
            setName('');
            setShowForm(false);
            onSuccess();
        },
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            return;
        }

        await register(name);
    };

    const handleCancel = () => {
        setShowForm(false);
        setName('');
    };

    if (!isSupported) {
        return <Text tone="muted">{t('auth.passkey.notSupported')}</Text>;
    }

    if (!showForm) {
        return (
            <Stack gap="none" align="start">
                <Button onClick={() => setShowForm(true)}>
                    {t('auth.passkey.addPasskey')}
                </Button>
            </Stack>
        );
    }

    return (
        <Surface tone="subtle" padding="compact" border>
            <form onSubmit={handleSubmit}>
                <Stack gap="default">
                    <TextField
                        id="passkey-name"
                        name="passkey-name"
                        label={t('auth.passkey.nameLabel')}
                        value={name}
                        onChange={setName}
                        placeholder={t('auth.passkey.namePlaceholder')}
                        description={t('auth.passkey.nameHelp')}
                        autoFocus
                    />

                    {error && <Alert tone="danger" title={error} />}

                    <Inline gap="tight">
                        <Button
                            type="submit"
                            isPending={isLoading}
                            disabled={!name.trim()}
                        >
                            {isLoading
                                ? t('auth.passkey.registering')
                                : t('auth.passkey.registerPasskey')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={handleCancel}
                        >
                            {t('auth.passkey.cancel')}
                        </Button>
                    </Inline>
                </Stack>
            </form>
        </Surface>
    );
}
