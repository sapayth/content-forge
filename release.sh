#!/bin/bash
# DESCRIPTION: Deploy Content Forge plugin to WordPress.org SVN repository.

set -e

PLUGIN_SLUG="content-forge"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_SLUG"
SVN_DIR="/tmp/$PLUGIN_SLUG-svn"
RELEASE_DIR="release"

# ------------------------------------------------------------------
# Load credentials from .env
# ------------------------------------------------------------------
if [ -f ".env" ]; then
    # shellcheck disable=SC1091
    source .env
fi

if [ -z "$WP_ORG_USERNAME" ] || [ -z "$WP_ORG_PASSWORD" ]; then
    echo "Error: WP_ORG_USERNAME and WP_ORG_PASSWORD must be set in .env"
    exit 1
fi

# ------------------------------------------------------------------
# Extract version from the main plugin file
# ------------------------------------------------------------------
VERSION=$(grep -i "Version:" "$PLUGIN_SLUG.php" | head -1 | awk '{print $NF}')

if [ -z "$VERSION" ]; then
    echo "Error: Could not determine plugin version from $PLUGIN_SLUG.php"
    exit 1
fi

echo "Deploying $PLUGIN_SLUG v$VERSION to WordPress.org..."

# ------------------------------------------------------------------
# 1. Build the release package
# ------------------------------------------------------------------
echo ""
echo "Step 1: Building release package..."
bash scripts/package.sh

if [ ! -d "$RELEASE_DIR" ]; then
    echo "Error: Release directory not found. Build may have failed."
    exit 1
fi

# ------------------------------------------------------------------
# 2. Checkout SVN repository
# ------------------------------------------------------------------
echo ""
echo "Step 2: Checking out SVN repository..."
rm -rf "$SVN_DIR"
svn checkout "$SVN_URL" "$SVN_DIR" --username "$WP_ORG_USERNAME" --password "$WP_ORG_PASSWORD" --non-interactive --trust-server-cert

# ------------------------------------------------------------------
# 3. Sync trunk with the release build
# ------------------------------------------------------------------
echo ""
echo "Step 3: Syncing trunk..."

# Clear trunk and copy new files
rm -rf "$SVN_DIR/trunk/"*
cp -R "$RELEASE_DIR/"* "$SVN_DIR/trunk/"

# ------------------------------------------------------------------
# 4. Copy WP.org assets (banners, icons, screenshots) if they exist
# ------------------------------------------------------------------
if [ -d "assets/wp-org" ]; then
    echo ""
    echo "Step 4: Syncing WP.org assets..."
    mkdir -p "$SVN_DIR/assets"
    cp -R assets/wp-org/* "$SVN_DIR/assets/"
fi

# ------------------------------------------------------------------
# 5. Create the version tag
# ------------------------------------------------------------------
echo ""
echo "Step 5: Creating tag $VERSION..."

if [ -d "$SVN_DIR/tags/$VERSION" ]; then
    echo "Warning: Tag $VERSION already exists. Removing and re-creating..."
    rm -rf "$SVN_DIR/tags/$VERSION"
fi

mkdir -p "$SVN_DIR/tags/$VERSION"
cp -R "$RELEASE_DIR/"* "$SVN_DIR/tags/$VERSION/"

# ------------------------------------------------------------------
# 6. Let SVN know about added/removed files
# ------------------------------------------------------------------
echo ""
echo "Step 6: Updating SVN file tracking..."
cd "$SVN_DIR"

# Add new files
svn add --force trunk/ --auto-props --parents 2>/dev/null || true
svn add --force "tags/$VERSION/" --auto-props --parents 2>/dev/null || true

if [ -d "assets" ]; then
    svn add --force assets/ --auto-props --parents 2>/dev/null || true
fi

# Remove deleted files
svn status | grep '^\!' | awk '{print $2}' | xargs -I {} svn rm {} 2>/dev/null || true

# ------------------------------------------------------------------
# 7. Commit to SVN
# ------------------------------------------------------------------
echo ""
echo "Step 7: Committing to WordPress.org..."
svn commit -m "Release v$VERSION" \
    --username "$WP_ORG_USERNAME" \
    --password "$WP_ORG_PASSWORD" \
    --non-interactive --trust-server-cert

# ------------------------------------------------------------------
# 8. Cleanup
# ------------------------------------------------------------------
echo ""
echo "Step 8: Cleaning up..."
cd -
rm -rf "$SVN_DIR"

echo ""
echo "Done! $PLUGIN_SLUG v$VERSION has been deployed to WordPress.org."
echo "It may take a few minutes to appear on the plugin page."
