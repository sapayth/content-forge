/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Header component
 *
 * @param {Object} props - Component props
 * @param {string} props.version - Plugin version
 * @param {string} props.heading - Main heading
 * @param {string} props.subheading - Subheading text
 * @param {Function} props.onAddNew - Add new button handler
 * @return {JSX.Element} The Header component.
 */
const Header = ({heading = '', subheading = '' }) => {
    const version = cforge.pluginVersion || '';
    return (
        <div className="cforge-bg-primary cforge-text-white cforge-p-6">
            <div className="cforge-flex cforge-justify-between cforge-items-center cforge-mx-auto">
                <div className="cforge-flex cforge-items-center cforge-gap-8">
                    <div className="cforge-flex cforge-items-center cforge-gap-2 cforge-border-r cforge-border-white/20 cforge-pr-8">
                        <h1 className="!cforge-text-2xl cforge-font-semibold !cforge-m-0 !cforge-p-0 cforge-text-white">
                            {__('Content Forge', 'content-forge')}
                        </h1>
                        {
                            version && (
                                <span className="cforge-bg-green-100 cforge-text-green-700 cforge-text-xs cforge-font-semibold cforge-px-2 cforge-py-1 cforge-rounded-full">
                                    v{version}
                                </span>
                            )
                        }
                    </div>
                    <div>
                        <h2 className="cforge-text-xl cforge-font-medium cforge-m-0 cforge-mb-1 cforge-text-white">
                            {heading}
                        </h2>
                        <p className="cforge-m-0 cforge-opacity-80 cforge-text-sm">
                            {subheading}
                        </p>
                    </div>
                </div>
                <div className="cforge-flex cforge-items-center cforge-gap-2">
                    <a
                    href="https://content-forge.canny.io/feature-requests"
                    target="_blank"
                    className="cforge-bg-white/20 cforge-px-2 cforge-py-1 cforge-rounded cforge-text-xs cforge-font-medium hover:cforge-text-white"
                    rel="noopener noreferrer"
                    >
                        {__('Request Feature', 'content-forge')}
                    </a>
                    <a
                        href="https://wordpress.org/support/plugin/content-forge/"
                        target="_blank"
                        className="cforge-bg-white/20 cforge-px-2 cforge-py-1 cforge-rounded cforge-text-xs cforge-font-medium hover:cforge-text-white"
                        rel="noopener noreferrer"
                    >
                        {__('Support', 'content-forge')}
                    </a>
                </div>
            </div>
        </div>
    );
};

export default Header;