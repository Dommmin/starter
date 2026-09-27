import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { PasswordConfirmationDialogProps } from '@/components/password-confirmation-dialog';
import { confirm as confirmPasswordPage } from '@/routes/password';

type Props = {
    children: React.ReactNode;
};

type DialogComponent = (
    props: PasswordConfirmationDialogProps,
) => React.ReactNode;

/**
 * Remembers the last Inertia visit and, when the server answers 423 with
 * `X-Password-Confirmation-Required`, asks for the password and retries that
 * visit. Only this listener is part of the entry chunk: the dialog (Radix
 * dialog, passkey client) is imported on the first 423 response. If that
 * chunk cannot be loaded, the full-page confirmation screen is opened.
 */
export default function PasswordConfirmationModal({ children }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [ConfirmationDialog, setConfirmationDialog] =
        useState<DialogComponent | null>(null);
    const pendingVisit = useRef<PendingVisit | null>(null);
    const isConfirming = useRef(false);
    const isLoadingDialog = useRef(false);

    useEffect(() => {
        function loadDialog(): void {
            if (isLoadingDialog.current) {
                return;
            }

            isLoadingDialog.current = true;

            import('@/components/password-confirmation-dialog')
                .then((module) => setConfirmationDialog(() => module.default))
                .catch(() => {
                    isLoadingDialog.current = false;
                    window.location.assign(confirmPasswordPage.url());
                });
        }

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
                loadDialog();
                setIsOpen(true);
            },
        );

        return () => {
            removeBeforeListener();
            removeHttpExceptionListener();
        };
    }, []);

    function closeDialog(): void {
        pendingVisit.current = null;
        setIsOpen(false);
    }

    function setConfirming(value: boolean): void {
        isConfirming.current = value;
    }

    function resumePendingVisit(): void {
        const visit = pendingVisit.current;

        closeDialog();

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

    return (
        <>
            {children}

            {ConfirmationDialog && (
                <ConfirmationDialog
                    open={isOpen}
                    onClose={closeDialog}
                    onConfirmed={resumePendingVisit}
                    onConfirmingChange={setConfirming}
                />
            )}
        </>
    );
}
