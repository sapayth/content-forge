import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/header';
import apiFetch from '@wordpress/api-fetch';
import ListView from './components/ListView';
import { createRoot } from 'react-dom/client';

const allowedCommentStatuses = ['approve', 'hold', 'spam'];

function AddNewView({ onCancel, onSuccess }) {
  const [comment, setComment] = useState({
    comment_number: 1,
    comment_post_ID: 0,
    comment_status: 'approve',
    allow_replies: false,
    reply_probability: 30,
  });
  const [posts, setPosts] = useState([]);
  const [errors, setErrors] = useState({});
  const [notice, setNotice] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    // Configure apiFetch middleware for nonce and root URL
    if (window.cforge?.rest_nonce) {
      apiFetch.use(apiFetch.createNonceMiddleware(window.cforge.rest_nonce));
    }
    if (window.cforge?.apiUrl) {
      apiFetch.use(apiFetch.createRootURLMiddleware(window.cforge.apiUrl));
    }

    // Fetch posts for target dropdown
    const restUrl = window.wpApiSettings?.root || '/wp-json/';
    fetch(restUrl + 'wp/v2/posts?per_page=50&status=publish')
      .then((response) => response.json())
      .then((data) => {
        const postsData = Array.isArray(data) ? data : [];
        // Also fetch pages
        return fetch(restUrl + 'wp/v2/pages?per_page=50&status=publish')
          .then((response) => response.json())
          .then((pagesData) => {
            const pages = Array.isArray(pagesData) ? pagesData : [];
            setPosts([...postsData, ...pages]);
          });
      })
      .catch(() => setPosts([]));
  }, []);

  const validate = () => {
    const newErrors = {};

    if (!allowedCommentStatuses.includes(comment['comment_status'])) {
      newErrors['comment_status'] = __('Invalid comment status selected', 'cforge');
    }

    const num = Number(comment['comment_number']);
    if (!num || num < 1) {
      newErrors['comment_number'] = __('Number of comments must be at least 1', 'cforge');
    }

    if (comment['comment_post_ID'] > 0) {
      const validPost = posts.some((post) => String(post.id) === String(comment['comment_post_ID']));
      if (!validPost) {
        newErrors['comment_post_ID'] = __('Invalid post selected', 'cforge');
      }
    }

    const probability = Number(comment['reply_probability']);
    if (probability < 0 || probability > 100) {
      newErrors['reply_probability'] = __('Reply probability must be between 0 and 100', 'cforge');
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
      comment_post_ID: Number(comment.comment_post_ID),
      comment_status: comment.comment_status,
      allow_replies: comment.allow_replies,
      reply_probability: Number(comment.reply_probability),
    };

    try {
      await apiFetch({
        path: 'comments/bulk',
        method: 'POST',
        data: payload,
      });

      setSubmitting(false);
      setNotice({
        message: __('Comments generated successfully!', 'cforge'),
        status: 'success',
      });

      setTimeout(() => {
        setNotice(null);
        onSuccess();
      }, 1500);
    } catch (error) {
      setSubmitting(false);
      setNotice({
        message: error?.message || __('An error occurred. Please try again.', 'cforge'),
        status: 'error',
      });
    }
  };

  const errorClass = (field) => (errors[field] ? 'cforge-border-red-500 cforge-outline-red-500' : '');

  return (
    <div className="cforge-w-full cforge-bg-white cforge-rounded cforge-p-6 cforge-relative">
      {notice && (
        <div className={`cforge-mb-4 cforge-p-3 cforge-rounded cforge-text-white ${notice.status === 'success' ? 'cforge-bg-green-500' : 'cforge-bg-red-500'}`}>
          {notice.message}
        </div>
      )}

      <form className="cforge-w-full" onSubmit={handleSubmit}>
        <div className="cforge-grid cforge-grid-cols-1 md:cforge-grid-cols-2 cforge-gap-4">
          <div className="cforge-mb-4">
            <label className="cforge-block cforge-mb-1 cforge-font-medium">
              {__('Number of Comments', 'cforge')}
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
            <label className="cforge-block cforge-mb-1 cforge-font-medium">
              {__('Target Post/Page', 'cforge')}
            </label>
            <select
              className={`cforge-input ${errorClass('comment_post_ID')}`}
              value={comment['comment_post_ID']}
              onChange={e => setComment({ ...comment, comment_post_ID: e.target.value })}
            >
              <option value="0">{__('Random Post/Page', 'cforge')}</option>
              {posts.map((post) => (
                <option key={post.id} value={post.id}>
                  {post.title?.rendered || post.title} ({post.type})
                </option>
              ))}
            </select>
            {errors['comment_post_ID'] && (
              <p className="cforge-text-red-500 cforge-text-sm">{errors['comment_post_ID']}</p>
            )}
          </div>

          <div className="cforge-mb-4">
            <label className="cforge-block cforge-mb-1 cforge-font-medium">
              {__('Comment Status', 'cforge')}
            </label>
            <select
              className={`cforge-input ${errorClass('comment_status')}`}
              value={comment['comment_status']}
              onChange={e => setComment({ ...comment, comment_status: e.target.value })}
            >
              <option value="approve">{__('Approved', 'cforge')}</option>
              <option value="hold">{__('Pending', 'cforge')}</option>
              <option value="spam">{__('Spam', 'cforge')}</option>
            </select>
            {errors['comment_status'] && (
              <p className="cforge-text-red-500 cforge-text-sm">{errors['comment_status']}</p>
            )}
          </div>

          <div className="cforge-mb-4">
            <label className="cforge-block cforge-mb-1 cforge-font-medium">
              {__('Reply Probability (%)', 'cforge')}
            </label>
            <input
              type="number"
              min="0"
              max="100"
              className={`cforge-input ${errorClass('reply_probability')}`}
              value={comment['reply_probability']}
              onChange={e => setComment({ ...comment, reply_probability: e.target.value })}
              disabled={!comment.allow_replies}
            />
            {errors['reply_probability'] && (
              <p className="cforge-text-red-500 cforge-text-sm">{errors['reply_probability']}</p>
            )}
          </div>
        </div>

        <div className="cforge-mb-4">
          <label className="cforge-flex cforge-items-center">
            <input
              type="checkbox"
              className="cforge-mr-2"
              checked={comment.allow_replies}
              onChange={e => setComment({ ...comment, allow_replies: e.target.checked })}
            />
            {__('Allow replies to existing comments', 'cforge')}
          </label>
        </div>

        <div className="cforge-flex cforge-gap-2">
          <button
            type="submit"
            disabled={submitting}
            className="cforge-btn cforge-btn-primary"
          >
            {submitting ? __('Generating...', 'cforge') : __('Generate Comments', 'cforge')}
          </button>
          <button
            type="button"
            onClick={onCancel}
            className="cforge-btn cforge-btn-secondary"
          >
            {__('Cancel', 'cforge')}
          </button>
        </div>
      </form>
    </div>
  );
}

