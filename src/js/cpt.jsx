import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import '../css/common.css';
import Header from './components/Header';
import apiFetch from '@wordpress/api-fetch';
import ListView from './components/ListView';
import MultiSelect from './components/MultiSelect';

const STOCK_STATUSES = [
	{ value: 'instock', label: 'In stock' },
	{ value: 'outofstock', label: 'Out of stock' },
	{ value: 'onbackorder', label: 'On backorder' },
];

const WEDOCS_LEVEL_NAMES = [
	{ key: 'documentation', label: __('Documentation', 'content-forge') },
	{ key: 'sections', label: __('Sections', 'content-forge') },
	{ key: 'articles', label: __('Articles', 'content-forge') },
	{ key: 'nested_articles', label: __('Nested Articles', 'content-forge') },
	{ key: 'deeper_nesting', label: __('Deeper Nesting', 'content-forge') },
];

function AddNewView({ onCancel, onSuccess }) {
	const postTypes = window.cforge?.post_types || [];
	const woocommerceActive = window.cforge?.woocommerce_active || false;
	const wedocsActive = window.cforge?.wedocs_active || false;

	const [form, setForm] = useState({
		post_type: postTypes.length ? postTypes[0].name : '',
		post_number: 1,
		post_status: 'publish',
		product_options: {
			price_min: 0,
			price_max: 99.99,
			sale_price_min: '',
			sale_price_max: '',
			generate_sku: true,
			sku_prefix: 'CF-',
			stock_status: ['instock'],
		},
		docs_options: {
			generation_mode: 'random',
			level_counts: [1, 0, 0, 0, 0],
		},
	});
	const [errors, setErrors] = useState({});
	const [notice, setNotice] = useState(null);
	const [submitting, setSubmitting] = useState(false);

	useEffect(() => {
		if (postTypes.length && !form.post_type) {
			setForm((f) => ({ ...f, post_type: postTypes[0].name }));
		}
	}, [postTypes, form.post_type]);

	// Calculate total from manual level counts (per-parent multiplication)
	const manualTotal = (() => {
		const counts = form.docs_options.level_counts || [0, 0, 0, 0, 0];
		let total = 0;
		let parentsAtLevel = 1; // Level 0 (Documentation) has 1 "parent" (the root)
		for (let i = 0; i < counts.length; i++) {
			const count = Number(counts[i]) || 0;
			if (i === 0) {
				// Level 0: Just the count itself (Documentation roots)
				total += count;
				parentsAtLevel = count;
			} else if (count > 0 && parentsAtLevel > 0) {
				// Level 1+: count is PER parent, so multiply
				const itemsAtThisLevel = count * parentsAtLevel;
				total += itemsAtThisLevel;
				parentsAtLevel = itemsAtThisLevel;
			}
		}
		return total;
	})();

	const validate = () => {
		const newErrors = {};
		const isDocs = form.post_type === 'docs' && wedocsActive;

		if (!form.post_type) {
			newErrors.post_type = __('Please select a post type', 'content-forge');
		}

		// For non-docs, validate post_number
		if (!isDocs) {
			const num = Number(form.post_number);
			if (!num || num < 1) {
				newErrors.post_number = __('Number must be at least 1', 'content-forge');
			}
		}

		// For docs manual mode, validate level counts
		if (isDocs && form.docs_options.generation_mode === 'manual') {
			const counts = form.docs_options.level_counts || [];
			// Documentation (first level) must be at least 1
			if ((Number(counts[0]) || 0) < 1) {
				newErrors.docs_documentation = __('Documentation must have at least 1 item.', 'content-forge');
			}
			// Total must be at least 1
			const sum = counts.reduce((s, c) => s + (Number(c) || 0), 0);
			if (sum < 1) {
				newErrors.docs_total = __('Total must be at least 1.', 'content-forge');
			}
		}

		if (form.post_type === 'product' && woocommerceActive) {
			const po = form.product_options;
			const priceMin = Number(po.price_min);
			const priceMax = Number(po.price_max);
			if (isNaN(priceMin) || priceMin < 0) {
				newErrors.price_min = __('Invalid minimum price', 'content-forge');
			}
			if (isNaN(priceMax) || priceMax < priceMin) {
				newErrors.price_max = __('Invalid maximum price', 'content-forge');
			}
			const statuses = Array.isArray(po.stock_status) ? po.stock_status : [po.stock_status];
			if (statuses.length === 0) {
				newErrors.stock_status = __('Select at least one stock status', 'content-forge');
			}
		}
		return newErrors;
	};

	const handleSubmit = async (e) => {
		e.preventDefault();
		const validationErrors = validate();
		setErrors(validationErrors);
		if (Object.keys(validationErrors).length > 0) return;

		setSubmitting(true);
		setNotice(null);
		const payload = {
			post_type: form.post_type,
			post_number: Number(form.post_number),
			post_status: form.post_status,
			comment_status: 'closed',
		};

		if (form.post_type === 'product' && woocommerceActive) {
			const po = form.product_options;
			payload.product_options = {
				price_min: Number(po.price_min),
				price_max: Number(po.price_max),
				stock_status: Array.isArray(po.stock_status) ? po.stock_status : [po.stock_status],
				generate_sku: Boolean(po.generate_sku),
				sku_prefix: po.sku_prefix || 'CF-',
			};
			if (po.sale_price_min !== '' && po.sale_price_max !== '') {
				payload.product_options.sale_price_min = Number(po.sale_price_min);
				payload.product_options.sale_price_max = Number(po.sale_price_max);
			}
		}

		if (form.post_type === 'docs' && wedocsActive) {
			const mode = form.docs_options.generation_mode;
			payload.docs_options = { generation_mode: mode };

			if (mode === 'manual') {
				// Manual mode: use the level counts directly
				const counts = form.docs_options.level_counts || [1, 0, 0, 0, 0];
				payload.docs_options.level_counts = counts.map((c) => Math.max(0, parseInt(c, 10) || 0));
				payload.post_number = counts.reduce((s, c) => s + (Number(c) || 0), 0);
			} else {
				// Random mode: server will auto-generate realistic amounts
				payload.post_number = 0; // Indicates auto-generate
			}
		}

		try {
			await apiFetch({
				path: 'posts/bulk',
				method: 'POST',
				data: payload,
			});
			setNotice({
				message: __('Content generated successfully!', 'content-forge'),
				status: 'success',
			});
			setTimeout(() => {
				setNotice(null);
				onSuccess();
			}, 1500);
		} catch (err) {
			setNotice({
				message: err?.message || __('An error occurred. Please try again.', 'content-forge'),
				status: 'error',
			});
		} finally {
			setSubmitting(false);
		}
	};

	const errorClass = (field) => (errors[field] ? 'cforge-border-red-500 cforge-outline-red-500' : '');
	const isProduct = form.post_type === 'product' && woocommerceActive;

	return (
		<div className="cforge-w-full cforge-bg-white cforge-rounded cforge-p-6 cforge-relative cforge-max-w-2xl">
			{notice && (
				<div
					className={`cforge-mb-4 cforge-p-3 cforge-rounded cforge-text-white ${
						notice.status === 'success' ? 'cforge-bg-success' : 'cforge-bg-error'
					}`}
				>
					{notice.message}
				</div>
			)}
			<form onSubmit={handleSubmit}>
				<div className="cforge-mt-6 cforge-space-y-4">
					<div>
						<label className="cforge-block cforge-mb-1 cforge-font-medium">
							{__('Post type', 'content-forge')}
						</label>
						<select
							className={`cforge-input cforge-w-full ${errorClass('post_type')}`}
							value={form.post_type}
							onChange={(e) => setForm({ ...form, post_type: e.target.value })}
						>
							{postTypes.map((pt) => (
								<option key={pt.name} value={pt.name}>
									{pt.label}
								</option>
							))}
						</select>
						{errors.post_type && (
							<p className="cforge-text-red-500 cforge-text-sm cforge-mt-1">{errors.post_type}</p>
						)}
					</div>
					{!(form.post_type === 'docs' && wedocsActive) && (
						<div>
							<label className="cforge-block cforge-mb-1 cforge-font-medium">
								{__('Number to generate', 'content-forge')}
							</label>
							<input
								type="number"
								min="1"
								className={`cforge-input ${errorClass('post_number')}`}
								value={form.post_number}
								onChange={(e) => setForm({ ...form, post_number: e.target.value })}
							/>
							{errors.post_number && (
								<p className="cforge-text-red-500 cforge-text-sm cforge-mt-1">{errors.post_number}</p>
							)}
						</div>
					)}
					<div>
						<label className="cforge-block cforge-mb-1 cforge-font-medium">
							{__('Status', 'content-forge')}
						</label>
						<select
							className="cforge-input cforge-w-full"
							value={form.post_status}
							onChange={(e) => setForm({ ...form, post_status: e.target.value })}
						>
							<option value="publish">{__('Published', 'content-forge')}</option>
							<option value="draft">{__('Draft', 'content-forge')}</option>
							<option value="pending">{__('Pending', 'content-forge')}</option>
						</select>
					</div>

					{form.post_type === 'docs' && wedocsActive && (
						<div className="cforge-border cforge-border-border cforge-rounded cforge-p-4 cforge-bg-tertiary/30 cforge-space-y-4">
							<h3 className="cforge-text-lg cforge-font-semibold cforge-m-0">
								{__('WeDocs hierarchy', 'content-forge')}
							</h3>
							<p className="cforge-text-sm cforge-text-secondary cforge-m-0">
								{__('In weDocs, content is organized as: Documentation (root) → Sections → Articles.', 'content-forge')}
								{' '}
								<a
									href="https://wedocs.co/docs/wedocs/how-to/how-to-create-a-section/"
									target="_blank"
									rel="noopener noreferrer"
									className="cforge-text-primary cforge-underline hover:cforge-no-underline"
								>
									{__('Learn more', 'content-forge')}
								</a>
							</p>
							<div>
								<label className="cforge-block cforge-mb-1 cforge-font-medium">
									{__('Generation mode', 'content-forge')}
								</label>
								<select
									className="cforge-input cforge-w-full"
									value={form.docs_options.generation_mode}
									onChange={(e) =>
										setForm({
											...form,
											docs_options: {
												...form.docs_options,
												generation_mode: e.target.value,
											},
										})
									}
								>
									<option value="random">{__('Generate Randomly', 'content-forge')}</option>
									<option value="manual">{__('Generate Manually', 'content-forge')}</option>
								</select>
							</div>

							{form.docs_options.generation_mode === 'random' && (
								<p className="cforge-text-sm cforge-text-secondary cforge-m-0">
									{__('We will automatically generate a realistic hierarchy with docs, sections, articles, and nested content.', 'content-forge')}
								</p>
							)}

							{form.docs_options.generation_mode === 'manual' && (
								<>
									<p className="cforge-text-sm cforge-text-secondary cforge-m-0">
										{__('Enter the number of items for each level. Counts are per parent (e.g., Sections per Documentation). Documentation must be at least 1.', 'content-forge')}
									</p>
									<div className="cforge-space-y-3">
										{WEDOCS_LEVEL_NAMES.map((level, idx) => (
											<div key={idx} className="cforge-flex cforge-items-center cforge-gap-2">
												<label className="cforge-w-40 cforge-text-sm cforge-font-medium">
													{level.label}:
												</label>
												<input
													type="number"
													min={idx === 0 ? 1 : 0}
													className={`cforge-input cforge-w-24 ${idx === 0 && errors.docs_documentation ? 'cforge-border-red-500 cforge-outline-red-500' : ''}`}
													value={form.docs_options.level_counts?.[idx] || 0}
													onChange={(e) => {
														const val = parseInt(e.target.value, 10);
														if (Number.isNaN(val) || val < (idx === 0 ? 1 : 0)) return;
														const next = [...(form.docs_options.level_counts || [1, 0, 0, 0, 0])];
														next[idx] = val;
														setForm({
															...form,
															docs_options: { ...form.docs_options, level_counts: next },
														});
													}}
												/>
											</div>
										))}
									</div>
									<p className="cforge-text-sm cforge-font-medium cforge-text-primary cforge-m-0">
										{__('Total:', 'content-forge')} {manualTotal}
									</p>
									{(errors.docs_documentation || errors.docs_total) && (
										<p className="cforge-text-sm cforge-text-red-500 cforge-m-0">
											{errors.docs_documentation || errors.docs_total}
										</p>
									)}
								</>
							)}
						</div>
					)}

					{isProduct && (
						<div className="cforge-border cforge-border-border cforge-rounded cforge-p-4 cforge-bg-tertiary/30 cforge-space-y-4">
							<h3 className="cforge-text-lg cforge-font-semibold cforge-m-0">
								{__('WooCommerce product settings', 'content-forge')}
							</h3>
							<div className="cforge-grid cforge-grid-cols-2 cforge-gap-4">
								<div>
									<label className="cforge-block cforge-mb-1 cforge-font-medium">
										{__('Price min', 'content-forge')}
									</label>
									<input
										type="number"
										step="0.01"
										min="0"
										className={`cforge-input cforge-w-full ${errorClass('price_min')}`}
										value={form.product_options.price_min}
										onChange={(e) =>
											setForm({
												...form,
												product_options: {
													...form.product_options,
													price_min: e.target.value,
												},
											})
										}
									/>
									{errors.price_min && (
										<p className="cforge-text-red-500 cforge-text-sm cforge-mt-1">{errors.price_min}</p>
									)}
								</div>
								<div>
									<label className="cforge-block cforge-mb-1 cforge-font-medium">
										{__('Price max', 'content-forge')}
									</label>
									<input
										type="number"
										step="0.01"
										min="0"
										className={`cforge-input cforge-w-full ${errorClass('price_max')}`}
										value={form.product_options.price_max}
										onChange={(e) =>
											setForm({
												...form,
												product_options: {
													...form.product_options,
													price_max: e.target.value,
												},
											})
										}
									/>
									{errors.price_max && (
										<p className="cforge-text-red-500 cforge-text-sm cforge-mt-1">{errors.price_max}</p>
									)}
								</div>
							</div>
							<div className="cforge-grid cforge-grid-cols-2 cforge-gap-4">
								<div>
									<label className="cforge-block cforge-mb-1 cforge-font-medium">
										{__('Sale price min (optional)', 'content-forge')}
									</label>
									<input
										type="number"
										step="0.01"
										min="0"
										placeholder={__('Leave empty to skip', 'content-forge')}
										className="cforge-input cforge-w-full"
										value={form.product_options.sale_price_min}
										onChange={(e) =>
											setForm({
												...form,
												product_options: {
													...form.product_options,
													sale_price_min: e.target.value,
												},
											})
										}
									/>
								</div>
								<div>
									<label className="cforge-block cforge-mb-1 cforge-font-medium">
										{__('Sale price max (optional)', 'content-forge')}
									</label>
									<input
										type="number"
										step="0.01"
										min="0"
										placeholder={__('Leave empty to skip', 'content-forge')}
										className="cforge-input cforge-w-full"
										value={form.product_options.sale_price_max}
										onChange={(e) =>
											setForm({
												...form,
												product_options: {
													...form.product_options,
													sale_price_max: e.target.value,
												},
											})
										}
									/>
								</div>
							</div>
							<div className="cforge-flex cforge-items-center cforge-gap-2">
								<input
									type="checkbox"
									id="cforge-generate-sku"
									checked={form.product_options.generate_sku}
									onChange={(e) =>
										setForm({
											...form,
											product_options: {
												...form.product_options,
												generate_sku: e.target.checked,
											},
										})
									}
								/>
								<label htmlFor="cforge-generate-sku" className="cforge-font-medium">
									{__('Generate SKU', 'content-forge')}
								</label>
							</div>
							{form.product_options.generate_sku && (
								<div>
									<label className="cforge-block cforge-mb-1 cforge-font-medium">
										{__('SKU prefix', 'content-forge')}
									</label>
									<input
										type="text"
										className="cforge-input cforge-w-full cforge-max-w-xs"
										value={form.product_options.sku_prefix}
										onChange={(e) =>
											setForm({
												...form,
												product_options: {
													...form.product_options,
													sku_prefix: e.target.value,
												},
											})
										}
									/>
								</div>
							)}
							<div>
								<MultiSelect
									options={STOCK_STATUSES}
									value={Array.isArray(form.product_options.stock_status) ? form.product_options.stock_status : [form.product_options.stock_status]}
									onChange={(selected) =>
										setForm({
											...form,
											product_options: {
												...form.product_options,
												stock_status: selected.length ? selected : ['instock'],
											},
										})
									}
									label={__('Stock status', 'content-forge')}
									placeholder={__('Select one or more...', 'content-forge')}
								/>
								{errors.stock_status && (
									<p className="cforge-text-red-500 cforge-text-sm cforge-mt-1">{errors.stock_status}</p>
								)}
							</div>
						</div>
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
	);
}

function CptApp() {
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
		if (window.cforge?.rest_nonce) {
			apiFetch.use(apiFetch.createNonceMiddleware(window.cforge.rest_nonce));
		}
		if (window.cforge?.apiUrl) {
			apiFetch.use(apiFetch.createRootURLMiddleware(window.cforge.apiUrl));
		}
	}, []);

	useEffect(() => {
		if (view !== 'list') return;

		let isMounted = true;
		setLoading(true);
		setError(null);

		apiFetch({
			path: `posts/list?page=${page}&per_page=${perPage}&exclude_post_types=post,page`,
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
			path: `posts/list?page=${pageToLoad}&per_page=${perPage}&exclude_post_types=post,page`,
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
		if (!window.confirm(__('Are you sure you want to delete this item?', 'content-forge'))) {
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
			const currentPageItemCount = items.length;
			if (currentPageItemCount === 1 && page > 1) {
				refreshList(page - 1);
			} else {
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
		if (!window.confirm(__('Are you sure you want to delete all generated items?', 'content-forge'))) {
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
		setPage(1);
	};

	return (
		<>
			<Header heading={__('Custom Post Types', 'content-forge')} />
			<div className="cforge-bg-white cforge-min-h-screen">
				{view === 'list' ? (
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
										title={__('Edit this item', 'content-forge')}
									>
										{item.title}
									</td>
									<td className="cforge-whitespace-nowrap cforge-px-3 cforge-py-4 cforge-text-sm cforge-text-gray-500">{item.author}</td>
									<td className="cforge-whitespace-nowrap cforge-px-3 cforge-py-4 cforge-text-sm cforge-text-gray-500">{item.type}</td>
									<td className="cforge-whitespace-nowrap cforge-px-3 cforge-py-4 cforge-text-sm cforge-text-gray-500">{item.date}</td>
								</>
							)}
							actions={(item, onDelete, deletingItem, itemId) => (
								<button
									onClick={() => onDelete(itemId)}
									disabled={deletingItem === itemId}
									className="cforge-text-red-600 hover:cforge-text-red-800 cforge-p-1 cforge-rounded hover:cforge-bg-red-50"
									title={__('Delete', 'content-forge')}
								>
									{deletingItem === itemId ? (
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
							title={__('Custom Post Types', 'content-forge')}
							description={__('A list of all generated custom post type items including title, author, type and date.', 'content-forge')}
						/>
					</>
				) : (
					<AddNewView onCancel={() => setView('list')} onSuccess={handleSuccess} />
				)}
			</div>
		</>
	);
}

const container = document.getElementById('cforge-cpt-app');
if (container) {
	const { createRoot } = require('react-dom/client');
	const root = createRoot(container);
	root.render(<CptApp />);
}
