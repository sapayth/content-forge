import { __ } from '@wordpress/i18n';
import Header from './components/header';

export default function Dashboard() {
    return (
        <div>
            <Header title={__('Dashboard', 'content-forge')}>
                <p className="cforge-text-text-secondary">{__('Welcome to Content Forge!', 'content-forge')}</p>
            </Header>
            <span className="cforge-text-text-primary cforge-font-semibold">{__('Pages/Posts', 'content-forge')}</span>
            <span className="cforge-text-text-primary cforge-font-semibold">{__('Users', 'content-forge')}</span>
            <span className="cforge-text-text-primary cforge-font-semibold">{__('Comments', 'content-forge')}</span>
        </div>
    );
} 