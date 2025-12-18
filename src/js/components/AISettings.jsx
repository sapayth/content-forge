import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, Button, SelectControl, TextControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function AISettings() {
    const [provider, setProvider] = useState('openai');
    const [model, setModel] = useState('');
    const [apiKey, setApiKey] = useState('');
    const [testing, setTesting] = useState(false);
    const [notice, setNotice] = useState(null);
    const [saving, setSaving] = useState(false);
    const [providers, setProviders] = useState([]);
    const [models, setModels] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        // Configure apiFetch middleware
        if (window.cforge?.rest_nonce) {
            apiFetch.use(apiFetch.createNonceMiddleware(window.cforge.rest_nonce));
        }
        if (window.cforge?.apiUrl) {
            apiFetch.use(apiFetch.createRootURLMiddleware(window.cforge.apiUrl));
        }

        loadSettings();
    }, []);

    useEffect(() => {
        if (provider && !loading) {
            loadModels(provider);
            loadApiKey(provider);
        }
    }, [provider, loading]);

    const loadSettings = async () => {
        try {
            setLoading(true);
            const response = await apiFetch({
                path: 'ai/settings',
                method: 'GET',
            });

            
            setProvider(response.provider || 'openai');
            setProviders(Object.entries(response.providers || {}).map(([value, label]) => ({ value, label })));
        } catch (error) {
            setNotice({
                message: error?.message || __('Failed to load settings', 'content-forge'),
                status: 'error',
            });
        } finally {
            setLoading(false);
        }
    };

    const loadModels = async (providerName) => {
        try {
            // Load available models for the provider
            const modelsResponse = await apiFetch({
                path: `ai/models/${providerName}`,
                method: 'GET',
            });

            const modelOptions = Object.entries(modelsResponse.models || {}).map(([value, label]) => ({ value, label }));

            // Load the stored model for this provider
            const storedModelResponse = await apiFetch({
                path: `ai/stored-model/${providerName}`,
                method: 'GET',
            });

            const storedModel = storedModelResponse.model || '';

            setModels(modelOptions);

            // Use stored model if it exists and is valid, otherwise use first model
            let selectedModel = storedModel;
            if (!storedModel || !modelOptions.find(m => m.value === storedModel)) {
                selectedModel = modelOptions.length > 0 ? modelOptions[0].value : '';
            }

            setModel(selectedModel);
        } catch (error) {
            setNotice({
                message: error?.message || __('Failed to load models', 'content-forge'),
                status: 'error',
            });
        }
    };

    const loadApiKey = async (providerName) => {
        try {
            const response = await apiFetch({
                path: `ai/api-key/${providerName}`,
                method: 'GET',
            });

            if (response.has_key && response.masked_key) {
                // Show masked key as placeholder
                setApiKey(response.masked_key);
            } else {
                setApiKey('');
            }
        } catch (error) {
            // Ignore errors, just clear the field
            setApiKey('');
        }
    };

    const handleTestConnection = async () => {
        setTesting(true);
        setNotice(null);

        // Check if API key is masked (contains asterisks)
        const isMasked = apiKey.includes('*') && (apiKey.startsWith('*') || apiKey.endsWith('*') || apiKey.length > 4);

        
        // Prepare request data
        const requestData = {
            provider,
            model,
        };

        // Only send API key if it's not masked (i.e., user entered a new key)
        // If masked, backend will use the stored API key from database
        if (!isMasked && apiKey.trim()) {
            requestData.api_key = apiKey;
        } else if (isMasked && apiKey.trim()) {
            // Using stored API key from database (key is masked)
        } else {
            setTesting(false);
            setNotice({
                message: __('Please enter an API key first', 'content-forge'),
                status: 'error',
            });
            return;
        }

        try {
            const response = await apiFetch({
                path: 'ai/test-connection',
                method: 'POST',
                data: requestData,
            });

            if (response.success) {
                setNotice({
                    message: __('Connection successful', 'content-forge'),
                    status: 'success',
                });
            } else {
                setNotice({
                    message: response.message || __('Connection failed', 'content-forge'),
                    status: 'error',
                });
            }
        } catch (error) {

            let message = __('Connection failed', 'content-forge');
            if (error?.message) {
                message = error.message;
            } else if (error?.code === 'invalid_api_key') {
                message = __('Invalid API key. Please verify your API key and try again.', 'content-forge');
            } else if (error?.code === 'rate_limit') {
                message = __('Rate limit exceeded. Please try again later.', 'content-forge');
            } else if (error?.code === 'network_error') {
                message = __('Connection failed. Please check your internet connection and try again.', 'content-forge');
            }

            setNotice({
                message,
                status: 'error',
            });
        } finally {
            setTesting(false);
        }
    };

    const handleSave = async () => {
        if (!provider || !model) {
            setNotice({
                message: __('Please select a provider and model', 'content-forge'),
                status: 'error',
            });
            return;
        }

        setSaving(true);
        setNotice(null);

        try {
            // Check if API key is masked before sending
            const isMaskedKey = apiKey.includes('*') && (apiKey.startsWith('*') || apiKey.endsWith('*') || apiKey.length > 4);

            const data = {
                provider,
                model,
            };

            // Only include API key if it's not masked (i.e., user entered a new key)
            if (!isMaskedKey && apiKey.trim()) {
                data.api_key = apiKey.trim();
            }

            const response = await apiFetch({
                path: 'ai/settings',
                method: 'POST',
                data,
            });

            if (response.success) {
                setNotice({
                    message: __('Settings saved successfully', 'content-forge'),
                    status: 'success',
                });
                // Clear API key field after saving (it's now stored)
                setApiKey('');
                // Refresh models to get the updated stored model
                loadModels(provider);
            } else {
                setNotice({
                    message: response.message || __('Failed to save settings', 'content-forge'),
                    status: 'error',
                });
            }
        } catch (error) {
            setNotice({
                message: error?.message || __('Failed to save settings', 'content-forge'),
                status: 'error',
            });
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="cforge-p-6">
                <p>{__('Loading settings...', 'content-forge')}</p>
            </div>
        );
    }

    const getApiKeyUrl = () => {
        switch (provider) {
            case 'google':
                return 'https://cloud.google.com/docs/authentication/api-keys';
            case 'anthropic':
                return 'https://platform.claude.com/docs/en/api/admin/api_keys/retrieve';
            case 'openai':
            default:
                return 'https://platform.openai.com/api-keys';
        }
    };

    return (
        <div className="cforge-w-full cforge-bg-white cforge-rounded cforge-p-6">
            <h2 className="cforge-text-2xl cforge-font-bold cforge-mb-6">{__('AI Settings', 'content-forge')}</h2>

            {notice && (
                <Notice
                    status={notice.status}
                    onRemove={() => setNotice(null)}
                    className="cforge-mb-4"
                >
                    {notice.message}
                </Notice>
            )}

            <div className="cforge-space-y-6">
                <div>
                    <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                        {__('AI Provider', 'content-forge')}
                    </label>
                    <SelectControl
                        value={provider}
                        options={providers}
                        onChange={(value) => {
                            setProvider(value);
                            setApiKey('');
                        }}
                        className="cforge-w-full"
                    />
                    <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                        {__('Select the AI service provider you want to use.', 'content-forge')}
                    </p>
                </div>

                <div>
                    <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                        {__('AI Model', 'content-forge')}
                    </label>
                    <SelectControl
                        value={model}
                        options={models}
                        onChange={setModel}
                        className="cforge-w-full"
                    />
                    <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                        {__('Select the AI model to use for content generation.', 'content-forge')}
                    </p>
                </div>

                <div>
                    <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                        {__('API Key', 'content-forge')}
                    </label>
                    <TextControl
                        type="text"
                        value={apiKey}
                        onChange={(value) => setApiKey(value)}
                        placeholder={__('Enter your API key', 'content-forge')}
                        className="cforge-w-full"
                    />
                    <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                        {__('Enter your AI service API key. Need help finding your API Key?', 'content-forge')}{' '}
                        <a
                            href={getApiKeyUrl()}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="cforge-text-blue-600 hover:cforge-underline"
                        >
                            {__('Get API Key', 'content-forge')}
                        </a>
                    </p>
                </div>

                <div className="cforge-flex cforge-gap-3">
                    <Button
                        onClick={handleTestConnection}
                        isBusy={testing}
                        disabled={testing || saving || !apiKey.trim()}
                        variant="secondary"
                    >
                        {__('Test Connection', 'content-forge')}
                    </Button>
                    <Button
                        onClick={handleSave}
                        isBusy={saving}
                        disabled={testing || saving || !provider || !model}
                        variant="primary"
                    >
                        {__('Save Changes', 'content-forge')}
                    </Button>
                </div>
            </div>
        </div>
    );
}
