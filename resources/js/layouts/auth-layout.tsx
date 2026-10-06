import { AuthShell } from '@/design-system/primitives';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthShell title={title} description={description || undefined}>
            {children}
        </AuthShell>
    );
}
