/**
 * Moves focus to the first field (in visual order) that has a validation
 * error after a rejected submit, so keyboard and screen reader users land on
 * the message instead of staying on the submit button. `fieldIds` lists the
 * form's field names in visual order; each name must also be the control id.
 */
export function focusFirstError(
    errors: Partial<Record<string, string>>,
    fieldIds: readonly string[],
): void {
    const firstInvalid = fieldIds.find((fieldId) => Boolean(errors[fieldId]));

    if (firstInvalid) {
        document.getElementById(firstInvalid)?.focus();
    }
}
