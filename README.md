# Content Forge

Generate realistic dummy posts, pages, users, comments, taxonomies, and more for WordPress development and testing.

## Features

- **AI-powered generation** (optional) using OpenAI, Anthropic, and Google
- **Traditional generation** with no AI or API keys required
- Posts with realistic titles, content, metadata, and excerpts
- Pages with hierarchical structure
- Featured images via Picsum and Placehold.co
- Users with various roles and capabilities
- Taxonomies (categories and tags)
- Comments and comment threads
- WooCommerce product generation
- weDocs integration
- Custom Post Type (CPT) support
- Bulk generation and cleanup tools
- Block Editor and Classic Editor support

## Requirements

- WordPress 5.6+
- PHP 7.4+

## Development Setup

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Start development build (watch mode)
npm run start

# Production build
npm run build
```

### Linting

```bash
# PHP CodeSniffer
composer phpcs

# Auto-fix PHP coding standards
composer phpcbf
```

## Project Structure

```
content-forge/
├── content-forge.php    # Main plugin file
├── includes/            # PHP classes (PSR-4 autoloaded under ContentForge\)
├── src/                 # Source JS/CSS (React components, Tailwind)
├── assets/              # Built CSS/JS output
├── Lib/                 # Bundled libraries (Action Scheduler)
├── vendor/              # Composer dependencies
├── languages/           # Translation files
├── scripts/             # Build scripts
│   └── package.sh       # Builds the release zip
├── release.sh           # Deploys to WordPress.org SVN
├── .env                 # SVN credentials (not committed)
├── readme.txt           # WordPress.org plugin readme
└── docs/                # Documentation and planning
```

## Release Process

Releasing to WordPress.org involves two steps: building the package and deploying to SVN.

### 1. Setup credentials

Copy the `.env` file and fill in your WordPress.org SVN credentials:

```bash
# .env
WP_ORG_USERNAME="your-wp-org-username"
WP_ORG_PASSWORD="your-wp-org-password"
```

This file is git-ignored and will never be committed.

### 2. Prepare WP.org assets (banners, icons, screenshots)

WordPress.org uses a separate `assets/` directory in SVN for plugin page visuals. Place these files in `assets/wp-org/` in the project root. The release script syncs this directory automatically.

#### Required assets

| File | Dimensions | Description |
|------|-----------|-------------|
| `banner-772x250.png` | 772x250 | Plugin banner (standard) |
| `banner-1544x500.png` | 1544x500 | Plugin banner (retina) |
| `icon-128x128.png` | 128x128 | Plugin icon (standard) |
| `icon-256x256.png` | 256x256 | Plugin icon (retina) |

#### Screenshots

Screenshots are referenced by number in `readme.txt` (e.g., `screenshot-1.png` maps to the first entry under `== Screenshots ==`).

| File | Description |
|------|-------------|
| `screenshot-1.png` | Main dashboard showing generation options |
| `screenshot-2.png` | Post generation interface with customization options |
| `screenshot-3.png` | AI-powered content generation settings |
| `screenshot-4.png` | User generation settings |
| `screenshot-5.png` | Bulk content management tools |

Place all screenshot files in `assets/wp-org/` alongside the banners and icons.

#### Example directory

```
assets/
└── wp-org/
    ├── banner-772x250.png
    ├── banner-1544x500.png
    ├── icon-128x128.png
    ├── icon-256x256.png
    ├── screenshot-1.png
    ├── screenshot-2.png
    ├── screenshot-3.png
    ├── screenshot-4.png
    └── screenshot-5.png
```

### 3. Update version numbers

Before releasing, update the version in these locations:

- `content-forge.php` — plugin header `Version:` and `ContentForge::VERSION`
- `readme.txt` — `Stable tag:` and add a changelog entry
- `package.json` — `version` field

### 4. Build and deploy

```bash
# Build the release zip only (no SVN deploy)
bash scripts/package.sh

# Full release: build + deploy to WordPress.org
./release.sh
```

The release script will:

1. Run `scripts/package.sh` to build production assets and create the `release/` directory
2. Check out the WP.org SVN repository
3. Sync `trunk/` with the built release files
4. Sync `assets/` from `assets/wp-org/` (if present)
5. Create a version tag (e.g., `tags/1.3.0/`)
6. Commit everything to SVN

### 5. Verify

After deploying, check the plugin page at:
`https://wordpress.org/plugins/content-forge/`

It may take a few minutes for changes to appear.

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
