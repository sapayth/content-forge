// DESCRIPTION: Taxonomy assignment section for the generation forms — loads the
// DESCRIPTION: assignable taxonomies for a post type and collects per-taxonomy options.
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import TermSelect from './TermSelect';

const defaultOption = { mode: 'none', terms: [], min: 1, max: 1 };

/**
 * Load the taxonomies assignable to a post type and hold the user's choices.
 *
 * Returns { taxonomies, options, setOption, payload }. `payload` is ready to be
 * merged into the bulk-create request body as `taxonomy_options`; it is empty
 * when nothing is selected, which preserves the previous behaviour.
 *
 * @param {string} postType Post type slug the form is targeting.
 */
export function useTaxonomyAssignment(postType) {
    const [taxonomies, setTaxonomies] = useState([]);
    const [options, setOptions] = useState({});

    useEffect(() => {
        if (!postType) {
            return undefined;
        }

        let cancelled = false;

        // Choices are per post type, so drop them whenever the target changes.
        setOptions({});

        apiFetch({ path: `taxonomy/assignable?post_type=${encodeURIComponent(postType)}` })
            .then((res) => {
                if (!cancelled) {
                    setTaxonomies(Array.isArray(res?.taxonomies) ? res.taxonomies : []);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setTaxonomies([]);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [postType]);

    const setOption = (slug, patch) => {
        setOptions((prev) => ({
            ...prev,
            [slug]: { ...defaultOption, ...prev[slug], ...patch },
        }));
    };

    const payload = {};
    Object.entries(options).forEach(([slug, option]) => {
        if (!option || option.mode === 'none') {
            return;
        }
        if (option.mode === 'specific' && (!option.terms || option.terms.length === 0)) {
            return;
        }
        payload[slug] = {
            mode: option.mode,
            min: option.min,
            max: option.max,
        };
        if (option.mode === 'specific') {
            payload[slug].terms = option.terms;
        }
    });

    return { taxonomies, options, setOption, payload };
}

/**
 * TaxonomySection - renders one TermSelect per assignable taxonomy.
 *
 * Props:
 * - taxonomies: Array (from useTaxonomyAssignment)
 * - options: Object (from useTaxonomyAssignment)
 * - setOption: (slug, patch) => void (from useTaxonomyAssignment)
 */
export default function TaxonomySection({ taxonomies = [], options = {}, setOption }) {
    if (!taxonomies.length) {
        return null;
    }

    return (
        <div className="cforge-flex cforge-flex-col cforge-gap-3">
            <label className="cforge-block cforge-font-medium">
                {__('Taxonomies', 'content-forge')}
            </label>
            {taxonomies.map((taxonomy) => {
                const option = options[taxonomy.slug] || defaultOption;
                return (
                    <TermSelect
                        key={taxonomy.slug}
                        taxonomy={taxonomy}
                        mode={option.mode}
                        onModeChange={(mode) => setOption(taxonomy.slug, { mode })}
                        selected={option.terms}
                        onSelectedChange={(terms) => setOption(taxonomy.slug, { terms })}
                        range={{ min: option.min, max: option.max }}
                        onRangeChange={(range) => setOption(taxonomy.slug, range)}
                    />
                );
            })}
        </div>
    );
}
