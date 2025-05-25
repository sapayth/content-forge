import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/header';
import apiFetch from '@wordpress/api-fetch';

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
    if (window.fakegen?.rest_nonce) {
        apiFetch.use(apiFetch.createNonceMiddleware(window.fakegen.rest_nonce));
    }
    if (window.fakegen?.apiUrl) {
        apiFetch.use(apiFetch.createRootURLMiddleware(window.fakegen.apiUrl));
    }
    // Fetch pages for parent dropdown
    fetch(window.fakegenData?.restUrl + 'wp/v2/pages')
      .then((response) => response.json())
      .then((data) => setPages(data || []));
  }, []);

  const validate = () => {
    const newErrors = {};
    if (!['publish', 'pending', 'draft'].includes(post['post_status'])) {
      newErrors['post_status'] = __('Invalid status selected', 'fakegen');
    }
    if (!allowedCommentStatuses.includes(post['comment_status'])) {
      newErrors['comment_status'] = __('Invalid comment status selected', 'fakegen');
    }
    if (post['post_type'] === 'page' && post['post_parent'] && post['post_parent'] !== '0') {
      const validParent = pages.some((page) => String(page.id) === String(post['post_parent']));
      if (!validParent) {
        newErrors['post_parent'] = __('Invalid parent page selected', 'fakegen');
      }
    }
    if (tab === 'auto') {
      const num = Number(post['post_number']);
      if (!num || num < 1) {
        newErrors['post_number'] = __('Number of Pages/Posts must be at least 1', 'fakegen');
      }
    } else if (tab === 'manual') {
      if (!allowedPostTypes.includes(post['post_type'])) {
        newErrors['post_type'] = __('Invalid type selected', 'fakegen');
      }
      if (!post['post_title'] || !post['post_title'].trim()) {
        newErrors['post_title'] = __('Please enter at least one title', 'fakegen');
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
        message: tab === 'auto' ? __('Pages/Posts generated successfully!', 'fakegen') : __('Pages/Posts added successfully!', 'fakegen'),
        status: 'success',
      });
      setTimeout(() => {
        setNotice(null);
        onSuccess();
      }, 1500);
    } catch (error) {
      setSubmitting(false);
      setNotice({
        message: error?.message || __('An error occurred. Please try again.', 'fakegen'),
        status: 'error',
      });
    }
  };

  const errorClass = (field) => (errors[field] ? 'fakegen-border-red-500 fakegen-outline-red-500' : '');

  return (
      <div className="fakegen-w-full fakegen-bg-white fakegen-rounded fakegen-p-6 fakegen-relative">
        {notice && (
            <div className={`fakegen-mb-4 fakegen-p-3 fakegen-rounded fakegen-text-white ${notice.status === 'success' ? 'fakegen-bg-green-500' : 'fakegen-bg-red-500'}`}>{notice.message}</div>
        )}
        {/* Tabs */}
        <div className="fakegen-flex fakegen-gap-4">
          <div className="fakegen-w-1/2">
            <label
              className={`fakegen-transition-all fakegen-relative fakegen-flex fakegen-cursor-pointer fakegen-rounded-lg fakegen-border fakegen-p-4 fakegen-shadow-sm focus:fakegen-outline-none fakegen-bg-white ${tab === 'auto' ? 'fakegen-border-primary fakegen-border-2' : ''}`}
            >
              <input
                type="radio"
                value="auto"
                className="fakegen-sr-only"
                checked={tab === 'auto'}
                onChange={() => setTab('auto')}
              />
              <div className="fakegen-flex-1">
                <p className="fakegen-block fakegen-text-sm fakegen-font-medium fakegen-text-gray-900">{__('Auto Generate', 'fakegen')}</p>
                <p className="fakegen-text-sm fakegen-text-gray-500">{__('Auto generate post/page name and contents', 'fakegen')}</p>
              </div>
              {tab === 'auto' && (
                <svg className="fakegen-h-5 fakegen-w-5 fakegen-text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                  <path fillRule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clipRule="evenodd" />
                </svg>
              )}
            </label>
          </div>
          <div className="fakegen-w-1/2">
            <label
              className={`fakegen-transition-all fakegen-relative fakegen-flex fakegen-cursor-pointer fakegen-rounded-lg fakegen-border fakegen-p-4 fakegen-shadow-sm focus:fakegen-outline-none fakegen-bg-white ${tab === 'manual' ? 'fakegen-border-primary fakegen-border-2' : ''}`}
            >
              <input
                type="radio"
                value="manual"
                className="fakegen-sr-only"
                checked={tab === 'manual'}
                onChange={() => setTab('manual')}
              />
              <div className="fakegen-flex-1">
                <p className="fakegen-block fakegen-text-sm fakegen-font-medium fakegen-text-gray-900">{__('Manual', 'fakegen')}</p>
                <p className="fakegen-text-sm fakegen-text-gray-500">{__('Manually input post/page name and contents', 'fakegen')}</p>
              </div>
              {tab === 'manual' && (
                <svg className="fakegen-h-5 fakegen-w-5 fakegen-text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                  <path fillRule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clipRule="evenodd" />
                </svg>
              )}
            </label>
          </div>
        </div>
        <div className="fakegen-flex fakegen-gap-4 fakegen-mb-4 fakegen-w-2/3">
          <form
              className="fakegen-w-full"
              onSubmit={handleSubmit}>
            <div className="fakegen-mt-8">
              {tab === 'auto' ? (
                  <>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Number of Pages/Posts', 'fakegen')}</label>
                      <input
                          type="number"
                          min="1"
                          className={`fakegen-input ${errorClass('post_number')}`}
                          value={post['post_number']}
                          onChange={e => setPost({ ...post, post_number: e.target.value })}
                      />
                      {errors['post_number'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_number']}</p>}
                    </div>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Type', 'fakegen')}</label>
                      <select
                          className={`fakegen-input ${errorClass('post_type')}`}
                          value={post['post_type']}
                          onChange={e => setPost({ ...post, post_type: e.target.value })}
                      >
                        <option value="post">{__('Post', 'fakegen')}</option>
                        <option value="page">{__('Page', 'fakegen')}</option>
                      </select>
                      {errors['post_type'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_type']}</p>}
                    </div>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Pages/Posts Status', 'fakegen')}</label>
                      <select
                          className={`fakegen-input ${errorClass('post_status')}`}
                          value={post['post_status']}
                          onChange={e => setPost({ ...post, post_status: e.target.value })}
                      >
                        <option value="publish">{__('Publish', 'fakegen')}</option>
                        <option value="pending">{__('Pending', 'fakegen')}</option>
                        <option value="draft">{__('Draft', 'fakegen')}</option>
                      </select>
                      {errors['post_status'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_status']}</p>}
                    </div>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Comment Status', 'fakegen')}</label>
                      <select
                          className={`fakegen-input ${errorClass('comment_status')}`}
                          value={post['comment_status']}
                          onChange={e => setPost({ ...post, comment_status: e.target.value })}
                      >
                        <option value="closed">{__('Closed', 'fakegen')}</option>
                        <option value="open">{__('Open', 'fakegen')}</option>
                      </select>
                      {errors['comment_status'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['comment_status']}</p>}
                    </div>
                    {post.post_type === 'page' && (
                        <div className="fakegen-mb-4">
                          <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Parent Page', 'fakegen')}</label>
                          <select
                              className={`fakegen-input ${errorClass('post_parent')}`}
                              value={post['post_parent']}
                              onChange={e => setPost({ ...post, post_parent: e.target.value })}
                          >
                            <option value="0">{__('No Parent', 'fakegen')}</option>
                            {pages.map((page) => (
                                <option key={page.id} value={page.id}>{page.title.rendered}</option>
                            ))}
                          </select>
                          {errors['post_parent'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_parent']}</p>}
                        </div>
                    )}
                  </>
              ) : (
                  <>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Type', 'fakegen')}</label>
                      <select
                          className={`fakegen-input ${errorClass('post_type')}`}
                          value={post['post_type']}
                          onChange={e => setPost({ ...post, post_type: e.target.value })}
                      >
                        <option value="post">{__('Post', 'fakegen')}</option>
                        <option value="page">{__('Page', 'fakegen')}</option>
                      </select>
                      {errors['post_type'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_type']}</p>}
                    </div>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Titles (comma separated)', 'fakegen')}</label>
                      <input
                          type="text"
                          className={`fakegen-input ${errorClass('post_title')}`}
                          value={post['post_title']}
                          onChange={e => setPost({ ...post, post_title: e.target.value })}
                      />
                      {errors['post_title'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_title']}</p>}
                      <p className="fakegen-text-sm fakegen-text-gray-500">{__('eg. Page1, Page2, page3, PAGE4, PAge5', 'fakegen')}</p>
                    </div>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Page/Post content', 'fakegen')}</label>
                      <textarea
                          className={`fakegen-input ${errorClass('post_content')}`}
                          value={post['post_content']}
                          onChange={e => setPost({ ...post, post_content: e.target.value })}
                      />
                      {errors['post_content'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_content']}</p>}
                      <p className="fakegen-text-sm fakegen-text-gray-500">{__('eg. This is the content of the page/post', 'fakegen')}</p>
                    </div>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Pages/Posts Status', 'fakegen')}</label>
                      <select
                          className={`fakegen-input ${errorClass('post_status')}`}
                          value={post['post_status']}
                          onChange={e => setPost({ ...post, post_status: e.target.value })}
                      >
                        <option value="publish">{__('Publish', 'fakegen')}</option>
                        <option value="pending">{__('Pending', 'fakegen')}</option>
                        <option value="draft">{__('Draft', 'fakegen')}</option>
                        <option value="private">{__('Private', 'fakegen')}</option>
                      </select>
                      {errors['post_status'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_status']}</p>}
                    </div>
                    <div className="fakegen-mb-4">
                      <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Comment Status', 'fakegen')}</label>
                      <select
                          className={`fakegen-input ${errorClass('comment_status')}`}
                          value={post['comment_status']}
                          onChange={e => setPost({ ...post, comment_status: e.target.value })}
                      >
                        <option value="closed">{__('Closed', 'fakegen')}</option>
                        <option value="open">{__('Open', 'fakegen')}</option>
                      </select>
                      {errors['comment_status'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['comment_status']}</p>}
                    </div>
                    {post.post_type === 'page' && (
                        <div className="fakegen-mb-4">
                          <label className="fakegen-block fakegen-mb-1 fakegen-font-medium">{__('Parent Page', 'fakegen')}</label>
                          <select
                              className={`fakegen-input ${errorClass('post_parent')}`}
                              value={post['post_parent']}
                              onChange={e => setPost({ ...post, post_parent: e.target.value })}
                          >
                            <option value="0">{__('No Parent', 'fakegen')}</option>
                            {pages.map((page) => (
                                <option key={page.id} value={page.id}>{page.title.rendered}</option>
                            ))}
                          </select>
                          {errors['post_parent'] && <p className="fakegen-text-red-500 fakegen-text-sm">{errors['post_parent']}</p>}
                        </div>
                    )}
                  </>
              )}
            </div>
            <div className="fakegen-flex fakegen-justify-end fakegen-mt-6 fakegen-gap-2">
              <button
                  type="button"
                  className="fakegen-bg-gray-200 fakegen-text-gray-700 fakegen-px-4 fakegen-py-2 fakegen-rounded fakegen-font-semibold hover:fakegen-bg-gray-300"
                  onClick={onCancel}
                  disabled={submitting}
              >
                {__('Cancel', 'fakegen')}
              </button>
              <button
                  type="submit"
                  className="fakegen-bg-primary fakegen-text-white fakegen-px-4 fakegen-py-2 fakegen-rounded fakegen-font-semibold hover:fakegen-bg-primaryHover"
                  disabled={submitting}
              >
                {submitting ? __('Generating...', 'fakegen') : __('Generate', 'fakegen')}
              </button>
            </div>
          </form>
        </div>
      </div>
  );
}

function ListView({ onAddNew }) {
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
        {/* Pagination Controls */}
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

function PagesPostsApp() {
  const [view, setView] = useState('list');
  return (
    <div className="fakegen-bg-white fakegen-p-8 fakegen-min-h-screen">
      <Header />
      {view === 'list' ? (
        <ListView onAddNew={() => setView('add')} />
      ) : (
        <AddNewView onCancel={() => setView('list')} onSuccess={() => setView('list')} />
      )}
    </div>
  );
}

const container = document.getElementById('fakegen-pages-posts-app');
if (container) {
  const { createRoot } = require('react-dom/client');
  const root = createRoot(container);
  root.render(<PagesPostsApp />);
}