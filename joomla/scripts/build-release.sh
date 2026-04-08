#!/usr/bin/env bash
# Build pkg_xenarch.zip for Joomla installation.
# Usage: ./scripts/build-release.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
SRC_DIR="$ROOT_DIR/src"
PACKAGES_DIR="$SRC_DIR/packages"

echo "Building Xenarch Joomla package..."

# Clean previous builds.
rm -rf "$PACKAGES_DIR"
mkdir -p "$PACKAGES_DIR"

# Build admin UI if node_modules exist.
if [ -d "$ROOT_DIR/admin-ui/node_modules" ]; then
    echo "Building admin UI..."
    (cd "$ROOT_DIR/admin-ui" && npm run build)
    cp "$ROOT_DIR/admin-ui/dist/xenarch-admin.js" "$SRC_DIR/media/com_xenarch/js/"
    cp "$ROOT_DIR/admin-ui/dist/xenarch-admin.css" "$SRC_DIR/media/com_xenarch/css/"
fi

# Package component.
echo "Packaging com_xenarch..."
(cd "$SRC_DIR/administrator/components/com_xenarch" && zip -r "$PACKAGES_DIR/com_xenarch.zip" . -x "*.DS_Store")

# Package system plugin.
echo "Packaging plg_system_xenarch..."
(cd "$SRC_DIR/plugins/system/xenarch" && zip -r "$PACKAGES_DIR/plg_system_xenarch.zip" . -x "*.DS_Store")

# Package webservices plugin.
echo "Packaging plg_webservices_xenarch..."
(cd "$SRC_DIR/plugins/webservices/xenarch" && zip -r "$PACKAGES_DIR/plg_webservices_xenarch.zip" . -x "*.DS_Store")

# Package everything into pkg_xenarch.zip.
echo "Building pkg_xenarch.zip..."
(cd "$ROOT_DIR" && zip -j "$PACKAGES_DIR/pkg_xenarch.zip" \
    pkg_xenarch.xml \
    "$PACKAGES_DIR/com_xenarch.zip" \
    "$PACKAGES_DIR/plg_system_xenarch.zip" \
    "$PACKAGES_DIR/plg_webservices_xenarch.zip")

echo "Done! Package: $PACKAGES_DIR/pkg_xenarch.zip"
