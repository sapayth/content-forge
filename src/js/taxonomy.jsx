import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/header';
import apiFetch from '@wordpress/api-fetch';
import { createRoot } from 'react-dom/client';

function ListViewTaxonomies({ endpoint, onAddNew }) {
    const [items, setItems] = useState([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [perPage] = useState(15);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const totalPages = Math.ceil(total / perPage);

    useEffect(() => {
        let isMounted = true;
        setLoading(true);
        setError(null);
        if (window.cforge?.rest_nonce) {
            apiFetch.use(apiFetch.createNonceMiddleware(window.cforge.rest_nonce));
        }
        if (window.cforge?.apiUrl) {
            apiFetch.use(apiFetch.createRootURLMiddleware(window.cforge.apiUrl));
        }
        apiFetch({
            path: `${endpoint}/list?page=${page}&per_page=${perPage}`,
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
    }, [page, perPage, endpoint]);

    const refreshList = () => {
        setLoading(true);
        setError(null);
        apiFetch({
            path: `${endpoint}/list?page=${page}&per_page=${perPage}`,
            method: 'GET',
        })
            .then((res) => {
                setItems(res.items || []);
                setTotal(res.total || 0);
                setLoading(false);
            })
            .catch((err) => {
                setError(err.message || __('Failed to load data', 'content-forge'));
                setLoading(false);
            });
    };

    const handleDeleteAll = async () => {
        if (!confirm(__('Are you sure you want to delete all generated terms? This cannot be undone.', 'content-forge'))) {
            return;
        }
        setDeleting('all');
        try {
            await apiFetch({
                path: `${endpoint}/bulk`,
                method: 'DELETE',
            });
            setItems([]);
            setTotal(0);
            setDeleting(null);
        } catch (err) {
            setError(err.message || __('Failed to delete terms', 'content-forge'));
            setDeleting(null);
        }
    };

    const handleIndividualDelete = async (itemId) => {
        if (!confirm(__('Are you sure you want to delete this term?', 'content-forge'))) {
            return;
        }
        setDeleting(itemId);
        try {
            await apiFetch({
                path: `${endpoint}/${itemId}`,
                method: 'DELETE',
            });
            refreshList();
            setDeleting(null);
        } catch (err) {
            setError(err.message || __('Failed to delete term', 'content-forge'));
            setDeleting(null);
        }
    };

    return (
        <>
            {items.length === 0 && (
                <div className="cforge-flex cforge-justify-end cforge-mb-8">
                    <button className="cforge-btn cforge-btn-primary cforge-mr-4" onClick={onAddNew}>{__('Add New', 'content-forge')}</button>
                </div>
            )}
            <div className="cforge-bg-tertiary cforge-rounded cforge-p-6 cforge-shadow cforge-overflow-x-auto">
                {items.length > 0 && (
                    <div className="cforge-flex cforge-justify-end cforge-mb-4">
                        <button className="cforge-btn cforge-btn-primary cforge-mr-4" onClick={onAddNew}>{__('Add New', 'content-forge')}</button>
                        <button
                            onClick={handleDeleteAll}
                            disabled={deleting === 'all'}
                            className="cforge-btn cforge-btn-danger"
                        >
                            {deleting === 'all' ? __('Deleting...', 'content-forge') : __('Delete All', 'content-forge')}
                        </button>
                    </div>
                )}
                {loading ? (
                    <p className="cforge-text-center cforge-text-text-secondary">{__('Loading...', 'content-forge')}</p>
                ) : error ? (
                    <p className="cforge-text-center cforge-text-error">{error}</p>
                ) : items.length === 0 ? (
                    <p className="cforge-text-center cforge-text-text-secondary">{__('No terms found. Click "Add New" to generate terms.', 'content-forge')}</p>
                ) : (
                    <table className="cforge-min-w-full cforge-table-auto cforge-bg-white">
                        <thead>
                            <tr>
                                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Title', 'content-forge')}</th>
                                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Taxonomy', 'content-forge')}</th>
                                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Date', 'content-forge')}</th>
                                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Actions', 'content-forge')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.ID || item.id} className="cforge-border-t cforge-border-gray-200">
                                    <td className="cforge-px-4 cforge-py-2">{item.title}</td>
                                    <td className="cforge-px-4 cforge-py-2">{item.taxonomy}</td>
                                    <td className="cforge-px-4 cforge-py-2 cforge-text-sm cforge-text-text-secondary">
                                        {new Date(item.date).toLocaleDateString()}
                                    </td>
                                    <td className="cforge-px-4 cforge-py-2">
                                        <button
                                            onClick={() => handleIndividualDelete(item.id)}
                                            disabled={deleting === item.id}
                                            className="cforge-text-error hover:cforge-text-error cforge-p-1 cforge-rounded hover:cforge-bg-tertiary"
                                            title={__('Delete', 'content-forge')}
                                        >
                                            {deleting === item.id ? (
                                                <span className="cforge-text-xs">{__('...', 'content-forge')}</span>
                                            ) : (
                                                <svg className="cforge-w-4 cforge-h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            )}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
                {totalPages > 1 && (
                    <div className="cforge-flex cforge-justify-end cforge-items-center cforge-gap-2 cforge-mt-4">
                        <button
                            className="cforge-px-2 cforge-py-1 cforge-rounded cforge-bg-gray-200"
                            onClick={() => setPage(1)}
                            disabled={page === 1}
                        >&laquo;</button>
                        <button
                            className="cforge-px-2 cforge-py-1 cforge-rounded cforge-bg-gray-200"
                            onClick={() => setPage(page - 1)}
                            disabled={page === 1}
                        >&lsaquo;</button>
                        <span className="cforge-px-2">{page} {__('of', 'content-forge')} {totalPages}</span>
                        <button
                            className="cforge-px-2 cforge-py-1 cforge-rounded cforge-bg-gray-200"
                            onClick={() => setPage(page + 1)}
                            disabled={page === totalPages}
                        >&rsaquo;</button>
                        <button
                            className="cforge-px-2 cforge-py-1 cforge-rounded cforge-bg-gray-200"
                            onClick={() => setPage(totalPages)}
                            disabled={page === totalPages}
                        >&raquo;</button>
                    </div>
                )}
            </div>
        </>
    );
}

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
    const handleAddNew = () => setView('add');
    const handleCancel = () => setView('list');
    const handleSuccess = () => setView('list');
    return (
        <div className="cforge-bg-white cforge-p-8 cforge-min-h-screen">
            <Header
                title={__('Taxonomies', 'content-forge')}
            />
            {view === 'list' && (
                <ListViewTaxonomies
                    endpoint="taxonomy"
                    onAddNew={handleAddNew}
                />
            )}
            {view === 'add' && (
                <AddNewView
                    onCancel={handleCancel}
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
