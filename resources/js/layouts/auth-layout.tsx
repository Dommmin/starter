import { HeaderUtility, ThemeSwitcher } from '@/design-system/primitives';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

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
        <>
            <HeaderUtility>
                <ThemeSwitcher />
            </HeaderUtility>
            <AuthLayoutTemplate title={title} description={description}>
                {children}
            </AuthLayoutTemplate>
        </>
    );
}
