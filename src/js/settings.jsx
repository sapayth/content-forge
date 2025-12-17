import { render } from '@wordpress/element';
import AISettings from './components/AISettings';
import '../css/common.css';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('cforge-settings-app');
    if (container) {
        render(<AISettings />, container);
    }
});
