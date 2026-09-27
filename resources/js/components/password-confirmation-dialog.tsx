import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button, Stack } from '@/design-system/primitives';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
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
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirm password</DialogTitle>
                    <DialogDescription>
                        Confirm your password to continue with this action.
                    </DialogDescription>
                </DialogHeader>

                <PasskeyVerify
                    routes={{
                        options: confirmOptions(),
                        submit: confirmWithPasskey(),
                    }}
                    label="Confirm with passkey"
                    loadingLabel="Confirming..."
                    separator="Or confirm with password"
                    onSuccess={confirmed}
                />

                <form onSubmit={submit}>
                    <Stack gap="default">
                        <Stack gap="tight">
                            <Label htmlFor="confirm-password">Password</Label>
                            <PasswordInput
                                id="confirm-password"
                                ref={passwordInput}
                                name="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                aria-describedby={
                                    form.errors.password
                                        ? 'confirm-password-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="confirm-password-error"
                                message={form.errors.password}
                            />
                        </Stack>

                        <Button
                            type="submit"
                            isPending={form.processing}
                            disabled={form.processing}
                        >
                            Confirm password
                        </Button>
                    </Stack>
                </form>
            </DialogContent>
        </Dialog>
    );
}
