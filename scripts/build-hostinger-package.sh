#!/usr/bin/env bash
# Builds an upload-ready package for hosts where you only have a File Manager (no SSH / Composer).
#
#   scripts/build-hostinger-package.sh [output.zip]
#
# Result, once unzipped next to your site's public_html:
#   foundation_app/   the application + vendor/ (everything, OUTSIDE the web root)
#   public_html/      only the public files (index.php, .htaccess, css) - this is what the web serves
#
# Needs PHP >= 8.3 and Composer on the machine that BUILDS the package, not on the host.
set -euo pipefail

OUT="$(realpath -m "${1:-foundation-hostinger.zip}")"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
export COMPOSER_ALLOW_SUPERUSER=1

mkdir -p "$STAGE/foundation_app"
git -C "$ROOT" archive HEAD | tar -x -C "$STAGE/foundation_app"

cd "$STAGE/foundation_app"
rm -rf tests phpunit.xml .github scripts
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --quiet
find vendor -name .git -prune -exec rm -rf {} + 2>/dev/null || true
find vendor -type d \( -iname tests -o -iname test -o -iname docs \) -prune -exec rm -rf {} + 2>/dev/null || true

# The web root holds only public files; index.php is repointed at the app folder next to it.
mv public ../public_html
sed -i "s#__DIR__\.'/\.\./#__DIR__.'/../foundation_app/#g" ../public_html/index.php
grep -q "foundation_app/vendor/autoload.php" ../public_html/index.php || { echo "index.php rewrite failed" >&2; exit 1; }

# Writable folders must exist before the first request (the installer renders views immediately).
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/{install,db,private,public} bootstrap/cache
for d in storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/install storage/app/db bootstrap/cache; do
  [ -e "$d/.gitignore" ] || printf '*\n!.gitignore\n' > "$d/.gitignore"
done
rm -f .env

cp "$ROOT/docs/HOSTINGER.md" "$STAGE/README-HOSTINGER.md"

cd "$STAGE"
rm -f "$OUT"
zip -qr "$OUT" foundation_app public_html README-HOSTINGER.md
echo "Built $OUT ($(du -h "$OUT" | cut -f1))"

# UPDATE zips: drop-in, no renaming or copying. Each one is extracted INSIDE its target folder and contains only
# complete code folders - never storage/, .env or index.php - so an installed site keeps its settings and data.
#   update-foundation_app.zip -> extract inside foundation_app   (code + vendor: version 2 adds the spreadsheet reader)
#   After extracting both, open the site: it redirects to /upgrade (token in storage/app/install/token) to migrate the data.
#   update-web.zip            -> extract inside the web folder (e.g. public_html/acr)
UPD_APP="${OUT%/*}/update-foundation_app.zip"
UPD_WEB="${OUT%/*}/update-web.zip"
rm -f "$UPD_APP" "$UPD_WEB"
(cd "$STAGE/foundation_app" && zip -qr "$UPD_APP" app bootstrap config database resources routes vendor composer.json composer.lock)
(cd "$STAGE/public_html" && zip -qr "$UPD_WEB" app css icons vendor)
echo "Built $UPD_APP ($(du -h "$UPD_APP" | cut -f1))"
echo "Built $UPD_WEB ($(du -h "$UPD_WEB" | cut -f1))"
