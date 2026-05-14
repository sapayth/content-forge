<!--
DESCRIPTION: User-facing guide for the Content Forge Autopilot feature.
Optimized for both humans and AI assistants learning the feature.
-->

# Autopilot — User Guide

> **TL;DR** — Autopilot is a free feature inside Content Forge that publishes AI-generated WordPress posts on a schedule you set. Pick a topic source, pick how often, pick what happens to the post (draft, pending, scheduled, or live), and it runs in the background.

---

## What Autopilot does

Autopilot turns Content Forge from a one-shot dummy-content generator into a **scheduled AI publishing assistant**. You define a recurring rule — for example, *"every weekday at 9 AM, generate one post about WordPress productivity tips, save it as a draft for me to review"* — and Content Forge does the rest using the AI provider you already configured (OpenAI, Anthropic, or Google).

It is designed for:

- **Solo bloggers** who want a steady stream of draft ideas without staring at a blank page.
- **Content marketers** filling out a niche site, demo site, or staging environment with realistic posts.
- **Agency teams** building out a content backbone before a client takes over editorial.
- **Anyone testing** how their theme, SEO plugin, or category structure handles real ongoing content.

Autopilot **never publishes without your permission** — by default it saves everything as a draft. You opt into auto-publishing explicitly.

---

## Who Autopilot is *not* for

- Production sites where every post must be hand-written and fact-checked.
- Replacing a human editor on a money site (AI posts still need review).
- Pumping out low-quality SEO spam — that is not what the feature is built for and Google will punish it.

Use it like a junior writer producing first drafts: helpful, fast, and always reviewed before it ships.

---

## Quick start — your first Autopilot in 5 minutes

### Step 1. Make sure AI is configured

Go to **Content Forge → Settings → AI** and connect at least one provider (OpenAI, Anthropic, or Google) with a valid API key. Autopilot will refuse to run if no AI provider is configured.

### Step 2. Open Autopilot

In the WordPress admin, go to **Content Forge → Autopilot**.

### Step 3. Click "New Autopilot"

You'll see a form with these sections: **Basics, Topics, Targeting, AI, Schedule, Publishing, Safety, Notifications**. Fill them in top to bottom.

#### Basics

- **Name** — a label for yourself. e.g. *"Monday SEO tips"* or *"Daily WP news drafts"*.

#### Topics — pick how Autopilot decides what to write

| Topic source | Best for |
|---|---|
| **List (cycle in order)** | You have 10–30 specific topics and want them written one by one, predictably. |
| **List (random pick)** | Same list, but Autopilot picks one at random each run. |
| **Topic queue (consumed)** | You drop in one-off topics; each one is used once and removed. Great for "this week I want these 5 posts." |
| **AI-suggested from theme** | You give it a theme like *"WordPress productivity tips"* and let the AI invent the topic each time. |

Toggle **"Avoid duplicating recent posts in this category"** to skip topics too similar to your last few posts (uses a similarity check, default threshold 0.7).

#### Targeting

- **Post type** — Post, Page, or any CPT.
- **Category** — required when duplicate-avoidance is on.
- **Tags / Author** — optional.
- **Featured image** — none, Picsum, Placehold.co, or a URL pattern.

#### AI

- **Custom prompt** — optional. Add brand voice notes, formatting requirements, banned phrases, etc.
- **Tone** — Professional, Casual, Technical, Conversational.
- **Approximate length** — Short (~300 words), Medium (~800), Long (~1500).

#### Schedule

- **Frequency** — Daily, Weekly, Monthly, or Custom interval (every N hours).
- **Time of day** — uses your WordPress site timezone.
- **Posts per run** — 1 to 5. Stagger them with the *stagger hours* option if you want spacing between drafts in the same run.

#### Publishing — the most important section

| Mode | What happens |
|---|---|
| **Save as draft** *(recommended)* | Post lands in Drafts. You review and publish manually. |
| **Mark as pending review** | Editorial workflow — sits in Pending Review for someone with publish rights. |
| **Schedule to publish later** | Goes live automatically after the *publish delay hours* you set (e.g. 24h gives you a day to review). |
| **Publish immediately** | Live on the front end the moment it's generated. Requires you to tick a confirmation. |

#### Safety

