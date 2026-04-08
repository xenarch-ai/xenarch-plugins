#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SLUG="xenarch"

# Read version from plugin header.
VERSION=$(grep -m1 "^ \* Version:" "$PLUGIN_DIR/xenarch.php" | sed 's/.*Version:[[:space:]]*//')

if [[ -z "$VERSION" ]]; then
    echo "ERROR: Could not read version from xenarch.php"
    exit 1
fi

echo "Building $SLUG v$VERSION"

# Build admin UI.
echo "--- Building admin UI ---"
cd "$PLUGIN_DIR/admin-ui"
npm install --legacy-peer-deps --silent
npm run build

# Stage release files.
BUILD_DIR="$PLUGIN_DIR/build"
STAGE_DIR="$BUILD_DIR/$SLUG"
rm -rf "$BUILD_DIR"
mkdir -p "$STAGE_DIR"

cp "$PLUGIN_DIR/xenarch.php"   "$STAGE_DIR/"
cp "$PLUGIN_DIR/readme.txt"    "$STAGE_DIR/"
cp "$PLUGIN_DIR/LICENSE"       "$STAGE_DIR/"
cp "$PLUGIN_DIR/uninstall.php" "$STAGE_DIR/"
cp -r "$PLUGIN_DIR/includes"   "$STAGE_DIR/includes"

mkdir -p "$STAGE_DIR/admin-ui/dist"
cp -r "$PLUGIN_DIR/admin-ui/dist/"* "$STAGE_DIR/admin-ui/dist/"

# Create ZIP.
ZIP_NAME="${SLUG}-${VERSION}.zip"
cd "$BUILD_DIR"
zip -rq "$ZIP_NAME" "$SLUG/"
mv "$ZIP_NAME" "$PLUGIN_DIR/$ZIP_NAME"

# Cleanup.
rm -rf "$BUILD_DIR"

echo "--- Done ---"
echo "Created: $PLUGIN_DIR/$ZIP_NAME"
echo ""
echo "ZIP contents:"
unzip -l "$PLUGIN_DIR/$ZIP_NAME"
