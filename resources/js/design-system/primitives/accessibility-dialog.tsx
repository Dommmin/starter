import {
    useAccessibilityPreferences,
    type TextSize,
} from '@/hooks/use-accessibility-preferences';
import { useTranslation } from '@/i18n';
import { Button } from './button';
import { Dialog } from './dialog';
import { RadioGroupField } from './radio-group-field';
import { SwitchField } from './switch-field';

export type AccessibilityDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const textSizes: TextSize[] = ['default', 'large', 'larger'];

const isTextSize = (value: string): value is TextSize =>
    (textSizes as string[]).includes(value);

/**
 * Display preferences of the public site: every change applies at once
 * and is remembered in this browser. Loaded lazily by
 * `AccessibilitySwitcher` (see `lazy-popup.ts`).
 */
export default function AccessibilityDialog({
    open,
    onOpenChange,
}: AccessibilityDialogProps) {
    const { t } = useTranslation();
    const { preferences, updatePreferences, resetPreferences } =
        useAccessibilityPreferences();

    return (
        <Dialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('accessibility.title')}
            description={t('accessibility.description')}
            closeLabel={t('accessibility.close')}
            actions={
                <>
                    {/* Never disabled: focus would drop to <body> after a reset. */}
                    <Button variant="outline" onClick={resetPreferences}>
                        {t('accessibility.reset')}
                    </Button>
                    <Button onClick={() => onOpenChange(false)}>
                        {t('accessibility.done')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <RadioGroupField
                    name="a11y-text-size"
                    label={t('accessibility.textSize.label')}
                    value={preferences.textSize}
                    onChange={(value) => {
                        if (isTextSize(value)) {
                            updatePreferences({ textSize: value });
                        }
                    }}
                    options={textSizes.map((size) => ({
                        value: size,
                        label: t(`accessibility.textSize.${size}`),
                    }))}
                />
                <SwitchField
                    name="a11y-contrast"
                    label={t('accessibility.contrast.label')}
                    description={t('accessibility.contrast.description')}
                    checked={preferences.highContrast}
                    onChange={(checked) =>
                        updatePreferences({ highContrast: checked })
                    }
                />
                <SwitchField
                    name="a11y-motion"
                    label={t('accessibility.motion.label')}
                    description={t('accessibility.motion.description')}
                    checked={preferences.reduceMotion}
                    onChange={(checked) =>
                        updatePreferences({ reduceMotion: checked })
                    }
                />
                <SwitchField
                    name="a11y-links"
                    label={t('accessibility.links.label')}
                    description={t('accessibility.links.description')}
                    checked={preferences.underlineLinks}
                    onChange={(checked) =>
                        updatePreferences({ underlineLinks: checked })
                    }
                />
            </div>
        </Dialog>
    );
}
