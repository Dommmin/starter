import type { PendingVisit } from '@inertiajs/core';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
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

type Props = {
    children: React.ReactNode;
};

export default function PasswordConfirmationModal({ children }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const pendingVisit = useRef<PendingVisit | null>(null);
    const isConfirming = useRef(false);
    const passwordInput = useRef<HTMLInputElement>(null);
    const form = useForm({ password: '' });

    useEffect(() => {
        const removeBeforeListener = router.on('before', (event) => {
            if (!isConfirming.current) {
                pendingVisit.current = event.detail.visit;
            }
        });

        const removeHttpExceptionListener = router.on(
            'httpException',
            (event) => {
                if (
                    event.detail.response.status !== 423 ||
                    event.detail.response.headers[
                        'x-password-confirmation-required'
                    ] !== 'true'
                ) {
                    return;
                }

                event.preventDefault();
                setIsOpen(true);
                requestAnimationFrame(() => passwordInput.current?.focus());
            },
        );

        return () => {
            removeBeforeListener();
            removeHttpExceptionListener();
        };
    }, []);

    function closeModal(): void {
        form.reset();
        form.clearErrors();
        pendingVisit.current = null;
        setIsOpen(false);
    }

    function resumePendingVisit(): void {
        const visit = pendingVisit.current;

        closeModal();

        if (!visit) {
            return;
        }

        router.visit(visit.url, {
            method: visit.method,
            data: visit.data,
            replace: visit.replace,
            preserveScroll: visit.preserveScroll,
            preserveState: visit.preserveState,
            only: visit.only,
            except: visit.except,
            errorBag: visit.errorBag,
            forceFormData: visit.forceFormData,
            queryStringArrayFormat: visit.queryStringArrayFormat,
            async: visit.async,
            showProgress: visit.showProgress,
            fresh: visit.fresh,
            reset: visit.reset,
            preserveUrl: visit.preserveUrl,
            preserveErrors: visit.preserveErrors,
            invalidateCacheTags: visit.invalidateCacheTags,
            viewTransition: visit.viewTransition,
        });
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        isConfirming.current = true;

        form.post(store.url(), {
            headers: {
                Accept: 'application/json',
            },
            onSuccess: resumePendingVisit,
            onError: () => passwordInput.current?.focus(),
            onFinish: () => {
                isConfirming.current = false;
            },
        });
    }

    return (
        <>
            {children}

            <Dialog
                open={isOpen}
                onOpenChange={(open) => !open && closeModal()}
            >
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
                        onSuccess={resumePendingVisit}
                    />

                    <form onSubmit={submit}>
                        <Stack gap="default">
                            <Stack gap="tight">
                                <Label htmlFor="confirm-password">
                                    Password
                                </Label>
                                <PasswordInput
                                    id="confirm-password"
                                    ref={passwordInput}
                                    name="password"
                                    autoComplete="current-password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
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
        </>
    );
}
