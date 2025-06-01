# Content Forge: Developer Documentation – Post Generation & Deletion

## Overview
This document provides a technical overview of how Content Forge handles fake post creation, deletion, and tracking. It is intended for developers who wish to understand, maintain, or extend the post generation functionality.

---

## Architecture & Key Classes

- **Namespace:** `ContentForge\Generator`
- **Primary Class:** `Post` (extends `Generator`)
- **Location:** `includes/Generator/Post.php`

### Responsibilities
- Generate fake posts with randomized titles and content
- Track generated posts in a custom database table
- Delete generated posts and remove tracking

---

## Process Flow

### 1. Post Generation
- **Method:** `generate($count = 1, $args = [])`
- **Steps:**
  1. Loop `$count` times to create posts.
  2. For each post:
     - Generate a random title (`randomize_title()`).
     - Generate random content (`randomize_content()`).
     - Merge with any custom `$args` provided.
     - Insert post using `wp_insert_post()`.
     - If successful, track the post in the custom DB table (`track_generated()`).
  3. Return array of generated post IDs.

#### Title & Content Generation
- **randomize_title():**
  - Uses arrays of adjectives, nouns, verbs, topics, and industries.
  - Randomly selects a template structure and fills it with random words.
- **randomize_content():**
  - Assembles paragraphs from a pool of business/tech sentences.
  - Appends an attribution line.

### 2. Post Deletion
- **Method:** `delete(array $object_ids)`
- **Steps:**
  1. Loop through provided post IDs.
  2. For each, call `wp_delete_post($post_id, true)`.
  3. If successful, remove tracking (`untrack_generated()`).
  4. Returns the number of posts deleted.

### 3. Tracking Generated Posts
- **Custom Table:** Uses a table defined by `CFORGE_DBNAME` (created by `Activator::create_tracking_table()`).
- **Tracked Data:**
  - `object_id` (post ID)
  - `data_type` (post type)
  - `created_at` (timestamp)
  - `created_by` (user ID)
- **Methods:**
  - `track_generated($post_id)` – Adds entry to tracking table.
  - `untrack_generated($post_id)` – Removes entry from tracking table.

---

## Extensibility
- **Custom Arguments:** Pass `$args` to `generate()` to override default post fields.
- **Subclassing:** Extend the `Post` class for custom generators.
- **Hooks/Filters:** (Add as needed for extensibility.)

---

## Example Usage

```php
// Generate 5 posts
global $contentforge_post_generator;
$ids = $contentforge_post_generator->generate(5);

// Delete generated posts
$contentforge_post_generator->delete($ids);
```

---

## Database Table Schema (Example)
| Column      | Type         | Description         |
|-------------|--------------|---------------------|
| object_id   | BIGINT       | WP Post ID          |
| data_type   | VARCHAR(20)  | Post type           |
| created_at  | DATETIME     | Creation timestamp  |
| created_by  | BIGINT       | User ID             |

---

## Security & Standards
- Uses WordPress functions for DB and post operations.
- Follows WP coding standards.
- All output is escaped and input is sanitized where appropriate.

---

## File Reference
- `includes/Generator/Post.php` – Main logic
- `includes/Activator.php` – Table creation

---

## Further Extension
- Add hooks/filters for custom logic.
- Support for custom post types.
- Internationalization (i18n) for generated content. 