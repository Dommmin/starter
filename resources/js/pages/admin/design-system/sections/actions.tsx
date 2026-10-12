import { Archive, Copy, Pencil, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    ActionMenu,
    Button,
    ConfirmDialog,
    ConflictDialog,
    Dialog,
    FormDialog,
    OrderableList,
    PasswordField,
    Stack,
    Text,
    TextField,
    type ActionMenuItem,
    type OrderableListItem,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

/** How long the simulated request of a dialog demo stays pending. */
const demoPendingMs = 1500;

/**
 * Local dialog state for the demos: `run` simulates a request (pending for
 * `demoPendingMs`) and then applies `onDone`. Nothing is sent anywhere.
 */
function useDialogDemo() {
    const [isOpen, setOpen] = useState(false);
    const [isPending, setPending] = useState(false);
    const timeout = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(timeout.current), []);

    return {
        isOpen,
        setOpen,
        isPending,
        run: (onDone: () => void) => {
            setPending(true);
            timeout.current = window.setTimeout(() => {
                setPending(false);
                onDone();
            }, demoPendingMs);
        },
    };
}

function useDemoText() {
    const { t } = useTranslation();

    return (key: string, params?: Record<string, string>) =>
        t(`admin.designSystem.actions.${key}`, params);
}

/** Shows the last action picked in a menu or dialog, for keyboard review. */
function LastAction({ value }: { value: string | null }) {
    const demo = useDemoText();

    return (
        <Text variant="caption" tone="muted" as="span">
            {demo('lastAction', { action: value ?? demo('none') })}
        </Text>
    );
}

function ConfirmDialogDemo({
    tone,
    simulatePending = false,
    long = false,
}: {
    tone: 'default' | 'destructive';
    simulatePending?: boolean;
    long?: boolean;
}) {
    const demo = useDemoText();
    const dialog = useDialogDemo();
    const [result, setResult] = useState<string | null>(null);
    const isDestructive = tone === 'destructive';
    const title = long
        ? demo('longConfirmTitle')
        : isDestructive
          ? demo('deleteTitle')
          : demo('publishTitle');
    const confirmLabel = isDestructive ? demo('delete') : demo('publish');

    return (
        <Stack gap="tight" align="start">
            <Button
                variant={isDestructive ? 'destructive' : 'secondary'}
                onClick={() => dialog.setOpen(true)}
            >
                {isDestructive ? demo('deletePage') : demo('publishPage')}
            </Button>
            <LastAction value={result} />
            <ConfirmDialog
                open={dialog.isOpen}
                onOpenChange={dialog.setOpen}
                tone={tone}
                title={title}
                description={
                    long
                        ? demo('longConfirmDescription')
                        : isDestructive
                          ? demo('deleteDescription')
                          : demo('publishDescription')
                }
                confirmLabel={confirmLabel}
                cancelLabel={demo('cancel')}
                closeLabel={demo('close')}
                isPending={dialog.isPending}
                onConfirm={() => {
                    if (!simulatePending) {
                        setResult(confirmLabel);
                        dialog.setOpen(false);

                        return;
                    }

                    dialog.run(() => {
                        setResult(confirmLabel);
                        dialog.setOpen(false);
                    });
                }}
            />
        </Stack>
    );
}

function FormDialogDemo({ failFirst = false }: { failFirst?: boolean }) {
    const demo = useDemoText();
    const dialog = useDialogDemo();
    const [name, setName] = useState('');
    const [error, setError] = useState<string | undefined>(undefined);
    const [hasFailed, setHasFailed] = useState(false);
    const [saved, setSaved] = useState<string | null>(null);

    return (
        <Stack gap="tight" align="start">
            <Button
                variant="outline"
                onClick={() => {
                    setError(undefined);
                    setHasFailed(false);
                    dialog.setOpen(true);
                }}
            >
                {demo('renameFolder')}
            </Button>
            <LastAction value={saved} />
            <FormDialog
                open={dialog.isOpen}
                onOpenChange={dialog.setOpen}
                title={demo('renameTitle')}
                description={demo('renameDescription')}
                submitLabel={demo('save')}
                cancelLabel={demo('cancel')}
                closeLabel={demo('close')}
                isPending={dialog.isPending}
                error={error}
                onSubmit={() => {
                    setError(undefined);
                    dialog.run(() => {
                        if (failFirst && !hasFailed) {
                            setHasFailed(true);
                            setError(demo('serverError'));

                            return;
                        }

                        setSaved(name === '' ? demo('none') : name);
                        dialog.setOpen(false);
                    });
                }}
            >
                <TextField
                    name="folder-name"
                    label={demo('folderName')}
                    value={name}
                    onChange={setName}
                    placeholder={demo('folderPlaceholder')}
                />
            </FormDialog>
        </Stack>
    );
}

