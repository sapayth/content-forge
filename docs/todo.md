# Content Forge - Open Tasks

Completed items are removed once shipped — git history is the record.

## Shipping

- [ ] Release v1.7.0: taxonomy assignment, AI featured images, Dashboard page,
      edit/view row links, orphaned-row cleanup. Committed and version-bumped;
      remaining gates before tagging `v1.7.0`:
  - [ ] Live API-key run — `wp eval-file tests/manual/featured-image-check.php`
        (the only feature that spends provider credits and has never run against
        a real endpoint)
  - [ ] `wp eval-file tests/manual/taxonomy-assignment-check.php`
  - [ ] `wp eval-file tests/manual/cleanup-check.php`
  - [ ] Editor matrix: latest WP + block theme, and + classic theme with Classic Editor
  - [ ] Smoke the Dashboard at `admin.php?page=cforge` in a browser

## Testing

- [ ] Set up PHPUnit following the WordPress core testing guidelines
  - https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/
  - https://github.com/WordPress/phpunit-test-runner
- [ ] Test suite for the Generator classes (Post, User, Comment)
- [ ] Test suite for the REST API endpoints
- [ ] Tests for the tracking system
- [ ] Configure CI for automated testing

## Code Quality

- [ ] Reduce duplication in admin script/style enqueuing — reusable method in `Admin.php`
- [ ] Constants cleanup in `content-forge.php` — remove duplicates, document what stays

## Conventions

- Content generation is custom-built. We do not use the Faker library.
