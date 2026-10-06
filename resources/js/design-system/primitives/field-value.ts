/**
 * Internal (not in the barrel): the editable/read-only contract shared by
 * `TextField`, `TextareaField`, `NumberField` and `DateField`. An editable
 * field needs `onChange`; a read-only one (native `readonly`: visible,
 * selectable, submitted, not editable) may omit it.
 */
export type FieldValueProps =
    | {
          readOnly?: false;
          onChange: (value: string) => void;
      }
    | {
          /**
           * Visible, selectable and submitted with the form, but not editable
           * (native `readonly`, not `disabled`). Hides the required marker.
           */
          readOnly: true;
          onChange?: (value: string) => void;
      };