function DestructiveFormDialogDemo() {
    const { t } = useTranslation();
    const demo = useDemoText();
    const dialog = useDialogDemo();
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | undefined>(undefined);
    const [deleted, setDeleted] = useState<string | null>(null);

    return (
        <Stack gap="tight" align="start">
            <Button
                variant="destructive"
                onClick={() => {
                    setError(undefined);
                    setPassword('');
                    dialog.setOpen(true);
                }}
            >
                {demo('deleteAccount')}
            </Button>
            <LastAction value={deleted} />
            <FormDialog
                open={dialog.isOpen}
                onOpenChange={dialog.setOpen}
                tone="destructive"
                title={demo('deleteAccountTitle')}
                description={demo('deleteAccountDescription')}
                submitLabel={demo('deleteAccount')}
                cancelLabel={demo('cancel')}
                closeLabel={demo('close')}
                isPending={dialog.isPending}
                onSubmit={() => {
                    setError(undefined);
                    dialog.run(() => {
                        if (password !== 'demo-password') {
                            setError(demo('wrongPassword'));

                            return;
                        }

                        setDeleted(demo('deleteAccount'));
                        dialog.setOpen(false);
                    });
                }}
            >
                <PasswordField
                    name="demo-delete-password"
                    label={demo('deletePassword')}
                    description={demo('deletePasswordHint')}
                    value={password}
                    onChange={setPassword}
                    error={error}
                    autoComplete="current-password"
                    showPasswordLabel={t('auth.passwordField.show')}
                    hidePasswordLabel={t('auth.passwordField.hide')}
                    required
                />
            </FormDialog>
        </Stack>
    );
}

function ConflictDialogDemo() {
    const demo = useDemoText();
    const dialog = useDialogDemo();
    const [result, setResult] = useState<string | null>(null);

    return (
        <Stack gap="tight" align="start">
            <Button variant="outline" onClick={() => dialog.setOpen(true)}>
                {demo('saveStale')}
            </Button>
            <LastAction value={result} />
            <ConflictDialog
                open={dialog.isOpen}
                onOpenChange={dialog.setOpen}
                title={demo('conflictTitle')}
                description={demo('conflictDescription')}
                reloadLabel={demo('reload')}
                overwriteLabel={demo('overwrite')}
                isPending={dialog.isPending}
                onReload={() => {
                    setResult(demo('reload'));
                    dialog.setOpen(false);
                }}
                onOverwrite={() =>
                    dialog.run(() => {
                        setResult(demo('overwrite'));
                        dialog.setOpen(false);
                    })
                }
            />
        </Stack>
    );
}

function DialogDemo() {
    const demo = useDemoText();
    const [isOpen, setOpen] = useState(false);
    const [step, setStep] = useState<1 | 2>(1);
    const [result, setResult] = useState<string | null>(null);

    return (
        <Stack gap="tight" align="start">
            <Button
                variant="outline"
                onClick={() => {
                    setStep(1);
                    setOpen(true);
                }}
            >
                {demo('dialogOpen')}
            </Button>
            <LastAction value={result} />
            <Dialog
                open={isOpen}
                onOpenChange={setOpen}
                title={demo('dialogTitle')}
                description={demo('dialogDescription')}
                closeLabel={demo('close')}
                actions={
                    step === 1 ? (
                        <Button onClick={() => setStep(2)}>
                            {demo('dialogContinue')}
                        </Button>
                    ) : (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setStep(1)}
                            >
                                {demo('dialogBack')}
                            </Button>
                            <Button
                                onClick={() => {
                                    setResult(demo('dialogDone'));
                                    setOpen(false);
                                }}
                            >
                                {demo('dialogDone')}
                            </Button>
                        </>
                    )
                }
            >
                {step === 1 ? (
                    <TextField
                        name="demo-dialog-key"
                        label={demo('dialogKey')}
                        value="JBSW Y3DP EHPK 3PXP"
                        readOnly
                    />
                ) : (
                    <Text>{demo('dialogStepTwo')}</Text>
                )}
            </Dialog>
        </Stack>
    );
}

function ActionMenuDemo({
    items,
    align,
    disabled = false,
}: {
    items: (select: (label: string) => void) => ActionMenuItem[];
    align?: 'start' | 'end';
    disabled?: boolean;
}) {
    const demo = useDemoText();
    const [selected, setSelected] = useState<string | null>(null);

    return (
        <Stack gap="tight" align="start">
            <ActionMenu
                triggerLabel={demo('rowActions')}
                items={items(setSelected)}
                align={align}
                disabled={disabled}
            />
            <LastAction value={selected} />
        </Stack>
    );
}

