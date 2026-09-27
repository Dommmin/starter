#!/usr/bin/env bash
# Backup of the PostgreSQL database and application storage.
#
# Produces BACKUP_DIR/starter-<UTC timestamp>/ containing:
#   db.dump           pg_dump custom format (-Fc)
#   storage.tar.gz    archive of BACKUP_STORAGE_DIRS under BACKUP_STORAGE_PATH
#   manifest.json     SHA-256 and size of each file, table/row counts, versions
# Files are optionally encrypted (BACKUP_ENCRYPT=age|gpg); checksums then
# cover the encrypted files. The directory is written as *.partial and
# renamed only after the manifest is complete, so a crash never leaves a
# backup that looks valid. Keeps the newest BACKUP_KEEP complete backups.
#
# Configuration (environment only; no secrets in the repository):
#   PGHOST PGPORT PGUSER PGPASSWORD PGDATABASE  standard libpq variables
#                                               (or PGPASSFILE / .pgpass)
#   BACKUP_DIR            target directory (required)
#   BACKUP_STORAGE_PATH   storage/app of the application (required)
#   BACKUP_STORAGE_DIRS   space-separated subdirectories (default: "media public")
#   BACKUP_KEEP           complete backups to keep (default: 7)
#   BACKUP_ENCRYPT        none (default) | age | gpg
#   BACKUP_AGE_RECIPIENT  age public key (BACKUP_ENCRYPT=age)
#   BACKUP_GPG_RECIPIENT  gpg key id/e-mail (BACKUP_ENCRYPT=gpg)
#   BACKUP_STATUS_FILE    UTC time of the last success (default: BACKUP_DIR/last-success)
#
# Exit code 0 only after a complete backup. Any failure logs a critical JSON
# line to stderr (journal) and, when available, to syslog via logger(1);
# the systemd unit additionally runs OnFailure= (deploy/systemd).
set -Eeuo pipefail
umask 077

readonly SCRIPT_NAME=backup
BACKUP_STORAGE_DIRS=${BACKUP_STORAGE_DIRS:-media public}
BACKUP_KEEP=${BACKUP_KEEP:-7}
BACKUP_ENCRYPT=${BACKUP_ENCRYPT:-none}

log() {
    local level=$1 event=$2 message=$3
    printf '{"time":"%s","level":"%s","service":"%s","event":"%s","message":"%s"}\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$level" "$SCRIPT_NAME" "$event" "$message" >&2
}

partial_dir=''
on_error() {
    local exit_code=$? line=$1
    log critical ops.backup.failed "backup failed at line ${line} (exit ${exit_code})"
    if command -v logger >/dev/null 2>&1; then
        logger -p user.crit -t starter-backup "ops.backup.failed exit=${exit_code} line=${line}" || true
    fi
    if [ -n "$partial_dir" ] && [ -d "$partial_dir" ]; then
        rm -rf -- "$partial_dir"
    fi
    exit "$exit_code"
}
trap 'on_error $LINENO' ERR

fail() {
    log critical ops.backup.failed "$1"
    if command -v logger >/dev/null 2>&1; then
        logger -p user.crit -t starter-backup "ops.backup.failed $1" || true
    fi
    exit 1
}

[ -n "${BACKUP_DIR:-}" ] || fail 'BACKUP_DIR is required'
[ -n "${BACKUP_STORAGE_PATH:-}" ] || fail 'BACKUP_STORAGE_PATH is required'
[ -n "${PGDATABASE:-}" ] || fail 'PGDATABASE is required'
[[ "$BACKUP_KEEP" =~ ^[1-9][0-9]*$ ]] || fail 'BACKUP_KEEP must be a positive integer'
BACKUP_STATUS_FILE=${BACKUP_STATUS_FILE:-$BACKUP_DIR/last-success}

for tool in pg_dump psql tar gzip sha256sum; do
    command -v "$tool" >/dev/null 2>&1 || fail "missing tool: $tool"
done

case "$BACKUP_ENCRYPT" in
    none) ;;
    age)
        command -v age >/dev/null 2>&1 || fail 'BACKUP_ENCRYPT=age but age is not installed'
        [ -n "${BACKUP_AGE_RECIPIENT:-}" ] || fail 'BACKUP_AGE_RECIPIENT is required for age'
        ;;
    gpg)
        command -v gpg >/dev/null 2>&1 || fail 'BACKUP_ENCRYPT=gpg but gpg is not installed'
        [ -n "${BACKUP_GPG_RECIPIENT:-}" ] || fail 'BACKUP_GPG_RECIPIENT is required for gpg'
        ;;
    *) fail "unsupported BACKUP_ENCRYPT: $BACKUP_ENCRYPT" ;;
esac

