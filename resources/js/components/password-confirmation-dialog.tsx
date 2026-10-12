import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import PasskeyVerify from '@/components/passkey-verify';
import {
    Button,
    Dialog,
    PasswordField,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmWithPasskey,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';

export type PasswordConfirmationDialogProps = {
    open: boolean;
    /** The user dismissed the dialog; the pending visit is dropped. */
    onClose: () => void;
    /** Password or passkey confirmed; the pending visit is retried. */
    onConfirmed: () => void;
    /** Marks the confirmation request so it is not taken for the pending visit. */
    onConfirmingChange: (isConfirming: boolean) => void;
};

/**
 * Password confirmation dialog (password or passkey). Loaded on demand by
 * `PasswordConfirmationModal` on the first 423 response, so the dialog,
 * passkey client and their dependencies stay out of the entry chunk.
 */
export default function PasswordConfirmationDialog({
    open,
    onClose,
    onConfirmed,
    onConfirmingChange,
}: PasswordConfirmationDialogProps) {
    const { t } = useTranslation();
    const passwordInput = useRef<HTMLInputElement>(null);
    const form = useForm({ password: '' });

    useEffect(() => {
        if (open) {
            requestAnimationFrame(() => passwordInput.current?.focus());
        }
    }, [open]);

    function resetForm(): void {
        form.reset();
        form.clearErrors();
    }

    function close(): void {
        resetForm();
        onClose();
    }

    function confirmed(): void {
        resetForm();
        onConfirmed();
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        onConfirmingChange(true);

        form.post(store.url(), {
            headers: {
                Accept: 'application/json',
            },
            onSuccess: confirmed,
            onError: () => passwordInput.current?.focus(),
            onFinish: () => onConfirmingChange(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => !isOpen && close()}
            title={t('auth.confirmPassword.heading')}
            description={t('auth.confirmPassword.subheading')}
            closeLabel={t('actions.close')}
        >
            <Stack gap="default">
                <PasskeyVerify
                    routes={{
                        options: confirmOptions(),
                        submit: confirmWithPasskey(),
                    }}
                    label={t('auth.passkey.confirm')}
                    loadingLabel={t('auth.passkey.confirming')}
                    separator={t('auth.passkey.orPassword')}
                    onSuccess={confirmed}
                />

                <form onSubmit={submit}>
                    <Stack gap="default">
                        <PasswordField
                            id="confirm-password"
                            ref={passwordInput}
                            name="password"
                            label={t('auth.confirmPassword.password')}
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(password) =>
                                form.setData('password', password)
                            }
                            error={form.errors.password}
                            showPasswordLabel={t('auth.passwordField.show')}
                            hidePasswordLabel={t('auth.passwordField.hide')}
                            required
                        />

                        <Button
                            type="submit"
                            isPending={form.processing}
                            disabled={form.processing}
                        >
                            {t('auth.confirmPassword.submit')}
                        </Button>
                    </Stack>
                </form>
            </Stack>
        </Dialog>
    );
}