function OrderableListDemo({
    initialItems,
    disabled = false,
}: {
    initialItems: OrderableListItem[];
    disabled?: boolean;
}) {
    const demo = useDemoText();
    const [items, setItems] = useState(initialItems);

    return (
        <OrderableList
            label={demo('orderLabel')}
            items={items}
            disabled={disabled}
            onReorder={(ids) =>
                setItems(
                    ids.flatMap(
                        (id) => items.find((item) => item.id === id) ?? [],
                    ),
                )
            }
        />
    );
}

/** ADM-12 — actions: ActionMenu, dialogs and OrderableList. */
function ActionsSection() {
    const demo = useDemoText();

    const menuItems = (select: (label: string) => void): ActionMenuItem[] => [
        {
            id: 'edit',
            label: demo('edit'),
            icon: Pencil,
            onSelect: () => select(demo('edit')),
        },
        {
            id: 'duplicate',
            label: demo('duplicate'),
            icon: Copy,
            onSelect: () => select(demo('duplicate')),
        },
        {
            id: 'archive',
            label: demo('archive'),
            icon: Archive,
            disabled: true,
            onSelect: () => select(demo('archive')),
        },
        {
            id: 'delete',
            label: demo('delete'),
            icon: Trash2,
            tone: 'destructive',
            onSelect: () => select(demo('delete')),
        },
    ];

    const longMenuItems = (
        select: (label: string) => void,
    ): ActionMenuItem[] => [
        {
            id: 'long',
            label: demo('longAction'),
            icon: Copy,
            onSelect: () => select(demo('longAction')),
        },
        {
            id: 'delete',
            label: demo('delete'),
            icon: Trash2,
            tone: 'destructive',
            onSelect: () => select(demo('delete')),
        },
    ];

    const orderItems: OrderableListItem[] = [
        { id: 'hero', label: demo('sections.hero'), meta: demo('visible') },
        {
            id: 'features',
            label: demo('sections.features'),
            meta: demo('visible'),
        },
        { id: 'faq', label: demo('sections.faq'), meta: demo('hidden') },
    ];

    const longOrderItems: OrderableListItem[] = [
        {
            id: 'long',
            label: demo('sections.long'),
            meta: demo('longMeta'),
        },
        ...orderItems.slice(0, 2),
    ];

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="ActionMenu"
                notApplicable={['loading', 'empty', 'error', 'noMedia']}
            >
                <ShowcaseState state="default" detail={demo('menuDetail')}>
                    <ActionMenuDemo items={menuItems} />
                </ShowcaseState>
                <ShowcaseState state="variants" detail="align=start">
                    <ActionMenuDemo items={menuItems} align="start" />
                </ShowcaseState>
                <ShowcaseState state="disabled">
                    <ActionMenuDemo items={menuItems} disabled />
                </ShowcaseState>
                <ShowcaseState state="longContent">
                    <ActionMenuDemo items={longMenuItems} />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="ConfirmDialog"
                notApplicable={['loading', 'empty', 'error', 'noMedia']}
            >
                <ShowcaseState state="default">
                    <ConfirmDialogDemo tone="default" />
                </ShowcaseState>
                <ShowcaseState state="destructive">
                    <ConfirmDialogDemo tone="destructive" />
                </ShowcaseState>
                <ShowcaseState state="pending">
                    <ConfirmDialogDemo tone="destructive" simulatePending />
                </ShowcaseState>
                <ShowcaseState state="longContent">
                    <ConfirmDialogDemo tone="default" long />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="FormDialog"
                notApplicable={['loading', 'empty', 'noMedia']}
            >
                <ShowcaseState state="pending" detail={demo('formDetail')}>
                    <FormDialogDemo />
                </ShowcaseState>
                <ShowcaseState state="error" detail={demo('formErrorDetail')}>
                    <FormDialogDemo failFirst />
                </ShowcaseState>
                <ShowcaseState
                    state="destructive"
                    detail={demo('destructiveFormDetail')}
                >
                    <DestructiveFormDialogDemo />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Dialog"
                notApplicable={['loading', 'empty', 'error', 'noMedia']}
            >
                <ShowcaseState state="default" detail={demo('dialogDetail')}>
                    <DialogDemo />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="ConflictDialog"
                notApplicable={['loading', 'empty', 'error', 'noMedia']}
            >
                <ShowcaseState state="conflict" detail={demo('conflictDetail')}>
                    <ConflictDialogDemo />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="OrderableList"
                layout="wide"
                notApplicable={['loading', 'empty', 'error', 'noMedia']}
            >
                <ShowcaseState state="default" fill>
                    <OrderableListDemo initialItems={orderItems} />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <OrderableListDemo initialItems={orderItems} disabled />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <OrderableListDemo initialItems={longOrderItems} />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const actionsFamily: ShowcaseFamily = {
    id: 'adm-12',
    titleKey: 'admin.designSystem.actions.title',
    descriptionKey: 'admin.designSystem.actions.description',
    Component: ActionsSection,
};
