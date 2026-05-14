// DESCRIPTION: Autopilot entry — mounts the AutopilotApp into the WP admin page root.
// Mirrors the pattern used by settings.jsx and other dashboard sub-pages.

import '../css/common.css';
import { createRoot } from 'react-dom/client';
import AutopilotApp from './autopilot/AutopilotApp';

const container = document.getElementById('cforge-autopilot-app');
if (container) {
    const root = createRoot(container);
    root.render(<AutopilotApp />);
}
