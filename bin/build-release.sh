#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(sed -n "s/^ \* Version: //p" "$ROOT_DIR/hdyhaus-identity-verification.php")"
BUILD_DIR="${TMPDIR:-/tmp}/hdyhaus-identity-verification-build"
PACKAGE_DIR="$BUILD_DIR/hdyhaus-identity-verification"
ZIP_PATH="$ROOT_DIR/hdyhaus-identity-verification-$VERSION.zip"

rm -rf "$BUILD_DIR"
mkdir -p "$PACKAGE_DIR"

rsync -a \
	--exclude '.git/' \
	--exclude '.github/' \
	--exclude '.wordpress-org/' \
	--exclude '.editorconfig' \
	--exclude '.gitignore' \
	--exclude 'bin/' \
	--exclude 'tests/' \
	--exclude 'docs/' \
	--exclude 'vendor/' \
	--exclude 'composer.json' \
	--exclude 'composer.lock' \
	--exclude 'CONTRIBUTING.md' \
	--exclude 'SECURITY.md' \
	--exclude 'phpcs.xml.dist' \
	--exclude '*.zip' \
	"$ROOT_DIR/" "$PACKAGE_DIR/"

rm -f "$ZIP_PATH"
( cd "$BUILD_DIR" && zip -qr "$ZIP_PATH" hdyhaus-identity-verification )
unzip -t "$ZIP_PATH"

printf 'Built %s\n' "$ZIP_PATH"