- **Daily post cap** — hard limit on posts created per day (default 5).
- **Auto-pause after N consecutive failures** — if the AI fails 3 times in a row, Autopilot pauses itself and emails you.

#### Notifications

- Email yourself on every run, only on failures, or never.

### Step 4. Save and watch it run

Hit **Save**. Your Autopilot is now active and will fire at the next scheduled time.

To test without waiting, click **Run now** on the Autopilot list — it queues an immediate run. Or click **Preview** while editing to generate one sample post without saving anything to your site.

### Step 5. Review the Run History

Each Autopilot has a **Run History** tab showing every execution: status (success / partial / failed / skipped), topic used, post created, and any error message. Failed runs can be retried with one click.

---

## Common recipes

### "I want a daily draft I can polish over coffee"

- Topic source: **AI-suggested from theme** with your niche.
- Frequency: **Daily** at 06:00.
- Publishing: **Save as draft**.
- Posts per run: **1**.
- Notifications: **Failure only**.

### "Fill out a brand-new niche site with 30 posts over a month"

- Topic source: **List (cycle in order)** with all 30 topics.
- Frequency: **Daily** at 09:00.
- Publishing: **Schedule to publish later** with a 6-hour delay (gives you a review window).
- Daily post cap: **1**.

### "Generate a week's worth of drafts on Sunday night"

- Topic source: **Topic queue** with 7 topics.
- Frequency: **Weekly**, Sunday at 22:00.
- Posts per run: **5** with **stagger hours: 0**.
- Publishing: **Save as draft**.

### "Demo site for a client pitch — needs to look populated by Monday"

- Topic source: **AI-suggested from theme**.
- Frequency: **Custom interval**, every 2 hours.
- Publishing: **Publish immediately**.
- Daily post cap: **10**.

---

## How Autopilot protects you

- **Default-to-draft.** You have to explicitly opt into auto-publishing.
- **Daily caps.** A hard ceiling per Autopilot per day.
- **Auto-pause on failure.** Three failures in a row (configurable) and it pauses itself.
- **Duplicate guard.** Optional similarity check against your recent posts in the same category, so it won't write the same article twice.
- **Dispatcher heartbeat.** The dashboard warns you if WP-Cron has stalled and Autopilot hasn't run when it should have.
- **Clean deactivation.** Deactivating the plugin removes Autopilot's scheduled jobs. Your data is preserved if you reactivate later.

---

## Troubleshooting

**Autopilot says "AI not configured" and won't run.**
Go to Content Forge → Settings → AI and add a provider API key.

**Dashboard warns "Dispatcher is stale."**
WP-Cron isn't firing on your host. Either visit your front end manually to wake cron, or set up a system cron that hits `wp-cron.php?doing_wp_cron` every minute.

**Posts are being created but they're too short / too long / wrong tone.**
Edit the Autopilot, change the **Tone** or **Approximate length**, and add specifics to the **Custom prompt**.

**My Autopilot paused itself.**
Check Run History for the failure reason. Common causes: API key expired, provider rate-limited, category deleted. Fix the cause and click **Resume**.

**I want to permanently delete an Autopilot.**
Use the Archive action, then delete with `?force=true` from the archived list.

---

## FAQ

**Is Autopilot free?**
Yes. Content Forge and all its features, including Autopilot, are free.

**Does it cost me anything to run?**
You pay your AI provider (OpenAI, Anthropic, or Google) for the API calls. Content Forge itself charges nothing.

**Can I run multiple Autopilots at the same time?**
Yes. Run one per category, one per niche, or one per author — they don't interfere.

**Does it work on multisite?**
Yes, per-site. Each site configures its own Autopilots.

**Can it write in a language other than English?**
Yes, if your AI provider supports the language. Set the language requirement in the **Custom prompt** field.

**Will Google penalize me for AI-generated content?**
Google penalizes *low-quality, unhelpful* content regardless of source. Use Autopilot for drafts you review and improve — not for unedited mass-publishing.

**Can I export or back up my Autopilot configurations?**
Each Autopilot is stored as a custom post type (`cforge_autopilot`), so it's included in your normal WordPress exports and backups.

---

## For developers

If you want to extend Autopilot — custom topic strategies, hook into the generation pipeline, build your own UI on top of the REST API — see [developer-autopilot.md](developer-autopilot.md).
