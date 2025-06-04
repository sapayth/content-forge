=== Content Forge ===
Contributors: sapayth
Tags: fake data, dummy content, testing, development, generator
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate fake/dummy posts, pages, users, taxonomies, and comments for development and testing purposes.

== Description ==

Content Forge is a powerful WordPress plugin designed for developers and site builders who need to quickly generate realistic dummy content for testing and development purposes. Whether you're building a new theme, testing functionality, or demonstrating a website, Content Forge provides an easy way to populate your WordPress site with meaningful fake data.

= Features =

* Generate fake posts with realistic titles, content, and metadata
* Create dummy pages with hierarchical structure
* Generate test users with various roles and capabilities
* Create fake taxonomies (categories and tags)
* Generate realistic comments and comment threads
* Bulk generation capabilities for efficient testing
* Clean and intuitive admin interface
* Follows WordPress coding standards
* Translation ready

= Use Cases =

* Theme development and testing
* Plugin development
* Client demonstrations
* Performance testing with large datasets
* Content structure planning
* Training and educational purposes

= Developer Friendly =

Content Forge is built with developers in mind:
* Clean, object-oriented code structure
* Follows WordPress coding standards
* Extensible with hooks and filters
* Well-documented codebase
* PSR-4 autoloading

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/content-forge` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the Content Forge menu in your WordPress admin dashboard.
4. Configure your generation settings and start creating dummy content.

== Frequently Asked Questions ==

= Is the generated content safe for production sites? =

Content Forge is designed for development and testing purposes only. While the generated content is safe, it's recommended to use this plugin only on development, staging, or testing environments.

= Can I customize the type of content generated? =

Yes, Content Forge provides various options to customize the generated content including post types, user roles, taxonomy terms, and content length.

= Will this plugin slow down my site? =

Content Forge only runs when you actively generate content through the admin interface. It doesn't affect your site's frontend performance.

= Can I delete all generated content at once? =

Yes, Content Forge provides bulk deletion options to easily remove all generated content when you're done testing.

= Is the plugin translation ready? =

Yes, Content Forge is fully translation ready and includes a .pot file for translators.

== Screenshots ==

1. Main dashboard showing generation options
2. Post generation interface with customization options
3. User generation settings
4. Bulk content management tools

== Changelog ==

= 1.0.0 =
* Initial release
* Post generation functionality
* Page generation with hierarchy support
* User generation with role assignment
* Taxonomy generation (categories and tags)
* Comment generation with threading
* Bulk generation capabilities
* Clean admin interface
* Translation support

== Upgrade Notice ==

= 1.0.0 =
Initial release of Content Forge. Start generating realistic dummy content for your WordPress development and testing needs.

== Developer Notes ==

Content Forge uses the Faker PHP library to generate realistic fake data. The plugin follows WordPress coding standards and best practices.

For developers looking to extend Content Forge:
* All classes use PSR-4 autoloading
* Hooks and filters are available for customization
* Clean separation of concerns with dedicated generator classes
* Comprehensive error handling and validation

== Support ==

For support, feature requests, or bug reports, please visit the plugin's support forum or contact the developer.