function CommentsApp() {
  const [view, setView] = useState('list');

  const handleAddNew = () => setView('add');
  const handleCancel = () => setView('list');
  const handleSuccess = () => setView('list');


  return (
    <div className="cforge-container">
      <Header
        title={__('Comments', 'cforge')}
        onAddNew={view === 'list' ? handleAddNew : null}
      />

      {view === 'list' && (
        <ListView
          endpoint="comments"
          columns={[
            { key: 'content', label: __('Content', 'cforge') },
            { key: 'author_name', label: __('Author', 'cforge') },
            { key: 'post_title', label: __('Post', 'cforge') },
            { key: 'status', label: __('Status', 'cforge') },
            { key: 'date', label: __('Date', 'cforge') },
          ]}
          renderRow={(item) => (
            <>
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
            </>
          )}
          actions={(item, handleDelete, deleting) => (
            <button
              onClick={() => handleDelete(item.id)}
              disabled={deleting === item.id}
              className="cforge-text-red-600 hover:cforge-text-red-800 cforge-p-1 cforge-rounded hover:cforge-bg-red-50"
              title={__('Delete', 'cforge')}
            >
              {deleting === item.id ? (
                <span className="cforge-text-xs">{__('...', 'cforge')}</span>
              ) : (
                <svg className="cforge-w-4 cforge-h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              )}
            </button>
          )}
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

