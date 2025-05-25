import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

export default function Header() {
    const [pluginVersion, setPluginVersion] = useState('1.0.0');
    useEffect(() => {
        // Get plugin version from global if available
        if (window.fakegenData?.pluginVersion) {
            setPluginVersion(window.fakegenData.pluginVersion);
        }
    }, []);
  return (
    <div className="fakegen-flex fakegen-items-center fakegen-justify-between fakegen-mb-6">
        <div className="fakegen-flex fakegen-items-center">
            <span className="fakegen-text-xl fakegen-font-bold fakegen-mr-2">{__('Fakegen', 'fakegen')}</span>
            <span className="fakegen-bg-green-100 fakegen-text-green-700 fakegen-text-xs fakegen-font-semibold fakegen-px-2 fakegen-py-1 fakegen-rounded-full">
                v{pluginVersion}
                </span>
        </div>
    </div>
  );
}