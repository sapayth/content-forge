// DESCRIPTION: Taxonomy term assignment control with None / Specific / Random modes.
// Shared by the Pages/Posts and Custom Post Types generation forms.
import { __, sprintf } from '@wordpress/i18n';

/**
 * TermSelect - choose how generated content is assigned to a taxonomy's terms.
 *
 * Props:
 * - taxonomy: { slug, label, hierarchical, total, truncated, terms: Array<{id, name, count}> }
 * - mode: 'none' | 'specific' | 'random'
 * - onModeChange: (mode: string) => void
 * - selected: Array<number> (term IDs, used when mode === 'specific')
 * - onSelectedChange: (ids: Array<number>) => void
 * - range: { min: number, max: number }
 * - onRangeChange: ({ min, max }) => void
 */
export default function TermSelect({
    taxonomy,
    mode = 'none',
    onModeChange,
    selected = [],
    onSelectedChange,
    range = { min: 1, max: 1 },
    onRangeChange,
}) {
    const terms = Array.isArray(taxonomy?.terms) ? taxonomy.terms : [];
    const hasTerms = terms.length > 0;
    const radioName = `cforge-term-mode-${taxonomy.slug}`;

    const handleMultiChange = (e) => {
        const ids = Array.from(e.target.selectedOptions).map((opt) => parseInt(opt.value, 10));
        onSelectedChange(ids);
    };

    const handleRangeChange = (key) => (e) => {
        const next = parseInt(e.target.value, 10);
        onRangeChange({ ...range, [key]: Number.isNaN(next) ? 1 : Math.max(1, next) });
    };

    const radioOption = (value, label, disabled = false) => (
        <label
            key={value}
            className={`cforge-flex cforge-items-center cforge-gap-2 ${disabled ? 'cforge-opacity-50 cforge-cursor-not-allowed' : 'cforge-cursor-pointer'}`}
        >
            <input
                type="radio"
                name={radioName}
                value={value}
                checked={mode === value}
                disabled={disabled}
                onChange={() => onModeChange(value)}
            />
            <span className="cforge-text-sm">{label}</span>
        </label>
    );

    return (
        <div className="cforge-border cforge-border-gray-200 cforge-rounded-lg cforge-p-4 cforge-bg-gray-50">
            <label className="cforge-block cforge-mb-2 cforge-font-medium">{taxonomy.label}</label>

            <div className="cforge-flex cforge-flex-col cforge-gap-2">
                {radioOption('none', __('Do not assign', 'content-forge'))}
                {radioOption('specific', __('Specific terms', 'content-forge'), !hasTerms)}
                {radioOption('random', __('Random from existing', 'content-forge'), !hasTerms)}
            </div>

            {mode === 'specific' && hasTerms && (
                <div className="cforge-mt-3">
                    <select
                        multiple
                        value={selected.map(String)}
                        onChange={handleMultiChange}
                        className="cforge-w-full cforge-p-2 cforge-border cforge-border-gray-300 cforge-rounded cforge-min-h-[8rem]"
                        aria-label={sprintf(
                            /* translators: %s: taxonomy name, e.g. Categories */
                            __('Select %s', 'content-forge'),
                            taxonomy.label
                        )}
                    >
                        {terms.map((term) => (
                            <option key={term.id} value={term.id}>
                                {term.name}
                            </option>
                        ))}
                    </select>
                    <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                        {__('Hold Ctrl/Cmd to select more than one.', 'content-forge')}
                    </p>
                    {taxonomy.truncated && (
                        <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                            {sprintf(
                                /* translators: 1: number of terms shown, 2: total number of terms */
                                __('Showing the first %1$d of %2$d terms. Use "Random from existing" to draw from all of them.', 'content-forge'),
                                terms.length,
                                taxonomy.total
                            )}
                        </p>
                    )}
                </div>
            )}

            {mode === 'random' && hasTerms && (
                <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-2">
                    {sprintf(
                        /* translators: %d: number of existing terms */
                        __('Terms are drawn at random from all %d existing terms.', 'content-forge'),
                        taxonomy.total
                    )}
                </p>
            )}

            {mode !== 'none' && hasTerms && (
                <div className="cforge-mt-3 cforge-flex cforge-items-end cforge-gap-3">
                    <div>
                        <label
                            className="cforge-block cforge-text-sm cforge-mb-1"
                            htmlFor={`cforge-term-min-${taxonomy.slug}`}
                        >
                            {__('Min per post', 'content-forge')}
                        </label>
                        <input
                            id={`cforge-term-min-${taxonomy.slug}`}
                            type="number"
                            min="1"
                            max="20"
                            value={range.min}
                            onChange={handleRangeChange('min')}
                            className="cforge-w-24 cforge-p-2 cforge-border cforge-border-gray-300 cforge-rounded"
                        />
                    </div>
                    <div>
                        <label
                            className="cforge-block cforge-text-sm cforge-mb-1"
                            htmlFor={`cforge-term-max-${taxonomy.slug}`}
                        >
                            {__('Max per post', 'content-forge')}
                        </label>
                        <input
                            id={`cforge-term-max-${taxonomy.slug}`}
                            type="number"
                            min="1"
                            max="20"
                            value={range.max}
                            onChange={handleRangeChange('max')}
                            className="cforge-w-24 cforge-p-2 cforge-border cforge-border-gray-300 cforge-rounded"
                        />
                    </div>
                </div>
            )}

            {!hasTerms && (
                <p className="cforge-text-sm cforge-text-yellow-600 cforge-mt-2">
                    {__('No terms exist yet. Generate some on the Taxonomy screen first.', 'content-forge')}
                </p>
            )}
        </div>
    );
}
