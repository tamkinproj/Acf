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
