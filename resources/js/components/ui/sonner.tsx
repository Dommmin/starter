import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            toastOptions={{
                classNames: {
                    toast: 'group toast border-[#e9e3d8] bg-[#faf8f5] text-[#21201c] shadow-lg rounded-xl dark:border-[#262421] dark:bg-[#1a1917] dark:text-[#edebe8] font-sans text-sm',
                    description: 'text-[#615c54] dark:text-[#a8a39a]',
                    actionButton: 'bg-primary text-primary-foreground font-medium rounded-lg',
                    cancelButton: 'bg-[#ede5da] text-[#21201c] dark:bg-[#26211c] dark:text-[#edebe8] rounded-lg',
                    closeButton: 'border-[#e9e3d8] bg-[#faf8f5] text-[#21201c] hover:bg-[#ede5da] dark:border-[#262421] dark:bg-[#1a1917] dark:text-[#edebe8] dark:hover:bg-[#26211c]',
                    success: '!border-[#de6c2c]/30 dark:!border-[#e77a3c]/30 text-[#21201c] dark:text-[#edebe8] [&_[data-icon]]:text-[#de6c2c] dark:[&_[data-icon]]:text-[#e77a3c]',
                    info: 'text-[#21201c] dark:text-[#edebe8] [&_[data-icon]]:text-blue-500',
                    warning: 'text-[#21201c] dark:text-[#edebe8] [&_[data-icon]]:text-amber-500',
                    error: '!border-destructive/30 text-[#21201c] dark:text-[#edebe8] [&_[data-icon]]:text-destructive',
                },
            }}
            style={
                {
                    '--normal-bg': '#faf8f5',
                    '--normal-text': '#21201c',
                    '--normal-border': '#e9e3d8',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
