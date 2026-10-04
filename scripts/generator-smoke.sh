#!/bin/sh
# Smoke test of the admin CRUD generator (`app:make-resource`).
#
# Acceptance criterion: a module generated with every field type, badge tones,
# list filters and the CSV export passes the project checks without manual
# fixes. The script copies the repository into a temporary directory (no
# .env, local database, Git metadata or build output; vendor and node_modules
# are linked, not reinstalled), generates the module there and runs Pint,
# PHPStan, the module's Pest tests (SQLite :memory: migrations), the i18n gate,
# type generation, Wayfinder, TypeScript, `npm run check` and the UI contract.
# The working tree is never modified; the copy is removed on exit.
#
# Usage: make generator-smoke (Docker) or `sh scripts/generator-smoke.sh` in CI.
set -eu

ROOT=$(cd "$(dirname "$0")/.." && pwd)
WORK=$(mktemp -d "${TMPDIR:-/tmp}/generator-smoke.XXXXXX")
if [ "${GENERATOR_SMOKE_KEEP:-0}" = 1 ]; then
    # Debugging aid: keep the copy to inspect the generated files.
    trap 'echo "Kept $WORK"' EXIT
else
    trap 'rm -rf "$WORK"' EXIT INT TERM
fi

step() {
    printf '\n==> %s\n' "$1"
}

GENERATED_PHP=''
GENERATED_UI=''
GENERATED_TESTS=''

# generate <Model> <app:make-resource options...>: generate one module and
# collect the created files from the command output.
generate() {
    model=$1
    shift
    step "Generate the $model resource"
    output=$(php artisan app:make-resource "$model" --no-interaction "$@" 2>&1) || {
        printf '%s\n' "$output"
        exit 1
    }
    printf '%s\n' "$output"
    created=$(printf '%s\n' "$output" | sed -n 's/^  create //p')
    GENERATED_PHP="$GENERATED_PHP $(printf '%s\n' "$created" | grep '\.php$' | grep -v '^tests/' | tr '\n' ' ')"
    GENERATED_UI="$GENERATED_UI $(printf '%s\n' "$created" | grep '\.tsx$' | tr '\n' ' ')"
    GENERATED_TESTS="$GENERATED_TESTS tests/Feature/Admin/${model}CrudTest.php"
}

step "Copy the repository to $WORK"
tar -C "$ROOT" \
    --exclude=./.git \
    --exclude=./.env \
    --exclude=./vendor \
    --exclude=./node_modules \
    --exclude=./.claude/worktrees \
    --exclude=./.backups \
    --exclude=./public/build \
    --exclude=./public/hot \
    --exclude=./public/storage \
    --exclude=./bootstrap/ssr \
    --exclude='./bootstrap/cache/*.php' \
    --exclude='./database/*.sqlite' \
    --exclude='./storage/logs/*' \
    --exclude='./storage/framework/*/*' \
    --exclude=./playwright-report \
    --exclude=./test-results \
    --exclude=./resources/js/actions \
    --exclude=./resources/js/routes \
    --exclude=./resources/js/wayfinder \
    -cf - . | tar -C "$WORK" -xf -

# Packages are linked; the autoloader and the tools that resolve the project
# root from their own real path (Pest, PHPStan) are copied so they point at
# the copy instead of the repository.
mkdir -p "$WORK/vendor"
cp "$ROOT/vendor/autoload.php" "$WORK/vendor/autoload.php"
for entry in "$ROOT"/vendor/*; do
    name=$(basename "$entry")
    case "$name" in
        autoload.php) ;;
        bin | composer | pestphp | phpstan) cp -RL "$entry" "$WORK/vendor/$name" ;;
        *) ln -s "$entry" "$WORK/vendor/$name" ;;
    esac
done
ln -s "$ROOT/node_modules" "$WORK/node_modules"

cd "$WORK"
cp .env.example .env
composer dump-autoload --no-interaction --quiet
php artisan key:generate --force --no-interaction >/dev/null

# Every field type, badge tones, a required and an optional relation, an
# optional many-to-many relation, image, rich text, filters (enum first) and
# the CSV export.
generate SmokeItem \
    --fields='title:string:required,summary:text,rank:integer,price:decimal,active:boolean,published_on:date,status:enum(draft|published:success|archived:danger),faq:belongsTo(Faq.question):required,backup_faq:belongsTo(Faq.question),related_faqs:belongsToMany(Faq.question),cover:image,body:richtext' \
    --searchable=title,summary \
    --sortable=title,rank,created_at \
    --filters=status,faq,active \
    --export
# An optional relation as the first filter, a boolean filter, a required
# many-to-many relation and rich text without images or enums; export
# without search.
generate SmokeNote \
    --fields='label:string:required,faq:belongsTo(Faq.question),faqs:belongsToMany(Faq.question):required,enabled:boolean,notes:richtext' \
    --filters=faq,enabled \
    --export
# The plain resource without relations, filters or export.
generate SmokeTag \
    --fields='name:string:required,weight:integer:required,starts_on:date' \
    --sortable=name,weight

step "Pint (generated files, routes and catalogs)"
# shellcheck disable=SC2086
vendor/bin/pint --test $GENERATED_PHP $GENERATED_TESTS routes/admin.php lang/en/admin.php lang/pl/admin.php lang/de/admin.php

step "PHPStan (generated application code)"
# shellcheck disable=SC2086
vendor/bin/phpstan analyse --no-progress --memory-limit=1G $GENERATED_PHP

step "Pest: generated CRUD tests (migrations on SQLite :memory:) and the i18n gate"
# shellcheck disable=SC2086
php artisan test --compact $GENERATED_TESTS tests/Feature/LocalizationCatalogGateTest.php

step "TypeScript contracts and Wayfinder routes"
composer types:generate
php artisan wayfinder:generate --with-form --no-interaction

step "TypeScript"
npm run types:check

step "npm run check (lint, format, UI tests)"
npm run check

step "UI contract (generated pages)"
# shellcheck disable=SC2086
node scripts/check-ui-contract.mjs --files $GENERATED_UI

step "Generator smoke passed: SmokeItem, SmokeNote and SmokeTag generated and verified without manual fixes"
