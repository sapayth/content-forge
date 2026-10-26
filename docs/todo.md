# Content Forge - Open Tasks

Completed items are removed once shipped — git history is the record.

## Testing

Plain PHPUnit unit tests (`tests/unit/`, `composer test`) already run in CI
(`.github/workflows/ci.yml`). What's missing is integration coverage against
a real WordPress install.

- [ ] Add the WordPress integration test suite (WP test library + test DB),
      following the core testing guidelines
  - https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/
  - https://github.com/WordPress/phpunit-test-runner
- [ ] Test suite for the Generator classes (Post, User, Comment)
- [ ] Test suite for the REST API endpoints (only Dashboard counts is covered)
- [ ] Tests for the tracking system

## Code Quality

- [ ] Reduce duplication in admin script/style enqueuing — reusable method in `Admin.php`
- [ ] Constants cleanup in `content-forge.php` — remove duplicates, document what stays

## Conventions

- Content generation is custom-built. We do not use the Faker library.
