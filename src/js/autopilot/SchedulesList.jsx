// DESCRIPTION: List view for autopilot schedules — cards with status, counters, and inline actions.
// Empty state CTA leads to the create form.

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl, Spinner } from '@wordpress/components';
import {
    listSchedules,
    pauseSchedule,
    resumeSchedule,
    cloneSchedule,
    runScheduleNow,
    deleteSchedule,
    bulkAction,
} from './api';

const STATUS_LABEL = {
    draft: __('Draft', 'content-forge'),
    active: __('Active', 'content-forge'),
    paused: __('Paused', 'content-forge'),
    auto_paused: __('Auto-paused', 'content-forge'),
    completed: __('Completed', 'content-forge'),
    archived: __('Archived', 'content-forge'),
};

const STATUS_COLOR = {
    draft: 'cforge-bg-gray-100 cforge-text-gray-700',
    active: 'cforge-bg-green-100 cforge-text-green-700',
    paused: 'cforge-bg-yellow-100 cforge-text-yellow-700',
    auto_paused: 'cforge-bg-red-100 cforge-text-red-700',
    completed: 'cforge-bg-blue-100 cforge-text-blue-700',
    archived: 'cforge-bg-gray-200 cforge-text-gray-600',
};

export default function SchedulesList({ onCreate, onEdit, onHistory }) {
    const [schedules, setSchedules] = useState([]);
    const [loading, setLoading] = useState(true);
    const [busyId, setBusyId] = useState(null);
    const [bulkBusy, setBulkBusy] = useState(false);
    const [error, setError] = useState(null);
    const [selected, setSelected] = useState(() => new Set());

    const toggleSelected = (id) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const selectAll = () => setSelected(new Set(schedules.map((s) => s.id)));
    const clearSelection = () => setSelected(new Set());

    const runBulk = async (action) => {
        if (selected.size === 0) return;
        if (action === 'archive' && !window.confirm(__('Archive the selected autopilots?', 'content-forge'))) {
            return;
        }
        try {
            setBulkBusy(true);
            await bulkAction(action, [...selected]);
            clearSelection();
            await load();
        } catch (e) {
            setError(e?.message || __('Bulk action failed', 'content-forge'));
        } finally {
            setBulkBusy(false);
        }
    };

    const load = useCallback(async () => {
        try {
            setLoading(true);
            const response = await listSchedules();
            setSchedules(response.schedules || []);
            setError(null);
        } catch (e) {
            setError(e?.message || __('Failed to load autopilots', 'content-forge'));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const withBusy = (id, fn) => async () => {
        try {
            setBusyId(id);
            await fn();
            await load();
        } catch (e) {
            setError(e?.message || __('Action failed', 'content-forge'));
        } finally {
            setBusyId(null);
        }
    };

    if (loading) {
        return <div className="cforge-flex cforge-justify-center cforge-py-12"><Spinner /></div>;
    }

    if (error) {
        return <div className="cforge-bg-red-50 cforge-text-red-800 cforge-p-4 cforge-rounded">{error}</div>;
    }

    return (
        <div>
            <div className="cforge-flex cforge-justify-between cforge-items-center cforge-mb-6">
                <h2 className="cforge-text-xl cforge-font-semibold cforge-m-0">
                    {__('Your Autopilots', 'content-forge')}
                </h2>
                <Button variant="primary" onClick={onCreate}>
                    {__('+ Create Autopilot', 'content-forge')}
                </Button>
            </div>

            {schedules.length === 0 ? (
                <div className="cforge-text-center cforge-py-16 cforge-border cforge-border-dashed cforge-rounded">
                    <p className="cforge-text-lg cforge-mb-4">
                        {__('Put your blog on autopilot.', 'content-forge')}
                    </p>
                    <p className="cforge-text-sm cforge-text-gray-600 cforge-mb-6">
                        {__('Schedule AI to write and publish posts for you. Daily, weekly, or monthly.', 'content-forge')}
                    </p>
                    <Button variant="primary" onClick={onCreate}>
                        {__('Create your first Autopilot', 'content-forge')}
                    </Button>
                </div>
            ) : (
                <>
                    {selected.size > 0 && (
                        <div className="cforge-flex cforge-items-center cforge-gap-2 cforge-bg-blue-50 cforge-border cforge-border-blue-200 cforge-p-3 cforge-rounded cforge-mb-3">
                            <span className="cforge-text-sm cforge-flex-1">
                                {sprintf_n(selected.size)}
                            </span>
                            <Button variant="secondary" isSmall onClick={() => runBulk('pause')} disabled={bulkBusy}>
                                {__('Pause', 'content-forge')}
                            </Button>
                            <Button variant="secondary" isSmall onClick={() => runBulk('resume')} disabled={bulkBusy}>
                                {__('Resume', 'content-forge')}
                            </Button>
                            <Button variant="secondary" isSmall isDestructive onClick={() => runBulk('archive')} disabled={bulkBusy}>
                                {__('Archive', 'content-forge')}
                            </Button>
                            <Button variant="tertiary" isSmall onClick={clearSelection} disabled={bulkBusy}>
                                {__('Clear', 'content-forge')}
                            </Button>
                            <Button variant="tertiary" isSmall onClick={selectAll} disabled={bulkBusy}>
                                {__('Select all', 'content-forge')}
                            </Button>
                        </div>
                    )}
                    <div className="cforge-flex cforge-flex-col cforge-gap-3">
                        {schedules.map((s) => (
                            <ScheduleCard
                                key={s.id}
                                schedule={s}
                                busy={busyId === s.id}
                                selected={selected.has(s.id)}
                                onToggleSelect={() => toggleSelected(s.id)}
                                onEdit={() => onEdit(s.id)}
                                onHistory={() => onHistory(s.id)}
                                onPause={withBusy(s.id, () => pauseSchedule(s.id))}
                                onResume={withBusy(s.id, () => resumeSchedule(s.id))}
                                onClone={withBusy(s.id, () => cloneSchedule(s.id))}
                                onRunNow={withBusy(s.id, () => runScheduleNow(s.id))}
                                onDelete={withBusy(s.id, async () => {
                                    /* eslint-disable no-alert */
                                    if (window.confirm(__('Archive this autopilot?', 'content-forge'))) {
                                        await deleteSchedule(s.id);
                                    }
                                })}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

function sprintf_n(count) {
    return count === 1
        ? __('1 selected', 'content-forge')
        : `${count} ${__('selected', 'content-forge')}`;
}

function ScheduleCard({
    schedule,
    busy,
    selected,
    onToggleSelect,
    onEdit,
    onHistory,
    onPause,
    onResume,
    onClone,
    onRunNow,
    onDelete,
}) {
    const isActive = schedule.status === 'active';
    const isPaused = schedule.status === 'paused' || schedule.status === 'auto_paused';
    const isDraft = schedule.status === 'draft';
    const freq = schedule.config?.frequency || {};
    const targeting = schedule.config?.targeting || {};

    return (
        <div className={`cforge-border cforge-rounded cforge-p-4 cforge-bg-white ${selected ? 'cforge-ring-2 cforge-ring-blue-300' : ''}`}>
            <div className="cforge-flex cforge-justify-between cforge-items-start cforge-mb-3">
                <div className="cforge-flex cforge-gap-3 cforge-items-start">
                    <div className="cforge-mt-1">
                        <CheckboxControl checked={!!selected} onChange={onToggleSelect} />
                    </div>
                    <div>
                        <div className="cforge-flex cforge-items-center cforge-gap-2 cforge-mb-1">
                            <h3 className="cforge-text-lg cforge-font-semibold cforge-m-0">
                                {schedule.name}
                            </h3>
                            <span className={`cforge-text-xs cforge-px-2 cforge-py-1 cforge-rounded-full cforge-font-medium ${STATUS_COLOR[schedule.status] || ''}`}>
                                {STATUS_LABEL[schedule.status] || schedule.status}
                            </span>
                        </div>
                        <p className="cforge-text-sm cforge-text-gray-600 cforge-m-0">
                            {formatCadence(freq)}
                            {targeting.category_id ? ` · ${__('Category', 'content-forge')} #${targeting.category_id}` : ''}
                        </p>
                    </div>
                </div>
            </div>

            <div className="cforge-grid cforge-grid-cols-4 cforge-gap-4 cforge-text-sm cforge-mb-4">
                <Stat label={__('Next run', 'content-forge')} value={schedule.next_run_at || '—'} />
                <Stat label={__('Last run', 'content-forge')} value={schedule.last_run_at || '—'} />
                <Stat label={__('Posts created', 'content-forge')} value={String(schedule.posts_created)} />
                <Stat label={__('Failures', 'content-forge')} value={String(schedule.failure_streak)} />
            </div>

            <div className="cforge-flex cforge-flex-wrap cforge-gap-2">
                {isDraft && <Button variant="primary" onClick={onResume} disabled={busy}>{__('Activate', 'content-forge')}</Button>}
                {isActive && <Button variant="secondary" onClick={onPause} disabled={busy}>{__('Pause', 'content-forge')}</Button>}
                {isPaused && <Button variant="secondary" onClick={onResume} disabled={busy}>{__('Resume', 'content-forge')}</Button>}
                {schedule.status !== 'archived' && schedule.status !== 'completed' && (
                    <Button variant="secondary" onClick={onRunNow} disabled={busy}>{__('Run now', 'content-forge')}</Button>
                )}
                <Button variant="secondary" onClick={onEdit} disabled={busy}>{__('Edit', 'content-forge')}</Button>
                <Button variant="secondary" onClick={onClone} disabled={busy}>{__('Clone', 'content-forge')}</Button>
                <Button variant="tertiary" onClick={onHistory} disabled={busy}>{__('History', 'content-forge')}</Button>
                <Button variant="tertiary" isDestructive onClick={onDelete} disabled={busy}>
                    {__('Archive', 'content-forge')}
                </Button>
            </div>
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div>
            <div className="cforge-text-xs cforge-text-gray-500">{label}</div>
            <div className="cforge-font-medium">{value}</div>
        </div>
    );
}

function formatCadence(freq) {
    if (!freq || !freq.mode) return '—';
    switch (freq.mode) {
        case 'daily':
            return __('Daily at', 'content-forge') + ' ' + (freq.time || '09:00');
        case 'weekly': {
            const days = (freq.weekdays || []).map(dayName).join(', ');
            return `${__('Weekly', 'content-forge')} · ${days} · ${freq.time || '09:00'}`;
        }
        case 'monthly':
            return `${__('Monthly on day', 'content-forge')} ${freq.day_of_month || 1} · ${freq.time || '09:00'}`;
        case 'interval':
            return `${__('Every', 'content-forge')} ${freq.interval_hours || 24}h`;
        default:
            return freq.mode;
    }
}

function dayName(iso) {
    const labels = [
        __('Mon', 'content-forge'),
        __('Tue', 'content-forge'),
        __('Wed', 'content-forge'),
        __('Thu', 'content-forge'),
        __('Fri', 'content-forge'),
        __('Sat', 'content-forge'),
        __('Sun', 'content-forge'),
    ];
    return labels[(iso - 1) % 7] || iso;
}
