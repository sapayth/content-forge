# Content Forge Developer Guide

## Plugin Architecture Overview

Content Forge is designed for extensibility and maintainability, using clear patterns for generating, tracking, and managing fake content in WordPress. The plugin is organized into logical modules for each content type (posts, comments, users, etc.), with a shared architecture for generators, REST API controllers, and React-based admin interfaces.

### Key Architectural Patterns

- **Generators**: Each content type (Post, Comment, User, etc.) has a generator class in `includes/Generator/`, extending a common abstract `Generator` base. Generators handle random data creation, use WordPress APIs for insertion, and track generated items in a custom DB table (`wp_cforge`).
- **REST API Controllers**: Each content type has a REST controller in `includes/Api/`, extending a base controller. These expose endpoints for bulk creation, listing, and deletion, and are registered in the main plugin file.
- **React Admin Apps**: Each feature has a React app in `src/js/`, rendered in a dedicated admin page. Data is passed from PHP to JS using `wp_localize_script`.
- **Tracking**: All generated content is tracked in a custom DB table for easy listing and deletion.
- **Extensibility**: Hooks and filters are provided throughout the generation process for customization.
- **Internationalization**: All strings are translatable in both PHP and JS.

---

## How Generators Work

- All generators extend `ContentForge\Generator\Generator`.
- Implement `generate($count, $args)` and `delete($object_ids)` methods.
- Use WordPress APIs (`wp_insert_post`, `wp_insert_comment`, `wp_insert_user`, etc.) for content creation.
- Track generated items in the `wp_cforge` table for later management.
- Example: See `includes/Generator/Post.php`, `includes/Generator/Comment.php`, `includes/Generator/User.php`.

---

## REST API Controllers

- Each controller (e.g., `Api/Post.php`, `Api/Comment.php`, `Api/User.php`) registers endpoints for bulk create, list, and delete.
- Controllers extend a base REST controller and are registered in `content-forge.php`.
- Endpoints are consumed by the React admin apps.

---

## React Admin Apps

- Each feature has a React app (e.g., `src/js/pages-posts.jsx`, `src/js/comments.jsx`, `src/js/users.jsx`).
- Apps are rendered in dedicated admin pages, registered in `includes/Admin.php`.
- Data (e.g., roles, post types) is passed from PHP using `wp_localize_script`.
- Apps provide UI for listing, creating, and deleting generated content.

---

## Tracking Generated Content

- All generated content is tracked in the `wp_cforge` table with fields: `object_id`, `data_type`, `created_at`, `created_by`.
- This enables listing and bulk deletion of only plugin-generated content.

---

## Extending Content Forge

- Add new generators by extending the base `Generator` class.
- Add new REST controllers for new content types.
- Register new admin pages and React apps as needed.
- Use provided hooks/filters to customize data generation or actions before/after creation.

---

## Hooks & Filters

- `cforge_generate_user_data`, `cforge_before_generate_user`, `cforge_after_generate_user` (see User generator)
- Similar hooks exist for posts and comments.
- Use these to customize or extend generation logic.

---

## Internationalization

- All PHP strings use `__()`, `_e()`, etc. with the `cforge` text domain.
- All JS strings use `@wordpress/i18n` and the same text domain.

---

## See Also

- [Feature-specific developer docs](#) (to be created for each content type)
- [User guides](user-guide-post-generation.md)

---

# Post Generation Developer Details

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