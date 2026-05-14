// DESCRIPTION: Run history view — lists the most recent runs for a single autopilot.
// Read-only in P2; retry is deferred to P4.

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { listRuns, retryRun } from './api';

const STATUS_LABEL = {
    queued: __('Queued', 'content-forge'),
    running: __('Running', 'content-forge'),
    success: __('Success', 'content-forge'),
    partial: __('Partial', 'content-forge'),
    failed: __('Failed', 'content-forge'),
    skipped: __('Skipped', 'content-forge'),
};

const STATUS_ICON = {
    queued: '⏳',
    running: '▶',
    success: '✓',
    partial: '◐',
    failed: '✗',
    skipped: '⊘',
};

const STATUS_COLOR = {
    queued: 'cforge-text-gray-600',
    running: 'cforge-text-blue-600',
    success: 'cforge-text-green-700',
    partial: 'cforge-text-yellow-700',
    failed: 'cforge-text-red-700',
    skipped: 'cforge-text-gray-500',
};

export default function RunHistory({ scheduleId, onBack }) {
    const [runs, setRuns] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [retryingId, setRetryingId] = useState(null);

    const load = useCallback(async () => {
        try {
            setLoading(true);
            const response = await listRuns(scheduleId, 30);
            setRuns(response.runs || []);
            setError(null);
        } catch (e) {
            setError(e?.message || __('Failed to load run history', 'content-forge'));
        } finally {
            setLoading(false);
        }
    }, [scheduleId]);

    useEffect(() => {
        load();
    }, [load]);

    const retry = async (runId) => {
        try {
            setRetryingId(runId);
            await retryRun(runId);
            await load();
        } catch (e) {
            setError(e?.message || __('Retry failed', 'content-forge'));
        } finally {
            setRetryingId(null);
        }
    };

    return (
        <div>
            <div className="cforge-flex cforge-justify-between cforge-items-center cforge-mb-6">
                <h2 className="cforge-text-xl cforge-font-semibold cforge-m-0">
                    {__('Run History', 'content-forge')}
                </h2>
                <Button variant="tertiary" onClick={onBack}>
                    {__('← Back to list', 'content-forge')}
                </Button>
            </div>

            {loading && <div className="cforge-flex cforge-justify-center cforge-py-12"><Spinner /></div>}

            {error && (
                <div className="cforge-bg-red-50 cforge-text-red-800 cforge-p-4 cforge-rounded">{error}</div>
            )}

            {!loading && !error && runs.length === 0 && (
                <div className="cforge-text-center cforge-py-12 cforge-border cforge-border-dashed cforge-rounded cforge-text-gray-600">
                    {__('No runs yet. They will appear here once the autopilot fires.', 'content-forge')}
                </div>
            )}

            {!loading && runs.length > 0 && (
                <div className="cforge-flex cforge-flex-col cforge-gap-2">
                    {runs.map((run) => (
                        <div key={run.id} className="cforge-border cforge-rounded cforge-p-3 cforge-bg-white">
                            <div className="cforge-flex cforge-justify-between cforge-items-start cforge-gap-4">
                                <div className="cforge-flex-1">
                                    <div className={`cforge-flex cforge-items-center cforge-gap-2 cforge-mb-1 ${STATUS_COLOR[run.status] || ''}`}>
                                        <span className="cforge-text-lg">{STATUS_ICON[run.status] || '·'}</span>
                                        <span className="cforge-font-medium">{STATUS_LABEL[run.status] || run.status}</span>
                                        <span className="cforge-text-xs cforge-text-gray-500">
                                            {run.started_at}
                                            {run.finished_at ? ` · ${duration(run.started_at, run.finished_at)}` : ''}
                                        </span>
                                    </div>
                                    {run.topic_used && (
                                        <div className="cforge-text-sm cforge-text-gray-700 cforge-mb-1">
                                            <strong>{__('Topic:', 'content-forge')}</strong> {run.topic_used}
                                        </div>
                                    )}
                                    {Array.isArray(run.posts_created) && run.posts_created.length > 0 && (
                                        <div className="cforge-text-sm cforge-text-gray-700">
                                            <strong>{__('Posts:', 'content-forge')}</strong>{' '}
                                            {run.posts_created.map((id) => (
                                                <a
                                                    key={id}
                                                    href={`${window.location.origin}/wp-admin/post.php?post=${id}&action=edit`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="cforge-mr-2"
                                                >
                                                    #{id}
                                                </a>
                                            ))}
                                        </div>
                                    )}
                                    {run.error_message && (
                                        <div className="cforge-text-sm cforge-text-red-700 cforge-mt-1">
                                            {run.error_message}
                                        </div>
                                    )}
                                </div>
                                <div className="cforge-flex cforge-flex-col cforge-items-end cforge-gap-2">
                                    {run.ai_provider && (
                                        <div className="cforge-text-xs cforge-text-gray-500 cforge-text-right">
                                            {run.ai_provider}
                                            {run.ai_model ? ` · ${run.ai_model}` : ''}
                                        </div>
                                    )}
                                    {['failed', 'partial', 'skipped'].includes(run.status) && (
                                        <Button
                                            variant="tertiary"
                                            isSmall
                                            disabled={retryingId === run.id}
                                            onClick={() => retry(run.id)}
                                        >
                                            {retryingId === run.id ? __('Retrying…', 'content-forge') : __('Retry', 'content-forge')}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function duration(start, end) {
    try {
        const s = Date.parse(start.replace(' ', 'T') + 'Z');
        const e = Date.parse(end.replace(' ', 'T') + 'Z');
        if (Number.isNaN(s) || Number.isNaN(e)) return '';
        const secs = Math.max(0, Math.round((e - s) / 1000));
        if (secs < 60) return `${secs}s`;
        return `${Math.floor(secs / 60)}m ${secs % 60}s`;
    } catch {
        return '';
    }
}
