import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * MultiSelect - A simple, accessible multi-select component.
 *
 * Props:
 * - options: Array<{ value: string, label: string }>
 * - value: Array<string>
 * - onChange: (selected: Array<string>) => void
 * - label: string
 * - placeholder: string
 */
export default function MultiSelect({ options = [], value = [], onChange, label, placeholder }) {
    const selectRef = useRef();

    const handleChange = (e) => {
        const selected = Array.from(e.target.selectedOptions).map((opt) => opt.value);
        onChange(selected);
    };

    return (
        <div className="cforge-multiselect">
            {label && (
                <label className="cforge-block cforge-mb-1 cforge-font-semibold" htmlFor="cforge-multiselect">
                    {label}
                </label>
            )}
            <select
                id="cforge-multiselect"
                ref={selectRef}
                multiple
                className="cforge-w-full cforge-p-2 cforge-border cforge-border-gray-300 cforge-rounded"
                value={value}
                onChange={handleChange}
                aria-label={label || placeholder || __('Select options', 'cforge')}
            >
                {placeholder && options.length === 0 && (
                    <option disabled>{placeholder}</option>
                )}
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
        </div>
    );
} 