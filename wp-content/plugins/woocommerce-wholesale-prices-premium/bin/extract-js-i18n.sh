#!/bin/bash
#
# Extract i18n strings from built JS files where wp.i18n.__() is used.
#
# wp i18n make-pot cannot detect wp.i18n.__() calls (only bare __() calls).
# Vite externalizes @wordpress/i18n to wp.i18n, and the minifier wraps it in
# a local alias like: c(wp.i18n.__)("string", "domain")
#
# This script creates a temporary copy of dist/, normalizes those patterns
# to bare __("string") calls, runs make-pot, and merges the result.
#
# Usage: bash bin/extract-js-i18n.sh
#
# @since 3.0.2

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DOMAIN="woocommerce-wholesale-prices-premium"
WP_PATH="${WP_PATH:-$PLUGIN_DIR/../../..}"
POT_FILE="$PLUGIN_DIR/languages/$DOMAIN.pot"
TMP_DIR=$(mktemp -d)
TMP_DIST="$TMP_DIR/dist"
TMP_POT="$TMP_DIR/js-strings.pot"

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

echo "Extracting JS i18n strings..."

# 1. Copy dist/ to temp directory.
cp -r "$PLUGIN_DIR/dist" "$TMP_DIST"

# 2. Normalize minified wp.i18n patterns to bare function calls.
#
#    Minified patterns:
#      x(wp.i18n.__)("string", "domain")  ->  __("string", "domain")
#      x(wp.i18n._n)("s","p",n,"domain")  ->  _n("s","p",n,"domain")
#      x(wp.i18n._x)("string","ctx","d")  ->  _x("string","ctx","d")
#      x(wp.i18n._nx)("s","p",n,"c","d")  ->  _nx("s","p",n,"c","d")
#      x(wp.i18n.sprintf)("fmt", ...)      ->  sprintf("fmt", ...)
#      wp.i18n.__("string", "domain")      ->  __("string", "domain")
#
#    Where x is any single minified variable character.
find "$TMP_DIST" -name '*.js' -exec sed -i.bak \
    -e 's/[a-zA-Z_$](wp\.i18n\.__)/__ /g' \
    -e 's/[a-zA-Z_$](wp\.i18n\._x)/_x /g' \
    -e 's/[a-zA-Z_$](wp\.i18n\._n)/_n /g' \
    -e 's/[a-zA-Z_$](wp\.i18n\._nx)/_nx /g' \
    -e 's/[a-zA-Z_$](wp\.i18n\.sprintf)/sprintf /g' \
    -e 's/wp\.i18n\.__/__/g' \
    -e 's/wp\.i18n\._x/_x/g' \
    -e 's/wp\.i18n\._n/_n/g' \
    -e 's/wp\.i18n\._nx/_nx/g' \
    -e 's/wp\.i18n\.sprintf/sprintf/g' \
    {} +
find "$TMP_DIST" -name '*.js.bak' -delete

# 3. Create a minimal plugin header file so make-pot works.
cat > "$TMP_DIR/$DOMAIN.php" <<'HEADER'
<?php
/**
 * Plugin Name: WooCommerce Wholesale Prices Premium
 * Text Domain: woocommerce-wholesale-prices-premium
 */
HEADER

# 4. Run make-pot on the temp dist/ only (JS files).
wp i18n make-pot "$TMP_DIR" "$TMP_POT" \
    --path="$WP_PATH" \
    --domain="$DOMAIN" \
    --skip-php \
    --include="dist" \
    --headers='{"Report-Msgid-Bugs-To":"https://wordpress.org/support/plugin/woocommerce-wholesale-prices-premium"}' \
    2>/dev/null

# 5. Fix source references: replace temp paths with relative dist/ paths.
sed -i.bak "s|$TMP_DIR/||g" "$TMP_POT" && rm -f "$TMP_POT.bak"

JS_COUNT=$(grep -c '^msgid ' "$TMP_POT" 2>/dev/null || echo 0)
echo "Found $JS_COUNT JS strings."

# 6. Merge JS strings into the main .pot file.
#    Use msgcat without --use-first so source references from both files are combined.
if [ -f "$POT_FILE" ]; then
    msgcat --no-wrap "$POT_FILE" "$TMP_POT" -o "$POT_FILE"
    echo "Merged JS strings into $POT_FILE"
else
    cp "$TMP_POT" "$POT_FILE"
    echo "Created $POT_FILE"
fi

TOTAL=$(grep -c '^msgid ' "$POT_FILE" 2>/dev/null || echo 0)
echo "Total strings in .pot: $TOTAL"
echo "Done."