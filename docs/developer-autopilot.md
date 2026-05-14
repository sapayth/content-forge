# Autopilot — Developer Reference

This document covers the extensibility surface for the Autopilot feature: hooks, filters, REST endpoints, custom topic strategies, and storage shape. The feature flag (`cforge_autopilot_enabled`) must be on for any of this to fire.

## Overview

Autopilot runs on three Action Scheduler hooks registered by `ContentForge\Autopilot\Plugin::boot()`:

| Hook | Cadence | Purpose |
|---|---|---|
| `cforge_autopilot_dispatch` | every 60s | Scans the schedules CPT for due rows, advances `next_run_at`, enqueues a runner action. |
| `cforge_autopilot_run_schedule` | one-shot | Resolves a topic, calls AI, inserts post(s), records a run row, applies safety logic. |
| `cforge_autopilot_maintenance` | daily | Prunes run history beyond 30 per schedule; marks stuck runs as failed. |

Schedules are stored as the `cforge_autopilot` CPT (built-in post statuses: `draft`/`publish`/`private`/`trash`). Runs are stored in `{$wpdb->prefix}cforge_autopilot_runs`.

## Actions

All actions fire after the relevant DB writes have settled — safe to read state inside the handler.

### `cforge_autopilot_before_run`
`(Schedule $schedule, int $run_id)` — Fires after preflight checks pass, before any AI calls. Use this to short-circuit a run via filters, log custom telemetry, or pre-populate run metadata.

### `cforge_autopilot_run_completed`
`(Schedule $schedule, int $run_id, string $status)` — Fires after the run finishes regardless of status (`success` | `partial` | `failed` | `skipped`).

### `cforge_autopilot_post_created`
`(int $post_id, Schedule $schedule, int $run_id)` — Fires immediately after each post is inserted. Use for custom taxonomy assignment, post meta, or external syndication.

### `cforge_autopilot_schedule_auto_paused`
`(Schedule $schedule, string $reason)` — Fires when the system auto-pauses a schedule. Reason keys include `consecutive_failures`, `queue_empty`, `ai_not_configured`, `category_missing`.

## Filters

### `cforge_autopilot_resolved_topic`
`(string $topic, Schedule $schedule) → string` — Filters the topic chosen by the resolver right before it is handed to the AI generator. Return `''` to skip the post.

### `cforge_autopilot_generated_post_args`
`(array $post_args, Schedule $schedule, array $ai_result) → array` — Filters the `wp_insert_post` payload after the AI response is shaped but before the post is created. Add custom meta or override the post status here.

### `cforge_autopilot_duplicate_threshold`
`(float $threshold, int $category_id) → float` — Filters the Jaccard similarity threshold used by `Duplicate_Guard`. Default 0.7. Range 0..1.

### `cforge_autopilot_email_recipients`
`(string[] $recipients, string $event, Schedule $schedule) → string[]` — Filters the email recipient list before `wp_mail`. Events: `success`, `failure`, `auto_paused`.

### `cforge_autopilot_email_body`
`(string $body, string $event, Schedule $schedule, Run|null $run) → string` — Filters the plain-text email body.

## REST API

All routes live under `cforge/v1` and require `manage_options`.

| Method | Path | Notes |
|---|---|---|
| GET | `/autopilot/schedules` | List all schedules. |
| POST | `/autopilot/schedules` | Create. Body: `{name, config}`. |
| GET | `/autopilot/schedules/{id}` | Read one. |
| PUT | `/autopilot/schedules/{id}` | Update. Body: `{name?, config?}`. Recomputes `next_run_at` if frequency changed and schedule is active. |
| DELETE | `/autopilot/schedules/{id}` | Archive (trash). `?force=true` for hard delete. |
| POST | `/autopilot/schedules/{id}/pause` | |
| POST | `/autopilot/schedules/{id}/resume` | Computes a new `next_run_at` from current frequency. |
| POST | `/autopilot/schedules/{id}/clone` | Returns a new draft. |
| POST | `/autopilot/schedules/{id}/run-now` | Enqueues an immediate run. Returns `{run_id}`. |
| POST | `/autopilot/schedules/preview` | Body: `{config}`. Generates one sample post, returns `{topic, title, excerpt, content}`. No persistence. |
| POST | `/autopilot/schedules/bulk` | Body: `{action: pause|resume|archive, ids: int[]}`. |
| GET | `/autopilot/schedules/{id}/runs?limit=N` | Last N run rows for a schedule. |
| POST | `/autopilot/runs/{id}/retry` | Retry a failed/partial/skipped run. Creates a fresh run row. |
| GET | `/autopilot/settings` | Read `{enabled}`. Global on/off for the entire feature. |
| PUT | `/autopilot/settings` | Update. |

