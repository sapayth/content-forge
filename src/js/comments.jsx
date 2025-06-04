import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/header';
import apiFetch from '@wordpress/api-fetch';
import MultiSelect from './components/MultiSelect';
import { createRoot } from 'react-dom/client';

// New: ListViewComments component for comments listing
function ListViewComments({ endpoint, onAddNew }) {
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
      path: `${endpoint}?page=${page}&per_page=${perPage}`,
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
    if (!confirm(__('Are you sure you want to delete all generated comments?', 'content-forge'))) {
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
      setError(err.message || __('Failed to delete comments', 'content-forge'));
      setDeleting(null);
    }
  };

  const handleIndividualDelete = async (itemId) => {
    if (!confirm(__('Are you sure you want to delete this comment?', 'content-forge'))) {
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
      setError(err.message || __('Failed to delete comment', 'content-forge'));
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
      <div className="cforge-bg-gray-50 cforge-rounded cforge-p-6 cforge-shadow cforge-overflow-x-auto">
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
          <p className="cforge-text-center cforge-text-gray-500">{__('Loading...', 'content-forge')}</p>
        ) : error ? (
          <p className="cforge-text-center cforge-text-red-500">{error}</p>
        ) : items.length === 0 ? (
          <p className="cforge-text-center cforge-text-gray-500">{__('No comments found. Click "Add New" to generate comments.', 'content-forge')}</p>
        ) : (
          <table className="cforge-min-w-full cforge-table-auto cforge-bg-white">
            <thead>
              <tr>
                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Content', 'content-forge')}</th>
                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Author', 'content-forge')}</th>
                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('In response to', 'content-forge')}</th>
                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Status', 'content-forge')}</th>
                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Date', 'content-forge')}</th>
                <th className="cforge-px-4 cforge-py-2 cforge-text-left cforge-font-semibold">{__('Actions', 'content-forge')}</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.ID || item.id} className="cforge-border-t cforge-border-gray-200">
                  <td className="cforge-px-4 cforge-py-2">
                    <div className="cforge-max-w-xs cforge-truncate" title={item.content}>
                      {item.content}
                    </div>
                  </td>
                  <td className="cforge-px-4 cforge-py-2">
                    <div>
                      <div className="cforge-font-medium">{item.author_name}</div>
                      <div className="cforge-text-sm cforge-text-gray-500">{item.author_email}</div>
                    </div>
                  </td>
                  <td className="cforge-px-4 cforge-py-2">
                    {item.post_edit_link ? (
                      <a
                        href={item.post_edit_link}
                        className="cforge-text-blue-600 hover:cforge-underline"
                        target="_blank"
                        rel="noopener noreferrer"
                      >
                        {item.post_title}
                      </a>
                    ) : (
                      item.post_title
                    )}
                  </td>
                  <td className="cforge-px-4 cforge-py-2">
                    <span className={`cforge-px-2 cforge-py-1 cforge-rounded cforge-text-xs ${item.status === 'approved' ? 'cforge-bg-green-100 cforge-text-green-800' :
                      item.status === 'unapproved' ? 'cforge-bg-yellow-100 cforge-text-yellow-800' :
                        'cforge-bg-red-100 cforge-text-red-800'
                      }`}>
                      {item.status}
                    </span>
                  </td>
                  <td className="cforge-px-4 cforge-py-2 cforge-text-sm cforge-text-gray-500">
                    {new Date(item.date).toLocaleDateString()}
                  </td>
                  <td className="cforge-px-4 cforge-py-2">
                    <button
                      onClick={() => handleIndividualDelete(item.id)}
                      disabled={deleting === item.id}
                      className="cforge-text-red-600 hover:cforge-text-red-800 cforge-p-1 cforge-rounded hover:cforge-bg-red-50"
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

const allowedCommentStatuses = ['0', '1', 'hold', 'spam'];

function AddNewView({ onCancel, onSuccess }) {
  const [comment, setComment] = useState({
    comment_number: 1,
    post_types: ['post'],
    comment_status: '0',
  });
  const [errors, setErrors] = useState({});
  const [notice, setNotice] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  const postTypes = cforge.post_types;

  const validate = () => {
    const newErrors = {};

    if (!allowedCommentStatuses.includes(comment['comment_status'])) {
      newErrors['comment_status'] = __('Invalid comment status selected', 'content-forge');
    }
    const num = Number(comment['comment_number']);
    if (!num || num < 1) {
      newErrors['comment_number'] = __('Number of comments must be at least 1', 'content-forge');
    }
    if (!comment['post_types'] || comment['post_types'].length === 0) {
      newErrors['post_types'] = __('Please select at least one post type', 'content-forge');
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
      comment_number: Number(comment.comment_number),
      post_types: comment.post_types,
      comment_status: comment.comment_status,
    };
    try {
      await apiFetch({
        path: 'comments/bulk',
        method: 'POST',
        data: payload,
      });
      setSubmitting(false);
      setNotice({
        message: __('Comments generated successfully!', 'content-forge'),
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
                {__('Number of Comments', 'content-forge')}
              </label>
              <input
                type="number"
                min="1"
                className={`cforge-input ${errorClass('comment_number')}`}
                value={comment['comment_number']}
                onChange={e => setComment({ ...comment, comment_number: e.target.value })}
              />
              {errors['comment_number'] && (
                <p className="cforge-text-red-500 cforge-text-sm">{errors['comment_number']}</p>
              )}
            </div>
            <div className="cforge-mb-4">
              <MultiSelect
                options={postTypes}
                value={comment['post_types']}
                onChange={selected => setComment({ ...comment, post_types: selected })}
                label={__('Target Post Types', 'content-forge')}
                placeholder={__('Select post types...', 'content-forge')}
              />
              {errors['post_types'] && (
                <p className="cforge-text-red-500 cforge-text-sm">{errors['post_types']}</p>
              )}
            </div>
            <div className="cforge-mb-4">
              <label className="cforge-block cforge-mb-1 cforge-font-medium">
                {__('Comment Status', 'content-forge')}
              </label>
              <select
                className={`cforge-input ${errorClass('comment_status')}`}
                value={comment['comment_status']}
                onChange={e => setComment({ ...comment, comment_status: e.target.value })}
              >
                <option value="1">{__('Approved', 'content-forge')}</option>
                <option value="0">{__('Pending', 'content-forge')}</option>
                <option value="spam">{__('Spam', 'content-forge')}</option>
              </select>
              {errors['comment_status'] && (
                <p className="cforge-text-red-500 cforge-text-sm">{errors['comment_status']}</p>
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
              {submitting ? __('Generating...', 'content-forge') : __('Generate Comments', 'content-forge')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

function CommentsApp() {
  const [view, setView] = useState('list');
  const handleAddNew = () => setView('add');
  const handleCancel = () => setView('list');
  const handleSuccess = () => setView('list');
  return (
    <div className="cforge-bg-white cforge-p-8 cforge-min-h-screen">
      <Header
        title={__('Comments', 'content-forge')}
      />
      {view === 'list' && (
        <ListViewComments
          endpoint="comments"
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

// Initialize the app
document.addEventListener('DOMContentLoaded', function () {

});

const container = document.getElementById('cforge-comments-app');
if (container) {
  const { createRoot } = require('react-dom/client');
  const root = createRoot(container);
  root.render(<CommentsApp />);
}

