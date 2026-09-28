import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import PasswordConfirmationModal from '@/components/password-confirmation-modal';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';

import { I18nProvider } from '@/i18n';
import { documentTitle } from '@/lib/document-title';
import { loadPage, resolveLayout, syncSurface } from '@/lib/page-resolver';

void createInertiaApp({
    title: documentTitle,
    resolve: loadPage,
    layout: resolveLayout,
    strictMode: true,
    withApp(app, { page }) {
        return (
            <TooltipProvider delayDuration={0}>
                <I18nProvider initialPage={page}>
                    <PasswordConfirmationModal>{app}</PasswordConfirmationModal>
                </I18nProvider>
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

router.on('navigate', (event) => {
    syncSurface(event.detail.page.component);
});
