// DESCRIPTION: Thin wrapper over @wordpress/api-fetch for the Autopilot REST endpoints.
// Centralizes nonce + base URL config and gives the views a typed-ish surface.

import apiFetch from '@wordpress/api-fetch';

let configured = false;

function configureOnce() {
    if (configured) return;
    if (window.cforge?.rest_nonce) {
        apiFetch.use(apiFetch.createNonceMiddleware(window.cforge.rest_nonce));
    }
    if (window.cforge?.apiUrl) {
        apiFetch.use(apiFetch.createRootURLMiddleware(window.cforge.apiUrl));
    }
    configured = true;
}

export async function listSchedules() {
    configureOnce();
    return apiFetch({ path: 'autopilot/schedules', method: 'GET' });
}

export async function getSchedule(id) {
    configureOnce();
    return apiFetch({ path: `autopilot/schedules/${id}`, method: 'GET' });
}

export async function createSchedule(payload) {
    configureOnce();
    return apiFetch({ path: 'autopilot/schedules', method: 'POST', data: payload });
}

export async function previewSchedule(payload) {
    configureOnce();
    return apiFetch({ path: 'autopilot/schedules/preview', method: 'POST', data: payload });
}

export async function updateSchedule(id, payload) {
    configureOnce();
    return apiFetch({ path: `autopilot/schedules/${id}`, method: 'PUT', data: payload });
}

export async function deleteSchedule(id, force = false) {
    configureOnce();
    return apiFetch({
        path: `autopilot/schedules/${id}${force ? '?force=true' : ''}`,
        method: 'DELETE',
    });
}

export async function pauseSchedule(id) {
    configureOnce();
    return apiFetch({ path: `autopilot/schedules/${id}/pause`, method: 'POST' });
}

export async function resumeSchedule(id) {
    configureOnce();
    return apiFetch({ path: `autopilot/schedules/${id}/resume`, method: 'POST' });
}

export async function cloneSchedule(id) {
    configureOnce();
    return apiFetch({ path: `autopilot/schedules/${id}/clone`, method: 'POST' });
}

export async function runScheduleNow(id) {
    configureOnce();
    return apiFetch({ path: `autopilot/schedules/${id}/run-now`, method: 'POST' });
}

export async function bulkAction(action, ids) {
    configureOnce();
    return apiFetch({
        path: 'autopilot/schedules/bulk',
        method: 'POST',
        data: { action, ids },
    });
}

export async function listRuns(scheduleId, limit = 30) {
    configureOnce();
    return apiFetch({
        path: `autopilot/schedules/${scheduleId}/runs?limit=${limit}`,
        method: 'GET',
    });
}

export async function retryRun(runId) {
    configureOnce();
    return apiFetch({ path: `autopilot/runs/${runId}/retry`, method: 'POST' });
}
