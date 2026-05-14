#!/bin/bash

set -e

PLUGIN_SLUG="content-forge"
RELEASE_DIR="release"
DIST_DIR="dist"
ZIP_FILE="$DIST_DIR/$PLUGIN_SLUG.zip"

echo "Starting Content Forge release build process..."

# 1. Run build process
echo "Building assets..."
npm run build

# 2. Clean up previous release and dist directories
echo "Cleaning up previous builds..."
rm -rf "$RELEASE_DIR" "$DIST_DIR"
mkdir -p "$RELEASE_DIR" "$DIST_DIR"

# 3. Copy required files and directories for WordPress.org submission
echo "Copying plugin files..."

# Core plugin files
cp "$PLUGIN_SLUG.php" "$RELEASE_DIR/"
cp readme.txt "$RELEASE_DIR/"
cp composer.json "$RELEASE_DIR/"

# Plugin directories
cp -R includes "$RELEASE_DIR/"
cp -R Lib "$RELEASE_DIR/"
cp -R assets "$RELEASE_DIR/"
cp -R src "$RELEASE_DIR/"

if [ -d "languages" ]; then
    cp -R languages "$RELEASE_DIR/"
fi

# Install production-only composer dependencies into the release
# (skips dev tools like phpcs, phpunit, wpcs). Restore dev deps afterwards
# so the developer's local environment is unaffected.
echo "Installing production-only composer dependencies for the release..."
composer install --no-dev --optimize-autoloader --quiet
cp -R vendor "$RELEASE_DIR/"

echo "Restoring developer composer dependencies..."
composer install --quiet

# 4. Create zip archive of the release directory contents
echo "Creating release zip..."
cd "$RELEASE_DIR"
zip -r "../$ZIP_FILE" .
cd ..

# 5. Display results
echo ""
echo "✅ Build and packaging complete!"
echo "📁 Release directory: $RELEASE_DIR/"
echo "📦 Distribution zip: $ZIP_FILE"
echo ""
echo "Files included in release:"
echo "- $PLUGIN_SLUG.php (main plugin file)"
echo "- readme.txt (WordPress.org readme)"
echo "- composer.json (dependency management)"
echo "- includes/ (PHP classes)"
echo "- assets/ (built CSS/JS files)"
echo "- src/ (source JS/CSS files)"
if [ -d "languages" ]; then
    echo "- languages/ (translation files)"
fi
if [ -d "vendor" ]; then
    echo "- vendor/ (composer dependencies)"
fi
echo ""
echo "🚀 Ready for WordPress.org submission!"

# 6. Audit summary — top-level zip contents + vendor packages shipped
echo ""
echo "--- Release audit ---"
echo "Top-level entries in the zip:"
unzip -l "$ZIP_FILE" | awk 'NR>3 {print $4}' | awk -F/ '{print $1}' | sort -u | grep -v '^$'

if [ -d "$RELEASE_DIR/vendor" ]; then
    echo ""
    echo "Vendor packages shipped:"
    find "$RELEASE_DIR/vendor" -mindepth 2 -maxdepth 2 -type d | sed "s|$RELEASE_DIR/vendor/||" | sort
fi

echo ""
ZIP_SIZE=$(du -h "$ZIP_FILE" | awk '{print $1}')
echo "Release zip size: $ZIP_SIZE"
echo "---"