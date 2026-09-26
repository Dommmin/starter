import { Link, usePage } from '@inertiajs/react';
import { LogIn, UserPlus } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { ThemeSwitcher } from '@/design-system/primitives';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/i18n';
import { login, register } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

export function AppHeader({ breadcrumbs = [] }: Props) {
    const page = usePage();
    const { auth } = page.props;
    const getInitials = useInitials();
    const { t } = useTranslation();

    return (
        <>
            <header className="border-border-subtle bg-background/85 relative z-10 border-b backdrop-blur-md">
                <div className="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <BrandLogo />

                    <nav
                        aria-label={t('a11y.mainNavigation')}
                        className="flex items-center gap-2 sm:gap-3"
                    >
                        <ThemeSwitcher />
                        {auth.user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        className="hover:bg-surface-subtle h-10 min-h-[44px] rounded-full py-1 pr-3.5 pl-1.5"
                                        aria-label={t('a11y.userMenu')}
                                    >
                                        <Avatar className="border-border-subtle h-8 w-8 overflow-hidden rounded-full border">
                                            <AvatarImage
                                                src={auth.user.avatar}
                                                alt={auth.user.name}
                                            />
                                            <AvatarFallback className="bg-primary text-primary-foreground rounded-full text-xs font-semibold">
                                                {getInitials(
                                                    auth.user.name ?? '',
                                                )}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span className="ml-2 max-w-[120px] truncate text-sm font-medium sm:max-w-[160px]">
                                            {auth.user.name}
                                        </span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    className="w-56"
                                    align="end"
                                >
                                    <UserMenuContent user={auth.user} />
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <>
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="sm"
                                    className="text-text-subtle hover:text-foreground min-h-[44px] rounded-full px-4"
                                >
                                    <Link href={login()}>
                                        <LogIn
                                            className="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        <span>{t('nav.login')}</span>
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="sm"
                                    className="min-h-[44px] rounded-full px-4 shadow-sm"
                                >
                                    <Link href={register()}>
                                        <UserPlus
                                            className="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        <span>{t('nav.register')}</span>
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            {breadcrumbs.length > 1 && (
                <div className="border-border-subtle bg-background/50 border-b">
                    <div className="text-muted-foreground mx-auto flex h-12 max-w-6xl items-center justify-start px-4 sm:px-6 lg:px-8">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}
