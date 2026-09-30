import { Heading, Stack, Text } from '@/design-system/primitives';

/**
 * Title block of an auth screen, rendered by the page itself (not through
 * `setLayoutProps`) so the `<h1>` is already present in the SSR HTML.
 */
export default function AuthPageHeading({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
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
