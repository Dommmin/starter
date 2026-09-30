import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            position="bottom-right"
            toastOptions={{
                classNames: {
                    toast: 'group toast shadow-lg rounded-xl font-sans text-sm',
                    description: 'text-muted-foreground',
                    actionButton: 'bg-primary text-primary-foreground font-medium rounded-lg',
                    cancelButton: 'bg-secondary text-secondary-foreground rounded-lg',
                    closeButton: 'border-border bg-popover text-popover-foreground hover:bg-accent',
                    success: '!border-status-success/40 [&_[data-icon]]:text-status-success',
                    info: '[&_[data-icon]]:text-status-info',
                    warning: '[&_[data-icon]]:text-status-warning',
                    error: '!border-status-danger/40 [&_[data-icon]]:text-status-danger',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
