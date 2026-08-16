// DESCRIPTION: Panel container for the Content Forge admin UI.
// DESCRIPTION: Hairline border, generous radius and near-flat shadow, following the HFE card language.

/**
 * Card component.
 *
 * @param {Object}      props
 * @param {string}      props.title     - Heading shown in the card header.
 * @param {string}      props.subtitle  - Supporting line under the heading.
 * @param {JSX.Element} props.icon      - Optional glyph, placed in a brand-tinted chip.
 * @param {JSX.Element} props.footer    - Optional action row pinned to the bottom.
 * @param {string}      props.className - Extra classes appended last.
 * @return {JSX.Element} The Card component.
 */
export default function Card({
    title,
    subtitle,
    icon,
    footer,
    className = '',
    children,
}) {
    const classes = [
        'cforge-bg-white cforge-border cforge-border-border cforge-rounded-xl cforge-shadow-sm cforge-overflow-hidden',
        className,
    ].filter(Boolean).join(' ');

    return (
        <div className={classes}>
            {(title || icon) && (
                <div className="cforge-flex cforge-items-center cforge-gap-3 cforge-px-5 cforge-py-4 cforge-border-0 cforge-border-b cforge-border-solid cforge-border-border">
                    {icon && (
                        <span className="cforge-flex cforge-items-center cforge-justify-center cforge-w-9 cforge-h-9 cforge-shrink-0 cforge-rounded-lg cforge-bg-brand-50 cforge-text-primaryHover">
                            {icon}
                        </span>
                    )}
                    <div className="cforge-min-w-0">
                        {title && (
                            <h3 className="cforge-m-0 cforge-text-sm cforge-font-semibold cforge-text-text-primary">{title}</h3>
                        )}
                        {subtitle && (
                            <p className="cforge-m-0 cforge-mt-0.5 cforge-text-xs cforge-text-text-secondary">{subtitle}</p>
                        )}
                    </div>
                </div>
            )}

            <div className="cforge-p-5 cforge-space-y-4">{children}</div>

            {footer && (
                <div className="cforge-flex cforge-items-center cforge-justify-end cforge-gap-2 cforge-px-5 cforge-py-4 cforge-bg-tertiary/40 cforge-border-0 cforge-border-t cforge-border-solid cforge-border-border">
                    {footer}
                </div>
            )}
        </div>
    );
}
