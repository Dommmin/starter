import {
    BrandLogo,
    Footer,
    LocaleSwitcher,
    MobileNav,
    Stack,
    Text,
    ThemeSwitcher,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from '../../admin/design-system/sections/showcase';
import { demoFooterGroups, demoLogo, demoNavItems } from './demo-data';

/** WEB-01 — public navigation: header, mobile menu, logo and footer. */
function NavigationSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.web.navigation.${key}`);
    const longName = t('admin.designSystem.web.longName');

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="PublicChrome · PublicHeader"
                layout="wide"
                notApplicable={['loading', 'empty', 'error']}
            >
                <ShowcaseState state="inContext" fill>
                    <Text>{demo('headerInContext')}</Text>
                </ShowcaseState>
                <ShowcaseState state="submenu" fill>
                    <Text>{demo('headerSubmenu')}</Text>
                </ShowcaseState>
                <ShowcaseState state="keyboard" fill>
                    <Text>{demo('headerKeyboard')}</Text>
                </ShowcaseState>
                <ShowcaseState state="languages" fill>
                    <Text>{demo('headerLanguages')}</Text>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="MobileNav"
                notApplicable={['loading', 'error', 'disabled']}
            >
                <ShowcaseState state="submenu" detail={demo('mobileItems')}>
                    <MobileNav
                        title={t('nav.menuTitle')}
                        items={demoNavItems(t)}
                        openLabel={t('a11y.openMenu')}
                        closeLabel={t('a11y.closeMenu')}
                    />
                </ShowcaseState>
                <ShowcaseState state="empty" detail={demo('mobileUtilities')}>
                    <MobileNav
                        title={t('nav.menuTitle')}
                        items={[]}
                        openLabel={t('a11y.openMenu')}
                        closeLabel={t('a11y.closeMenu')}
                        utilities={
                            <>
                                <ThemeSwitcher variant="labelled" />
                                <LocaleSwitcher variant="labelled" />
                            </>
                        }
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="BrandLogo"
                layout="wide"
                notApplicable={['loading', 'empty', 'error', 'disabled']}
            >
                <ShowcaseState state="default">
                    <BrandLogo />
                </ShowcaseState>
                <ShowcaseState state="longContent">
                    <BrandLogo name={longName} />
                </ShowcaseState>
                <ShowcaseState state="withMedia">
                    <BrandLogo image={demoLogo} />
                </ShowcaseState>
                <ShowcaseState state="internalLink" detail="href">
                    <BrandLogo href="/articles" ariaLabel={demo('logoLink')} />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Footer"
                layout="full"
                notApplicable={['loading', 'error', 'disabled']}
            >
                <ShowcaseState state="minimal" fill>
                    <Footer copyright={demo('copyright')} />
                </ShowcaseState>
                <ShowcaseState state="default" fill>
                    <Footer
                        copyright={demo('copyright')}
                        groups={demoFooterGroups(t)}
                        contact={{
                            email: 'kontakt@example.test',
                            phone: '+48 600 000 000',
                            address: demo('address'),
                        }}
                        social={[
                            {
                                network: 'linkedin',
                                label: 'LinkedIn',
                                url: 'https://www.linkedin.com/',
                            },
                        ]}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <Footer
                        copyright={`© 2026 ${longName}`}
                        brandName={longName}
                        groups={[
                            {
                                id: 'long-group',
                                label: longName,
                                kind: 'group',
                                children: [
                                    {
                                        id: 'long-link',
                                        label: longName,
                                        href: '#web-01',
                                        kind: 'anchor',
                                    },
                                ],
                            },
                        ]}
                        contact={{
                            email: 'bardzo.dluga.nazwa.skrzynki.kontaktowej@przyklad-firmy.example.test',
                            address: demo('address'),
                        }}
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const navigationFamily: ShowcaseFamily = {
    id: 'web-01',
    titleKey: 'admin.designSystem.web.navigation.title',
    descriptionKey: 'admin.designSystem.web.navigation.description',
    Component: NavigationSection,
};
