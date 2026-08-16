// DESCRIPTION: Create / edit form for an Autopilot schedule.
// All sections in one file for now; refactor when any section grows past ~80 LOC.

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button,
    TextControl,
    TextareaControl,
    SelectControl,
    CheckboxControl,
    Spinner,
    Notice,
} from '@wordpress/components';
import {
    getSchedule,
    createSchedule,
    updateSchedule,
    resumeSchedule,
    previewSchedule,
} from './api';

const DEFAULT_CONFIG = {
    schema_version: 1,
    topic: {
        mode: 'list_cycle',
        list: [],
        queue: [],
        queue_cursor: 0,
        list_cursor: 0,
        theme: '',
    },
    avoid_recent_duplicates: true,
    targeting: {
        post_type: 'post',
        category_id: 0,
        tag_ids: [],
        ai_suggests_tags: false,
        author_id: 0,
        featured_image: { source: 'none', url: '' },
    },
    ai: {
        provider_override: null,
        model_override: null,
        custom_prompt: '',
        tone: 'professional',
        length: 'medium',
    },
    frequency: {
        mode: 'daily',
        time: '09:00',
        weekdays: [1],
        day_of_month: 1,
        interval_hours: 24,
        start_at: '',
        stagger_hours: 0,
    },
    publishing: {
        mode: 'draft',
        publish_delay_hours: 1,
        ack_publish_immediately: false,
    },
    safety: {
        daily_post_cap: 5,
        auto_pause_after_failures: 3,
    },
    notifications: {
        email_mode: 'none',
        email_to: '',
    },
    posts_per_run: 1,
};

