// DESCRIPTION: Status pill for the Content Forge admin UI.
// DESCRIPTION: Always tinted and never clickable, which is what separates it from a Button.

// Tints are Tailwind's default 50/200 steps, matching the values HFE uses for badges.
const VARIANTS = {
    success: 'cforge-bg-green-50 cforge-border-green-200 cforge-text-success',
    error: 'cforge-bg-red-50 cforge-border-red-200 cforge-text-error',
    warning: 'cforge-bg-yellow-50 cforge-border-yellow-200 cforge-text-warning',
    neutral: 'cforge-bg-tertiary cforge-border-border cforge-text-text-primary',
    brand: 'cforge-bg-brand-50 cforge-border-primary/25 cforge-text-primaryHover',
};

/**
 * Badge component.
 *
 * @param {Object}  props
 * @param {string}  props.status    - success | error | warning | neutral | brand.
 * @param {boolean} props.dot       - Shows a leading dot in the current text color.
 * @param {string}  props.className - Extra classes appended last.
 * @return {JSX.Element} The Badge component.
 */
export default function Badge({ status = 'neutral', dot = false, className = '', children }) {
    const classes = [
        'cforge-inline-flex cforge-items-center cforge-gap-1.5 cforge-border',
        'cforge-px-2 cforge-py-0.5 cforge-rounded-full cforge-text-xxs cforge-font-semibold',
        VARIANTS[status] || VARIANTS.neutral,
        className,
    ].filter(Boolean).join(' ');

    return (
        <span className={classes}>
            {dot && (
                <span className="cforge-w-1.5 cforge-h-1.5 cforge-rounded-full cforge-bg-current" aria-hidden="true" />
            )}
            {children}
        </span>
    );
}
