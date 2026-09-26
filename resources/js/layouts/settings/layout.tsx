import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/i18n';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();

    const sidebarNavItems: NavItem[] = [
        {
            title: t('nav.profile'),
            href: edit(),
            icon: null,
        },
        {
            title: t('nav.security'),
            href: editSecurity(),
            icon: null,
        },
        {
            title: t('nav.appearance'),
            href: editAppearance(),
            icon: null,
        },
    ];

    return (
        <div className="py-8">
            <Heading
                title={t('settings.title')}
                description={t('settings.description')}
            />

            <div className="mt-8 flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-56">
                    <nav
                        className="flex flex-col space-y-1.5 space-x-0"
                        aria-label={t('a11y.accountSettings')}
                    >
                        {sidebarNavItems.map((item, index) => {
                            const active = isCurrentOrParentUrl(item.href);
                            return (
                                <Button
                                    key={`${toUrl(item.href)}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn(
                                        'min-h-[44px] w-full justify-start rounded-lg px-3.5 py-2 font-medium transition-colors',
                                        active
                                            ? 'bg-surface-subtle text-primary font-semibold'
                                            : 'text-text-subtle hover:bg-surface-subtle/50 hover:text-foreground',
                                    )}
                                >
                                    <Link href={item.href}>
                                        {item.icon && (
                                            <item.icon className="h-4 w-4" />
                                        )}
                                        {item.title}
                                    </Link>
                                </Button>
                            );
                        })}
                    </nav>
                </aside>

                <Separator className="border-border-subtle my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
