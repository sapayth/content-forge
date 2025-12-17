import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/Header';
import apiFetch from '@wordpress/api-fetch';
import ListView from './components/ListView';
import { createRoot } from 'react-dom/client';


function AddNewView({ onCancel, onSuccess }) {
    const [user, setUser] = useState({
        user_number: 1,
        roles: ['subscriber'],
    });
    const [errors, setErrors] = useState({});
    const [notice, setNotice] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const roles = Object.entries(cforge.roles || {});

    const validate = () => {
        const newErrors = {};
        const num = Number(user['user_number']);
        if (!num || num < 1) {
            newErrors['user_number'] = __('Number of users must be at least 1', 'content-forge');
        }
        if (!user['roles'] || user['roles'].length === 0) {
            newErrors['roles'] = __('Please select at least one role', 'content-forge');
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
            user_number: Number(user.user_number),
            roles: user.roles,
        };
        try {
            await apiFetch({
                path: 'users/bulk',
                method: 'POST',
                data: payload,
            });
            setSubmitting(false);
            setNotice({
                message: __('Users generated successfully!', 'content-forge'),
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
                <div className={`cforge-mb-4 cforge-p-3 cforge-rounded cforge-text-white ${notice.status === 'success' ? 'cforge-bg-green-500' : 'cforge-bg-red-500'}`}>{notice.message}</div>
            )}
            <div className="cforge-flex cforge-gap-4">
                <form className="cforge-w-2/3" onSubmit={handleSubmit}>
                    <div className="cforge-mt-8">
                        <div className="cforge-mb-4">
                            <label className="cforge-block cforge-mb-1 cforge-font-medium">
                                {__('Number of Users', 'content-forge')}
                            </label>
                            <input
                                type="number"
                                min="1"
                                className={`cforge-input ${errorClass('user_number')}`}
                                value={user['user_number']}
                                onChange={e => setUser({ ...user, user_number: e.target.value })}
                            />
                            {errors['user_number'] && (
                                <p className="cforge-text-red-500 cforge-text-sm">{errors['user_number']}</p>
                            )}
                        </div>
                        <div className="cforge-mb-4">
                            <label className="cforge-block cforge-mb-1 cforge-font-medium">
                                {__('Roles', 'content-forge')}
                            </label>
                            <select
                                multiple
                                className={`cforge-input ${errorClass('roles')}`}
                                value={user['roles']}
                                onChange={e => {
                                    const selected = Array.from(e.target.selectedOptions).map(opt => opt.value);
                                    setUser({ ...user, roles: selected });
                                }}
                            >
                                {roles.map(([role, label]) => (
                                    <option key={role} value={role}>{label}</option>
                                ))}
                            </select>
                            {errors['roles'] && (
                                <p className="cforge-text-red-500 cforge-text-sm">{errors['roles']}</p>
                            )}
                        </div>
                    </div>
                    <div className="cforge-flex cforge-justify-end cforge-mt-6 cforge-gap-2">
                        <button
                            type="button"
                            className="cforge-bg-gray-200 cforge-text-gray-700 cforge-px-4 cforge-py-2 cforge-rounded cforge-font-semibold hover:cforge-bg-gray-300"
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
                            {submitting ? __('Generating...', 'content-forge') : __('Generate Users', 'content-forge')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function UsersApp() {
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
            path: `users?page=${page}&per_page=${perPage}`,
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
            path: `users?page=${pageToLoad}&per_page=${perPage}`,
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
        if (!confirm(__('Are you sure you want to delete this user?', 'content-forge'))) {
            return;
        }

        setDeleting(itemId);
        setNotice(null);
        try {
            await apiFetch({
                path: `users/${itemId}`,
                method: 'DELETE',
            });
            setNotice({
                message: __('User deleted successfully!', 'content-forge'),
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
                message: err.message || __('Failed to delete user', 'content-forge'),
                status: 'error',
            });
            setDeleting(null);
            setTimeout(() => setNotice(null), 5000);
        }
    };

    const handleDeleteAll = async () => {
        if (!confirm(__('Are you sure you want to delete all generated users?', 'content-forge'))) {
            return;
        }

        setDeleting('all');
        setNotice(null);
        try {
            await apiFetch({
                path: 'users/bulk',
                method: 'DELETE',
            });
            setNotice({
                message: __('All users deleted successfully!', 'content-forge'),
                status: 'success',
            });
            setItems([]);
            setTotal(0);
            setDeleting(null);
            setTimeout(() => setNotice(null), 3000);
        } catch (err) {
            setNotice({
                message: err.message || __('Failed to delete users', 'content-forge'),
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
        <div className="cforge-bg-white cforge-min-h-screen">
            <Header
                heading={__('Users', 'content-forge')}
            />
            {view === 'list' && (
                <>
                    {notice && (
                        <div className={`cforge-mb-4 cforge-p-3 cforge-rounded cforge-text-white ${notice.status === 'success' ? 'cforge-bg-green-500' : 'cforge-bg-red-500'}`}>
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
                        { key: 'username', label: __('Username', 'content-forge') },
                        { key: 'email', label: __('Email', 'content-forge') },
                        { key: 'role', label: __('Role', 'content-forge') },
                    ]}
                    renderRow={(item) => (
                        <>
                            <td className="cforge-whitespace-nowrap cforge-py-4 cforge-pl-4 cforge-pr-3 cforge-text-sm cforge-font-medium cforge-text-gray-900 sm:cforge-pl-6">
                                {item.user_login}
                            </td>
                            <td className="cforge-whitespace-nowrap cforge-px-3 cforge-py-4 cforge-text-sm cforge-text-gray-500">
                                {item.user_email}
                            </td>
                            <td className="cforge-whitespace-nowrap cforge-px-3 cforge-py-4 cforge-text-sm cforge-text-gray-500">
                                {item.role}
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
                    title={__('Users', 'content-forge')}
                    description={__('A list of all the users in your account including their name, email and role.', 'content-forge')}
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

const container = document.getElementById('cforge-users-app');
if (container) {
    const { createRoot } = require('react-dom/client');
    const root = createRoot(container);
    root.render(<UsersApp />);
}
