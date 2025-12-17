import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SelectControl, TextareaControl, Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function AIGenerateTab({ post, setPost, onSuccess }) {
    const [isConfigured, setIsConfigured] = useState(false);
    const [contentType, setContentType] = useState('general');
    const [customPrompt, setCustomPrompt] = useState('');
    const [generating, setGenerating] = useState(false);
    const [notice, setNotice] = useState(null);
    const [contentTypes, setContentTypes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pages, setPages] = useState([]);
    const [numberOfPosts, setNumberOfPosts] = useState(1);

    useEffect(() => {
        // Configure apiFetch middleware
        if (window.cforge?.rest_nonce) {
            apiFetch.use(apiFetch.createNonceMiddleware(window.cforge.rest_nonce));
        }
        if (window.cforge?.apiUrl) {
            apiFetch.use(apiFetch.createRootURLMiddleware(window.cforge.apiUrl));
        }

        checkConfiguration();
        
        // Fetch pages for parent dropdown
        if (post.post_type === 'page') {
            fetch(window.cforge?.restUrl + 'wp/v2/pages')
                .then((response) => response.json())
                .then((data) => setPages(data || []))
                .catch(() => setPages([]));
        }
    }, []);

    const checkConfiguration = async () => {
        try {
            setLoading(true);
            const response = await apiFetch({
                path: 'ai/settings',
                method: 'GET',
            });

            setIsConfigured(response.is_configured || false);

            // Load content types (we'll get these from the API or use defaults)
            const defaultTypes = [
                { label: 'General/Blog', value: 'general' },
                { label: 'E-commerce', value: 'e-commerce' },
                { label: 'Portfolio', value: 'portfolio' },
                { label: 'Business/Corporate', value: 'business' },
                { label: 'Education', value: 'education' },
                { label: 'Health/Medical', value: 'health' },
                { label: 'Technology', value: 'technology' },
                { label: 'Food/Recipe', value: 'food' },
                { label: 'Travel', value: 'travel' },
                { label: 'Fashion', value: 'fashion' },
            ];
            setContentTypes(defaultTypes);
        } catch (error) {
            setIsConfigured(false);
        } finally {
            setLoading(false);
        }
    };

    const handleGenerate = async () => {
        if (!isConfigured) {
            return;
        }

        setGenerating(true);
        setNotice(null);

        try {
            // Detect editor type
            const editorType = window.cforge?.editor_type || 'block';

            const response = await apiFetch({
                path: 'ai/generate',
                method: 'POST',
                data: {
                    content_type: contentType,
                    custom_prompt: customPrompt,
                    editor_type: editorType,
                    number_of_posts: numberOfPosts,
                },
            });

            if (response.success) {
                // Update post with generated title and content, and store AI params
                setPost({
                    ...post,
                    post_title: response.title || '',
                    post_content: response.content || '',
                    content_type: contentType,
                    ai_prompt: customPrompt,
                    number_of_posts: numberOfPosts,
                });

                setNotice({
                    message: __('Content generated successfully!', 'content-forge'),
                    status: 'success',
                });

                if (onSuccess) {
                    setTimeout(() => {
                        onSuccess();
                    }, 1500);
                }
            } else {
                setNotice({
                    message: response.message || __('Failed to generate content', 'content-forge'),
                    status: 'error',
                });
            }
        } catch (error) {
            let message = __('An error occurred while generating content', 'content-forge');
            if (error?.message) {
                message = error.message;
            } else if (error?.code === 'not_configured') {
                message = __('AI is not configured. Please configure AI settings first.', 'content-forge');
            } else if (error?.code === 'rate_limit') {
                message = __('Rate limit exceeded. Please try again later.', 'content-forge');
            }

            setNotice({
                message,
                status: 'error',
            });
        } finally {
            setGenerating(false);
        }
    };

    if (loading) {
        return (
            <div className="cforge-p-6">
                <p>{__('Loading...', 'content-forge')}</p>
            </div>
        );
    }

    if (!isConfigured) {
        return (
            <div className="cforge-w-full cforge-bg-white cforge-rounded cforge-p-6 cforge-text-center">
                <p className="cforge-text-lg cforge-mb-4">
                    {__('Configure AI model to use AI content generation', 'content-forge')}
                </p>
                <Button
                    href={window.location.origin + window.location.pathname + '?page=cforge-settings'}
                    variant="primary"
                >
                    {__('Go to Settings', 'content-forge')}
                </Button>
            </div>
        );
    }

    return (
        <div className="cforge-w-full cforge-bg-white cforge-rounded cforge-p-6">
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
                <div className="cforge-grid cforge-grid-cols-2 cforge-gap-4">
                    <div>
                        <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                            {__('Type', 'content-forge')}
                        </label>
                        <select
                            className="cforge-input cforge-w-full"
                            value={post.post_type}
                            onChange={e => setPost({ ...post, post_type: e.target.value })}
                        >
                            <option value="post">{__('Post', 'content-forge')}</option>
                            <option value="page">{__('Page', 'content-forge')}</option>
                        </select>
                    </div>
                    <div>
                        <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                            {__('Status', 'content-forge')}
                        </label>
                        <select
                            className="cforge-input cforge-w-full"
                            value={post.post_status}
                            onChange={e => setPost({ ...post, post_status: e.target.value })}
                        >
                            <option value="publish">{__('Publish', 'content-forge')}</option>
                            <option value="pending">{__('Pending', 'content-forge')}</option>
                            <option value="draft">{__('Draft', 'content-forge')}</option>
                            <option value="private">{__('Private', 'content-forge')}</option>
                        </select>
                    </div>
                </div>

                {post.post_type === 'page' && (
                    <div>
                        <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                            {__('Parent Page', 'content-forge')}
                        </label>
                        <select
                            className="cforge-input cforge-w-full"
                            value={post.post_parent}
                            onChange={e => setPost({ ...post, post_parent: e.target.value })}
                        >
                            <option value="0">{__('No Parent', 'content-forge')}</option>
                            {pages.map((page) => (
                                <option key={page.id} value={page.id}>{page.title.rendered}</option>
                            ))}
                        </select>
                    </div>
                )}

                <div>
                    <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                        {__('Content Type', 'content-forge')}
                    </label>
                    <SelectControl
                        value={contentType}
                        options={contentTypes}
                        onChange={setContentType}
                        className="cforge-w-full"
                    />
                    <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                        {__('Select the type of content you want to generate.', 'content-forge')}
                    </p>
                </div>

                <div>
                    <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                        {__('Number of Pages/Posts', 'content-forge')}
                    </label>
                    <input
                        type="number"
                        min="1"
                        max="50"
                        value={numberOfPosts}
                        onChange={(e) => {
                            const value = parseInt(e.target.value) || 1;
                            setNumberOfPosts(Math.min(50, Math.max(1, value)));
                        }}
                        className="cforge-input cforge-w-full"
                    />
                    <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                        {__('Enter the number of posts/pages to generate (maximum 50).', 'content-forge')}
                    </p>
                </div>

                <div>
                    <label className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-700 cforge-mb-2">
                        {__('Custom Prompt (Optional)', 'content-forge')}
                    </label>
                    <TextareaControl
                        value={customPrompt}
                        onChange={setCustomPrompt}
                        placeholder={__('Enter additional instructions for content generation...', 'content-forge')}
                        rows={4}
                        className="cforge-w-full"
                    />
                    <p className="cforge-text-sm cforge-text-gray-500 cforge-mt-1">
                        {__('Provide additional context or instructions to guide the AI generation.', 'content-forge')}
                    </p>
                </div>

                {post.post_title && post.post_content && (
                    <div className="cforge-mt-4 cforge-p-4 cforge-bg-gray-50 cforge-rounded">
                        <h3 className="cforge-font-semibold cforge-mb-2">{__('Generated Content', 'content-forge')}</h3>
                        <p className="cforge-text-sm cforge-text-gray-600 cforge-mb-2">
                            <strong>{__('Title:', 'content-forge')}</strong> {post.post_title}
                        </p>
                        <div className="cforge-text-sm cforge-text-gray-600">
                            <strong>{__('Content Preview:', 'content-forge')}</strong>
                            <div className="cforge-mt-2 cforge-max-h-40 cforge-overflow-y-auto" dangerouslySetInnerHTML={{ __html: post.post_content.substring(0, 500) + '...' }} />
                        </div>
                    </div>
                )}

                <div className="cforge-flex cforge-gap-3">
                    <Button
                        onClick={handleGenerate}
                        isBusy={generating}
                        disabled={generating}
                        variant="primary"
                    >
                        {__('Generate Content', 'content-forge')}
                    </Button>
                    {post.post_title && post.post_content && (
                        <div className="cforge-text-sm cforge-text-gray-600 cforge-mt-2">
                            {__('Content generated! Use the "Save Generated Content" button below to save it.', 'content-forge')}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
