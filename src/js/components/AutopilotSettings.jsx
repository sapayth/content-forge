// DESCRIPTION: Autopilot global on/off toggle on the Settings page.
// When off, the dispatcher and runner don't register; schedules stay where they are.

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, ToggleControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function AutopilotSettings() {
    const [enabled, setEnabled] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        if (window.cforge?.rest_nonce) {
            apiFetch.use(apiFetch.createNonceMiddleware(window.cforge.rest_nonce));
        }
        if (window.cforge?.apiUrl) {
            apiFetch.use(apiFetch.createRootURLMiddleware(window.cforge.apiUrl));
        }
        (async () => {
            try {
                setLoading(true);
                const data = await apiFetch({ path: 'autopilot/settings', method: 'GET' });
                setEnabled(Boolean(data.enabled));
            } catch (e) {
                setNotice({
                    status: 'error',
                    message: e?.message || __('Failed to load Autopilot settings', 'content-forge'),
                });
            } finally {
                setLoading(false);
            }
        })();
    }, []);

    const update = async (patch) => {
        try {
            setSaving(true);
            setNotice(null);
            const data = await apiFetch({
                path: 'autopilot/settings',
                method: 'PUT',
                data: patch,
            });
            setEnabled(Boolean(data.enabled));
            setNotice({ status: 'success', message: __('Saved.', 'content-forge') });
        } catch (e) {
            setNotice({
                status: 'error',
                message: e?.message || __('Save failed', 'content-forge'),
            });
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return <div className="cforge-flex cforge-justify-center cforge-py-6"><Spinner /></div>;
    }

    return (
        <div className="cforge-bg-white cforge-p-6 cforge-rounded cforge-border cforge-mt-6">
            <h2 className="cforge-text-lg cforge-font-semibold cforge-mt-0 cforge-mb-1">
                {__('Autopilot', 'content-forge')}
            </h2>
            <p className="cforge-text-sm cforge-text-gray-600 cforge-mt-0 cforge-mb-4">
                {__('Schedule AI to generate posts on a cadence. Requires AI configured above.', 'content-forge')}
            </p>

            {notice && (
                <div className="cforge-mb-3">
                    <Notice status={notice.status} isDismissible onRemove={() => setNotice(null)}>
                        {notice.message}
                    </Notice>
                </div>
            )}

            <ToggleControl
                label={__('Enable Autopilot', 'content-forge')}
                help={__('When off, no autopilots will run. Per-schedule active/paused state is preserved.', 'content-forge')}
                checked={enabled}
                disabled={saving}
                onChange={(v) => update({ enabled: v })}
            />
        </div>
    );
}
