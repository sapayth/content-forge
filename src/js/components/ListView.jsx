import { __ } from '@wordpress/i18n';

export default function ListView({
    items = [],
    loading = false,
    error = null,
    page = 1,
    totalPages = 1,
    columns = [],
    renderRow,
    actions,
    onAddNew,
    onPageChange,
    onDelete,
    onDeleteAll,
    deleting = null,
    title,
    description,
}) {
    // Helper to get item ID (handles both ID and id fields)
    const getItemId = (item) => item.ID || item.id;

    return (
        <div className="cforge-list-view cforge-p-6">
            <div className="sm:cforge-flex sm:cforge-items-center">
                <div className="sm:cforge-flex-auto">
                    {title && (
                        <h1 className="cforge-text-base cforge-font-semibold cforge-text-gray-900">{title}</h1>
                    )}
                    {description && (
                        <p className="cforge-mt-2 cforge-text-sm cforge-text-gray-700">{description}</p>
                    )}
                </div>
                <div className="cforge-mt-4 sm:cforge-ml-16 sm:cforge-mt-0 sm:cforge-flex-none">
                    <div className="cforge-flex cforge-gap-2">
                        {items.length > 0 && onDeleteAll && (
                            <button
                                onClick={onDeleteAll}
                                disabled={deleting === 'all'}
                                className="cforge-block cforge-rounded-md cforge-bg-red-600 cforge-px-3 cforge-py-2 cforge-text-center cforge-text-sm cforge-font-semibold cforge-text-white cforge-shadow-sm hover:cforge-bg-red-500 focus-visible:cforge-outline focus-visible:cforge-outline-2 focus-visible:cforge-outline-offset-2 focus-visible:cforge-outline-red-600 disabled:cforge-opacity-50"
                            >
                                {deleting === 'all' ? __('Deleting...', 'content-forge') : __('Delete All', 'content-forge')}
                            </button>
                        )}
                        {onAddNew && (
                            <button
                                type="button"
                                onClick={onAddNew}
                                className="cforge-block cforge-rounded-md cforge-bg-primary cforge-px-3 cforge-py-2 cforge-text-center cforge-text-sm cforge-font-semibold cforge-text-white cforge-shadow-sm hover:cforge-bg-primaryHover focus-visible:cforge-outline focus-visible:cforge-outline-2 focus-visible:cforge-outline-offset-2 focus-visible:cforge-outline-indigo-600"
                            >
                                {__('Add New', 'content-forge')}
                            </button>
                        )}
                    </div>
                </div>
            </div>
            <div className="cforge-mt-8 cforge-flow-root">
                <div className="-cforge-mx-4 -cforge-my-2 cforge-overflow-x-auto sm:-cforge-mx-6 lg:-cforge-mx-8">
                    <div className="cforge-inline-block cforge-min-w-full cforge-py-2 cforge-align-middle sm:cforge-px-6 lg:cforge-px-8">
                        {loading ? (
                            <div className="cforge-text-center cforge-text-gray-500 cforge-py-8">{__('Loading...', 'content-forge')}</div>
                        ) : error ? (
                            <div className="cforge-text-center cforge-text-red-500 cforge-py-8">{error}</div>
                        ) : items.length === 0 ? (
                            <div className="cforge-text-center cforge-text-gray-500 cforge-py-8">{__('No items found.', 'content-forge')}</div>
                        ) : (
                            <div className="cforge-overflow-hidden cforge-shadow cforge-outline cforge-outline-1 cforge-outline-black/5 sm:cforge-rounded-lg">
                                <table className="cforge-relative cforge-min-w-full cforge-divide-y cforge-divide-gray-300">
                                    <thead className="cforge-bg-gray-50">
                                        <tr>
                                            {columns.map((column, index) => (
                                                <th
                                                    key={column.key}
                                                    scope="col"
                                                    className={
                                                        index === 0
                                                            ? 'cforge-py-3.5 cforge-pl-4 cforge-pr-3 cforge-text-left cforge-text-sm cforge-font-semibold cforge-text-gray-900 sm:cforge-pl-6'
                                                            : index === columns.length - 1 && !actions
                                                            ? 'cforge-py-3.5 cforge-pl-3 cforge-pr-4 cforge-text-left cforge-text-sm cforge-font-semibold cforge-text-gray-900 sm:cforge-pr-6'
                                                            : 'cforge-px-3 cforge-py-3.5 cforge-text-left cforge-text-sm cforge-font-semibold cforge-text-gray-900'
                                                    }
                                                >
                                                    {column.label}
                                                </th>
                                            ))}
                                            {actions && (
                                                <th scope="col" className="cforge-py-3.5 cforge-pl-3 cforge-pr-4 sm:cforge-pr-6">
                                                    <span className="cforge-sr-only">{__('Actions', 'content-forge')}</span>
                                                </th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody className="cforge-divide-y cforge-divide-gray-200 cforge-bg-white">
                                        {items.map((item) => {
                                            const itemId = getItemId(item);
                                            return (
                                                <tr key={itemId}>
                                                    {renderRow(item)}
                                                    {actions && (
                                                        <td className="cforge-whitespace-nowrap cforge-py-4 cforge-pl-3 cforge-pr-4 cforge-text-right cforge-text-sm cforge-font-medium sm:cforge-pr-6">
                                                            {actions(item, onDelete, deleting, itemId)}
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {totalPages > 1 && onPageChange && (
                <div className="cforge-flex cforge-justify-end cforge-items-center cforge-gap-2 cforge-mt-4">
                    <button
                        className="cforge-px-2 cforge-py-1 cforge-rounded cforge-bg-gray-200 hover:cforge-bg-gray-300 disabled:cforge-opacity-50 disabled:cforge-cursor-not-allowed"
                        onClick={() => onPageChange(1)}
                        disabled={page === 1}
                    >&laquo;</button>
                    <button
                        className="cforge-px-2 cforge-py-1 cforge-rounded cforge-bg-gray-200 hover:cforge-bg-gray-300 disabled:cforge-opacity-50 disabled:cforge-cursor-not-allowed"
                        onClick={() => onPageChange(page - 1)}
                        disabled={page === 1}
                    >&lsaquo;</button>
                    <span className="cforge-px-2 cforge-text-sm">{page} {__('of', 'content-forge')} {totalPages}</span>
                    <button
                        className="cforge-px-2 cforge-py-1 cforge-rounded cforge-bg-gray-200 hover:cforge-bg-gray-300 disabled:cforge-opacity-50 disabled:cforge-cursor-not-allowed"
                        onClick={() => onPageChange(page + 1)}
                        disabled={page === totalPages}
                    >&rsaquo;</button>
                    <button
                        className="cforge-px-2 cforge-py-1 cforge-rounded cforge-bg-gray-200 hover:cforge-bg-gray-300 disabled:cforge-opacity-50 disabled:cforge-cursor-not-allowed"
                        onClick={() => onPageChange(totalPages)}
                        disabled={page === totalPages}
                    >&raquo;</button>
                </div>
            )}
        </div>
    );
}