// DESCRIPTION: Author assignment control with Me / Specific / Random modes.
// Shared by the Pages/Posts and Custom Post Types generation forms.
import { __ } from '@wordpress/i18n';

/**
 * AuthorSelect - choose how generated posts are attributed to authors.
 *
 * Props:
 * - authors: Array<{ value: number|string, label: string }> (eligible authors)
 * - mode: 'me' | 'specific' | 'random'
 * - onModeChange: (mode: string) => void
 * - selected: Array<number> (selected author IDs, used when mode === 'specific')
 * - onSelectedChange: (ids: Array<number>) => void
 */
export default function AuthorSelect({
    authors = [],
    mode = 'me',
    onModeChange,
    selected = [],
    onSelectedChange,
}) {
    const hasAuthors = Array.isArray(authors) && authors.length > 0;

    const handleMultiChange = (e) => {
        const ids = Array.from(e.target.selectedOptions).map((opt) => parseInt(opt.value, 10));
        onSelectedChange(ids);
    };

    const radioOption = (value, label, disabled = false) => (
        <label
            className={`cforge-flex cforge-items-center cforge-gap-2 ${disabled ? 'cforge-opacity-50 cforge-cursor-not-allowed' : 'cforge-cursor-pointer'}`}
        >
            <input
                type="radio"
                name="cforge-author-mode"
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
            <label className="cforge-block cforge-mb-2 cforge-font-medium">
                {__('Assign Author', 'content-forge')}
            </label>
            <div className="cforge-flex cforge-flex-col cforge-gap-2">
                {radioOption('me', __('Me (current user)', 'content-forge'))}
                {radioOption('specific', __('Specific authors', 'content-forge'), !hasAuthors)}
                {radioOption('random', __('Random authors', 'content-forge'), !hasAuthors)}
            </div>

            {mode === 'specific' && hasAuthors && (
                <div className="cforge-mt-3">
                    <select
                        multiple
                        value={selected.map(String)}
                        onChange={handleMultiChange}
                        className="cforge-w-full cforge-p-2 cforge-border cforge-border-gray-300 cforge-rounded cforge-min-h-[8rem]"
                        aria-label={__('Select authors', 'content-forge')}
                    >
                        {authors.map((author) => (
                            <option key={author.value} value={author.value}>
                                {author.label}
                            </option>
                        ))}
                    </select>
                    <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                        {__('Posts are distributed randomly among the selected authors. Hold Ctrl/Cmd to select more than one.', 'content-forge')}
                    </p>
                </div>
            )}

            {mode === 'random' && hasAuthors && (
                <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-2">
                    {__('Posts are distributed randomly among all eligible authors.', 'content-forge')}
                </p>
            )}

            {!hasAuthors && (
                <p className="cforge-text-sm cforge-text-yellow-600 cforge-mt-2">
                    {__('No other eligible authors found. Posts will be assigned to you.', 'content-forge')}
                </p>
            )}
        </div>
    );
}
