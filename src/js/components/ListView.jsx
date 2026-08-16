import { __ } from '@wordpress/i18n';
import Button from './Button';

// Cell and header styling lives on the table so callers can render bare <td>s.
// Content wraps by default; add cforge-whitespace-nowrap per cell where it must not.
const CELL = [
    '[&_td]:cforge-px-3 [&_td]:cforge-py-3 [&_td]:cforge-text-sm [&_td]:cforge-align-middle [&_td]:cforge-text-text-secondary',
    '[&_td:first-child]:cforge-pl-4 sm:[&_td:first-child]:cforge-pl-6',
    '[&_td:first-child]:cforge-font-medium [&_td:first-child]:cforge-text-text-primary',
    '[&_td:last-child]:cforge-pr-4 sm:[&_td:last-child]:cforge-pr-6',
].join(' ');

const HEAD = [
    '[&_th]:cforge-px-3 [&_th]:cforge-py-2.5 [&_th]:cforge-text-left [&_th]:cforge-whitespace-nowrap',
    '[&_th]:cforge-text-xxs [&_th]:cforge-font-semibold [&_th]:cforge-uppercase [&_th]:cforge-tracking-wider [&_th]:cforge-text-text-secondary',
    '[&_th:first-child]:cforge-pl-4 sm:[&_th:first-child]:cforge-pl-6',
    '[&_th:last-child]:cforge-pr-4 sm:[&_th:last-child]:cforge-pr-6',
].join(' ');

const ICONS = {
    edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    view: 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14',
    trash: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
};

const ACTION_BASE = 'cforge-inline-flex cforge-items-center cforge-justify-center cforge-w-7 cforge-h-7 cforge-rounded cforge-bg-transparent cforge-border-0 cforge-cursor-pointer cforge-transition-colors focus-visible:cforge-outline focus-visible:cforge-outline-2 focus-visible:cforge-outline-offset-1';

function ActionIcon({ name }) {
    return (
        <svg className="cforge-w-4 cforge-h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={ICONS[name]} />
        </svg>
    );
}

/**
 * Row actions, derived from the item itself. The REST formatters omit edit_link
 * and permalink when the user lacks the capability, so the action disappears
 * rather than 403-ing on click.
 */
function RowActions({ item, itemId, onDelete, deleting }) {
    const isDeleting = deleting === itemId;
    return (
        <div className="cforge-flex cforge-items-center cforge-justify-end cforge-gap-0.5">
            {item.edit_link && (
                <a
                    href={item.edit_link}
                    aria-label={__('Edit', 'content-forge')}
                    title={__('Edit', 'content-forge')}
                    className={`${ACTION_BASE} cforge-text-text-secondary hover:cforge-text-primary hover:cforge-bg-brand-50 focus-visible:cforge-outline-primary`}
                >
                    <ActionIcon name="edit" />
                </a>
            )}
            {item.permalink && (
                <a
                    href={item.permalink}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={__('View', 'content-forge')}
                    title={__('View', 'content-forge')}
                    className={`${ACTION_BASE} cforge-text-text-secondary hover:cforge-text-primary hover:cforge-bg-brand-50 focus-visible:cforge-outline-primary`}
                >
                    <ActionIcon name="view" />
                </a>
            )}
            {onDelete && (
                <button
                    type="button"
                    onClick={() => onDelete(itemId)}
                    disabled={isDeleting}
                    aria-label={__('Delete', 'content-forge')}
                    title={__('Delete', 'content-forge')}
                    className={`${ACTION_BASE} cforge-text-text-secondary hover:cforge-text-error hover:cforge-bg-red-50 focus-visible:cforge-outline-error disabled:cforge-opacity-50 disabled:cforge-cursor-not-allowed`}
                >
                    {isDeleting ? <span className="cforge-text-xxs">…</span> : <ActionIcon name="trash" />}
                </button>
            )}
        </div>
    );
}

