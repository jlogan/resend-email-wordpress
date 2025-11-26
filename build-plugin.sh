#!/bin/bash

# Build script for Resend Email Integration WordPress Plugin
# This script creates a distribution-ready zip file including vendor/ folder

set -e

PLUGIN_NAME="resend-email-integration"
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_DIR="$PLUGIN_DIR/build"
ZIP_NAME="$PLUGIN_NAME.zip"

echo "Building $PLUGIN_NAME for distribution..."

# Clean previous build
if [ -d "$BUILD_DIR" ]; then
    rm -rf "$BUILD_DIR"
fi
mkdir -p "$BUILD_DIR"

# Ensure vendor/ folder exists
if [ ! -d "$PLUGIN_DIR/vendor" ]; then
    echo "Installing Composer dependencies..."
    cd "$PLUGIN_DIR"
    composer install --no-dev --optimize-autoloader
fi

# Copy all plugin files to build directory
echo "Copying plugin files..."
cp -r "$PLUGIN_DIR"/* "$BUILD_DIR/" 2>/dev/null || true

# Remove files that shouldn't be in distribution
echo "Cleaning up build directory..."
cd "$BUILD_DIR"
rm -rf .git .gitignore .gitattributes .idea .vscode *.swp *.swo *~ .DS_Store Thumbs.db *.log build-plugin.sh

# Create zip file
echo "Creating zip file..."
cd "$PLUGIN_DIR"
if [ -f "$ZIP_NAME" ]; then
    rm "$ZIP_NAME"
fi

cd "$BUILD_DIR"
zip -r "$PLUGIN_DIR/$ZIP_NAME" . -q

# Clean up build directory
cd "$PLUGIN_DIR"
rm -rf "$BUILD_DIR"

echo "✓ Build complete: $ZIP_NAME"
echo "✓ The zip file includes the vendor/ folder and is ready for distribution"

