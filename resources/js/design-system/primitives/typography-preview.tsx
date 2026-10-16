import type { ReactNode } from 'react';

export type TypographyPreviewProps = {
    /** Typography preset applied to the children (`data-typography`). */
    preset: App.Enums.TypographyPreset;
    children: ReactNode;
    className?: never;
    style?: never;
};

/**
 * Applies a typography preset of the site settings to its children only,
 * the same way `<html data-typography>` does for the whole page, so the
 * design-system showcase can compare presets side by side. The attribute is
 * always set, so `default` also overrides a non-default page preset.
 */
export function TypographyPreview({
    preset,
    children,
}: TypographyPreviewProps) {
    return <div data-typography={preset}>{children}</div>;
}
