import { Heading } from './heading';
import { Stack } from './stack';
import { Text } from './text';

export type AuthHeadingProps = {
    title: string;
    description: string;
    className?: never;
    style?: never;
};

/**
 * Title block of an auth screen: the page `<h1>` and a short description.
 * Rendered by the page itself (not through `setLayoutProps`), so the heading
 * is already present in the first SSR HTML.
 */
export function AuthHeading({ title, description }: AuthHeadingProps) {
    return (
        <Stack gap="tight" align="center">
            <Heading level={1} variant="subsection" align="center">
                {title}
            </Heading>
            <Text tone="muted" align="center">
                {description}
            </Text>
        </Stack>
    );
}