export default function ScheduleForm({ scheduleId, onCancel, onSaved }) {
    const isEdit = Boolean(scheduleId);
    const [name, setName] = useState('');
    const [config, setConfig] = useState(DEFAULT_CONFIG);
    const [activateOnSave, setActivateOnSave] = useState(false);
    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [preview, setPreview] = useState(null);

    useEffect(() => {
        if (!isEdit) return;
        (async () => {
            try {
                setLoading(true);
                const data = await getSchedule(scheduleId);
                setName(data.name || '');
                setConfig({ ...DEFAULT_CONFIG, ...(data.config || {}) });
                setError(null);
            } catch (e) {
                setError(e?.message || __('Failed to load autopilot', 'content-forge'));
            } finally {
                setLoading(false);
            }
        })();
    }, [scheduleId, isEdit]);

    const patch = useCallback((path, value) => {
        setConfig((prev) => {
            const next = structuredClone(prev);
            const segments = path.split('.');
            let node = next;
            for (let i = 0; i < segments.length - 1; i++) {
                node = node[segments[i]];
            }
            node[segments[segments.length - 1]] = value;
            return next;
        });
    }, []);

    const runPreview = async () => {
        try {
            setPreviewing(true);
            setError(null);
            setPreview(null);
            const result = await previewSchedule({ config });
            setPreview(result);
        } catch (e) {
            setError(e?.message || __('Preview failed', 'content-forge'));
        } finally {
            setPreviewing(false);
        }
    };

    const submit = async () => {
        try {
            setSaving(true);
            setError(null);

            // Pre-flight: auto-publish ack required.
            if (config.publishing.mode === 'publish' && !config.publishing.ack_publish_immediately) {
                setError(__('Acknowledge auto-publish before saving.', 'content-forge'));
                setSaving(false);
                return;
            }

            const payload = { name, config };
            const saved = isEdit
                ? await updateSchedule(scheduleId, payload)
                : await createSchedule(payload);

            // If user asked to activate on save, resume the schedule.
            if (activateOnSave && saved?.id) {
                await resumeSchedule(saved.id);
            }
            onSaved();
        } catch (e) {
            setError(e?.message || __('Save failed', 'content-forge'));
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return <div className="cforge-flex cforge-justify-center cforge-py-12"><Spinner /></div>;
    }

    const categories = window.cforge?.categories || [];
    const authors = window.cforge?.authors || [];
    const postTypes = window.cforge?.post_types || [{ value: 'post', label: 'Post' }];
    const providerSupportsImages = (window.cforge?.ai_image_providers || []).includes(
        window.cforge?.ai_provider
    );

    return (
        <div>
            <div className="cforge-flex cforge-justify-between cforge-items-center cforge-mb-6">
                <h2 className="cforge-text-xl cforge-font-semibold cforge-m-0">
                    {isEdit ? __('Edit Autopilot', 'content-forge') : __('Create Autopilot', 'content-forge')}
                </h2>
                <Button variant="tertiary" onClick={onCancel} disabled={saving}>
                    {__('← Back to list', 'content-forge')}
                </Button>
            </div>

            {error && (
                <div className="cforge-mb-4">
                    <Notice status="error" isDismissible={false}>{error}</Notice>
                </div>
            )}

            <div className="cforge-flex cforge-flex-col cforge-gap-6 cforge-max-w-3xl">
                <Section title={__('Basics', 'content-forge')}>
                    <TextControl
                        label={__('Name', 'content-forge')}
                        value={name}
                        onChange={setName}
                        help={__('A label for this autopilot, e.g. "Monday SEO tips"', 'content-forge')}
                    />
                </Section>

                <Section title={__('Topics', 'content-forge')}>
                    <SelectControl
                        label={__('Topic source', 'content-forge')}
                        value={config.topic.mode}
                        options={[
                            { value: 'list_cycle', label: __('List (cycle in order)', 'content-forge') },
                            { value: 'list_random', label: __('List (random pick)', 'content-forge') },
                            { value: 'queue', label: __('Topic queue (consumed)', 'content-forge') },
                            { value: 'ai_theme', label: __('AI-suggested from theme', 'content-forge') },
                        ]}
                        onChange={(v) => patch('topic.mode', v)}
                    />

                    {(config.topic.mode === 'list_cycle' || config.topic.mode === 'list_random') && (
                        <LinesTextarea
                            label={__('Topics (one per line)', 'content-forge')}
                            value={config.topic.list || []}
                            onChange={(lines) => patch('topic.list', lines)}
                            rows={6}
                        />
                    )}
                    {config.topic.mode === 'queue' && (
                        <LinesTextarea
                            label={__('Queue (consumed one per run)', 'content-forge')}
                            value={config.topic.queue || []}
                            onChange={(lines) => patch('topic.queue', lines)}
                            rows={6}
                            help={__('Autopilot pauses when this queue empties.', 'content-forge')}
                        />
                    )}
                    {config.topic.mode === 'ai_theme' && (
                        <TextControl
                            label={__('Theme', 'content-forge')}
                            value={config.topic.theme}
                            onChange={(v) => patch('topic.theme', v)}
                            help={__('e.g. "beginner WordPress tutorials"', 'content-forge')}
                        />
                    )}

                    <CheckboxControl
                        label={__('Avoid duplicating recent posts in this category', 'content-forge')}
                        checked={config.avoid_recent_duplicates}
                        onChange={(v) => patch('avoid_recent_duplicates', v)}
                    />
                </Section>

                <Section title={__('Targeting', 'content-forge')}>
                    <SelectControl
                        label={__('Post type', 'content-forge')}
                        value={config.targeting.post_type}
                        options={postTypes}
                        onChange={(v) => patch('targeting.post_type', v)}
                    />
                    <SelectControl
                        label={__('Category', 'content-forge')}
                        value={String(config.targeting.category_id || 0)}
                        options={[
                            { value: '0', label: __('— Select —', 'content-forge') },
                            ...categories.map((c) => ({ value: String(c.value), label: c.label })),
                        ]}
                        onChange={(v) => patch('targeting.category_id', parseInt(v, 10) || 0)}
                    />
                    <SelectControl
                        label={__('Author', 'content-forge')}
                        value={String(config.targeting.author_id || 0)}
                        options={[
                            { value: '0', label: __('— Current user —', 'content-forge') },
                            ...authors.map((a) => ({ value: String(a.value), label: a.label })),
                        ]}
                        onChange={(v) => patch('targeting.author_id', parseInt(v, 10) || 0)}
                    />
                    <SelectControl
                        label={__('Featured image', 'content-forge')}
                        value={config.targeting.featured_image?.source || 'none'}
                        options={[
                            { value: 'none', label: __('None', 'content-forge') },
                            { value: 'placeholder', label: __('Placeholder image', 'content-forge') },
                            { value: 'ai', label: __('AI-generated from the post title', 'content-forge') },
                        ]}
                        onChange={(v) =>
                            patch('targeting.featured_image', {
                                ...(config.targeting.featured_image || {}),
                                source: v,
                            })
                        }
                        help={__('AI images use your own provider credits — roughly one image per generated post.', 'content-forge')}
                    />
                    {config.targeting.featured_image?.source === 'ai' && !providerSupportsImages && (
                        <Notice status="warning" isDismissible={false}>
                            {__('The active AI provider has no image model. Runs will fall back to a placeholder image.', 'content-forge')}
                        </Notice>
                    )}
                </Section>

                <Section title={__('AI', 'content-forge')}>
                    <TextareaControl
                        label={__('Custom prompt (optional)', 'content-forge')}
                        value={config.ai.custom_prompt}
                        onChange={(v) => patch('ai.custom_prompt', v)}
                        rows={3}
                        help={__('Appended to the system prompt. Use to set tone, length, style.', 'content-forge')}
                    />
                    <SelectControl
                        label={__('Tone', 'content-forge')}
                        value={config.ai.tone}
                        options={[
                            { value: 'professional', label: __('Professional', 'content-forge') },
                            { value: 'casual', label: __('Casual', 'content-forge') },
                            { value: 'technical', label: __('Technical', 'content-forge') },
                            { value: 'conversational', label: __('Conversational', 'content-forge') },
                        ]}
                        onChange={(v) => patch('ai.tone', v)}
                    />
                    <SelectControl
                        label={__('Approximate length', 'content-forge')}
                        value={config.ai.length}
                        options={[
                            { value: 'short', label: __('Short (~300 words)', 'content-forge') },
                            { value: 'medium', label: __('Medium (~800 words)', 'content-forge') },
                            { value: 'long', label: __('Long (~1500 words)', 'content-forge') },
                        ]}
                        onChange={(v) => patch('ai.length', v)}
                    />
                </Section>

                <Section title={__('Schedule', 'content-forge')}>
                    <SelectControl
                        label={__('Frequency', 'content-forge')}
                        value={config.frequency.mode}
                        options={[
                            { value: 'daily', label: __('Daily', 'content-forge') },
                            { value: 'weekly', label: __('Weekly', 'content-forge') },
                            { value: 'monthly', label: __('Monthly', 'content-forge') },
                            { value: 'interval', label: __('Custom interval', 'content-forge') },
                        ]}
                        onChange={(v) => patch('frequency.mode', v)}
                    />

                    {(config.frequency.mode === 'daily' || config.frequency.mode === 'weekly' || config.frequency.mode === 'monthly') && (
                        <TextControl
                            label={__('Time of day (HH:MM, site timezone)', 'content-forge')}
                            value={config.frequency.time}
                            onChange={(v) => patch('frequency.time', v)}
                            help={window.cforge?.site_timezone ? `${__('Timezone', 'content-forge')}: ${window.cforge.site_timezone}` : null}
                        />
                    )}

                    {config.frequency.mode === 'weekly' && (
                        <WeekdayPicker
                            value={config.frequency.weekdays}
                            onChange={(v) => patch('frequency.weekdays', v)}
                        />
                    )}

                    {config.frequency.mode === 'monthly' && (
                        <TextControl
                            type="number"
                            label={__('Day of month (1–31)', 'content-forge')}
                            value={config.frequency.day_of_month}
                            onChange={(v) => patch('frequency.day_of_month', Math.max(1, Math.min(31, parseInt(v, 10) || 1)))}
                            help={__('If the month is shorter, fires on the last day.', 'content-forge')}
                        />
                    )}

                    {config.frequency.mode === 'interval' && (
                        <TextControl
                            type="number"
                            label={__('Interval (hours)', 'content-forge')}
                            value={config.frequency.interval_hours}
                            onChange={(v) => patch('frequency.interval_hours', Math.max(1, parseInt(v, 10) || 24))}
                        />
                    )}

                    <TextControl
                        type="number"
                        label={__('Posts per run (1–5)', 'content-forge')}
                        value={config.posts_per_run}
                        onChange={(v) => setConfig((prev) => ({ ...prev, posts_per_run: Math.max(1, Math.min(5, parseInt(v, 10) || 1)) }))}
                    />
                </Section>

                <Section title={__('Publishing', 'content-forge')}>
                    <SelectControl
                        label={__('What to do with generated posts', 'content-forge')}
                        value={config.publishing.mode}
                        options={[
                            { value: 'draft', label: __('Save as draft (recommended)', 'content-forge') },
                            { value: 'pending', label: __('Mark as pending review', 'content-forge') },
                            { value: 'scheduled', label: __('Schedule to publish later', 'content-forge') },
                            { value: 'publish', label: __('Publish immediately', 'content-forge') },
                        ]}
                        onChange={(v) => patch('publishing.mode', v)}
                    />
                    {config.publishing.mode === 'scheduled' && (
                        <TextControl
                            type="number"
                            label={__('Publish delay (hours)', 'content-forge')}
                            value={config.publishing.publish_delay_hours}
                            onChange={(v) => patch('publishing.publish_delay_hours', Math.max(0, parseInt(v, 10) || 0))}
                            help={__('Posts are generated now and scheduled to publish after this delay.', 'content-forge')}
                        />
                    )}
                    {config.publishing.mode === 'publish' && (
                        <div className="cforge-bg-yellow-50 cforge-border cforge-border-yellow-200 cforge-p-3 cforge-rounded">
                            <strong>{__('Heads up:', 'content-forge')}</strong>{' '}
                            {__('Posts will be published live with no review. Make sure your prompt and topics are solid.', 'content-forge')}
                            <div className="cforge-mt-2">
                                <CheckboxControl
                                    label={__('I understand and want to enable auto-publish.', 'content-forge')}
                                    checked={config.publishing.ack_publish_immediately}
                                    onChange={(v) => patch('publishing.ack_publish_immediately', v)}
                                />
                            </div>
                        </div>
                    )}
                </Section>

                <Section title={__('Safety', 'content-forge')}>
                    <TextControl
                        type="number"
                        label={__('Daily post cap', 'content-forge')}
                        value={config.safety.daily_post_cap}
                        onChange={(v) => patch('safety.daily_post_cap', Math.max(1, parseInt(v, 10) || 5))}
                        help={__('Hard ceiling per day for this autopilot.', 'content-forge')}
                    />
                    <TextControl
                        type="number"
                        label={__('Auto-pause after N consecutive failures', 'content-forge')}
                        value={config.safety.auto_pause_after_failures}
                        onChange={(v) => patch('safety.auto_pause_after_failures', Math.max(1, Math.min(10, parseInt(v, 10) || 3)))}
                    />
                </Section>

                <Section title={__('Notifications', 'content-forge')}>
                    <SelectControl
                        label={__('Email me about runs', 'content-forge')}
                        value={config.notifications.email_mode}
                        options={[
                            { value: 'none', label: __('Don’t email', 'content-forge') },
                            { value: 'failure', label: __('Only on failure', 'content-forge') },
                            { value: 'every', label: __('Every run', 'content-forge') },
                        ]}
                        onChange={(v) => patch('notifications.email_mode', v)}
                    />
                    {config.notifications.email_mode !== 'none' && (
                        <TextControl
                            type="email"
                            label={__('Recipient', 'content-forge')}
                            value={config.notifications.email_to}
                            onChange={(v) => patch('notifications.email_to', v)}
                            help={__('Leave blank to use your account email.', 'content-forge')}
                        />
                    )}
                </Section>

                {preview && (
                    <div className="cforge-border cforge-rounded cforge-p-4 cforge-bg-blue-50 cforge-border-blue-200">
                        <div className="cforge-flex cforge-justify-between cforge-items-start cforge-gap-2 cforge-mb-2">
                            <h3 className="cforge-text-base cforge-font-semibold cforge-m-0">
                                {__('Sample post', 'content-forge')}
                            </h3>
                            <button
                                type="button"
                                className="cforge-text-xs cforge-text-blue-700 cforge-underline"
                                onClick={() => setPreview(null)}
                            >
                                {__('Dismiss', 'content-forge')}
                            </button>
                        </div>
                        {preview.topic && (
                            <div className="cforge-text-xs cforge-text-gray-600 cforge-mb-2">
                                {__('Topic used:', 'content-forge')} <em>{preview.topic}</em>
                            </div>
                        )}
                        <div className="cforge-text-lg cforge-font-medium cforge-mb-2">{preview.title}</div>
                        <p className="cforge-text-sm cforge-text-gray-700 cforge-m-0">{preview.excerpt}</p>
                        <p className="cforge-text-xs cforge-text-gray-500 cforge-mt-2 cforge-mb-0">
                            {__('Preview only — nothing has been saved.', 'content-forge')}
                        </p>
                    </div>
                )}

                {!isEdit && (
                    <CheckboxControl
                        label={__('Activate immediately after creating', 'content-forge')}
                        checked={activateOnSave}
                        onChange={setActivateOnSave}
                    />
                )}

                <div className="cforge-flex cforge-gap-2 cforge-justify-end cforge-pt-4 cforge-border-t">
                    <Button variant="tertiary" onClick={onCancel} disabled={saving || previewing}>
                        {__('Cancel', 'content-forge')}
                    </Button>
                    <Button variant="secondary" onClick={runPreview} disabled={saving || previewing}>
                        {previewing ? __('Generating…', 'content-forge') : __('Preview sample', 'content-forge')}
                    </Button>
                    <Button variant="primary" onClick={submit} disabled={saving || previewing}>
                        {saving ? __('Saving…', 'content-forge') : (isEdit ? __('Save changes', 'content-forge') : __('Create autopilot', 'content-forge'))}
                    </Button>
                </div>
            </div>
        </div>
    );
}

// Edits an array-of-strings as a textarea (one per line).
// Holds raw text locally while typing so trailing/blank lines aren't stripped mid-edit;
// normalizes (trim + drop empties) only on blur.
function LinesTextarea({ label, value, onChange, rows, help }) {
    const [draft, setDraft] = useState((value || []).join('\n'));

    // Re-sync when the upstream array changes due to something other than this textarea
    // (e.g. loading an existing schedule). Compare canonical forms to avoid clobbering
    // an in-progress edit that just normalizes to the same array.
    useEffect(() => {
        const canonical = draft.split('\n').map((s) => s.trim()).filter(Boolean).join('\n');
        const upstream = (value || []).join('\n');
        if (canonical !== upstream) {
            setDraft(upstream);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    return (
        <TextareaControl
            label={label}
            value={draft}
            onChange={setDraft}
            onBlur={() => {
                const lines = draft.split('\n').map((s) => s.trim()).filter(Boolean);
                onChange(lines);
            }}
            rows={rows}
            help={help}
        />
    );
}

function Section({ title, children }) {
    return (
        <div className="cforge-border cforge-rounded cforge-p-4 cforge-bg-white">
            <h3 className="cforge-text-base cforge-font-semibold cforge-mt-0 cforge-mb-3">{title}</h3>
            <div className="cforge-flex cforge-flex-col cforge-gap-3">{children}</div>
        </div>
    );
}

function WeekdayPicker({ value, onChange }) {
    const days = [
        { iso: 1, label: __('Mon', 'content-forge') },
        { iso: 2, label: __('Tue', 'content-forge') },
        { iso: 3, label: __('Wed', 'content-forge') },
        { iso: 4, label: __('Thu', 'content-forge') },
        { iso: 5, label: __('Fri', 'content-forge') },
        { iso: 6, label: __('Sat', 'content-forge') },
        { iso: 7, label: __('Sun', 'content-forge') },
    ];
    const toggle = (iso) => {
        const set = new Set(value || []);
        if (set.has(iso)) set.delete(iso);
        else set.add(iso);
        const next = [...set].sort((a, b) => a - b);
        onChange(next.length === 0 ? [1] : next);
    };
    return (
        <div className="cforge-flex cforge-gap-2">
            {days.map((d) => {
                const active = (value || []).includes(d.iso);
                return (
                    <button
                        key={d.iso}
                        type="button"
                        onClick={() => toggle(d.iso)}
                        className={`cforge-px-3 cforge-py-1 cforge-rounded cforge-text-sm cforge-border ${active ? 'cforge-bg-blue-600 cforge-text-white cforge-border-blue-600' : 'cforge-bg-white cforge-text-gray-700 cforge-border-gray-300'}`}
                    >
                        {d.label}
                    </button>
                );
            })}
        </div>
    );
}
