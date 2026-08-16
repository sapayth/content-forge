import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import '../css/common.css';
import Header from './components/Header';
import apiFetch from '@wordpress/api-fetch';
import ListView from './components/ListView';
import AIGenerateTab from './components/AIGenerateTab';
import DateRangePicker from './components/DateRangePicker';
import AuthorSelect from './components/AuthorSelect';
import TaxonomySection, { useTaxonomyAssignment } from './components/TaxonomySection';
import Button from './components/Button';
import Field, { errorClass } from './components/Field';
import Notice from './components/Notice';

const allowedPostTypes = ['post', 'page'];
const allowedPostStatuses = ['publish', 'pending', 'draft', 'private', 'future'];
const allowedCommentStatuses = ['closed', 'open'];

/**
 * Build the value a datetime-local input expects ("YYYY-MM-DDTHH:mm"),
 * defaulting a new schedule to an hour from now.
 */
function defaultScheduleDate() {
  const pad = (n) => String(n).padStart(2, '0');
  const d = new Date(Date.now() + 60 * 60 * 1000);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/**
 * Publish date/time picker, shown only when the "Scheduled" status is selected.
 */
function ScheduleField({ value, error, onChange }) {
  return (
    <Field
      label={__('Publish On', 'content-forge')}
      htmlFor="cforge-pp-post-date"
      error={error}
      hint={__('Uses your site timezone. Posts publish automatically at this time.', 'content-forge')}
      className="cforge-flex-1"
    >
      <input id="cforge-pp-post-date"
        type="datetime-local"
        className={`cforge-input ${errorClass(error)}`}
        value={value || ''}
        onChange={e => onChange(e.target.value)}
      />
    </Field>
  );
}

/**
 * Build the author-related payload keys from the selected mode/IDs.
 * Returns {} for 'me' so the backend defaults to the current user.
 */
function buildAuthorPayload(authorMode, authorIds) {
  if (authorMode === 'specific' && authorIds.length > 0) {
    return { author_mode: 'specific', authors: authorIds };
  }
  if (authorMode === 'random') {
    return { author_mode: 'random' };
  }
  return {};
}

function AddNewView({ onCancel, onSuccess }) {
  const [tab, setTab] = useState('auto');
  const [post, setPost] = useState({
    post_number: 1,
    post_type: 'post',
    post_status: 'publish',
    post_date: '',
    comment_status: 'closed',
    post_parent: '0',
    post_title: '',
    post_content: '',
  });
  const [generateImage, setGenerateImage] = useState(false);
  const [imageSources, setImageSources] = useState({ picsum: true, placehold: false });
  const [generateExcerpt, setGenerateExcerpt] = useState(true);
  const authors = window.cforge?.authors || [];
  const [authorMode, setAuthorMode] = useState('me');
  const [authorIds, setAuthorIds] = useState([]);
  const [randomizeDates, setRandomizeDates] = useState(false);
  const taxonomyAssignment = useTaxonomyAssignment(post.post_type);
  const today = new Date().toISOString().split('T')[0];
  const [dateFrom, setDateFrom] = useState(today);
  const [dateTo, setDateTo] = useState(today);
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
    fetch(window.cforge?.restUrl + 'wp/v2/pages')
      .then((response) => response.json())
      .then((data) => setPages(data || []))

  }, []);

  // Switching to "Scheduled" seeds a date and drops randomized dates, which it replaces.
  const handleStatusChange = (value) => {
    if (value === 'future') {
      setRandomizeDates(false);
    }
    setPost({
      ...post,
      post_status: value,
      post_date: value === 'future' && !post.post_date ? defaultScheduleDate() : post.post_date,
    });
  };

  const validate = () => {
    const newErrors = {};
    if (!allowedPostStatuses.includes(post['post_status'])) {
      newErrors['post_status'] = __('Invalid status selected', 'content-forge');
    }
    if (post['post_status'] === 'future') {
      const scheduled = new Date(post['post_date']);
      if (!post['post_date'] || isNaN(scheduled.getTime())) {
        newErrors['post_date'] = __('Please choose a publish date', 'content-forge');
      } else if (scheduled.getTime() <= Date.now()) {
        newErrors['post_date'] = __('Scheduled date must be in the future', 'content-forge');
      }
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

    // For AI tab, generation is handled by the AIGenerateTab component
    if (tab === 'ai') {
      // Don't submit form for AI generation - it's handled by the component
      return;
    }

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
    // Add featured image options for auto-generated posts
    if (tab === 'auto' && generateImage) {
      payload.generate_image = true;
      const selectedSources = [];
      if (imageSources.picsum) selectedSources.push('picsum');
      if (imageSources.placehold) selectedSources.push('placehold');
      payload.image_sources = selectedSources.length > 0 ? selectedSources : ['picsum'];
    }
    // Add excerpt generation option
    if (tab === 'auto') {
      payload.generate_excerpt = generateExcerpt;
    }
    // Add scheduling or date range options (scheduling replaces the range)
    if (post.post_status === 'future') {
      payload.post_date = post.post_date;
    } else if (randomizeDates && dateFrom && dateTo) {
      payload.date_from = dateFrom;
      payload.date_to = dateTo;
    }
    // Add author assignment options
    Object.assign(payload, buildAuthorPayload(authorMode, authorIds));
    // Add taxonomy term assignment options
    if (Object.keys(taxonomyAssignment.payload).length > 0) {
      payload.taxonomy_options = taxonomyAssignment.payload;
    }
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


  return (
    <div className="cforge-w-full cforge-bg-white cforge-rounded cforge-p-6 cforge-relative">
      {notice && (
        <Notice status={notice.status}>{notice.message}</Notice>
      )}
      <div className="cforge-flex cforge-gap-4">
        <div className="cforge-w-1/3">
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
        <div className="cforge-w-1/3">
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
        <div className="cforge-w-1/3">
          <label
            className={`cforge-transition-all cforge-relative cforge-flex cforge-cursor-pointer cforge-rounded-lg cforge-border cforge-p-4 cforge-shadow-sm focus:cforge-outline-none cforge-bg-white ${tab === 'ai' ? 'cforge-border-primary cforge-border-2' : ''}`}
          >
            <input
              type="radio"
              value="ai"
              className="cforge-sr-only"
              checked={tab === 'ai'}
              onChange={() => setTab('ai')}
            />
            <div className="cforge-flex-1">
              <div className="cforge-flex">
                <p className="cforge-block cforge-text-sm cforge-font-medium cforge-text-gray-900 cforge-mr-4">{__('AI Generate', 'content-forge')}</p>
                <div className="cforge-bg-green-100 cforge-text-green-700 cforge-text-xs cforge-font-semibold cforge-px-2 cforge-py-1 cforge-rounded-full">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="cforge-size-4 cforge-self-center">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                </svg>
              </div>
              </div>
              <p className="cforge-text-sm cforge-text-gray-500">{__('Generate content using AI', 'content-forge')}</p>
            </div>
            {tab === 'ai' && (
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
                <div className="cforge-flex cforge-gap-4 cforge-mb-4">
                  <Field
                    label={__('Type', 'content-forge')}
                    htmlFor="cforge-pp-type"
                    error={errors['post_type']}
                    className="cforge-flex-1"
                  >
                    <select id="cforge-pp-type"
                      className={`cforge-input ${errorClass(errors['post_type'])}`}
                      value={post['post_type']}
                      onChange={e => setPost({ ...post, post_type: e.target.value })}
                    >
                      <option value="post">{__('Post', 'content-forge')}</option>
                      <option value="page">{__('Page', 'content-forge')}</option>
                    </select>
                  </Field>
                  <Field
                    label={__('Number of Pages/Posts', 'content-forge')}
                    htmlFor="cforge-pp-number-of-pages-posts"
                    error={errors['post_number']}
                    className="cforge-flex-1"
                  >
                    <input id="cforge-pp-number-of-pages-posts"
                      type="number"
                      min="1"
                      className={`cforge-input ${errorClass(errors['post_number'])}`}
                      value={post['post_number']}
                      onChange={e => setPost({ ...post, post_number: e.target.value })}
                    />
                  </Field>
                </div>
                <div className="cforge-flex cforge-gap-4 cforge-mb-4">
                  <Field
                    label={__('Pages/Posts Status', 'content-forge')}
                    htmlFor="cforge-pp-pages-posts-status"
                    error={errors['post_status']}
                    className="cforge-flex-1"
                  >
                    <select id="cforge-pp-pages-posts-status"
                      className={`cforge-input ${errorClass(errors['post_status'])}`}
                      value={post['post_status']}
                      onChange={e => handleStatusChange(e.target.value)}
                    >
                      <option value="publish">{__('Publish', 'content-forge')}</option>
                      <option value="pending">{__('Pending', 'content-forge')}</option>
                      <option value="draft">{__('Draft', 'content-forge')}</option>
                      <option value="private">{__('Private', 'content-forge')}</option>
                      <option value="future">{__('Scheduled', 'content-forge')}</option>
                    </select>
                  </Field>
                  <Field
                    label={__('Comment Status', 'content-forge')}
                    htmlFor="cforge-pp-comment-status"
                    error={errors['comment_status']}
                    className="cforge-flex-1"
                  >
                    <select id="cforge-pp-comment-status"
                      className={`cforge-input ${errorClass(errors['comment_status'])}`}
                      value={post['comment_status']}
                      onChange={e => setPost({ ...post, comment_status: e.target.value })}
                    >
                      <option value="closed">{__('Closed', 'content-forge')}</option>
                      <option value="open">{__('Open', 'content-forge')}</option>
                    </select>
                  </Field>
                </div>
                {post.post_status === 'future' && (
                  <div className="cforge-flex cforge-gap-4 cforge-mb-4">
                    <ScheduleField
                      value={post.post_date}
                      error={errors['post_date']}
                      onChange={value => setPost({ ...post, post_date: value })}
                    />
                    <div className="cforge-flex-1" />
                  </div>
                )}
                {post.post_type === 'page' && (
                  <Field
                    label={__('Parent Page', 'content-forge')}
                    htmlFor="cforge-pp-parent-page"
                    error={errors['post_parent']}
                    className="cforge-mb-4"
                  >
                    <select id="cforge-pp-parent-page"
                      className={`cforge-input ${errorClass(errors['post_parent'])}`}
                      value={post['post_parent']}
                      onChange={e => setPost({ ...post, post_parent: e.target.value })}
                    >
                      <option value="0">{__('No Parent', 'content-forge')}</option>
                      {pages.map((page) => (
                        <option key={page.id} value={page.id}>{page.title.rendered}</option>
                      ))}
                    </select>
                  </Field>
                )}
                <div className="cforge-mb-4 cforge-border cforge-border-gray-200 cforge-rounded-lg cforge-p-4 cforge-bg-gray-50">
                  <div className="cforge-flex cforge-gap-6">
                    <div className="cforge-flex-1">
                      <ToggleControl
                        label={__('Generate Featured Image', 'content-forge')}
                        checked={generateImage}
                        onChange={setGenerateImage}
                      />
                    </div>
                    <div className="cforge-flex-1">
                      <ToggleControl
                        label={__('Generate Post Excerpt', 'content-forge')}
                        checked={generateExcerpt}
                        onChange={setGenerateExcerpt}
                        help={__('Auto-generate excerpt from content', 'content-forge')}
                      />
                    </div>
                    <div className="cforge-flex-1">
                      <ToggleControl
                        label={__('Randomize Post Dates', 'content-forge')}
                        checked={randomizeDates}
                        onChange={setRandomizeDates}
                        disabled={post.post_status === 'future'}
                        help={post.post_status === 'future'
                          ? __('Unavailable for scheduled posts', 'content-forge')
                          : __('Random date within a range', 'content-forge')}
                      />
                    </div>
                  </div>
                  {generateImage && (
                    <div className="cforge-ml-6 cforge-space-y-2 cforge-mt-3 cforge-pt-3 cforge-border-t cforge-border-gray-200">
                      <p className="cforge-text-sm cforge-text-gray-600 cforge-mb-2">{__('Image Sources:', 'content-forge')}</p>
                      <div className="cforge-flex cforge-items-center">
                        <input
                          type="checkbox"
                          id="source-picsum"
                          className="cforge-h-4 cforge-w-4 cforge-text-primary cforge-border-gray-300 cforge-rounded focus:cforge-ring-primary"
                          checked={imageSources.picsum}
                          onChange={(e) => setImageSources({ ...imageSources, picsum: e.target.checked })}
                        />
                        <label htmlFor="source-picsum" className="cforge-ml-2 cforge-block cforge-text-sm cforge-text-gray-700">
                          {__('Picsum Photos', 'content-forge')} <span className="cforge-text-gray-500">({__('Real photos', 'content-forge')})</span>
                        </label>
                      </div>
                      <div className="cforge-flex cforge-items-center">
                        <input
                          type="checkbox"
                          id="source-placehold"
                          className="cforge-h-4 cforge-w-4 cforge-text-primary cforge-border-gray-300 cforge-rounded focus:cforge-ring-primary"
                          checked={imageSources.placehold}
                          onChange={(e) => setImageSources({ ...imageSources, placehold: e.target.checked })}
                        />
                        <label htmlFor="source-placehold" className="cforge-ml-2 cforge-block cforge-text-sm cforge-text-gray-700">
                          {__('Placehold.co', 'content-forge')} <span className="cforge-text-gray-500">({__('Simple placeholders', 'content-forge')})</span>
                        </label>
                      </div>
                      {!imageSources.picsum && !imageSources.placehold && (
                        <p className="cforge-text-xs cforge-text-yellow-600 cforge-mt-1">
                          {__('⚠ No source selected. Will default to Picsum.', 'content-forge')}
                        </p>
                      )}
                    </div>
                  )}
                  {randomizeDates && (
                    <div className="cforge-mt-3 cforge-pt-3 cforge-border-t cforge-border-gray-200">
                      <DateRangePicker
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onDateFromChange={setDateFrom}
                        onDateToChange={setDateTo}
                      />
                    </div>
                  )}
                </div>
                <div className="cforge-mb-4">
                  <AuthorSelect
                    authors={authors}
                    mode={authorMode}
                    onModeChange={setAuthorMode}
                    selected={authorIds}
                    onSelectedChange={setAuthorIds}
                  />
                </div>
                <div className="cforge-mb-4">
                  <TaxonomySection {...taxonomyAssignment} />
                </div>
              </>
            ) : tab === 'manual' ? (
              <>
                <div className="cforge-flex cforge-gap-4 cforge-mb-4">
                  <Field
                    label={__('Type', 'content-forge')}
                    htmlFor="cforge-pp-type"
                    error={errors['post_type']}
                    className="cforge-flex-1"
                  >
                    <select id="cforge-pp-type"
                      className={`cforge-input ${errorClass(errors['post_type'])}`}
                      value={post['post_type']}
                      onChange={e => setPost({ ...post, post_type: e.target.value })}
                    >
                      <option value="post">{__('Post', 'content-forge')}</option>
                      <option value="page">{__('Page', 'content-forge')}</option>
                    </select>
                  </Field>
                  <Field
                    label={__('Pages/Posts Status', 'content-forge')}
                    htmlFor="cforge-pp-pages-posts-status"
                    error={errors['post_status']}
                    className="cforge-flex-1"
                  >
                    <select id="cforge-pp-pages-posts-status"
                      className={`cforge-input ${errorClass(errors['post_status'])}`}
                      value={post['post_status']}
                      onChange={e => handleStatusChange(e.target.value)}
                    >
                      <option value="publish">{__('Publish', 'content-forge')}</option>
                      <option value="pending">{__('Pending', 'content-forge')}</option>
                      <option value="draft">{__('Draft', 'content-forge')}</option>
                      <option value="private">{__('Private', 'content-forge')}</option>
                      <option value="future">{__('Scheduled', 'content-forge')}</option>
                    </select>
                  </Field>
                  <Field
                    label={__('Comment Status', 'content-forge')}
                    htmlFor="cforge-pp-comment-status"
                    error={errors['comment_status']}
                    className="cforge-flex-1"
                  >
                    <select id="cforge-pp-comment-status"
                      className={`cforge-input ${errorClass(errors['comment_status'])}`}
                      value={post['comment_status']}
                      onChange={e => setPost({ ...post, comment_status: e.target.value })}
                    >
                      <option value="closed">{__('Closed', 'content-forge')}</option>
                      <option value="open">{__('Open', 'content-forge')}</option>
                    </select>
                  </Field>
                </div>
                {post.post_status === 'future' && (
                  <div className="cforge-flex cforge-gap-4 cforge-mb-4">
                    <ScheduleField
                      value={post.post_date}
                      error={errors['post_date']}
                      onChange={value => setPost({ ...post, post_date: value })}
                    />
                    <div className="cforge-flex-1" />
                  </div>
                )}
                {post.post_type === 'page' && (
                  <Field
                    label={__('Parent Page', 'content-forge')}
                    htmlFor="cforge-pp-parent-page"
                    error={errors['post_parent']}
                    className="cforge-mb-4"
                  >
                    <select id="cforge-pp-parent-page"
                      className={`cforge-input ${errorClass(errors['post_parent'])}`}
                      value={post['post_parent']}
                      onChange={e => setPost({ ...post, post_parent: e.target.value })}
                    >
                      <option value="0">{__('No Parent', 'content-forge')}</option>
                      {pages.map((page) => (
                        <option key={page.id} value={page.id}>{page.title.rendered}</option>
                      ))}
                    </select>
                  </Field>
                )}
                <Field
                  label={__('Titles (comma separated)', 'content-forge')}
                  htmlFor="cforge-pp-titles-comma-separated"
                  error={errors['post_title']}
                  hint={__('eg. Page1, Page2, page3, PAGE4, PAge5', 'content-forge')}
                  className="cforge-mb-4"
                >
                  <input id="cforge-pp-titles-comma-separated"
                    type="text"
                    className={`cforge-input ${errorClass(errors['post_title'])}`}
                    value={post['post_title']}
                    onChange={e => setPost({ ...post, post_title: e.target.value })}
                  />
                </Field>
                <Field
                  label={__('Page/Post content', 'content-forge')}
                  htmlFor="cforge-pp-page-post-content"
                  error={errors['post_content']}
                  hint={__('eg. This is the content of the page/post', 'content-forge')}
                  className="cforge-mb-4"
                >
                  <textarea id="cforge-pp-page-post-content"
                    className={`cforge-input ${errorClass(errors['post_content'])}`}
                    value={post['post_content']}
                    onChange={e => setPost({ ...post, post_content: e.target.value })}
                  />
                </Field>
                <div className="cforge-mb-4 cforge-border cforge-border-gray-200 cforge-rounded-lg cforge-p-4 cforge-bg-gray-50">
                  <ToggleControl
                    label={__('Randomize Post Dates', 'content-forge')}
                    checked={randomizeDates}
                    onChange={setRandomizeDates}
                    disabled={post.post_status === 'future'}
                    help={post.post_status === 'future'
                      ? __('Unavailable for scheduled posts', 'content-forge')
                      : __('Assign a random publish date within a date range to each generated post', 'content-forge')}
                  />
                  {randomizeDates && (
                    <div className="cforge-mt-3">
                      <DateRangePicker
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onDateFromChange={setDateFrom}
                        onDateToChange={setDateTo}
                      />
                    </div>
                  )}
                </div>
                <div className="cforge-mb-4">
                  <AuthorSelect
                    authors={authors}
                    mode={authorMode}
                    onModeChange={setAuthorMode}
                    selected={authorIds}
                    onSelectedChange={setAuthorIds}
                  />
                </div>
                <div className="cforge-mb-4">
                  <TaxonomySection {...taxonomyAssignment} />
                </div>
              </>
            ) : tab === 'ai' ? (
              <AIGenerateTab
                post={post}
                setPost={setPost}
                authors={authors}
                authorMode={authorMode}
                setAuthorMode={setAuthorMode}
                authorIds={authorIds}
                setAuthorIds={setAuthorIds}
                taxonomyAssignment={taxonomyAssignment}
                onSuccess={() => {
                  // Content generated, user can now submit the form
                }}
              />
            ) : null}
          </div>
          <div className="cforge-flex cforge-justify-end cforge-mt-6 cforge-gap-2">
            <Button variant="secondary" onClick={onCancel} disabled={submitting}>
              {__('Cancel', 'content-forge')}
            </Button>
            {tab !== 'ai' && (
              <Button type="submit" disabled={submitting}>
                {submitting ? __('Generating...', 'content-forge') : __('Generate', 'content-forge')}
              </Button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
}

function PagesPostsApp() {
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

    const path = `posts/list?page=${page}&per_page=${perPage}&post_types=post,page`;
    apiFetch({ path, method: 'GET' })
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

    const refreshPath = `posts/list?page=${pageToLoad}&per_page=${perPage}&post_types=post,page`;
    apiFetch({ path: refreshPath, method: 'GET' })
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
    if (!confirm(__('Are you sure you want to delete this item?', 'content-forge'))) {
      return;
    }

    setDeleting(itemId);
    setNotice(null);
    try {
      await apiFetch({
        path: `posts/${itemId}`,
        method: 'DELETE',
      });
      setNotice({
        message: __('Item deleted successfully!', 'content-forge'),
        status: 'success',
      });
      // Refresh the list - if current page becomes empty, go to page 1
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
        message: err.message || __('Failed to delete item', 'content-forge'),
        status: 'error',
      });
      setDeleting(null);
      setTimeout(() => setNotice(null), 5000);
    }
  };

  const handleDeleteAll = async () => {
    if (!confirm(__('Are you sure you want to delete all generated items?', 'content-forge'))) {
      return;
    }

    setDeleting('all');
    setNotice(null);
    try {
      await apiFetch({
        path: 'posts/bulk',
        method: 'DELETE',
      });
      setNotice({
        message: __('All items deleted successfully!', 'content-forge'),
        status: 'success',
      });
      setItems([]);
      setTotal(0);
      setDeleting(null);
      setTimeout(() => setNotice(null), 3000);
    } catch (err) {
      setNotice({
        message: err.message || __('Failed to delete items', 'content-forge'),
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
          heading={__('Pages/Posts', 'content-forge')}
        />
        <div className="cforge-bg-white cforge-min-h-screen">
      

      {view === 'list' ? (
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
              { key: 'title', label: __('Title', 'content-forge') },
              { key: 'author', label: __('Author', 'content-forge') },
              { key: 'type', label: __('Type', 'content-forge') },
              { key: 'date', label: __('Date', 'content-forge') },
            ]}
            renderRow={(item) => (
              <>
                <td
                  className="cforge-whitespace-nowrap cforge-py-4 cforge-pl-4 cforge-pr-3 cforge-text-sm cforge-font-medium cforge-text-gray-900 sm:cforge-pl-6 cforge-cursor-pointer cforge-underline cforge-text-blue-700 hover:cforge-text-blue-900"
                  onClick={() => {
                    const editUrl = `${window.location.origin}/wp-admin/post.php?post=${item.ID}&action=edit`;
                    window.open(editUrl, '_blank');
                  }}
                  title={__('Edit this post/page', 'content-forge')}
                >
                  {item.title}
                </td>
                <td>{item.author}</td>
                <td className="cforge-whitespace-nowrap">{item.type}</td>
                <td className="cforge-whitespace-nowrap">{item.date}</td>
              </>
            )}
            onAddNew={() => setView('add')}
            onPageChange={handlePageChange}
            onDelete={handleDelete}
            onDeleteAll={handleDeleteAll}
            deleting={deleting}
            title={__('Pages/Posts', 'content-forge')}
            description={__('A list of all the generated pages and posts including their title, author, type and date.', 'content-forge')}
          />
        </>
      ) : (
        <AddNewView onCancel={() => setView('list')} onSuccess={handleSuccess} />
      )}
    </div>
    </>
    
  );
}

const container = document.getElementById('cforge-pages-posts-app');
if (container) {
  const { createRoot } = require('react-dom/client');
  const root = createRoot(container);
  root.render(<PagesPostsApp />);
}