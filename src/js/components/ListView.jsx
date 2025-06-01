import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

export default function ListView({ endpoint, columns, renderRow, actions, onAddNew }) {
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

        // Configure apiFetch middleware for nonce and root URL
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
                setError(err.message || __('Failed to load data', 'cforge'));
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
                setError(err.message || __('Failed to load data', 'cforge'));
                setLoading(false);
            });
    };

    const handleDeleteAll = async () => {
        if (!confirm(__('Are you sure you want to delete all generated items?', 'cforge'))) {
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
            setError(err.message || __('Failed to delete items', 'cforge'));
            setDeleting(null);
        }
    };

    const handleIndividualDelete = async (itemId) => {
        if (!confirm(__('Are you sure you want to delete this item?', 'cforge'))) {
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
            setError(err.message || __('Failed to delete item', 'cforge'));
            setDeleting(null);
        }
    };

    return (
        <>
            {items.length === 0 && (
                <div className="cforge-flex cforge-justify-end cforge-mb-8">
                    <button className="cforge-btn cforge-btn-primary cforge-mr-4" onClick={onAddNew}>{__('Add New', 'cforge')}</button>
                </div>
            )}
            <div className="cforge-bg-gray-50 cforge-rounded cforge-p-6 cforge-shadow cforge-overflow-x-auto">
                {items.length > 0 && (
                    <div className="cforge-flex cforge-justify-end cforge-mb-4">
                        <button className="cforge-btn cforge-btn-primary cforge-mr-4" onClick={onAddNew}>{__('Add New', 'cforge')}</button>
                        <button
                            onClick={handleDeleteAll}
                            disabled={deleting === 'all'}
                            className="cforge-btn cforge-btn-danger"
                        >
                            {deleting === 'all' ? __('Deleting...', 'cforge') : __('Delete All', 'cforge')}
                        </button>
                    </div>
                )}

                {loading ? (
                    <p className="cforge-text-center cforge-text-gray-500">{__('Loading...', 'cforge')}</p>
                ) : error ? (
                    <p className="cforge-text-center cforge-text-red-500">{error}</p>
                ) : items.length === 0 ? (
                    <p className="cforge-text-center cforge-text-gray-500">{__('No items found. Click "Add New" to generate content.', 'cforge')}</p>
                ) : (
                    <table className="cforge-min-w-full cforge-table-auto cforge-bg-white">
                        <thead>
                            <tr>
                                {columns.map((column) => (
                                    <th key={column.key} className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">
                                        {column.label}
                                    </th>
                                ))}
                                {actions && (
                                    <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">
                                        {__('Actions', 'cforge')}
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.ID || item.id} className="cforge-border-t cforge-border-gray-200">
                                    {renderRow(item)}
                                    {actions && (
                                        <td className="cforge-px-4 cforge-py-2">
                                            {actions(item, handleIndividualDelete, deleting)}
                                        </td>
                                    )}
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
                        <span className="cforge-px-2">{page} {__('of', 'cforge')} {totalPages}</span>
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