## Schedule config shape

```jsonc
{
  "schema_version": 1,
  "topic": {
    "mode": "list_cycle | list_random | queue | ai_theme",
    "list": ["topic 1", "topic 2"],
    "queue": ["one-shot topic 1"],
    "queue_cursor": 0,
    "list_cursor": 0,
    "theme": "WordPress productivity tips"
  },
  "avoid_recent_duplicates": true,
  "targeting": {
    "post_type": "post",
    "category_id": 12,
    "tag_ids": [3, 7],
    "ai_suggests_tags": false,
    "author_id": 0,
    "featured_image": { "source": "none|picsum|placehold|url", "url": "" }
  },
  "ai": {
    "provider_override": null,
    "model_override": null,
    "custom_prompt": "",
    "tone": "professional|casual|technical|conversational",
    "length": "short|medium|long"
  },
  "frequency": {
    "mode": "daily|weekly|monthly|interval",
    "time": "09:00",
    "weekdays": [1, 3],
    "day_of_month": 1,
    "interval_hours": 24,
    "start_at": "",
    "stagger_hours": 0
  },
  "publishing": {
    "mode": "draft|pending|scheduled|publish",
    "publish_delay_hours": 1,
    "ack_publish_immediately": false
  },
  "safety": {
    "daily_post_cap": 5,
    "auto_pause_after_failures": 3
  },
  "notifications": {
    "email_mode": "none|failure|every",
    "email_to": ""
  },
  "posts_per_run": 1
}
```

## Adding a custom topic strategy

Topic resolution is dispatched in `ContentForge\Autopilot\Topic\Topic_Resolver::resolve()` via a `switch` on `topic.mode`. To add a new mode:

1. Implement a class with a `resolve(Schedule $schedule): string` method.
2. Add a case in `Topic_Resolver::resolve()` (the resolver is internal; for now, use the `cforge_autopilot_resolved_topic` filter as the extension seam — return your own topic string).

Example — pick a topic from a transient queue:

```php
add_filter( 'cforge_autopilot_resolved_topic', function ( $topic, $schedule ) {
    if ( $schedule->config_get( 'topic.mode' ) !== 'my_custom_mode' ) {
        return $topic;
    }
    $queue = get_transient( 'my_topic_queue_' . $schedule->id() );
    return is_array( $queue ) && ! empty( $queue ) ? array_shift( $queue ) : $topic;
}, 10, 2 );
```

## Observability

- **Dispatcher heartbeat**: option `cforge_autopilot_last_dispatch` holds the last successful dispatch tick timestamp. The dashboard widget reads this and warns when stale (>5 min).
- **Action Scheduler admin page**: Tools → Scheduled Actions filters by hook `cforge_autopilot_*` for ops debugging.
- **In-admin notices**: `Admin_Notices` records persistent notices for auto-pause events. Stored in option `cforge_autopilot_admin_notices` (capped at 20).

## Health checks for hosts

If the dashboard widget shows the dispatcher as stale:

1. Check WP-Cron health — visit a page and confirm `wp-cron.php` is being requested.
2. Visit Tools → Scheduled Actions and confirm `cforge_autopilot_dispatch` is "pending" (not "failed").
3. If using a system cron, ensure it hits `wp-cron.php?doing_wp_cron` at least once a minute.

## Disabling Autopilot cleanly

Deactivating the plugin calls `Plugin::unschedule_recurring_actions()`, which removes the dispatcher and maintenance recurring AS actions. Schedule CPT data and the runs table are preserved across deactivation/reactivation.

To purge data permanently: hard-delete CPT rows + `DROP TABLE {$wpdb->prefix}cforge_autopilot_runs;` + `delete_option('cforge_autopilot_db_version');`. There is no built-in UI for this.
