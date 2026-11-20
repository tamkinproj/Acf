#!/bin/bash
set -e
# Builds the Hostinger upload: <output>/insania_app (with vendor, no dev tools) and <output>/public_html/server.
# Needs composer on the machine that builds it (not on the server).
SRC="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:?usage: deploy/build-package.sh <output-folder>}"
if [ -e "$OUT" ] && [ -n "$(ls -A "$OUT")" ]; then echo "Output folder must be empty or new: $OUT" >&2; exit 1; fi
mkdir -p "$OUT/insania_app" "$OUT/public_html/server"
cd "$SRC"
tar --exclude=./vendor --exclude=./.env --exclude=./tests --exclude=./node_modules --exclude=./.git --exclude=./storage/logs/* --exclude=./storage/framework/*/* --exclude=./bootstrap/cache/*.php --exclude=./.phpunit.result.cache --exclude=./database/*.sqlite --exclude=./public --exclude=./deploy -cf - . | tar -xf - -C "$OUT/insania_app"
cd "$OUT/insania_app"
rm -f phpunit.xml vite.config.js package.json
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts 2>&1 | tail -3
mkdir -p storage/app/install storage/app/private storage/logs storage/framework/{cache/data,sessions,views} bootstrap/cache
for d in storage/app/install storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache; do touch $d/.gitkeep; done
cp "$SRC/deploy/server/index.php" "$OUT/public_html/server/index.php"
cp "$SRC/deploy/server/.htaccess" "$OUT/public_html/server/.htaccess"
( cd "$OUT/insania_app/vendor" && find . -name .git -type d -prune -exec rm -rf {} + ; find . -type d \( -name tests -o -name Tests -o -name docs \) -prune -exec rm -rf {} + )
echo "${BUILD_ID:-$(date -u +%Y%m%d%H%M%S)}" > "$OUT/insania_app/BUILD"   # set BUILD_ID to rebuild an old release exactly
printf "Require all denied\n" > "$OUT/insania_app/.htaccess"
echo built
