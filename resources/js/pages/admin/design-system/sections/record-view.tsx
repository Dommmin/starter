import { Pencil, Trash2 } from 'lucide-react';
import {
    ActionMenu,
    Avatar,
    Badge,
    Button,
    DescriptionList,
    RecordDetails,
    Stack,
    type DescriptionListItem,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

/** ADM-11 — record view: DescriptionList and RecordDetails. */
function RecordViewSection() {
    const { t, formatDate } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.recordView.${key}`);

    const defaultItems: DescriptionListItem[] = [
        { id: 'name', label: demo('labels.name'), value: 'Zofia Łęcka' },
        {
            id: 'email',
            label: demo('labels.email'),
            value: 'zofia.lecka@example.com',
        },
        {
            id: 'status',
            label: demo('labels.status'),
            value: <Badge tone="success">{demo('active')}</Badge>,
        },
        {
            id: 'createdAt',
            label: demo('labels.createdAt'),
            value: formatDate('2026-09-28T10:15:00Z', {
                dateStyle: 'long',
                timeZone: 'UTC',
            }),
        },
    ];

    const emptyItems: DescriptionListItem[] = [
        { id: 'name', label: demo('labels.name'), value: 'Anna Nowak' },
        { id: 'phone', label: demo('labels.phone'), value: null },
        { id: 'company', label: demo('labels.company'), value: '' },
    ];

    const longItems: DescriptionListItem[] = [
        {
            id: 'name',
            label: demo('labels.name'),
            value: 'Maximiliane Konstantina Wiśniewska-Grünberg von Hohenstein',
        },
        {
            id: 'email',
            label: demo('labels.email'),
            value: 'maximiliane.konstantina.wisniewska-grunberg@przykładowa-domena-źdźbło.example.com',
        },
        {
            id: 'notes',
            label: demo('labels.longLabel'),
            value: demo('longValue'),
        },
    ];

    const mediaItems: DescriptionListItem[] = [
        {
            id: 'avatar',
            label: demo('labels.avatar'),
            value: <Avatar name="Jürgen Weiß" fallback="JW" />,
        },
        { id: 'name', label: demo('labels.name'), value: 'Jürgen Weiß' },
    ];

    const actions = (
        <ActionMenu
            triggerLabel={demo('recordActions')}
            items={[
                {
                    id: 'edit',
                    label: demo('edit'),
                    icon: Pencil,
                    onSelect: () => {},
                },
                {
                    id: 'delete',
                    label: demo('delete'),
                    icon: Trash2,
                    tone: 'destructive',
                    onSelect: () => {},
                },
            ]}
        />
    );

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="DescriptionList"
                layout="wide"
                notApplicable={['loading', 'error', 'disabled', 'pending']}
            >
                <ShowcaseState state="default" fill>
                    <DescriptionList items={defaultItems} />
                </ShowcaseState>
                <ShowcaseState
                    state="empty"
                    detail={demo('placeholderDetail')}
                    fill
                >
                    <DescriptionList
                        items={emptyItems}
                        emptyValuePlaceholder={demo('noValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <DescriptionList items={longItems} />
                </ShowcaseState>
                <ShowcaseState state="noMedia" fill>
                    <DescriptionList items={mediaItems} />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="RecordDetails"
                layout="wide"
                notApplicable={['loading', 'error', 'disabled', 'pending']}
            >
                <ShowcaseState state="withAction" fill>
                    <RecordDetails
                        title={demo('recordTitle')}
                        description={demo('recordDescription')}
                        items={defaultItems}
                        actions={actions}
                    />
                </ShowcaseState>
                <ShowcaseState state="minimal" fill>
                    <RecordDetails
                        title={demo('recordTitle')}
                        items={emptyItems}
                        emptyValuePlaceholder={demo('noValue')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <RecordDetails
                        title={demo('longTitle')}
                        description={demo('longValue')}
                        items={longItems}
                        actions={
                            <Button variant="outline" size="sm" href="#adm-11">
                                <Pencil aria-hidden="true" />
                                {demo('edit')}
                            </Button>
                        }
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const recordViewFamily: ShowcaseFamily = {
    id: 'adm-11',
    titleKey: 'admin.designSystem.recordView.title',
    descriptionKey: 'admin.designSystem.recordView.description',
    Component: RecordViewSection,
};
