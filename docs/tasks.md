# Content Forge - Development Tasks

## Content Generation Improvements

### Enhance `randomize_content()` and `randomize_title()`
- [x] Expand `randomize_title()` in `includes/Generator/Post.php` with more varied title templates
- [x] Add more realistic content patterns to `randomize_content()`
- [x] Include different content types (listicles, how-to guides, news articles, etc.)
- [x] Add paragraph variation (short, medium, long)

**Note:** We are NOT using the Faker library. All content generation will be custom-built.

## Testing Implementation

### PHPUnit Setup
- [ ] Set up PHPUnit following WordPress core testing guidelines
- [ ] Reference: https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/
- [ ] Reference: https://github.com/WordPress/phpunit-test-runner
- [ ] Create test suite for Generator classes (Post, User, Comment)
- [ ] Create test suite for API endpoints
- [ ] Add tests for tracking system
- [ ] Configure CI/CD for automated testing

## Code Quality Improvements

### Refactor Admin Script Enqueuing
- [ ] Create reusable method in `Admin.php` to reduce code duplication
- [ ] Consolidate script/style registration logic
- [ ] Improve maintainability of asset loading

### Constants Cleanup
- [ ] Review and keep only necessary constants in `content-forge.php`
- [ ] Remove duplicate constant definitions
- [ ] Document purpose of each constant
