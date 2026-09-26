import { usePage } from '@inertiajs/react';
import { LogIn, UserPlus } from 'lucide-react';
import type { ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/i18n';
import { login, register } from '@/routes';
import {
    login as localizedLogin,
    register as localizedRegister,
} from '@/routes/localized';
import { Button } from './button';
import { HeaderUtility } from './header-utility';
import { Link } from './link';
import { MobileNav, type MobileNavItem } from './mobile-nav';

export type PublicHeaderNavItem = MobileNavItem;

export type PublicHeaderProps = {
    navItems?: PublicHeaderNavItem[];
    className?: never;
    style?: never;
};

export function PublicHeader({ navItems = [] }: PublicHeaderProps) {
    const { t, locale, defaultLocale } = useTranslation();
    const page = usePage();
    const { auth } = page.props;
    const getInitials = useInitials();

    const loginUrl =
        locale === defaultLocale ? login.url() : localizedLogin.url({ locale });
    const registerUrl =
        locale === defaultLocale
            ? register.url()
            : localizedRegister.url({ locale });

    const authControls: ReactNode = auth.user ? (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="hover:bg-surface-subtle focus-visible:ring-ring flex min-h-[44px] cursor-pointer items-center rounded-full py-1 pr-1.5 pl-1.5 transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none sm:pr-3.5"
                    aria-label={t('a11y.userMenu')}
                >
                    <Avatar className="border-border-subtle h-8 w-8 overflow-hidden rounded-full border">
                        <AvatarImage
                            src={auth.user.avatar}
                            alt={auth.user.name}
                        />
                        <AvatarFallback className="bg-primary text-primary-foreground rounded-full text-xs font-semibold">
                            {getInitials(auth.user.name ?? '')}
                        </AvatarFallback>
                    </Avatar>
                    <span className="ml-2 hidden max-w-[120px] truncate text-sm font-medium sm:inline sm:max-w-[160px]">
                        {auth.user.name}
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    ) : (
        <>
            <Button
                variant="ghost"
                size="sm"
                href={loginUrl}
                ariaLabel={t('nav.login')}
                responsiveLabel
            >
                <LogIn className="h-4 w-4" aria-hidden="true" />
                <span>{t('nav.login')}</span>
            </Button>
            <Button
                variant="primary"
                size="sm"
                href={registerUrl}
                ariaLabel={t('nav.register')}
                responsiveLabel
            >
                <UserPlus className="h-4 w-4" aria-hidden="true" />
                <span>{t('nav.register')}</span>
            </Button>
        </>
    );

    return (
        <HeaderUtility>
            {navItems.length > 0 && (
                <div className="hidden items-center gap-4 md:flex">
                    {navItems.map((item) => (
                        <Link key={item.id} href={item.href}>
                            {item.label}
                        </Link>
                    ))}
                </div>
            )}
            <div className="flex items-center gap-1.5 sm:gap-3">
                {authControls}
            </div>
            {navItems.length > 0 && (
                <div className="md:hidden">
                    <MobileNav
                        title={t('nav.menuTitle')}
                        items={navItems}
                        openLabel={t('a11y.openMenu')}
                        closeLabel={t('a11y.closeMenu')}
                    />
                </div>
            )}
        </HeaderUtility>
    );
}