mkdir -p "$BACKUP_DIR"
started_epoch=$(date -u +%s)
stamp=$(date -u +%Y%m%dT%H%M%SZ)
name="starter-$stamp"
partial_dir="$BACKUP_DIR/$name.partial"
final_dir="$BACKUP_DIR/$name"
[ ! -e "$final_dir" ] || fail "backup $name already exists"
mkdir "$partial_dir"

log info ops.backup.started "backup $name started"

# 1. Database: custom format (compressed, selective pg_restore possible).
pg_dump --format=custom --no-owner --no-privileges --file="$partial_dir/db.dump"

# Table and exact row counts of the public schema, for restore verification.
# Taken right after the dump: on a busy database they may differ slightly;
# the drill reports such differences (see restore.sh --drill).
table_counts=$(psql --no-psqlrc --tuples-only --no-align --quiet --set ON_ERROR_STOP=1 <<'SQL'
SELECT coalesce(json_object_agg(t.table_name, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', t.table_schema, t.table_name), false, true, '')))[1]::text::bigint), '{}'::json)
FROM information_schema.tables t
WHERE t.table_schema = 'public' AND t.table_type = 'BASE TABLE';
SQL
)

# 2. Storage: only the configured subdirectories that exist.
storage_dirs=()
for dir in $BACKUP_STORAGE_DIRS; do
    if [ -d "$BACKUP_STORAGE_PATH/$dir" ]; then
        storage_dirs+=("$dir")
    fi
done
if [ "${#storage_dirs[@]}" -gt 0 ]; then
    tar -C "$BACKUP_STORAGE_PATH" -czf "$partial_dir/storage.tar.gz" -- "${storage_dirs[@]}"
    storage_files=$(tar -tzf "$partial_dir/storage.tar.gz" | grep -vc '/$' || true)
else
    tar -czf "$partial_dir/storage.tar.gz" -T /dev/null
    storage_files=0
fi

# 3. Optional encryption; plaintext is removed only after success.
suffix=''
for file in db.dump storage.tar.gz; do
    case "$BACKUP_ENCRYPT" in
        age)
            age --recipient "$BACKUP_AGE_RECIPIENT" --output "$partial_dir/$file.age" "$partial_dir/$file"
            rm -f -- "$partial_dir/$file"
            suffix='.age'
            ;;
        gpg)
            gpg --batch --yes --trust-model always --recipient "$BACKUP_GPG_RECIPIENT" \
                --output "$partial_dir/$file.gpg" --encrypt "$partial_dir/$file"
            rm -f -- "$partial_dir/$file"
            suffix='.gpg'
            ;;
    esac
done

# 4. Manifest with checksums, then atomic rename to the final name.
file_entries=''
for file in "db.dump$suffix" "storage.tar.gz$suffix"; do
    sum=$(sha256sum "$partial_dir/$file" | cut -d ' ' -f 1)
    size=$(wc -c < "$partial_dir/$file" | tr -d ' ')
    file_entries+="${file_entries:+,}\"$file\":{\"sha256\":\"$sum\",\"bytes\":$size}"
done
storage_dirs_json=$(printf '"%s",' "${storage_dirs[@]+"${storage_dirs[@]}"}")
storage_dirs_json="[${storage_dirs_json%,}]"
[ "$storage_dirs_json" = '[""]' ] && storage_dirs_json='[]'
finished_epoch=$(date -u +%s)

cat > "$partial_dir/manifest.json" <<JSON
{
  "format": 1,
  "name": "$name",
  "created_at": "$(date -u -d "@$started_epoch" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || date -u +%Y-%m-%dT%H:%M:%SZ)",
  "duration_seconds": $((finished_epoch - started_epoch)),
  "database": "$PGDATABASE",
  "pg_dump_version": "$(pg_dump --version | sed -E 's/^[^0-9]*([0-9.]+).*/\1/')",
  "encryption": "$BACKUP_ENCRYPT",
  "files": {$file_entries},
  "tables": $table_counts,
  "storage_dirs": $storage_dirs_json,
  "storage_files": $storage_files
}
JSON

mv -- "$partial_dir" "$final_dir"
partial_dir=''

# 5. Rotation: keep the newest BACKUP_KEEP complete backups, drop stale partials.
mapfile -t complete < <(find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name 'starter-*' ! -name '*.partial' -printf '%f\n' | sort -r)
for old in "${complete[@]:$BACKUP_KEEP}"; do
    rm -rf -- "${BACKUP_DIR:?}/$old"
    log info ops.backup.rotated "removed $old"
done
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name 'starter-*.partial' -mmin +1440 -exec rm -rf -- {} +

date -u +%Y-%m-%dT%H:%M:%SZ > "$BACKUP_STATUS_FILE.tmp"
mv -- "$BACKUP_STATUS_FILE.tmp" "$BACKUP_STATUS_FILE"

log info ops.backup.completed "backup $name completed in $((finished_epoch - started_epoch)) s"
printf '%s\n' "$final_dir"
