#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="persona26"
DIST_DIR="dist"

rm -rf "$DIST_DIR/$PLUGIN_SLUG"
rm -f "$PLUGIN_SLUG.zip"
mkdir -p "$DIST_DIR"
cp -R "$PLUGIN_SLUG" "$DIST_DIR/$PLUGIN_SLUG"

find "$DIST_DIR/$PLUGIN_SLUG" -name ".DS_Store" -delete
rm -rf "$DIST_DIR/$PLUGIN_SLUG/node_modules"

cd "$DIST_DIR"
rm -f "$PLUGIN_SLUG.zip"
zip -qr "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG"
cp "$PLUGIN_SLUG.zip" "../$PLUGIN_SLUG.zip"