export default function ListView({
    items = [],
    loading = false,
    error = null,
    page = 1,
    total = null,
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
    emptyTitle = __('Nothing here yet', 'content-forge'),
    emptyMessage = __('Generate some content to see it listed here.', 'content-forge'),
    skeletonRows = 5,
}) {
    // Helper to get item ID (handles both ID and id fields)
    const getItemId = (item) => item.ID || item.id;
    // Either a caller-supplied renderer, or the built-in edit/view/delete set.
    const showActions = Boolean(actions || onDelete);

    return (
        <div className="cforge-list-view cforge-p-6">
            <div className="sm:cforge-flex sm:cforge-items-center">
                <div className="sm:cforge-flex-auto">
                    {title && (
                        <h1 className="cforge-text-base cforge-font-semibold cforge-text-text-primary">{title}</h1>
                    )}
                    {description && (
                        <p className="cforge-mt-2 cforge-text-sm cforge-text-text-secondary">{description}</p>
                    )}
                </div>
                <div className="cforge-mt-4 sm:cforge-ml-16 sm:cforge-mt-0 sm:cforge-flex-none">
                    <div className="cforge-flex cforge-gap-2">
                        {items.length > 0 && onDeleteAll && (
                            <Button
                                variant="danger"
                                onClick={onDeleteAll}
                                disabled={deleting === 'all'}
                            >
                                {deleting === 'all' ? __('Deleting...', 'content-forge') : __('Delete All', 'content-forge')}
                            </Button>
                        )}
                        {onAddNew && (
                            <Button onClick={onAddNew}>
                                {__('Add New', 'content-forge')}
                            </Button>
                        )}
                    </div>
                </div>
            </div>
            <div className="cforge-mt-8 cforge-flow-root">
                <div className="-cforge-mx-4 -cforge-my-2 cforge-overflow-x-auto sm:-cforge-mx-6 lg:-cforge-mx-8">
                    <div className="cforge-inline-block cforge-min-w-full cforge-py-2 cforge-align-middle sm:cforge-px-6 lg:cforge-px-8">
                        {error ? (
                            <div className="cforge-text-center cforge-text-error cforge-py-8">{error}</div>
                        ) : !loading && items.length === 0 ? (
                            <div className="cforge-flex cforge-flex-col cforge-items-center cforge-gap-3 cforge-py-16 cforge-px-6 cforge-text-center cforge-border cforge-border-border cforge-rounded-xl cforge-bg-white">
                                <span className="cforge-flex cforge-items-center cforge-justify-center cforge-w-11 cforge-h-11 cforge-rounded-full cforge-bg-brand-50 cforge-text-primaryHover" aria-hidden="true">
                                    <svg className="cforge-w-5 cforge-h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 13h6m-3-3v6m5 6H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </span>
                                <p className="cforge-m-0 cforge-text-sm cforge-font-semibold cforge-text-text-primary">{emptyTitle}</p>
                                <p className="cforge-m-0 cforge-text-sm cforge-text-text-secondary cforge-max-w-sm">{emptyMessage}</p>
                                {onAddNew && (
                                    <Button className="cforge-mt-1" onClick={onAddNew}>
                                        {__('Add New', 'content-forge')}
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="cforge-overflow-hidden cforge-border cforge-border-border cforge-rounded-xl cforge-shadow-sm cforge-bg-white">
                                <table className={`cforge-relative cforge-min-w-full cforge-tabular-nums ${CELL} ${HEAD}`}>
                                    <thead className="cforge-bg-tertiary">
                                        <tr>
                                            {columns.map((column) => (
                                                <th key={column.key} scope="col">{column.label}</th>
                                            ))}
                                            {showActions && (
                                                <th scope="col" className="cforge-text-right">
                                                    <span className="cforge-sr-only">{__('Actions', 'content-forge')}</span>
                                                </th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody className="cforge-divide-y cforge-divide-border cforge-bg-white">
                                        {loading
                                            ? Array.from({ length: skeletonRows }).map((_, row) => (
                                                <tr key={`skeleton-${row}`} aria-hidden="true">
                                                    {Array.from({ length: columns.length + (showActions ? 1 : 0) }).map((__ignored, col) => (
                                                        <td key={col}>
                                                            <span className="cforge-block cforge-h-3 cforge-rounded-full cforge-bg-tertiary cforge-animate-pulse motion-reduce:cforge-animate-none" style={{ width: col === 0 ? '60%' : '40%' }} />
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))
                                            : items.map((item) => {
                                                const itemId = getItemId(item);
                                                return (
                                                    <tr key={itemId} className="hover:cforge-bg-tertiary/40">
                                                        {renderRow(item)}
                                                        {showActions && (
                                                            <td className="cforge-whitespace-nowrap cforge-text-right">
                                                                {actions
                                                                    ? actions(item, onDelete, deleting, itemId)
                                                                    : <RowActions item={item} itemId={itemId} onDelete={onDelete} deleting={deleting} />}
                                                            </td>
                                                        )}
                                                    </tr>
                                                );
                                            })}
                                    </tbody>
                                </table>
                                {loading && <span className="cforge-sr-only" role="status">{__('Loading…', 'content-forge')}</span>}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {totalPages > 1 && onPageChange && (
                <div className="cforge-flex cforge-justify-end cforge-items-center cforge-gap-2 cforge-mt-4">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => onPageChange(1)}
                        disabled={page === 1}
                        aria-label={__('First page', 'content-forge')}
                    >&laquo;</Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => onPageChange(page - 1)}
                        disabled={page === 1}
                        aria-label={__('Previous page', 'content-forge')}
                    >&lsaquo;</Button>
                    <span className="cforge-px-2 cforge-text-sm cforge-text-text-secondary cforge-tabular-nums">
                        {page} {__('of', 'content-forge')} {totalPages}
                        {total !== null && ` \u00b7 ${total} ${__('items', 'content-forge')}`}
                    </span>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => onPageChange(page + 1)}
                        disabled={page === totalPages}
                        aria-label={__('Next page', 'content-forge')}
                    >&rsaquo;</Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => onPageChange(totalPages)}
                        disabled={page === totalPages}
                        aria-label={__('Last page', 'content-forge')}
                    >&raquo;</Button>
                </div>
            )}
        </div>
    );
}