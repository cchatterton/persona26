#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

PLUGIN_SLUG="persona26"
DIST_DIR="dist"

rm -rf "$DIST_DIR/$PLUGIN_SLUG"
rm -f "$PLUGIN_SLUG.zip"
mkdir -p "$DIST_DIR"
cp -R "$PLUGIN_SLUG" "$DIST_DIR/$PLUGIN_SLUG"

find "$DIST_DIR/$PLUGIN_SLUG" -type f \( -name ".DS_Store" -o -name ".env*" -o -name "*.zip" -o -name "*.log" \) -delete
find "$DIST_DIR/$PLUGIN_SLUG" -type d \( -name ".git" -o -name "__pycache__" \) -prune -exec rm -rf {} +
test -f "$DIST_DIR/$PLUGIN_SLUG/LICENSE"
test -f "$DIST_DIR/$PLUGIN_SLUG/readme.txt"
rm -rf "$DIST_DIR/$PLUGIN_SLUG/node_modules"

cd "$DIST_DIR"
rm -f "$PLUGIN_SLUG.zip"
zip -qr "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG"
cp "$PLUGIN_SLUG.zip" "../$PLUGIN_SLUG.zip"
