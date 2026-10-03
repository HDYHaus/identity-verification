#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(sed -n "s/^ \* Version: //p" "$ROOT_DIR/trustgate-registration.php")"
BUILD_DIR="${TMPDIR:-/tmp}/trustgate-registration-build"
PACKAGE_DIR="$BUILD_DIR/trustgate-registration"
ZIP_PATH="$ROOT_DIR/trustgate-registration-$VERSION.zip"

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
( cd "$BUILD_DIR" && zip -qr "$ZIP_PATH" trustgate-registration )
unzip -t "$ZIP_PATH"

printf 'Built %s\n' "$ZIP_PATH"
