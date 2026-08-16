// DESCRIPTION: Shared button for the Content Forge admin UI.
// DESCRIPTION: Solid fills read as actions; see Badge for the tinted state counterpart.

const VARIANTS = {
    primary: 'cforge-bg-primary cforge-text-white hover:cforge-bg-primaryHover focus-visible:cforge-outline-primary',
    secondary: 'cforge-bg-tertiary cforge-text-text-primary hover:cforge-bg-border focus-visible:cforge-outline-primary',
    danger: 'cforge-bg-error cforge-text-white hover:cforge-bg-errorHover focus-visible:cforge-outline-error',
    ghost: 'cforge-bg-white cforge-text-text-secondary cforge-border cforge-border-border hover:cforge-border-primary hover:cforge-text-primaryHover focus-visible:cforge-outline-primary',
};

const SIZES = {
    sm: 'cforge-px-3 cforge-py-1.5 cforge-text-xs',
    md: 'cforge-px-4 cforge-py-2 cforge-text-sm',
};

/**
 * Button component.
 *
 * @param {Object}   props
 * @param {string}   props.variant   - primary | secondary | danger | ghost.
 * @param {string}   props.size      - sm | md.
 * @param {string}   props.type      - Native button type.
 * @param {boolean}  props.disabled  - Disables the button and dims it.
 * @param {string}   props.className - Extra classes appended last.
 * @param {Function} props.onChange  - Unused; native handlers pass through via rest.
 * @return {JSX.Element} The Button component.
 */
export default function Button({
    variant = 'primary',
    size = 'md',
    type = 'button',
    disabled = false,
    className = '',
    children,
    ...rest
}) {
    const classes = [
        'cforge-rounded cforge-font-semibold cforge-cursor-pointer cforge-transition-colors',
        'focus-visible:cforge-outline focus-visible:cforge-outline-2 focus-visible:cforge-outline-offset-2',
        'disabled:cforge-opacity-50 disabled:cforge-cursor-not-allowed',
        SIZES[size] || SIZES.md,
        VARIANTS[variant] || VARIANTS.primary,
        className,
    ].filter(Boolean).join(' ');

    return (
        <button type={type} className={classes} disabled={disabled} {...rest}>
            {children}
        </button>
    );
}
