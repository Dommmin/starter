import { FileText, Image as ImageIcon, LayoutDashboard } from 'lucide-react';
import { useState } from 'react';
import {
    Button,
    CommandPalette,
    Stack,
    Text,
    type CommandPaletteItem,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as articlesIndex } from '@/routes/admin/articles';
import { index as mediaIndex } from '@/routes/admin/media';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

/**
 * ADM-15 — panel navigation. `AdminShell` frames this very page, so its
 * states are reviewed on the live shell (instructions below); only the
 * command palette can also be opened here on synthetic items.
 */
function PanelNavigationSection() {
    const { t } = useTranslation();
    const demo = (key: string) =>
        t(`admin.designSystem.panelNavigation.${key}`);
    const [isPaletteOpen, setPaletteOpen] = useState(false);
    const [isLongPaletteOpen, setLongPaletteOpen] = useState(false);

    const group = demo('paletteGroup');
    const paletteItems: CommandPaletteItem[] = [
        {
            id: 'dashboard',
            label: t('admin.dashboard'),
            group,
            href: adminIndex(),
            icon: LayoutDashboard,
        },
        {
            id: 'articles',
            label: demo('paletteArticles'),
            group,
            href: articlesIndex(),
            icon: FileText,
        },
        {
            id: 'media',
            label: demo('paletteMedia'),
            group,
            href: mediaIndex(),
            icon: ImageIcon,
        },
    ];
    const longPaletteItems: CommandPaletteItem[] = [
        ...paletteItems,
        {
            id: 'long',
            label: demo('paletteLongItem'),
            group: demo('paletteLongGroup'),
            href: adminIndex(),
            icon: FileText,
        },
    ];

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="AdminShell"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="default" detail={demo('liveShell')} fill>
                    <Text>{demo('shellDefault')}</Text>
                </ShowcaseState>
                <ShowcaseState state="keyboard" fill>
                    <Text>{demo('shellKeyboard')}</Text>
                </ShowcaseState>
                <ShowcaseState state="mobileDrawer" fill>
                    <Text>{demo('shellDrawer')}</Text>
                </ShowcaseState>
                <ShowcaseState state="longContent" detail="Breadcrumbs" fill>
                    <Stack gap="tight" align="start">
                        <Text>{demo('breadcrumbs')}</Text>
                        <Button variant="link" href={adminIndex()}>
                            {demo('toDashboard')}
                        </Button>
                    </Stack>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="CommandPalette"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="default" detail={demo('synthetic')} fill>
                    <Stack gap="tight" align="start">
                        <Button
                            variant="outline"
                            onClick={() => setPaletteOpen(true)}
                        >
                            {demo('openPalette')}
                        </Button>
                        <CommandPalette
                            open={isPaletteOpen}
                            onOpenChange={setPaletteOpen}
                            items={paletteItems}
                        />
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <Stack gap="tight" align="start">
                        <Button
                            variant="outline"
                            onClick={() => setLongPaletteOpen(true)}
                        >
                            {demo('openLongPalette')}
                        </Button>
                        <CommandPalette
                            open={isLongPaletteOpen}
                            onOpenChange={setLongPaletteOpen}
                            items={longPaletteItems}
                        />
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="empty" fill>
                    <Text>{demo('paletteEmpty')}</Text>
                </ShowcaseState>
                <ShowcaseState state="keyboard" fill>
                    <Text>{demo('paletteKeyboard')}</Text>
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const panelNavigationFamily: ShowcaseFamily = {
    id: 'adm-15',
    titleKey: 'admin.designSystem.panelNavigation.title',
    descriptionKey: 'admin.designSystem.panelNavigation.description',
    Component: PanelNavigationSection,
};
