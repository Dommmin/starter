import type { InertiaLinkProps } from '@inertiajs/react';
import { Link, router } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { LucideIcon } from 'lucide-react';
import {
    ChevronDown,
    LogOut,
    Menu,
    Monitor,
    Moon,
    Search,
    Sun,
    User as UserIcon,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/i18n';
import { cn } from '@/lib/utils';
import { useAdminLocaleChange } from './admin-locale-select';
import { CommandPalette } from './command-palette';
import type { CommandPaletteItem } from './command-palette';

type Href = NonNullable<InertiaLinkProps['href']>;

export type AdminNavItem = {
    id: string;
    label: string;
    href: Href;
    icon: LucideIcon;
    isCurrent: boolean;
};

export type AdminNavGroup = {
    id: string;
    /** Omit for the ungrouped first entry (dashboard). */
    label?: string;
    items: AdminNavItem[];
};

export type AdminBreadcrumb = {
    label: string;
    /** Omit for the current page (last crumb). */
    href?: Href;
};

export type AdminShellUser = {
    name: string;
    email: string;
    roleLabel?: string;
};

export type AdminShellProps = {
    brandName: string;
    brandSubtitle: string;
    homeHref: Href;
    navGroups: AdminNavGroup[];
    footerItems: AdminNavItem[];
    breadcrumbs: AdminBreadcrumb[];
    user: AdminShellUser;
    profileHref: Href;
    logoutHref: Href;
    children: ReactNode;
    className?: never;
    style?: never;
};

const focusRing =
    'focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none';

const themeModes: { value: Appearance; icon: LucideIcon; labelKey: string }[] =
    [
        { value: 'light', icon: Sun, labelKey: 'theme.light' },
        { value: 'dark', icon: Moon, labelKey: 'theme.dark' },
        { value: 'system', icon: Monitor, labelKey: 'theme.system' },
    ];

function Kbd({ children }: { children: string }) {
    return (
        <kbd className="border-border bg-card text-muted-foreground inline-grid h-[18px] min-w-[18px] place-items-center rounded-[4px] border border-b-2 px-1 font-mono text-[10.5px]">
            {children}
        </kbd>
    );
}

function NavLink({
    item,
    onNavigate,
}: {
    item: AdminNavItem;
    onNavigate?: () => void;
}) {
    const ItemIcon = item.icon;

    return (
        <Link
            href={item.href}
            prefetch
            onClick={onNavigate}
            aria-current={item.isCurrent ? 'page' : undefined}
            className={cn(
                'group text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground relative flex h-8 items-center gap-2.5 rounded-md px-2 font-[450] max-md:h-11 pointer-coarse:h-11',
                'aria-[current=page]:bg-sidebar-accent aria-[current=page]:text-sidebar-accent-foreground aria-[current=page]:font-medium',
                'aria-[current=page]:before:bg-sidebar-primary aria-[current=page]:before:absolute aria-[current=page]:before:inset-y-[7px] aria-[current=page]:before:-left-3 aria-[current=page]:before:w-0.5 aria-[current=page]:before:rounded-r-sm',
                focusRing,
            )}
        >
            <ItemIcon
                className="text-muted-foreground group-aria-[current=page]:text-sidebar-primary size-4 shrink-0"
                aria-hidden="true"
            />
            <span className="truncate">{item.label}</span>
        </Link>
    );
}

type SidebarContentProps = Pick<
    AdminShellProps,
    'brandName' | 'brandSubtitle' | 'homeHref' | 'navGroups' | 'footerItems'
> & {
    onOpenCommand: () => void;
    onNavigate?: () => void;
    closeButton?: ReactNode;
};

function SidebarContent({
    brandName,
    brandSubtitle,
    homeHref,
    navGroups,
    footerItems,
    onOpenCommand,
    onNavigate,
    closeButton,
}: SidebarContentProps) {
    const { t } = useTranslation();

    return (
        <>
            <div className="flex h-(--admin-topbar-height) shrink-0 items-center gap-2.5 px-3">
                <Link
                    href={homeHref}
                    onClick={onNavigate}
                    className={cn(
                        'flex min-w-0 items-center gap-2.5 rounded-md',
                        focusRing,
                    )}
                >
                    <span
                        aria-hidden="true"
                        className="bg-foreground text-background after:bg-sidebar-primary after:border-sidebar relative grid size-6 shrink-0 place-items-center rounded-md text-[13px] font-semibold after:absolute after:-right-0.5 after:-bottom-0.5 after:size-2 after:rounded-full after:border-2"
                    >
                        {brandName.charAt(0)}
                    </span>
                    <span className="grid min-w-0 leading-tight">
                        <span className="text-sidebar-accent-foreground truncate font-semibold">
                            {brandName}
                        </span>
                        <span className="text-muted-foreground truncate text-xs">
                            {brandSubtitle}
                        </span>
                    </span>
                </Link>
                {closeButton}
            </div>

            <button
                type="button"
                onClick={onOpenCommand}
                aria-keyshortcuts="Meta+K Control+K"
                className={cn(
                    'border-border bg-card text-muted-foreground hover:border-input mx-3 mt-1 mb-2 flex h-(--admin-control-height) shrink-0 items-center gap-2 rounded-md border pr-2 pl-2.5 text-left',
                    focusRing,
                )}
            >
                <Search className="size-4 shrink-0" aria-hidden="true" />
                <span className="flex-1 truncate">
                    {t('admin.shell.search')}
                </span>
                <span
                    aria-hidden="true"
                    className="flex gap-0.5 max-md:hidden pointer-coarse:hidden"
                >
                    <Kbd>⌘</Kbd>
                    <Kbd>K</Kbd>
                </span>
            </button>

            <nav
                aria-label={t('admin.shell.modulesLabel')}
                className="grid flex-1 content-start gap-3.5 overflow-y-auto px-3 pt-1 pb-3"
            >
                {navGroups.map((group) => {
                    const labelId = `admin-nav-${group.id}`;

                    return (
                        <div
                            key={group.id}
                            role={group.label ? 'group' : undefined}
                            aria-labelledby={group.label ? labelId : undefined}
                            className="grid gap-px"
                        >
                            {group.label && (
                                <div
                                    id={labelId}
                                    className="text-text-subtle px-2 pb-1 text-xs font-medium"
                                >
                                    {group.label}
                                </div>
                            )}
                            {group.items.map((item) => (
                                <NavLink
                                    key={item.id}
                                    item={item}
                                    onNavigate={onNavigate}
                                />
                            ))}
                        </div>
                    );
                })}
            </nav>

            {footerItems.length > 0 && (
                <div className="border-sidebar-border grid shrink-0 gap-px border-t px-3 py-2">
                    {footerItems.map((item) => (
                        <NavLink
                            key={item.id}
                            item={item}
                            onNavigate={onNavigate}
                        />
                    ))}
                </div>
            )}
        </>
    );
}

function Breadcrumbs({ items }: { items: AdminBreadcrumb[] }) {
    const { t } = useTranslation();

    if (items.length === 0) {
        return null;
    }

    return (
        <nav aria-label={t('admin.shell.breadcrumbLabel')} className="min-w-0">
            <ol className="flex min-w-0 items-center gap-1.5">
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li
                            key={`${index}-${item.label}`}
                            className={cn(
                                'text-muted-foreground flex min-w-0 items-center gap-1.5 whitespace-nowrap',
                                index > 0 &&
                                    'before:text-text-subtle before:content-["/"] max-md:before:content-none',
                                !isLast && 'max-md:hidden',
                            )}
                        >
                            {isLast || !item.href ? (
                                <span
                                    aria-current={isLast ? 'page' : undefined}
                                    className="text-foreground truncate font-medium"
                                >
                                    {item.label}
                                </span>
                            ) : (
                                <Link
                                    href={item.href}
                                    className={cn(
                                        'hover:text-foreground rounded-sm underline-offset-[3px] hover:underline',
                                        focusRing,
                                    )}
                                >
                                    {item.label}
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

function UserMenu({
    user,
    profileHref,
    logoutHref,
}: Pick<AdminShellProps, 'user' | 'profileHref' | 'logoutHref'>) {
    const { t, locale, availableLocales } = useTranslation();
    const { appearance, updateAppearance } = useAppearance();
    const { isPending, changeLocale } = useAdminLocaleChange();
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={`${t('a11y.userMenu')}: ${user.name}`}
                    className={cn(
                        'hover:bg-accent data-[state=open]:bg-accent flex h-(--admin-control-height) min-w-(--admin-control-height) shrink-0 items-center justify-center gap-2 rounded-md pr-1.5 pl-1 text-left max-md:px-0',
                        focusRing,
                    )}
                >
                    <span
                        aria-hidden="true"
                        className="border-border bg-muted text-foreground grid size-[26px] shrink-0 place-items-center rounded-full border text-[10.5px] font-semibold tracking-wide"
                    >
                        {getInitials(user.name)}
                    </span>
                    <span className="grid leading-tight max-md:hidden">
                        <span className="text-(length:--admin-text-sm) font-medium">
                            {user.name}
                        </span>
                        {user.roleLabel && (
                            <span className="text-muted-foreground text-xs">
                                {user.roleLabel}
                            </span>
                        )}
                    </span>
                    <ChevronDown
                        className="text-muted-foreground size-3.5 max-md:hidden"
                        aria-hidden="true"
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={4}
                className="w-64 shadow-(--admin-shadow-overlay)"
            >
                <DropdownMenuLabel className="grid font-normal">
                    <span className="font-medium">{user.name}</span>
                    <span className="text-muted-foreground truncate text-xs">
                        {user.roleLabel
                            ? `${user.email} · ${user.roleLabel}`
                            : user.email}
                    </span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href={profileHref}
                        className="w-full pointer-coarse:min-h-11"
                    >
                        <UserIcon aria-hidden="true" />
                        {t('nav.profile')}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuLabel className="text-text-subtle text-xs font-medium">
                    {t('admin.shell.theme')}
                </DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) =>
                        updateAppearance(value as Appearance)
                    }
                >
                    {themeModes.map(({ value, icon: ModeIcon, labelKey }) => (
                        <DropdownMenuRadioItem
                            key={value}
                            value={value}
                            onSelect={(event) => event.preventDefault()}
                            className="pointer-coarse:min-h-11"
                        >
                            <ModeIcon aria-hidden="true" />
                            {t(labelKey)}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
                {availableLocales.length > 1 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel className="text-text-subtle text-xs font-medium">
                            {t('admin.shell.language')}
                        </DropdownMenuLabel>
                        <DropdownMenuRadioGroup
                            value={locale}
                            onValueChange={changeLocale}
                        >
                            {availableLocales.map((option) => (
                                <DropdownMenuRadioItem
                                    key={option.code}
                                    value={option.code}
                                    disabled={isPending}
                                    className="pointer-coarse:min-h-11"
                                >
                                    {option.native}
                                </DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                    </>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href={logoutHref}
                        as="button"
                        onClick={() => {
                            document.body.style.removeProperty(
                                'pointer-events',
                            );
                            router.flushAll();
                        }}
                        data-test="logout-button"
                        className="text-status-danger focus:text-status-danger w-full pointer-coarse:min-h-11 [&_svg]:text-current"
                    >
                        <LogOut aria-hidden="true" />
                        {t('nav.logout')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Admin panel frame from the approved mockup: fixed sidebar on desktop,
 * a modal drawer below 768 px, topbar with breadcrumbs and user menu, and
 * the ⌘K / Ctrl+K command palette built from the same navigation data.
 */
export function AdminShell({
    brandName,
    brandSubtitle,
    homeHref,
    navGroups,
    footerItems,
    breadcrumbs,
    user,
    profileHref,
    logoutHref,
    children,
}: AdminShellProps) {
    const { t } = useTranslation();
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [isCommandOpen, setIsCommandOpen] = useState(false);

    useEffect(() => {
        const handleKeyDown = (event: globalThis.KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setIsDrawerOpen(false);
                setIsCommandOpen((open) => !open);
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, []);

    const openCommand = () => {
        setIsDrawerOpen(false);
        setIsCommandOpen(true);
    };

    const navigationGroupLabel = t('admin.command.navigation');
    const commandItems: CommandPaletteItem[] = [
        ...navGroups.flatMap((group) => group.items),
        ...footerItems,
    ].map((item) => ({
        id: item.id,
        label: item.label,
        group: navigationGroupLabel,
        href: item.href,
        icon: item.icon,
    }));

    const sidebarProps = {
        brandName,
        brandSubtitle,
        homeHref,
        navGroups,
        footerItems,
        onOpenCommand: openCommand,
    };

    return (
        <div className="bg-background text-foreground flex min-h-svh">
            <a
                href="#admin-main"
                className="bg-card text-foreground focus:ring-ring sr-only z-50 rounded-md px-3 py-2 focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:ring-2"
            >
                {t('a11y.skipToContent')}
            </a>

            <aside
                aria-label={t('admin.shell.sidebarLabel')}
                className="border-sidebar-border bg-sidebar text-sidebar-foreground sticky top-0 flex h-svh w-(--admin-sidebar-width) shrink-0 flex-col border-r max-md:hidden"
            >
                <SidebarContent {...sidebarProps} />
            </aside>

            <DialogPrimitive.Root
                open={isDrawerOpen}
                onOpenChange={setIsDrawerOpen}
            >
                <DialogPrimitive.Portal>
                    <DialogPrimitive.Overlay className="bg-overlay data-[state=open]:animate-in data-[state=open]:fade-in-0 fixed inset-0 z-50 motion-reduce:animate-none md:hidden" />
                    <DialogPrimitive.Content
                        aria-describedby={undefined}
                        className="bg-sidebar text-sidebar-foreground data-[state=open]:animate-in data-[state=open]:slide-in-from-left fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col shadow-(--admin-shadow-overlay) motion-reduce:animate-none md:hidden"
                    >
                        <DialogPrimitive.Title className="sr-only">
                            {t('admin.shell.sidebarLabel')}
                        </DialogPrimitive.Title>
                        <SidebarContent
                            {...sidebarProps}
                            onNavigate={() => setIsDrawerOpen(false)}
                            closeButton={
                                <DialogPrimitive.Close
                                    aria-label={t('a11y.closeMenu')}
                                    className={cn(
                                        'text-muted-foreground hover:bg-accent hover:text-foreground ml-auto grid size-11 shrink-0 place-items-center rounded-md',
                                        focusRing,
                                    )}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                </DialogPrimitive.Close>
                            }
                        />
                    </DialogPrimitive.Content>
                </DialogPrimitive.Portal>
            </DialogPrimitive.Root>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="border-border bg-background sticky top-0 z-30 flex h-(--admin-topbar-height) shrink-0 items-center gap-2 border-b pr-4 pl-(--admin-page-gutter) max-md:gap-1 max-md:pr-2">
                    <button
                        type="button"
                        onClick={() => setIsDrawerOpen(true)}
                        aria-label={t('a11y.openMenu')}
                        aria-expanded={isDrawerOpen}
                        className={cn(
                            'text-muted-foreground hover:bg-accent hover:text-foreground -ml-2 grid size-(--admin-control-height) shrink-0 place-items-center rounded-md md:hidden',
                            focusRing,
                        )}
                    >
                        <Menu className="size-4" aria-hidden="true" />
                    </button>
                    <Breadcrumbs items={breadcrumbs} />
                    <div className="ml-auto flex items-center gap-1">
                        <button
                            type="button"
                            onClick={openCommand}
                            aria-label={t('admin.shell.openSearch')}
                            aria-keyshortcuts="Meta+K Control+K"
                            className={cn(
                                'text-muted-foreground hover:bg-accent hover:text-foreground grid size-(--admin-control-height) place-items-center rounded-md md:hidden',
                                focusRing,
                            )}
                        >
                            <Search className="size-4" aria-hidden="true" />
                        </button>
                        <UserMenu
                            user={user}
                            profileHref={profileHref}
                            logoutHref={logoutHref}
                        />
                    </div>
                </header>

                <main
                    id="admin-main"
                    tabIndex={-1}
                    className="min-w-0 flex-1 px-(--admin-page-gutter) pt-6 pb-8 outline-none max-md:pt-4"
                >
                    {children}
                </main>
            </div>

            <CommandPalette
                open={isCommandOpen}
                onOpenChange={setIsCommandOpen}
                items={commandItems}
            />
        </div>
    );
}
