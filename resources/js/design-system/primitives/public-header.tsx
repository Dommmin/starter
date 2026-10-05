import { usePage } from '@inertiajs/react';
import * as CollapsiblePrimitive from '@radix-ui/react-collapsible';
import { ChevronDown, LogIn, UserPlus } from 'lucide-react';
import { useRef, useState, type ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
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
import type { BrandLogoImage } from './brand-logo';
import { Button } from './button';
import { HeaderUtility } from './header-utility';
import { MobileNav } from './mobile-nav';
import { ThemeMenuGroup } from './theme-switcher';
import type { NavItem } from './nav-item';
import { isCurrentNavItem, isNavLink, NavItemLink } from './nav-item-link';

export type PublicHeaderProps = {
    /**
     * Main navigation. Entries with `children` (one level) open a disclosure
     * submenu on desktop and render nested in the mobile panel.
     */
    navItems?: NavItem[];
    /** Optional image logo; the text logo otherwise. */
    logo?: BrandLogoImage;
    /** One-tone text logo name; the catalog brand otherwise. */
    brandName?: string;
    className?: never;
    style?: never;
};

const topLinkClasses =
    'text-muted-foreground hover:text-foreground hover:bg-surface-subtle aria-[current=page]:text-foreground inline-flex min-h-[44px] items-center rounded-md px-2 text-sm font-medium transition-colors duration-150 focus-visible:ring-ring focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none';
const submenuLinkClasses =
    'text-foreground hover:bg-surface-subtle aria-[current=page]:bg-surface-subtle focus-visible:ring-ring block rounded-md px-3 py-2 text-sm whitespace-nowrap focus-visible:ring-2 focus-visible:outline-none';

/**
 * Disclosure submenu (button + list of links), deliberately not an ARIA
 * `menu`: links stay in the Tab order, Escape closes and returns focus to the
 * trigger, and focus leaving the item closes it.
 */
function DesktopSubmenu({ item }: { item: NavItem }) {
    const { t, locale } = useTranslation();
    const { url } = usePage();
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const links = [
        ...(isNavLink(item) ? [{ ...item, children: undefined }] : []),
        ...(item.children ?? []),
    ].filter(isNavLink);
    const hasCurrent = links.some((link) =>
        isCurrentNavItem(link, url, locale),
    );

    return (
        <CollapsiblePrimitive.Root
            open={open}
            onOpenChange={setOpen}
            className="relative"
            onKeyDown={(event) => {
                if (event.key === 'Escape' && open) {
                    event.stopPropagation();
                    setOpen(false);
                    triggerRef.current?.focus();
                }
            }}
            onBlur={(event) => {
                if (
                    !event.currentTarget.contains(
                        event.relatedTarget as Node | null,
                    )
                ) {
                    setOpen(false);
                }
            }}
        >
            <CollapsiblePrimitive.Trigger
                ref={triggerRef}
                data-current={hasCurrent ? 'true' : undefined}
                className="text-muted-foreground hover:text-foreground data-[current=true]:text-foreground hover:bg-surface-subtle focus-visible:ring-ring inline-flex min-h-[44px] items-center gap-1 rounded-md px-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none [&[data-state=open]>svg]:rotate-180"
            >
                {item.label}
                <ChevronDown
                    className="text-muted-foreground size-4 shrink-0 transition-transform"
                    aria-hidden="true"
                />
            </CollapsiblePrimitive.Trigger>
            <CollapsiblePrimitive.Content className="bg-background border-border-subtle absolute top-full left-0 z-20 mt-2 min-w-48 rounded-lg border p-2 shadow-lg">
                <ul aria-label={t('nav.submenu', { label: item.label })}>
                    {links.map((link, index) => (
                        <li
                            key={
                                index === 0 && isNavLink(item)
                                    ? 'self'
                                    : link.id
                            }
                        >
                            <NavItemLink
                                item={link}
                                classes={submenuLinkClasses}
                                onNavigate={() => setOpen(false)}
                            />
                        </li>
                    ))}
                </ul>
            </CollapsiblePrimitive.Content>
        </CollapsiblePrimitive.Root>
    );
}

export function PublicHeader({
    navItems = [],
    logo,
    brandName,
}: PublicHeaderProps) {
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
                <ThemeMenuGroup />
                <DropdownMenuSeparator />
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    ) : (
        <>
            <Button
                variant="ghost"
                size="default"
                href={loginUrl}
                ariaLabel={t('nav.login')}
                responsiveLabel
            >
                <LogIn className="h-4 w-4" aria-hidden="true" />
                <span>{t('nav.login')}</span>
            </Button>
            <Button
                variant="primary"
                size="default"
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
        <HeaderUtility
            logo={logo}
            brandName={brandName}
            navigation={
                navItems.length > 0 ? (
                    <ul className="flex items-center gap-1">
                        {navItems.map((item) => (
                            <li key={item.id}>
                                {(item.children ?? []).length > 0 ? (
                                    <DesktopSubmenu item={item} />
                                ) : isNavLink(item) ? (
                                    <NavItemLink
                                        item={item}
                                        classes={topLinkClasses}
                                    />
                                ) : null}
                            </li>
                        ))}
                    </ul>
                ) : undefined
            }
        >
            <div className="flex items-center gap-1.5 sm:gap-3">
                {authControls}
            </div>
            {navItems.length > 0 && (
                <div className="lg:hidden">
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
