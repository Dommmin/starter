import type { FieldAssist } from '@/design-system/primitives';

/**
 * Helpers of one field that are available to the user, e.g. "Suggest" and
 * "Improve text"; `undefined` when none is.
 */
export function fieldAssists(
    ...assists: (FieldAssist | undefined)[]
): FieldAssist[] | undefined {
    const available = assists.filter(
        (assist): assist is FieldAssist => assist !== undefined,
    );

    return available.length > 0 ? available : undefined;
}
