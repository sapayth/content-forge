// DESCRIPTION: Cross-promotion card for the plugin author's other free WordPress.org plugins.
// DESCRIPTION: Installs go through plugins_api() server-side, so downloads always come from WordPress.org.

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import Card from './Card';
import Button from './Button';
import Notice from './Notice';

// Copy leads with what the site owner gains; the feature list backs the claim up.
// No ratings or install counts — we do not have that data, so we do not show it.
const PLUGINS = [
    {
        slug: 'nemtly-booking',
        name: __('Nemtly Booking', 'content-forge'),
        headline: __('Start taking paid appointments on your site', 'content-forge'),
        body: __(
            'Real-time availability, Stripe checkout and Google Calendar sync — clients book and pay themselves instead of emailing you to find a time.',
            'content-forge'
        ),
        icon: '📅',
    },
    {
        slug: 'nemtly-jobs',
        name: __('Nemtly Jobs', 'content-forge'),
        headline: __('Turn your site into a job board that earns', 'content-forge'),
        body: __(
            'Employers post openings and pay for listings, candidates apply and track progress — a new revenue line from the audience you already have.',
            'content-forge'
        ),
        icon: '💼',
    },
];

/**
 * PromoPlugins component.
 *
 * @param {Object} props
 * @param {Array}  props.plugins - Install state per slug, from the dashboard endpoint.
 * @return {JSX.Element} The PromoPlugins component.
 */
export default function PromoPlugins({ plugins = [] }) {
    const [state, setState] = useState(() =>
        plugins.reduce((acc, p) => ({ ...acc, [p.slug]: p }), {})
    );
    const [busy, setBusy] = useState('');
    const [error, setError] = useState('');

    const canInstall = !!window.cforge?.canInstallPlugins;

    const install = async (slug) => {
        setBusy(slug);
        setError('');

        try {
            await apiFetch({ path: 'dashboard/install', method: 'POST', data: { slug } });
            setState((prev) => ({ ...prev, [slug]: { slug, installed: true, active: true } }));
        } catch (e) {
            setError(e.message || __('The plugin could not be installed.', 'content-forge'));
        } finally {
            setBusy('');
        }
    };

    return (
        <Card
            title={__('Add a revenue stream to your site', 'content-forge')}
            subtitle={__('Free plugins from the author of Content Forge', 'content-forge')}
        >
            <Notice status="error">{error}</Notice>

            <div className="cforge-divide-y cforge-divide-border">
                {PLUGINS.map((plugin, index) => {
                    const status = state[plugin.slug] || {};
                    const isActive = !!status.active;
                    const isBusy = busy === plugin.slug;

                    return (
                        <div
                            key={plugin.slug}
                            className={`cforge-flex cforge-items-start cforge-gap-4 ${index ? 'cforge-pt-4 cforge-mt-4' : ''}`}
                        >
                            <span
                                className="cforge-flex cforge-items-center cforge-justify-center cforge-w-10 cforge-h-10 cforge-shrink-0 cforge-rounded-lg cforge-bg-brand-50 cforge-text-lg"
                                aria-hidden="true"
                            >
                                {plugin.icon}
                            </span>

                            <div className="cforge-min-w-0 cforge-flex-1">
                                <h4 className="cforge-m-0 cforge-text-sm cforge-font-semibold cforge-text-text-primary">
                                    {plugin.headline}
                                </h4>
                                <p className="cforge-m-0 cforge-mt-1 cforge-text-sm cforge-text-text-secondary">
                                    {plugin.body}
                                </p>
                                <a
                                    href={`https://wordpress.org/plugins/${plugin.slug}/`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="cforge-inline-block cforge-mt-2 cforge-text-xs cforge-text-text-secondary"
                                >
                                    {plugin.name}
                                </a>
                            </div>

                            <div className="cforge-shrink-0">
                                {isActive && (
                                    <span className="cforge-text-xs cforge-font-semibold cforge-text-success">
                                        {__('✓ Active', 'content-forge')}
                                    </span>
                                )}

                                {!isActive && canInstall && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        disabled={isBusy}
                                        onClick={() => install(plugin.slug)}
                                    >
                                        {isBusy
                                            ? __('Installing…', 'content-forge')
                                            : status.installed
                                                ? __('Activate', 'content-forge')
                                                : __('Install', 'content-forge')}
                                    </Button>
                                )}

                                {/* No install capability — never hide the plugin, just stop offering the action. */}
                                {!isActive && !canInstall && (
                                    <a
                                        href={`https://wordpress.org/plugins/${plugin.slug}/`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="cforge-text-xs cforge-text-primaryHover"
                                    >
                                        {__('View on WordPress.org', 'content-forge')}
                                    </a>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </Card>
    );
}
