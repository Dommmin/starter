import { useState } from 'react';
import { useTranslation } from '@/i18n';
import { Button } from './button';
import { Dialog } from './dialog';
import { SwitchField } from './switch-field';

type ConsentCategory = App.Enums.ConsentCategory;

export type CookieSettingsDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Optional categories offered by the site. */
    categories: ConsentCategory[];
    /** Currently allowed categories; nothing is pre-selected for a new visitor. */
    granted: ConsentCategory[];
    onSave: (granted: ConsentCategory[]) => void;
    className?: never;
    style?: never;
};

/**
 * Per-category cookie choice: necessary cookies are always on (disabled
 * switch), every optional category starts as the visitor left it, off
 * when undecided. Loaded lazily by `CookieConsent`.
 */
export function CookieSettingsDialog({
    open,
    onOpenChange,
    categories,
    granted,
    onSave,
}: CookieSettingsDialogProps) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState<ConsentCategory[]>(granted);

    const toggle = (category: ConsentCategory, checked: boolean) => {
        setSelected((current) =>
            checked
                ? [...current.filter((value) => value !== category), category]
                : current.filter((value) => value !== category),
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('cookies.dialogTitle')}
            description={t('cookies.dialogDescription')}
            closeLabel={t('cookies.close')}
            actions={
                <>
                    <Button
                        variant="outline"
                        onClick={() => onSave(categories)}
                    >
                        {t('cookies.acceptAll')}
                    </Button>
                    <Button
                        onClick={() =>
                            onSave(
                                categories.filter((category) =>
                                    selected.includes(category),
                                ),
                            )
                        }
                    >
                        {t('cookies.save')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <SwitchField
                    name="cookies-necessary"
                    label={t('cookies.categories.necessary.label')}
                    description={t('cookies.categories.necessary.description')}
                    checked
                    disabled
                    onChange={() => {}}
                />
                {categories.map((category) => (
                    <SwitchField
                        key={category}
                        name={`cookies-${category}`}
                        label={t(`cookies.categories.${category}.label`)}
                        description={t(
                            `cookies.categories.${category}.description`,
                        )}
                        checked={selected.includes(category)}
                        onChange={(checked) => toggle(category, checked)}
                    />
                ))}
            </div>
        </Dialog>
    );
}
