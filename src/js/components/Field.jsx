// DESCRIPTION: Label, control and validation message wrapper for Content Forge forms.
// DESCRIPTION: Collapses the three error-text spellings that were in use into one.

/**
 * Border and outline classes for a control in an invalid state.
 *
 * @param {*} error - Truthy when the field has a validation error.
 * @return {string} Classes to append to the control, or an empty string.
 */
export const errorClass = (error) => (error ? 'cforge-border-error cforge-outline-error' : '');

/**
 * Field component. Wraps any control — input, select, or a composite like MultiSelect.
 *
 * @param {Object} props
 * @param {string} props.label     - Label text. Omit for controls that render their own.
 * @param {string} props.htmlFor   - id of the control, so the label is clickable.
 * @param {string} props.hint      - Helper text shown under the control when there is no error.
 * @param {string} props.error     - Validation message. Replaces the hint when present.
 * @param {string} props.className - Extra classes. Field carries no margin of its own;
 *                                   the container spaces its children.
 * @return {JSX.Element} The Field component.
 */
export default function Field({
    label,
    htmlFor,
    hint,
    error,
    className = '',
    children,
}) {
    return (
        <div className={className || undefined}>
            {label && (
                <label
                    htmlFor={htmlFor}
                    className="cforge-block cforge-mb-1 cforge-text-sm cforge-font-medium cforge-text-text-primary"
                >
                    {label}
                </label>
            )}
            {children}
            {error ? (
                <p className="cforge-mt-1 cforge-mb-0 cforge-text-sm cforge-text-error">{error}</p>
            ) : hint ? (
                <p className="cforge-mt-1 cforge-mb-0 cforge-text-sm cforge-text-text-secondary">{hint}</p>
            ) : null}
        </div>
    );
}
