# Running Tests for Content Forge

This document explains how to set up and run the PHPUnit tests for the Content Forge plugin.

## Prerequisites

- Docker installed and running (for wp-env)
- Node.js and npm installed
- Composer installed

## Setup

### Option 1: Using wp-env (Recommended)

1. **Install wp-env globally**:
   ```bash
   npm install -g @wordpress/env
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Start the WordPress environment**:
   ```bash
   wp-env start
   ```

4. **Run the tests**:
   ```bash
   composer test
   ```

### Option 2: Using Traditional WordPress Test Suite

1. **Install PHP dependencies**:
   ```bash
   composer install
   ```

2. **Set up the test environment**:
   ```bash
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```
   
   Replace `root` and `''` with your MySQL username and password.

3. **Run the tests**:
   ```bash
   composer test
   ```

## Running Tests

### Run all tests:
```bash
composer test
```

### Run specific test suites:
```bash
# Generator tests only
vendor/bin/phpunit --testsuite="Generator Tests"

# API tests only
vendor/bin/phpunit --testsuite="API Tests"

# Tracking tests only
vendor/bin/phpunit --testsuite="Tracking Tests"
```

### Run a specific test file:
```bash
vendor/bin/phpunit tests/test-post-generator.php
```

### Run with code coverage:
```bash
composer test:coverage
```

This will generate an HTML coverage report in the `coverage/` directory.

## Test Structure

```
tests/
├── bootstrap.php              # Test bootstrap file
├── test-base.php             # Base test case class
├── test-post-generator.php   # Post generator tests
├── test-user-generator.php   # User generator tests
├── test-comment-generator.php # Comment generator tests
├── test-post-api.php         # Post API endpoint tests
├── test-user-api.php         # User API endpoint tests
├── test-comment-api.php      # Comment API endpoint tests
└── test-tracking.php         # Tracking system tests
```

## Troubleshooting

### "Could not find WordPress tests" error

Make sure you've run the installation script:
```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Database connection errors

Check your database credentials in the installation script and ensure MySQL is running.

### wp-env not starting

Make sure Docker is running and you have the latest version of wp-env:
```bash
npm install -g @wordpress/env@latest
```

## Continuous Integration

The test suite is designed to run in CI/CD environments. You can add this to your GitHub Actions workflow:

```yaml
- name: Install dependencies
  run: composer install

- name: Run tests
  run: composer test
```
