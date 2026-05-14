// DESCRIPTION: Top-level Autopilot view switcher — list / form / history.
// Owns the current view + selected schedule id; passes navigation callbacks down.

import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Header from '../components/Header';
import SchedulesList from './SchedulesList';
import ScheduleForm from './ScheduleForm';
import RunHistory from './RunHistory';

export default function AutopilotApp() {
    const [view, setView] = useState('list');
    const [activeId, setActiveId] = useState(null);

    const aiConfigured = window.cforge?.ai_configured ?? true;
    const autopilotEnabled = window.cforge?.autopilot_enabled ?? false;
    const settingsUrl = window.cforge?.settings_url || '';

    const showList = useCallback(() => {
        setView('list');
        setActiveId(null);
    }, []);

    const showCreate = useCallback(() => {
        setView('form');
        setActiveId(null);
    }, []);

    const showEdit = useCallback((id) => {
        setView('form');
        setActiveId(id);
    }, []);

    const showHistory = useCallback((id) => {
        setView('history');
        setActiveId(id);
    }, []);

    return (
        <>
            <Header
                heading={__('Autopilot', 'content-forge')}
                subheading={__('Schedule AI to generate posts on a cadence', 'content-forge')}
            />
            <div className="cforge-bg-white cforge-min-h-screen cforge-p-6">
                {!autopilotEnabled && (
                    <div className="cforge-bg-gray-100 cforge-border cforge-border-gray-300 cforge-text-gray-800 cforge-p-3 cforge-rounded cforge-mb-4 cforge-text-sm">
                        {__('Autopilot is turned off.', 'content-forge')}{' '}
                        {settingsUrl ? (
                            <>
                                {__('Enable it from', 'content-forge')}{' '}
                                <a href={settingsUrl} className="cforge-underline">
                                    {__('Settings', 'content-forge')}
                                </a>
                                {'.'}
                            </>
                        ) : (
                            __('Enable it from the Settings page.', 'content-forge')
                        )}
                    </div>
                )}
                {!aiConfigured && (
                    <div className="cforge-bg-yellow-50 cforge-border cforge-border-yellow-200 cforge-text-yellow-800 cforge-p-4 cforge-rounded cforge-mb-6">
                        {__('Autopilot needs an AI provider. Configure it in Settings before creating an autopilot.', 'content-forge')}
                    </div>
                )}

                {view === 'list' && (
                    <SchedulesList
                        onCreate={showCreate}
                        onEdit={showEdit}
                        onHistory={showHistory}
                    />
                )}
                {view === 'form' && (
                    <ScheduleForm
                        scheduleId={activeId}
                        onCancel={showList}
                        onSaved={showList}
                    />
                )}
                {view === 'history' && (
                    <RunHistory
                        scheduleId={activeId}
                        onBack={showList}
                    />
                )}
            </div>
        </>
    );
}
