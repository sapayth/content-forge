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
cp -R vendor "$RELEASE_DIR/"
cp -R languages "$RELEASE_DIR/"
cp -R src "$RELEASE_DIR/"

if [ -d "languages" ]; then
    cp -R languages "$RELEASE_DIR/"
fi

# Vendor dependencies (if they exist)
if [ -d "vendor" ]; then
    echo "Copying vendor dependencies..."
    cp -R vendor "$RELEASE_DIR/"
fi

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