import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

export default function Header({ title, onAddNew }) {
    const [pluginVersion, setPluginVersion] = useState('1.0.0');
    useEffect(() => {
        // Get plugin version from global if available
        if (window.cforgeData?.pluginVersion) {
            setPluginVersion(window.cforgeData.pluginVersion);
        }
    }, []);
  return (
    <div className="cforge-flex cforge-items-center cforge-justify-between cforge-mb-6">
        <div className="cforge-flex cforge-items-center">
            <span className="cforge-text-xl cforge-font-bold cforge-mr-2">
                {title ? `${__('Content Forge', 'cforge')} - ${title}` : __('Content Forge', 'cforge')}
            </span>
            <span className="cforge-bg-green-100 cforge-text-green-700 cforge-text-xs cforge-font-semibold cforge-px-2 cforge-py-1 cforge-rounded-full">
                v{pluginVersion}
            </span>
        </div>
        {onAddNew && (
            <button
                onClick={onAddNew}
                className="cforge-btn cforge-btn-primary"
            >
                {__('Add New', 'cforge')}
            </button>
        )}
    </div>
  );
}