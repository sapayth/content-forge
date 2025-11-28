# Implement WordPress Telemetry Integration

## Summary

This PR implements WordPress telemetry tracking functionality for the Content Forge plugin, allowing anonymous usage data collection to help improve the plugin. The implementation integrates the `bitapps/wp-telemetry` library and provides users with an opt-in mechanism through the plugin's admin interface. Telemetry data is sent weekly to a configured server endpoint and includes plugin usage statistics, WordPress environment information, and plugin compatibility data.

## Changes Made

### Core Telemetry Integration
- Added `bitapps/wp-telemetry` dependency (v0.0.9) via Composer
- Created new `Telemetry_Manager` class (`includes/Telemetry_Manager.php`) to handle telemetry initialization and configuration
- Integrated telemetry initialization in `Loader.php` to automatically set up tracking on plugin load
- Configured telemetry with plugin-specific settings (slug, prefix, version, server URL)
- Added custom filters to extend telemetry data with plugin name and inactive plugins list

### Frontend Implementation
- Updated `Header` component to display telemetry opt-in button when tracking is disabled
- Added telemetry status state management in React components
- Implemented AJAX-based opt-in functionality with loading states and user feedback
- Fixed component import paths (standardized to `Header` from `header`)
- Added telemetry-related data to global JavaScript variables (`window.cforge`) for all admin pages

### Backend Implementation
- Added `handle_telemetry_opt_in()` AJAX handler in `Admin.php` with proper nonce verification and capability checks
- Extended `enqueue_assets()` to pass telemetry configuration to frontend (AJAX URL, nonce, enabled status)
- Implemented secure opt-in flow with error handling and logging

### Code Quality Improvements
- Fixed duplicate constant definition in `content-forge.php` (removed duplicate `CFORGE_VERSION` and `CFORGE_PATH`)
- Standardized code formatting (spacing around braces, consistent conditional formatting)
- Updated global variable naming from `window.cforgeData` to `window.cforge` for consistency
- Fixed indentation and formatting in `pages-posts.jsx` ListView component

### Telemetry Features
- Weekly automatic telemetry reports sent to configured server endpoint
- Deactivation feedback survey integration
- Customizable telemetry data via WordPress filters (`cforge_telemetry_data`, `cforge_telemetry_additional_data`)
- Configurable server URL, terms URL, and policy URL via filters
- Opt-in/opt-out programmatic methods for developers

## Dependencies

- **New dependency**: `bitapps/wp-telemetry` (^0.0.9)
  - License: GPL-2.0-or-later
  - PHP requirement: >=5.6 (compatible with plugin's >=7.4 requirement)
  - Provides WordPress telemetry tracking and deactivation feedback functionality

## Security Considerations

- All AJAX requests are protected with nonce verification (`cforge_telemetry` nonce)
- Capability checks ensure only users with `manage_options` can opt-in to telemetry
- Input sanitization and output escaping follow WordPress coding standards
- Telemetry data collection is opt-in only (users must explicitly enable it)
- Server URL can be filtered/overridden for custom implementations

## Performance Impact

- Minimal performance impact: telemetry initialization only occurs once on plugin load
- Telemetry reports are sent weekly (not on every page load)
- AJAX opt-in handler includes proper error handling to prevent blocking
- Autoloader checks ensure telemetry library is only loaded when available

## WordPress Compatibility

- Compatible with WordPress 4.0+ (follows WordPress minimum version requirement)
- PHP 7.4+ compatible (maintains existing PHP version requirement)
- Follows WordPress coding standards and best practices
- Uses WordPress hooks and filters for extensibility
- Proper internationalization (i18n) support with `content-forge` text domain

## Developer Notes

### Available Filters

Developers can customize telemetry behavior using the following filters:

- `cforge_telemetry_server_url` - Override the telemetry server URL (default: `http://feedio.test/api/`)
- `cforge_telemetry_terms_url` - Set terms of service URL
- `cforge_telemetry_policy_url` - Set privacy policy URL
- `cforge_telemetry_data` - Modify telemetry data before sending
- `cforge_telemetry_additional_data` - Add additional data to tracking reports
- `cforge_deactivate_reasons` - Add custom deactivation reasons to feedback survey

### Programmatic Usage

```php
// Opt in to telemetry
Telemetry_Manager::opt_in();

// Opt out of telemetry
Telemetry_Manager::opt_out();

// Check if tracking is enabled
$is_enabled = Telemetry_Manager::is_tracking_allowed();
```

## Testing Notes

- Verify telemetry opt-in button appears in admin header when tracking is disabled
- Test AJAX opt-in flow with proper nonce and capability checks
- Confirm telemetry data includes plugin name and inactive plugins list
- Verify weekly report scheduling works correctly
- Test deactivation feedback survey appears when plugin is deactivated
- Ensure all error handling works correctly (missing autoloader, class not found, etc.)

## Related Issues

<!-- Link to related issues here if applicable -->

