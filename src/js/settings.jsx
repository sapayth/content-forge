import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/Header';
import AISettings from './components/AISettings';

function SettingsApp() {
    return (
        <>
            <Header
                heading={__('Settings', 'content-forge')}
            />
            <div className="cforge-bg-white cforge-min-h-screen">
                <AISettings />
            </div>
        </>
    );
}

const container = document.getElementById('cforge-settings-app');
if (container) {
    const { createRoot } = require('react-dom/client');
    const root = createRoot(container);
    root.render(<SettingsApp />);
}
