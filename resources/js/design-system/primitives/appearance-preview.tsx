import type { ReactNode } from 'react';

export type AppearancePreviewProps = {
    /** Accent preset applied to the children (`data-accent`). */
    accent: App.Enums.AccentColor;
    /** Typography preset applied to the children (`data-typography`). */
    typography: App.Enums.TypographyPreset;
    /** Accessible description of the sample, e.g. the skin it shows. */
    label: string;
    children: ReactNode;
    className?: never;
    style?: never;
};

/**
 * A sample of the public site in an accent and typography preset, for
 * comparing site skins side by side. The box re-declares the public tokens
 * of the current theme (see `[data-appearance-preview]` in app.css), so it
 * shows the public look even inside the admin surface. The sample is inert:
 * its buttons and links are pictures, not controls.
 */
export function AppearancePreview({
    accent,
    typography,
    label,
    children,
}: AppearancePreviewProps) {
    return (
        <div
            data-appearance-preview=""
            data-accent={accent}
            data-typography={typography}
            role="img"
            aria-label={label}
            inert
            className="bg-background text-foreground border-border-subtle rounded-lg border p-6"
        >
            {children}
        </div>
    );
}
