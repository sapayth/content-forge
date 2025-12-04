import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/Header';
import apiFetch from '@wordpress/api-fetch';
import ListView from './components/ListView';
import { createRoot } from 'react-dom/client';


function AddNewView({ onCancel, onSuccess }) {
    const [taxonomy, setTaxonomy] = useState({
        taxonomy_type: 'category',
        count: 5,
    });
    const [errors, setErrors] = useState({});
    const [notice, setNotice] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const taxonomies = window.cforge?.taxonomies ? Object.values(window.cforge.taxonomies).map(tax => ({
        label: tax.label,
        value: tax.name,
    })) : [];

    const validate = () => {
        const newErrors = {};
        const num = Number(taxonomy['count']);
        if (!num || num < 1) {
            newErrors['count'] = __('Number of terms must be at least 1', 'content-forge');
        }
        if (num > 100) {
            newErrors['count'] = __('Number of terms cannot exceed 100', 'content-forge');
        }
        if (!taxonomy['taxonomy_type']) {
            newErrors['taxonomy_type'] = __('Please select a taxonomy', 'content-forge');
        }
        return newErrors;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const validationErrors = validate();
        setErrors(validationErrors);
        if (Object.keys(validationErrors).length > 0) {
            return;
        }
        setSubmitting(true);
        setNotice(null);
        const payload = {
            taxonomy: taxonomy.taxonomy_type,
            count: Number(taxonomy.count),
        };
        try {
            await apiFetch({
                path: 'taxonomy/bulk',
                method: 'POST',
                data: payload,
            });
            setSubmitting(false);
            setNotice({
                message: __('Terms generated successfully!', 'content-forge'),
                status: 'success',
            });
            setTimeout(() => {
                setNotice(null);
                onSuccess();
            }, 1500);
        } catch (error) {
            setSubmitting(false);
            setNotice({
                message: error?.message || __('An error occurred. Please try again.', 'content-forge'),
                status: 'error',
            });
        }
    };

    const errorClass = (field) => (errors[field] ? 'cforge-border-red-500 cforge-outline-red-500' : '');

    return (
        <div className="cforge-w-full cforge-bg-white cforge-rounded cforge-p-6 cforge-relative">
            {notice && (
                <div className={`cforge-mb-4 cforge-p-3 cforge-rounded cforge-text-white ${notice.status === 'success' ? 'cforge-bg-success' : 'cforge-bg-error'}`}>{notice.message}</div>
            )}
            <div className="cforge-flex cforge-gap-4">
                <form className="cforge-w-2/3" onSubmit={handleSubmit}>
                    <div className="cforge-mt-8">
                        <div className="cforge-mb-4">
                            <label className="cforge-block cforge-mb-1 cforge-font-medium">
                                {__('Select Taxonomy', 'content-forge')}
                            </label>
                            <select
                                className={`cforge-input ${errorClass('taxonomy_type')}`}
                                value={taxonomy['taxonomy_type']}
                                onChange={e => setTaxonomy({ ...taxonomy, taxonomy_type: e.target.value })}
                            >
                                {taxonomies.map((tax) => (
                                    <option key={tax.value} value={tax.value}>{tax.label}</option>
                                ))}
                            </select>
                            {errors['taxonomy_type'] && (
                                <p className="cforge-text-error cforge-text-sm">{errors['taxonomy_type']}</p>
                            )}
                        </div>
                        <div className="cforge-mb-4">
                            <label className="cforge-block cforge-mb-1 cforge-font-medium">
                                {__('Number of Terms', 'content-forge')}
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="100"
                                className={`cforge-input ${errorClass('count')}`}
                                value={taxonomy['count']}
                                onChange={e => setTaxonomy({ ...taxonomy, count: e.target.value })}
                            />
                            {errors['count'] && (
                                <p className="cforge-text-error cforge-text-sm">{errors['count']}</p>
                            )}
                        </div>
                    </div>
                    <div className="cforge-flex cforge-justify-end cforge-mt-6 cforge-gap-2">
                        <button
                            type="button"
                            className="cforge-bg-tertiary cforge-text-text-primary cforge-px-4 cforge-py-2 cforge-rounded cforge-font-semibold hover:cforge-bg-border"
                            onClick={onCancel}
                            disabled={submitting}
                        >
                            {__('Cancel', 'content-forge')}
                        </button>
                        <button
                            type="submit"
                            className="cforge-bg-primary cforge-text-white cforge-px-4 cforge-py-2 cforge-rounded cforge-font-semibold hover:cforge-bg-primaryHover"
                            disabled={submitting}
                        >
                            {submitting ? __('Generating...', 'content-forge') : __('Generate Terms', 'content-forge')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function TaxonomiesApp() {
    const [view, setView] = useState('list');
    const [items, setItems] = useState([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [perPage] = useState(15);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [notice, setNotice] = useState(null);
    const totalPages = Math.ceil(total / perPage);

    useEffect(() => {
        if (view !== 'list') return;

        let isMounted = true;
        setLoading(true);
        setError(null);

        // Configure apiFetch middleware for nonce and root URL
        if (window.cforge?.rest_nonce) {
            apiFetch.use(apiFetch.createNonceMiddleware(window.cforge.rest_nonce));
        }
        if (window.cforge?.apiUrl) {
            apiFetch.use(apiFetch.createRootURLMiddleware(window.cforge.apiUrl));
        }

        apiFetch({
            path: `taxonomy/list?page=${page}&per_page=${perPage}`,
            method: 'GET',
        })
            .then((res) => {
                if (!isMounted) return;
                setItems(res.items || []);
                setTotal(res.total || 0);
                setLoading(false);
            })
            .catch((err) => {
                if (!isMounted) return;
                setError(err.message || __('Failed to load data', 'content-forge'));
                setLoading(false);
            });
        return () => {
            isMounted = false;
        };
    }, [page, perPage, view]);

    const handlePageChange = (newPage) => {
        setPage(newPage);
    };

    const refreshList = (targetPage = null) => {
        const pageToLoad = targetPage !== null ? targetPage : page;
        setLoading(true);
        setError(null);

        apiFetch({
            path: `taxonomy/list?page=${pageToLoad}&per_page=${perPage}`,
            method: 'GET',
        })
            .then((res) => {
                setItems(res.items || []);
                setTotal(res.total || 0);
                if (targetPage !== null) {
                    setPage(pageToLoad);
                }
                setLoading(false);
            })
            .catch((err) => {
                setError(err.message || __('Failed to load data', 'content-forge'));
                setLoading(false);
            });
    };

    const handleDelete = async (itemId) => {
        if (!confirm(__('Are you sure you want to delete this term?', 'content-forge'))) {
            return;
        }

        setDeleting(itemId);
        setNotice(null);
        try {
            await apiFetch({
                path: `taxonomy/${itemId}`,
                method: 'DELETE',
            });
            setNotice({
                message: __('Term deleted successfully!', 'content-forge'),
                status: 'success',
            });
            // Refresh the list - if current page becomes empty, go to previous page
            const currentPageItemCount = items.length;
            if (currentPageItemCount === 1 && page > 1) {
                // If this was the last item on the page, go to previous page
                refreshList(page - 1);
            } else {
                // Otherwise refresh current page
                refreshList();
            }
            setDeleting(null);
            setTimeout(() => setNotice(null), 3000);
        } catch (err) {
            setNotice({
                message: err.message || __('Failed to delete term', 'content-forge'),
                status: 'error',
            });
            setDeleting(null);
            setTimeout(() => setNotice(null), 5000);
        }
    };

    const handleDeleteAll = async () => {
        if (!confirm(__('Are you sure you want to delete all generated terms? This cannot be undone.', 'content-forge'))) {
            return;
        }

        setDeleting('all');
        setNotice(null);
        try {
            await apiFetch({
                path: 'taxonomy/bulk',
                method: 'DELETE',
            });
            setNotice({
                message: __('All terms deleted successfully!', 'content-forge'),
                status: 'success',
            });
            setItems([]);
            setTotal(0);
            setDeleting(null);
            setTimeout(() => setNotice(null), 3000);
        } catch (err) {
            setNotice({
                message: err.message || __('Failed to delete terms', 'content-forge'),
                status: 'error',
            });
            setDeleting(null);
            setTimeout(() => setNotice(null), 5000);
        }
    };

    const handleSuccess = () => {
        setView('list');
        setPage(1); // Reset to first page after adding new item
    };

    return (
        <div className="cforge-bg-white cforge-p-8 cforge-min-h-screen">
            <Header
                title={__('Taxonomies', 'content-forge')}
            />
            {view === 'list' && (
                <>
                    {notice && (
                        <div className={`cforge-mb-4 cforge-p-3 cforge-rounded cforge-text-white ${notice.status === 'success' ? 'cforge-bg-success' : 'cforge-bg-error'}`}>
                            {notice.message}
                        </div>
                    )}
                    <ListView
                    items={items}
                    loading={loading}
                    error={error}
                    page={page}
                    totalPages={totalPages}
                    columns={[
                        { key: 'title', label: __('Title', 'content-forge') },
                        { key: 'taxonomy', label: __('Taxonomy', 'content-forge') },
                        { key: 'date', label: __('Date', 'content-forge') },
                    ]}
                    renderRow={(item) => (
                        <>
                            <td className="cforge-whitespace-nowrap cforge-py-4 cforge-pl-4 cforge-pr-3 cforge-text-sm cforge-font-medium cforge-text-gray-900 sm:cforge-pl-6">
                                {item.title}
                            </td>
                            <td className="cforge-whitespace-nowrap cforge-px-3 cforge-py-4 cforge-text-sm cforge-text-gray-500">
                                {item.taxonomy}
                            </td>
                            <td className="cforge-whitespace-nowrap cforge-px-3 cforge-py-4 cforge-text-sm cforge-text-gray-500">
                                {new Date(item.date).toLocaleDateString()}
                            </td>
                        </>
                    )}
                    actions={(item, onDelete, deleting, itemId) => (
                        <button
                            onClick={() => onDelete(itemId)}
                            disabled={deleting === itemId}
                            className="cforge-text-indigo-600 hover:cforge-text-indigo-900"
                            title={__('Delete', 'content-forge')}
                        >
                            {deleting === itemId ? (
                                <span className="cforge-text-xs">{__('...', 'content-forge')}</span>
                            ) : (
                                <svg className="cforge-w-4 cforge-h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            )}
                        </button>
                    )}
                    onAddNew={() => setView('add')}
                    onPageChange={handlePageChange}
                    onDelete={handleDelete}
                    onDeleteAll={handleDeleteAll}
                    deleting={deleting}
                    title={__('Taxonomies', 'content-forge')}
                    description={__('A list of all the generated taxonomy terms including their title, taxonomy type and date.', 'content-forge')}
                    />
                </>
            )}
            {view === 'add' && (
                <AddNewView
                    onCancel={() => setView('list')}
                    onSuccess={handleSuccess}
                />
            )}
        </div>
    );
}

const container = document.getElementById('cforge-taxonomies-app');
if (container) {
    const { createRoot } = require('react-dom/client');
    const root = createRoot(container);
    root.render(<TaxonomiesApp />);
}
