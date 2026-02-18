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

    // Async generation states
    const [batchId, setBatchId] = useState(null);
    const [progress, setProgress] = useState(0);
    const [statusMessage, setStatusMessage] = useState('');
    const [createdPosts, setCreatedPosts] = useState([]);
    const [polling, setPolling] = useState(false);
    const [generationErrors, setGenerationErrors] = useState([]);

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

    // Clean up polling on unmount
    useEffect(() => {
        return () => {
            if (polling) {
                setPolling(false);
            }
        };
    }, [polling]);

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
        setProgress(0);
        setCreatedPosts([]);
        setGenerationErrors([]);
        setStatusMessage(__('Initializing AI generation...', 'content-forge'));

        try {
            // Detect editor type
            const editorType = window.cforge?.editor_type || 'block';

            const response = await apiFetch({
                path: 'posts/bulk',
                method: 'POST',
                data: {
                    post_number: numberOfPosts,
                    post_type: post.post_type,
                    post_status: post.post_status,
                    post_parent: post.post_parent,
                    content_type: contentType,
                    ai_prompt: customPrompt,
                    editor_type: editorType,
                    use_ai: true,
                },
            });

            if (response.batch_id) {
                // Start async generation
                setBatchId(response.batch_id);
                setStatusMessage(response.message);
                setPolling(true);

                // Start polling for progress
                pollProgress(response.batch_id);
            } else {
                setNotice({
                    message: response.message || __('Failed to start generation', 'content-forge'),
                    status: 'error',
                });
                setGenerating(false);
            }
        } catch (error) {
            let message = __('An error occurred while starting generation', 'content-forge');
            if (error?.message) {
                message = error.message;
            } else if (error?.code) {
                message = error.message || __('Server error occurred', 'content-forge');
            }

            setNotice({
                message,
                status: 'error',
            });
            setGenerating(false);
        }
    };

    // Poll for generation progress
    const pollProgress = async (batchId) => {
        try {
            const response = await apiFetch({
                path: `generation/status?batch_id=${batchId}`,
                method: 'GET',
            });

            if (response.status === 'processing') {
                setProgress(response.progress_percentage || 0);
                setStatusMessage(`Generating post ${response.completed + 1} of ${response.total}...`);

                // Update created posts list
                if (response.posts_created && response.posts_created.length > 0) {
                    setCreatedPosts(response.posts_created);
                }

                // Update errors list
                if (response.errors && response.errors.length > 0) {
                    setGenerationErrors(response.errors);
                    const latestError = response.errors[response.errors.length - 1];
                    if (latestError && latestError.error) {
                        setStatusMessage(`Generating post ${response.completed + 1} of ${response.total}... (Error: ${latestError.error})`);
                    }
                }

                // Continue polling
                setTimeout(() => {
                    if (polling) {
                        pollProgress(batchId);
                    }
                }, 2000); // Poll every 2 seconds
            } else if (response.status === 'failed') {
                // Generation failed
                setPolling(false);
                setGenerating(false);

                // Get the specific error message
                let errorMessage = __('AI generation failed. Please check your AI settings configuration.', 'content-forge');
                if (response.errors && response.errors.length > 0) {
                    // Use the first error message as they're likely all the same (e.g., quota exceeded)
                    errorMessage = response.errors[0].error;
                    setGenerationErrors(response.errors);
                }

                setNotice({
                    message: errorMessage,
                    status: 'error',
                });
            } else if (response.status === 'completed') {
                // Generation complete
                setProgress(100);
                setStatusMessage(__('✓ Generation complete!', 'content-forge'));
                setCreatedPosts(response.posts_created || []);
                setPolling(false);
                setGenerating(false);

                setNotice({
                    message: __('All posts generated successfully!', 'content-forge'),
                    status: 'success',
                });

                // Show errors if any
                if (response.errors && response.errors.length > 0) {
                    setGenerationErrors(response.errors);
                    // Display the first specific error message
                    const firstError = response.errors[0];
                    let errorDetails = '';

                    // Extract just the error message part (remove the "Error generating post X/Y:" prefix if present)
                    if (firstError && firstError.error) {
                        errorDetails = firstError.error;
                    }

                    setNotice({
                        message: `${response.errors.length} posts had errors during generation. ${errorDetails ? `Error: ${errorDetails}` : ''}`,
                        status: 'warning',
                    });
                }

                if (onSuccess) {
                    setTimeout(() => {
                        onSuccess();
                    }, 2000);
                }
            }
        } catch (error) {
            setNotice({
                message: __('Error checking generation status', 'content-forge'),
                status: 'error',
            });
            setPolling(false);
            setGenerating(false);
        }
    };

    // Stop polling
    const stopGeneration = () => {
        setPolling(false);
        setGenerating(false);
        setBatchId(null);
        setProgress(0);
        setStatusMessage('');
        setCreatedPosts([]);
        setGenerationErrors([]);
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

                <div className="cforge-mt-6">
                    <Button
                        variant="primary"
                        onClick={handleGenerate}
                        disabled={generating || !isConfigured}
                        className="cforge-w-full"
                    >
                        {generating ? __('Generating...', 'content-forge') : __('Generate AI Content', 'content-forge')}
                    </Button>
                </div>

                {generating && (
                    <div className="cforge-mt-4 cforge-p-4 cforge-bg-blue-50 cforge-border cforge-border-blue-200 cforge-rounded">
                        <div className="cforge-flex cforge-justify-between cforge-items-center cforge-mb-2">
                            <h3 className="cforge-font-semibold cforge-text-blue-900">{__('Generating AI Content', 'content-forge')}</h3>
                            {polling && (
                                <Button
                                    isSmall
                                    isDestructive
                                    onClick={stopGeneration}
                                    className="cforge-text-xs"
                                >
                                    {__('Stop', 'content-forge')}
                                </Button>
                            )}
                        </div>

                        <div className="cforge-mb-2">
                            <div className="cforge-text-sm cforge-text-blue-700 cforge-mb-1">{statusMessage}</div>
                            <div className="cforge-w-full cforge-bg-gray-200 cforge-rounded-full cforge-h-2">
                                <div
                                    className="cforge-bg-blue-600 cforge-h-2 cforge-rounded-full cforge-transition-all cforge-duration-300"
                                    style={{ width: `${progress}%` }}
                                ></div>
                            </div>
                            <div className="cforge-text-xs cforge-text-gray-600 cforge-mt-1">{progress}% complete</div>
                        </div>

                        {createdPosts.length > 0 && (
                            <div className="cforge-mt-3">
                                <p className="cforge-text-sm cforge-font-medium cforge-text-blue-900 cforge-mb-2">
                                    {__('Created Posts:', 'content-forge')}
                                </p>
                                <div className="cforge-max-h-32 cforge-overflow-y-auto cforge-space-y-1">
                                    {createdPosts.map((post, index) => (
                                        <div key={post.post_id} className="cforge-text-xs cforge-text-blue-700 cforge-p-1 cforge-bg-white cforge-rounded">
                                            <span className="cforge-font-medium">{post.title}</span>
                                            <a
                                                href={`/wp-admin/post.php?post=${post.post_id}&action=edit`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="cforge-ml-2 cforge-text-blue-600 hover:cforge-text-blue-800"
                                            >
                                                {__('Edit', 'content-forge')}
                                            </a>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Show error summary if any errors occurred */}
                        {generationErrors.length > 0 && (
                            <div className="cforge-mt-3">
                                <p className="cforge-text-sm cforge-font-medium cforge-text-red-900 cforge-mb-2">
                                    {__('Errors:', 'content-forge')}
                                </p>
                                <div className="cforge-max-h-32 cforge-overflow-y-auto cforge-space-y-1">
                                    {generationErrors.map((error, index) => (
                                        <div key={index} className="cforge-text-xs cforge-text-red-700 cforge-p-1 cforge-bg-red-50 cforge-rounded">
                                            {__('Post', 'content-forge')} {error.index + 1}: {error.error}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {post.post_title && post.post_content && !generating && (
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
            </div>
        </div>
    );
}
