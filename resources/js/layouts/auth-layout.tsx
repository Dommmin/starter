import { usePage } from '@inertiajs/react';
import { HeaderUtility, type BrandLogoImage } from '@/design-system/primitives';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

/**
 * Auth frame: the brand comes from the shared site settings, the same way
 * `PublicChrome` resolves it (image logo, else the saved site name once
 * customised, else the catalog brand). Pages render their own heading.
 */
export default function AuthLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const site = usePage().props.site as
        | App.Data.Settings.SiteSettingsData
        | undefined;
    const logo: BrandLogoImage | undefined = site?.logo
        ? { ...site.logo, alt: site.name }
        : undefined;

    return (
        <>
            <HeaderUtility
                logo={logo}
                brandName={site?.isCustomized ? site.name : undefined}
            />
            <AuthLayoutTemplate>{children}</AuthLayoutTemplate>
        </>
    );
}
