import { HeaderUtility, ThemeSwitcher } from '@/design-system/primitives';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

/**
 * Auth frame: `HeaderUtility` takes the brand from the shared site settings
 * (image logo, else the saved site name once customised, else the catalog
 * brand). These pages have no footer or account menu, so the theme switcher
 * stays in the header. Pages render their own heading.
 */
export default function AuthLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <>
            <HeaderUtility>
                <ThemeSwitcher />
            </HeaderUtility>
            <AuthLayoutTemplate>{children}</AuthLayoutTemplate>
        </>
    );
}
