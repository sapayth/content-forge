import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/header';
import apiFetch from '@wordpress/api-fetch';
import ListView from './components/ListView';

const allowedPostTypes = ['post', 'page'];
const allowedPostStatuses = ['publish', 'pending', 'draft', 'private'];
const allowedCommentStatuses = ['closed', 'open'];

function AddNewView({ onCancel, onSuccess }) {
  const [tab, setTab] = useState('auto');
  const [post, setPost] = useState({
    post_number: 1,
    post_type: 'post',
    post_status: 'publish',
    comment_status: 'closed',
    post_parent: '0',
    post_title: '',
    post_content: '',
  });
  const [pages, setPages] = useState([]);
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
    // Fetch pages for parent dropdown
    fetch(window.cforgeData?.restUrl + 'wp/v2/pages')
      .then((response) => response.json())
      .then((data) => setPages(data || []));
  }, []);

  const validate = () => {
    const newErrors = {};
    if (!['publish', 'pending', 'draft'].includes(post['post_status'])) {
      newErrors['post_status'] = __('Invalid status selected', 'content-forge');
    }
    if (!allowedCommentStatuses.includes(post['comment_status'])) {
      newErrors['comment_status'] = __('Invalid comment status selected', 'content-forge');
    }
    if (post['post_type'] === 'page' && post['post_parent'] && post['post_parent'] !== '0') {
      const validParent = pages.some((page) => String(page.id) === String(post['post_parent']));
      if (!validParent) {
        newErrors['post_parent'] = __('Invalid parent page selected', 'content-forge');
      }
    }
    if (tab === 'auto') {
      const num = Number(post['post_number']);
      if (!num || num < 1) {
        newErrors['post_number'] = __('Number of Pages/Posts must be at least 1', 'content-forge');
      }
    } else if (tab === 'manual') {
      if (!allowedPostTypes.includes(post['post_type'])) {
        newErrors['post_type'] = __('Invalid type selected', 'content-forge');
      }
      if (!post['post_title'] || !post['post_title'].trim()) {
        newErrors['post_title'] = __('Please enter at least one title', 'content-forge');
      }
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
    let payload = {
      post_type: post.post_type,
      post_status: post.post_status,
      comment_status: post.comment_status,
      post_parent: post.post_type === 'page' ? post.post_parent : '0',
    };
    if (tab === 'auto') {
      payload.post_number = Number(post.post_number);
    } else {
      payload.post_titles = post.post_title.split(',').map(t => t.trim()).filter(Boolean);
      payload.post_contents = payload.post_titles.map(() => post.post_content);
    }
    try {
      await apiFetch({
        path: 'posts/bulk',
        method: 'POST',
        data: payload,
      });
      setSubmitting(false);
      setNotice({
        message: tab === 'auto' ? __('Pages/Posts generated successfully!', 'content-forge') : __('Pages/Posts added successfully!', 'content-forge'),
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
        <div className="cforge-w-1/2">
          <label
            className={`cforge-transition-all cforge-relative cforge-flex cforge-cursor-pointer cforge-rounded-lg cforge-border cforge-p-4 cforge-shadow-sm focus:cforge-outline-none cforge-bg-white ${tab === 'auto' ? 'cforge-border-primary cforge-border-2' : ''}`}
          >
            <input
              type="radio"
              value="auto"
              className="cforge-sr-only"
              checked={tab === 'auto'}
              onChange={() => setTab('auto')}
            />
            <div className="cforge-flex-1">
              <p className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-900">{__('Auto Generate', 'content-forge')}</p>
              <p className="cforge-text-sm cforge-text-gray-500">{__('Auto generate post/page name and contents', 'content-forge')}</p>
            </div>
            {tab === 'auto' && (
              <svg className="cforge-h-5 cforge-w-5 cforge-text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                <path fillRule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clipRule="evenodd" />
              </svg>
            )}
          </label>
        </div>
        <div className="cforge-w-1/2">
          <label
            className={`cforge-transition-all cforge-relative cforge-flex cforge-cursor-pointer cforge-rounded-lg cforge-border cforge-p-4 cforge-shadow-sm focus:cforge-outline-none cforge-bg-white ${tab === 'manual' ? 'cforge-border-primary cforge-border-2' : ''}`}
          >
            <input
              type="radio"
              value="manual"
              className="cforge-sr-only"
              checked={tab === 'manual'}
              onChange={() => setTab('manual')}
            />
            <div className="cforge-flex-1">
              <p className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-900">{__('Manual', 'content-forge')}</p>
              <p className="cforge-text-sm cforge-text-gray-500">{__('Manually input post/page name and contents', 'content-forge')}</p>
            </div>
            {tab === 'manual' && (
              <svg className="cforge-h-5 cforge-w-5 cforge-text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                <path fillRule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clipRule="evenodd" />
              </svg>
            )}
          </label>
        </div>
      </div>
      <div className="cforge-flex cforge-gap-4 cforge-mb-4 cforge-w-2/3">
        <form
          className="cforge-w-full"
          onSubmit={handleSubmit}>
          <div className="cforge-mt-8">
            {tab === 'auto' ? (
              <>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Number of Pages/Posts', 'content-forge')}</label>
                  <input
                    type="number"
                    min="1"
                    className={`cforge-input ${errorClass('post_number')}`}
                    value={post['post_number']}
                    onChange={e => setPost({ ...post, post_number: e.target.value })}
                  />
                  {errors['post_number'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_number']}</p>}
                </div>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Type', 'content-forge')}</label>
                  <select
                    className={`cforge-input ${errorClass('post_type')}`}
                    value={post['post_type']}
                    onChange={e => setPost({ ...post, post_type: e.target.value })}
                  >
                    <option value="post">{__('Post', 'content-forge')}</option>
                    <option value="page">{__('Page', 'content-forge')}</option>
                  </select>
                  {errors['post_type'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_type']}</p>}
                </div>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Pages/Posts Status', 'content-forge')}</label>
                  <select
                    className={`cforge-input ${errorClass('post_status')}`}
                    value={post['post_status']}
                    onChange={e => setPost({ ...post, post_status: e.target.value })}
                  >
                    <option value="publish">{__('Publish', 'content-forge')}</option>
                    <option value="pending">{__('Pending', 'content-forge')}</option>
                    <option value="draft">{__('Draft', 'content-forge')}</option>
                  </select>
                  {errors['post_status'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_status']}</p>}
                </div>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Comment Status', 'content-forge')}</label>
                  <select
                    className={`cforge-input ${errorClass('comment_status')}`}
                    value={post['comment_status']}
                    onChange={e => setPost({ ...post, comment_status: e.target.value })}
                  >
                    <option value="closed">{__('Closed', 'content-forge')}</option>
                    <option value="open">{__('Open', 'content-forge')}</option>
                  </select>
                  {errors['comment_status'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['comment_status']}</p>}
                </div>
                {post.post_type === 'page' && (
                  <div className="cforge-mb-4">
                    <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Parent Page', 'content-forge')}</label>
                    <select
                      className={`cforge-input ${errorClass('post_parent')}`}
                      value={post['post_parent']}
                      onChange={e => setPost({ ...post, post_parent: e.target.value })}
                    >
                      <option value="0">{__('No Parent', 'content-forge')}</option>
                      {pages.map((page) => (
                        <option key={page.id} value={page.id}>{page.title.rendered}</option>
                      ))}
                    </select>
                    {errors['post_parent'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_parent']}</p>}
                  </div>
                )}
              </>
            ) : (
              <>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Type', 'content-forge')}</label>
                  <select
                    className={`cforge-input ${errorClass('post_type')}`}
                    value={post['post_type']}
                    onChange={e => setPost({ ...post, post_type: e.target.value })}
                  >
                    <option value="post">{__('Post', 'content-forge')}</option>
                    <option value="page">{__('Page', 'content-forge')}</option>
                  </select>
                  {errors['post_type'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_type']}</p>}
                </div>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Titles (comma separated)', 'content-forge')}</label>
                  <input
                    type="text"
                    className={`cforge-input ${errorClass('post_title')}`}
                    value={post['post_title']}
                    onChange={e => setPost({ ...post, post_title: e.target.value })}
                  />
                  {errors['post_title'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_title']}</p>}
                  <p className="cforge-text-sm cforge-text-gray-500">{__('eg. Page1, Page2, page3, PAGE4, PAge5', 'content-forge')}</p>
                </div>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Page/Post content', 'content-forge')}</label>
                  <textarea
                    className={`cforge-input ${errorClass('post_content')}`}
                    value={post['post_content']}
                    onChange={e => setPost({ ...post, post_content: e.target.value })}
                  />
                  {errors['post_content'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_content']}</p>}
                  <p className="cforge-text-sm cforge-text-gray-500">{__('eg. This is the content of the page/post', 'content-forge')}</p>
                </div>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Pages/Posts Status', 'content-forge')}</label>
                  <select
                    className={`cforge-input ${errorClass('post_status')}`}
                    value={post['post_status']}
                    onChange={e => setPost({ ...post, post_status: e.target.value })}
                  >
                    <option value="publish">{__('Publish', 'content-forge')}</option>
                    <option value="pending">{__('Pending', 'content-forge')}</option>
                    <option value="draft">{__('Draft', 'content-forge')}</option>
                    <option value="private">{__('Private', 'content-forge')}</option>
                  </select>
                  {errors['post_status'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_status']}</p>}
                </div>
                <div className="cforge-mb-4">
                  <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Comment Status', 'content-forge')}</label>
                  <select
                    className={`cforge-input ${errorClass('comment_status')}`}
                    value={post['comment_status']}
                    onChange={e => setPost({ ...post, comment_status: e.target.value })}
                  >
                    <option value="closed">{__('Closed', 'content-forge')}</option>
                    <option value="open">{__('Open', 'content-forge')}</option>
                  </select>
                  {errors['comment_status'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['comment_status']}</p>}
                </div>
                {post.post_type === 'page' && (
                  <div className="cforge-mb-4">
                    <label className="cforge-block cforge-mb-1 cforge-font-medium">{__('Parent Page', 'content-forge')}</label>
                    <select
                      className={`cforge-input ${errorClass('post_parent')}`}
                      value={post['post_parent']}
                      onChange={e => setPost({ ...post, post_parent: e.target.value })}
                    >
                      <option value="0">{__('No Parent', 'content-forge')}</option>
                      {pages.map((page) => (
                        <option key={page.id} value={page.id}>{page.title.rendered}</option>
                      ))}
                    </select>
                    {errors['post_parent'] && <p className="cforge-text-red-500 cforge-text-sm">{errors['post_parent']}</p>}
                  </div>
                )}
              </>
            )}
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
              {submitting ? __('Generating...', 'content-forge') : __('Generate', 'content-forge')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

function PagesPostsApp() {
  const [view, setView] = useState('list');
  return (
    <div className="cforge-bg-white cforge-p-8 cforge-min-h-screen">
      <Header
        title={__('Pages/Posts', 'content-forge')}
      />

      {view === 'list' ? (
        <ListView
          endpoint="posts"
          columns={[
            { key: 'title', label: __('Title', 'content-forge') },
            { key: 'author', label: __('Author', 'content-forge') },
            { key: 'type', label: __('Type', 'content-forge') },
            { key: 'date', label: __('Date', 'content-forge') },
          ]}
          renderRow={(item) => (
            <>
              <td
                className="cforge-px-4 cforge-py-2 cforge-text-blue-700 cforge-font-medium cforge-cursor-pointer cforge-underline"
                onClick={() => {
                  const editUrl = `${window.location.origin}/wp-admin/post.php?post=${item.ID}&action=edit`;
                  window.open(editUrl, '_blank');
                }}
                title={__('Edit this post/page', 'content-forge')}
              >
                {item.title}
              </td>
              <td className="cforge-px-4 cforge-py-2">{item.author}</td>
              <td className="cforge-px-4 cforge-py-2">{item.type}</td>
              <td className="cforge-px-4 cforge-py-2">{item.date}</td>
            </>
          )}
          actions={(item, handleDelete, deleting) => (
            <button
              onClick={() => handleDelete(item.ID)}
              disabled={deleting === item.ID}
              className="cforge-text-red-600 hover:cforge-text-red-800 cforge-p-1 cforge-rounded hover:cforge-bg-red-50"
              title={__('Delete', 'content-forge')}
            >
              {deleting === item.ID ? (
                <span className="cforge-text-xs">{__('...', 'content-forge')}</span>
              ) : (
                <svg className="cforge-w-4 cforge-h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              )}
            </button>
          )}
          onAddNew={() => setView('add')}
        />
      ) : (
        <AddNewView onCancel={() => setView('list')} onSuccess={() => setView('list')} />
      )}
    </div>
  );
}

const container = document.getElementById('cforge-pages-posts-app');
if (container) {
  const { createRoot } = require('react-dom/client');
  const root = createRoot(container);
  root.render(<PagesPostsApp />);
}