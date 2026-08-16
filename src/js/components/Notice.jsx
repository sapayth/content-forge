// DESCRIPTION: Inline status message for the Content Forge admin UI.
// DESCRIPTION: Tinted rather than solid, so a result never reads as a clickable action.

// Tints are Tailwind's default 50/200 steps, which match the values HFE uses for alerts.
const VARIANTS = {
    success: 'cforge-bg-green-50 cforge-border-green-200 cforge-text-success',
    error: 'cforge-bg-red-50 cforge-border-red-200 cforge-text-error',
    warning: 'cforge-bg-yellow-50 cforge-border-yellow-200 cforge-text-warning',
    info: 'cforge-bg-sky-50 cforge-border-sky-200 cforge-text-sky-700',
};

/**
 * Notice component.
 *
 * @param {Object} props
 * @param {string} props.status    - success | error | warning | info.
 * @param {string} props.className - Extra classes appended last.
 * @return {JSX.Element|null} The Notice component, or null when empty.
 */
export default function Notice({ status = 'info', className = '', children }) {
    if (!children) {
        return null;
    }

    const classes = [
        'cforge-mb-4 cforge-p-3 cforge-rounded-lg cforge-border cforge-text-sm',
        VARIANTS[status] || VARIANTS.info,
        className,
    ].filter(Boolean).join(' ');

    return (
        <div className={classes} role="status">
            {children}
        </div>
    );
}
