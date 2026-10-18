import { useHttp } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import {
    Alert,
    Button,
    Dialog,
    IconButton,
    Inline,
    ItemList,
    Link,
    Stack,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { destroy, index, store } from '@/routes/admin/saved-views';

type ViewsData = App.Data.Admin.SavedViews.SavedListViewsData;
type View = App.Data.Admin.SavedViews.SavedListViewData;

export type SavedViewsMenuProps = {
    /** Route name of the list, e.g. `admin.articles.index`. */
    list: string;
};

/** Current query parameters of the list, without the page number. */
function currentQuery(): Record<string, string> {
    const query: Record<string, string> = {};

    new URLSearchParams(window.location.search).forEach((value, key) => {
        if (key !== 'page' && value !== '') {
            query[key] = value;
        }
    });

    return query;
}

/**
 * "Views" of an admin list: open a saved set of filters, search and sort,
 * save the current one under a name or remove one. Views are private to
 * the user; they load only once the dialog is opened.
 */
export function SavedViewsMenu({ list }: SavedViewsMenuProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                {t('admin.savedViews.open')}
            </Button>
            <Dialog
                open={open}
                onOpenChange={setOpen}
                title={t('admin.savedViews.title')}
                description={t('admin.savedViews.description')}
                closeLabel={t('actions.close')}
            >
                <SavedViewsPanel list={list} />
            </Dialog>
        </>
    );
}

/** Dialog body; mounted only while the dialog is open. */
function SavedViewsPanel({ list }: SavedViewsMenuProps) {
    const { t } = useTranslation();
    const [views, setViews] = useState<View[] | null>(null);
    const [loadFailed, setLoadFailed] = useState(false);
    const save = useHttp<
        { list: string; name: string; query: Record<string, string> },
        ViewsData
    >({
        list,
        name: '',
        query: {},
    });
    const remove = useHttp<Record<string, never>, ViewsData>({});

    const load = useCallback(async () => {
        try {
            const response = await fetch(index.url({ query: { list } }), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Saved views failed: ${response.status}`);
            }

            setViews(((await response.json()) as ViewsData).views);
            setLoadFailed(false);
        } catch {
            setLoadFailed(true);
        }
    }, [list]);

    useEffect(() => {
        void load();
    }, [load]);

    function saveCurrent() {
        save.transform((data) => ({ ...data, query: currentQuery() }));
        save.post(store.url()).then(
            (data) => {
                setViews(data.views);
                save.reset('name');
            },
            () => {},
        );
    }

    function removeView(view: View) {
        remove.delete(destroy.url(view.id)).then(
            (data) => setViews(data.views),
            () => {},
        );
    }

    return (
        <Stack gap="relaxed">
            {loadFailed ? (
                <Alert tone="danger" title={t('admin.savedViews.loadError')} />
            ) : views === null ? (
                <Text tone="muted">{t('admin.savedViews.loading')}</Text>
            ) : views.length === 0 ? (
                <Text tone="muted">{t('admin.savedViews.empty')}</Text>
            ) : (
                <ItemList
                    items={views.map((view) => ({
                        id: String(view.id),
                        content: (
                            <Link href={view.url} tone="default">
                                {view.name}
                            </Link>
                        ),
                        end: (
                            <IconButton
                                icon={Trash2}
                                variant="ghost"
                                size="sm"
                                ariaLabel={t('admin.savedViews.remove', {
                                    name: view.name,
                                })}
                                isPending={remove.processing}
                                onClick={() => removeView(view)}
                            />
                        ),
                    }))}
                />
            )}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    saveCurrent();
                }}
            >
                <Stack gap="tight">
                    <TextField
                        name="name"
                        label={t('admin.savedViews.nameLabel')}
                        description={t('admin.savedViews.nameHint')}
                        value={save.data.name}
                        onChange={(value) => save.setData('name', value)}
                        error={save.errors.name ?? save.errors.list}
                        required
                    />
                    <Inline gap="tight">
                        <Button type="submit" isPending={save.processing}>
                            {t('admin.savedViews.save')}
                        </Button>
                    </Inline>
                </Stack>
            </form>
        </Stack>
    );
}
