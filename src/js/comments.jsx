import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/Header';
import apiFetch from '@wordpress/api-fetch';
import MultiSelect from './components/MultiSelect';
import ListView from './components/ListView';
import Badge from './components/Badge';
import Button from './components/Button';
import Card from './components/Card';
import Field, { errorClass } from './components/Field';
import Notice from './components/Notice';
import { createRoot } from 'react-dom/client';


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

  return (
    <div className="cforge-w-full cforge-p-6 cforge-relative">
      {notice && (
        <Notice status={notice.status}>{notice.message}</Notice>
      )}
      <div className="cforge-flex cforge-gap-4">
        <form className="cforge-w-2/3" onSubmit={handleSubmit}>
          <Card
            title={__('Generate Comments', 'content-forge')}
            footer={
              <>
                <Button variant="secondary" onClick={onCancel} disabled={submitting}>
                  {__('Cancel', 'content-forge')}
                </Button>
                <Button type="submit" disabled={submitting}>
                  {submitting ? __('Generating...', 'content-forge') : __('Generate Comments', 'content-forge')}
                </Button>
              </>
            }
          >
            <Field
              label={__('Number of Comments', 'content-forge')}
              htmlFor="cforge-comment-number"
              error={errors['comment_number']}
            >
              <input
                id="cforge-comment-number"
                type="number"
                min="1"
                className={`cforge-input ${errorClass(errors['comment_number'])}`}
                value={comment['comment_number']}
                onChange={e => setComment({ ...comment, comment_number: e.target.value })}
              />
            </Field>
            <Field error={errors['post_types']}>
              <MultiSelect
                options={postTypes}
                value={comment['post_types']}
                onChange={selected => setComment({ ...comment, post_types: selected })}
                label={__('Target Post Types', 'content-forge')}
                placeholder={__('Select post types...', 'content-forge')}
              />
            </Field>
            <Field
              label={__('Comment Status', 'content-forge')}
              htmlFor="cforge-comment-status"
              error={errors['comment_status']}
            >
              <select
                id="cforge-comment-status"
                className={`cforge-input ${errorClass(errors['comment_status'])}`}
                value={comment['comment_status']}
                onChange={e => setComment({ ...comment, comment_status: e.target.value })}
              >
                <option value="1">{__('Approved', 'content-forge')}</option>
                <option value="0">{__('Pending', 'content-forge')}</option>
                <option value="spam">{__('Spam', 'content-forge')}</option>
              </select>
            </Field>
          </Card>
        </form>
      </div>
    </div>
  );
}

function CommentsApp() {
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
      path: `comments?page=${page}&per_page=${perPage}`,
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
      path: `comments?page=${pageToLoad}&per_page=${perPage}`,
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
    if (!confirm(__('Are you sure you want to delete this comment?', 'content-forge'))) {
      return;
    }

    setDeleting(itemId);
    setNotice(null);
    try {
      await apiFetch({
        path: `comments/${itemId}`,
        method: 'DELETE',
      });
      setNotice({
        message: __('Comment deleted successfully!', 'content-forge'),
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
        message: err.message || __('Failed to delete comment', 'content-forge'),
        status: 'error',
      });
      setDeleting(null);
      setTimeout(() => setNotice(null), 5000);
    }
  };

  const handleDeleteAll = async () => {
    if (!confirm(__('Are you sure you want to delete all generated comments?', 'content-forge'))) {
      return;
    }

    setDeleting('all');
    setNotice(null);
    try {
      await apiFetch({
        path: 'comments/bulk',
        method: 'DELETE',
      });
      setNotice({
        message: __('All comments deleted successfully!', 'content-forge'),
        status: 'success',
      });
      setItems([]);
      setTotal(0);
      setDeleting(null);
      setTimeout(() => setNotice(null), 3000);
    } catch (err) {
      setNotice({
        message: err.message || __('Failed to delete comments', 'content-forge'),
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
        heading={__('Comments', 'content-forge')}
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
            { key: 'content', label: __('Content', 'content-forge') },
            { key: 'author', label: __('Author', 'content-forge') },
            { key: 'post', label: __('In response to', 'content-forge') },
            { key: 'status', label: __('Status', 'content-forge') },
            { key: 'date', label: __('Date', 'content-forge') },
          ]}
          renderRow={(item) => (
            <>
              <td>
                <div className="cforge-max-w-xs cforge-truncate" title={item.content}>
                  {item.content}
                </div>
              </td>
              <td>
                <div>
                  <div className="cforge-font-medium">{item.author_name}</div>
                  <div className="cforge-text-sm cforge-text-text-secondary">{item.author_email}</div>
                </div>
              </td>
              <td>
                {item.post_edit_link ? (
                  <a
                    href={item.post_edit_link}
                    className="cforge-text-error hover:cforge-text-errorHover"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {item.post_title}
                  </a>
                ) : (
                  item.post_title
                )}
              </td>
              <td className="cforge-whitespace-nowrap">
                <Badge
                  status={item.status === 'approved' ? 'success' : item.status === 'unapproved' ? 'warning' : 'error'}
                  dot
                >
                  {item.status}
                </Badge>
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
          title={__('Comments', 'content-forge')}
          description={__('A list of all the generated comments including their content, author, status and date.', 'content-forge')}
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

// Initialize the app
document.addEventListener('DOMContentLoaded', function () {

});

const container = document.getElementById('cforge-comments-app');
if (container) {
  const { createRoot } = require('react-dom/client');
  const root = createRoot(container);
  root.render(<CommentsApp />);
}
