import {useEffect, useState} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {__} from '@wordpress/i18n';

export default function ListView({ onAddNew }) {
    const [items, setItems] = useState([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [perPage] = useState(15);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const totalPages = Math.ceil(total / perPage);

    useEffect(() => {
        let isMounted = true;
        setLoading(true);
        setError(null);

        // Configure apiFetch middleware for nonce and root URL
        if (window.fakegen?.rest_nonce) {
            apiFetch.use(apiFetch.createNonceMiddleware(window.fakegen.rest_nonce));
        }
        if (window.fakegen?.apiUrl) {
            apiFetch.use(apiFetch.createRootURLMiddleware(window.fakegen.apiUrl));
        }

        apiFetch({
            path: `posts/list?page=${page}&per_page=${perPage}`,
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
                setError(err.message || __('Failed to load data', 'fakegen'));
                setLoading(false);
            });
        return () => {
            isMounted = false;
        };
    }, [page, perPage]);

    return (
        <>
            <div className="fakegen-flex fakegen-justify-between fakegen-items-center fakegen-mb-6">
                <h1 className="fakegen-text-2xl fakegen-font-bold">{__('Pages/Posts', 'fakegen')}</h1>
                <button
                    className="fakegen-bg-primary fakegen-text-white fakegen-px-4 fakegen-py-2 fakegen-rounded fakegen-font-semibold hover:fakegen-bg-primaryHover"
                    onClick={onAddNew}
                >
                    {__('Add New', 'fakegen')}
                </button>
            </div>
            <div className="fakegen-bg-gray-50 fakegen-rounded fakegen-p-6 fakegen-shadow fakegen-overflow-x-auto">
                {loading ? (
                    <p className="fakegen-text-center fakegen-text-gray-500">{__('Loading...', 'fakegen')}</p>
                ) : error ? (
                    <p className="fakegen-text-center fakegen-text-red-500">{error}</p>
                ) : items.length === 0 ? (
                    <p className="fakegen-text-center fakegen-text-gray-500">{__('No items found. Click "Add New" to generate pages or posts.', 'fakegen')}</p>
                ) : (
                    <table className="fakegen-min-w-full fakegen-table-auto fakegen-bg-white">
                        <thead>
                        <tr>
                            <th className="fakegen-px-4 fakegen-py-2 fakegen-text-left fakegen-font-semibold">{__('Title', 'fakegen')}</th>
                            <th className="fakegen-px-4 fakegen-py-2 fakegen-text-left fakegen-font-semibold">{__('Author', 'fakegen')}</th>
                            <th className="fakegen-px-4 fakegen-py-2 fakegen-text-left fakegen-font-semibold">{__('Type', 'fakegen')}</th>
                            <th className="fakegen-px-4 fakegen-py-2 fakegen-text-left fakegen-font-semibold">{__('Date', 'fakegen')}</th>
                        </tr>
                        </thead>
                        <tbody>
                        {items.map((item) => (
                            <tr key={item.ID} className="fakegen-border-t fakegen-border-gray-200">
                                <td className="fakegen-px-4 fakegen-py-2 fakegen-text-blue-700 fakegen-font-medium fakegen-cursor-pointer fakegen-underline">
                                    {item.title}
                                </td>
                                <td className="fakegen-px-4 fakegen-py-2">{item.author}</td>
                                <td className="fakegen-px-4 fakegen-py-2">{item.type}</td>
                                <td className="fakegen-px-4 fakegen-py-2">{item.date}</td>
                            </tr>
                        ))}
                        </tbody>
                    </table>
                )}
                {totalPages > 1 && (
                    <div className="fakegen-flex fakegen-justify-end fakegen-items-center fakegen-gap-2 fakegen-mt-4">
                        <button
                            className="fakegen-px-2 fakegen-py-1 fakegen-rounded fakegen-bg-gray-200"
                            onClick={() => setPage(1)}
                            disabled={page === 1}
                        >&laquo;</button>
                        <button
                            className="fakegen-px-2 fakegen-py-1 fakegen-rounded fakegen-bg-gray-200"
                            onClick={() => setPage(page - 1)}
                            disabled={page === 1}
                        >&lsaquo;</button>
                        <span className="fakegen-px-2">{page} {__('of', 'fakegen')} {totalPages}</span>
                        <button
                            className="fakegen-px-2 fakegen-py-1 fakegen-rounded fakegen-bg-gray-200"
                            onClick={() => setPage(page + 1)}
                            disabled={page === totalPages}
                        >&rsaquo;</button>
                        <button
                            className="fakegen-px-2 fakegen-py-1 fakegen-rounded fakegen-bg-gray-200"
                            onClick={() => setPage(totalPages)}
                            disabled={page === totalPages}
                        >&raquo;</button>
                    </div>
                )}
            </div>
        </>
    );
}