// DESCRIPTION: Single generated-content count for the Content Forge dashboard.
// DESCRIPTION: Renders as a link so every number is a route into the generator that made it.

/**
 * StatTile component.
 *
 * @param {Object} props
 * @param {string} props.label     - Object type, shown above the number.
 * @param {number} props.value     - The count.
 * @param {string} props.caption   - Line under the number.
 * @param {string} props.href      - Generator page this tile links to.
 * @param {JSX.Element} props.icon - Glyph shown in a brand-tinted chip.
 * @return {JSX.Element} The StatTile component.
 */
export default function StatTile({ label, value = 0, caption, href, icon }) {
    const isEmpty = !value;

    return (
        <a
            href={href}
            className="cforge-block cforge-no-underline cforge-bg-white cforge-border cforge-border-border cforge-rounded-xl cforge-shadow-sm cforge-p-5 hover:cforge-border-primary focus-visible:cforge-outline focus-visible:cforge-outline-2 focus-visible:cforge-outline-offset-2 focus-visible:cforge-outline-primary cforge-transition-colors"
        >
            <span className="cforge-flex cforge-items-center cforge-gap-2">
                {icon && (
                    <span className="cforge-flex cforge-items-center cforge-justify-center cforge-w-7 cforge-h-7 cforge-shrink-0 cforge-rounded-lg cforge-bg-brand-50 cforge-text-primaryHover">
                        {icon}
                    </span>
                )}
                <span className="cforge-text-xxs cforge-font-semibold cforge-uppercase cforge-tracking-wide cforge-text-text-secondary">
                    {label}
                </span>
            </span>

            <span
                className={`cforge-block cforge-mt-3 cforge-text-2xl cforge-font-bold cforge-leading-none ${
                    isEmpty ? 'cforge-text-text-secondary/50' : 'cforge-text-text-primary'
                }`}
            >
                {value.toLocaleString()}
            </span>

            {/* Without this the number reads as a site-wide total rather than our own. */}
            <span className="cforge-block cforge-mt-1 cforge-text-xs cforge-text-text-secondary">
                {caption}
            </span>
        </a>
    );
}
