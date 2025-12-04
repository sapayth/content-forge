import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

export default function Header({ title, onAddNew }) {
    const [pluginVersion, setPluginVersion] = useState('1.0.0');
    const [telemetryEnabled, setTelemetryEnabled] = useState(false);
    const [isOptingIn, setIsOptingIn] = useState(false);

    useEffect(() => {
        // Get plugin version from global if available
        if (window.cforge?.pluginVersion) {
            setPluginVersion(window.cforge.pluginVersion);
        }
        // Get telemetry status
        if (window.cforge?.telemetry_enabled !== undefined) {
            setTelemetryEnabled(window.cforge.telemetry_enabled);
        }
    }, []);

    const handleTelemetryOptIn = async () => {
        setIsOptingIn(true);
        try {
            const formData = new FormData();
            formData.append('action', 'cforge_telemetry_opt_in');
            formData.append('nonce', window.cforge.ajax_nonce);

            const response = await fetch(window.cforge.ajax_url, {
                method: 'POST',
                body: formData,
            });

            const responseText = await response.text();

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                alert(__('Server returned invalid response. Check console for details.', 'content-forge'));
                return;
            }

            if (data.success) {
                setTelemetryEnabled(true);
                alert(data.data.message);
            } else {
                alert(data.data.message || __('Failed to enable telemetry.', 'content-forge'));
            }
        } catch (error) {
            alert(__('An error occurred. Please check the browser console for details.', 'content-forge'));
        } finally {
            setIsOptingIn(false);
        }
    };

    return (
        <div className="cforge-flex cforge-items-center cforge-justify-between cforge-mb-6">
            <div className="cforge-flex cforge-items-center">
                <span className="cforge-text-xl cforge-font-bold cforge-mr-2">
                    {title ? `${__('Content Forge', 'content-forge')} - ${title}` : __('Content Forge', 'content-forge')}
                </span>
                <span className="cforge-bg-green-100 cforge-text-green-700 cforge-text-xs cforge-font-semibold cforge-px-2 cforge-py-1 cforge-rounded-full">
                    v{pluginVersion}
                </span>
            </div>
            <div className="cforge-flex cforge-items-center cforge-gap-2">
                {onAddNew && (
                    <button
                        onClick={onAddNew}
                        className="cforge-btn cforge-btn-primary"
                    >
                        {__('Add New', 'content-forge')}
                    </button>
                )}
            </div>
        </div>
    );
}