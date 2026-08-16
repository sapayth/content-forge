import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/Header';
import apiFetch from '@wordpress/api-fetch';
import ListView from './components/ListView';
import Button from './components/Button';
import Card from './components/Card';
import Field, { errorClass } from './components/Field';
import Notice from './components/Notice';


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

    return (
        <div className="cforge-w-full cforge-p-6 cforge-relative">
            {notice && (
                <Notice status={notice.status}>{notice.message}</Notice>
            )}
            <div className="cforge-flex cforge-gap-4">
                <form className="cforge-w-2/3" onSubmit={handleSubmit}>
                    <Card
                        title={__('Generate Terms', 'content-forge')}
                        footer={
                            <>
                                <Button variant="secondary" onClick={onCancel} disabled={submitting}>
                                    {__('Cancel', 'content-forge')}
                                </Button>
                                <Button type="submit" disabled={submitting}>
                                    {submitting ? __('Generating...', 'content-forge') : __('Generate Terms', 'content-forge')}
                                </Button>
                            </>
                        }
                    >
                        <Field
                            label={__('Select Taxonomy', 'content-forge')}
                            htmlFor="cforge-taxonomy-type"
                            error={errors['taxonomy_type']}
                        >
                            <select
                                id="cforge-taxonomy-type"
                                className={`cforge-input ${errorClass(errors['taxonomy_type'])}`}
                                value={taxonomy['taxonomy_type']}
                                onChange={e => setTaxonomy({ ...taxonomy, taxonomy_type: e.target.value })}
                            >
                                {taxonomies.map((tax) => (
                                    <option key={tax.value} value={tax.value}>{tax.label}</option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label={__('Number of Terms', 'content-forge')}
                            htmlFor="cforge-taxonomy-count"
                            error={errors['count']}
                        >
                            <input
                                id="cforge-taxonomy-count"
                                type="number"
                                min="1"
                                max="100"
                                className={`cforge-input ${errorClass(errors['count'])}`}
                                value={taxonomy['count']}
                                onChange={e => setTaxonomy({ ...taxonomy, count: e.target.value })}
                            />
                        </Field>
                    </Card>
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
        <>
        <Header
            heading={__('Taxonomies', 'content-forge')}
        />
        <div className="cforge-bg-white cforge-min-h-screen">
            {view === 'list' && (
                <>
                    {notice && (
                        <Notice status={notice.status}>{notice.message}</Notice>
                    )}
                    <ListView
                    items={items}
                    loading={loading}
                    error={error}
                    page={page}
                    total={total}
                    totalPages={totalPages}
                    columns={[
                        { key: 'title', label: __('Title', 'content-forge') },
                        { key: 'taxonomy', label: __('Taxonomy', 'content-forge') },
                        { key: 'date', label: __('Date', 'content-forge') },
                    ]}
                    renderRow={(item) => (
                        <>
                            <td>
                                {item.title}
                            </td>
                            <td className="cforge-whitespace-nowrap">
                                {item.taxonomy}
                            </td>
                            <td className="cforge-whitespace-nowrap">
                                {new Date(item.date).toLocaleDateString()}
                            </td>
                        </>
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
        </>
    );
}

const container = document.getElementById('cforge-taxonomies-app');
if (container) {
    const { createRoot } = require('react-dom/client');
    const root = createRoot(container);
    root.render(<TaxonomiesApp />);
}
